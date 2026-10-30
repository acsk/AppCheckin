<?php

namespace App\Services\Admin;

use App\Models\Parametro;
use App\Repositories\TenantPaymentCredentialsRepository;
use App\Services\Asaas\AsaasService;
use App\Services\EncryptionService;
use App\Services\MercadoPago\MercadoPagoService;
use App\Support\AsaasApiKey;
use App\Support\AsaasTarifaCartao;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Credenciais de pagamento do tenant (um registro por provider).
 */
class AdminPaymentCredentialsService
{
    /** @var list<string> */
    private const PROVIDERS = ['mercadopago', 'asaas'];

    /** @var \Closure(int): AsaasService */
    private readonly \Closure $asaas;

    public function __construct(
        private readonly TenantPaymentCredentialsRepository $credentials,
        private readonly EncryptionService $encryption,
        ?\Closure $asaas = null,
    ) {
        $this->asaas = $asaas ?? fn (int $tenantId) => new AsaasService($tenantId);
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function obter(int $tenantId): array
    {
        try {
            $porCodigo = [];
            foreach ($this->credentials->listarPorTenant($tenantId) as $row) {
                $codigo = strtolower((string) ($row['provider'] ?? ''));
                if ($codigo === 'asaas') {
                    $row = $this->antecipacaoDaConta($tenantId, $row);
                }
                $porCodigo[$codigo] = $this->mascararLinha($row);
            }

            $providers = [];
            foreach (self::PROVIDERS as $codigo) {
                $providers[] = $porCodigo[$codigo] ?? $this->slotVazio($codigo);
            }

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'data' => [
                        'providers' => $providers,
                    ],
                ],
            ];
        } catch (Throwable $e) {
            error_log('[PaymentCredentials] Erro ao obter: '.$e->getMessage());

            return [
                'status' => 500,
                'body' => [
                    'success' => false,
                    'message' => 'Erro ao obter credenciais: '.$e->getMessage(),
                ],
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{status: int, body: array<string, mixed>}
     */
    public function salvar(int $tenantId, array $body): array
    {
        try {
            $environment = $body['environment'] ?? 'sandbox';
            $provider = strtolower((string) ($body['provider'] ?? 'mercadopago'));
            if (! in_array($provider, self::PROVIDERS, true)) {
                return [
                    'status' => 400,
                    'body' => [
                        'success' => false,
                        'message' => 'Provider inválido. Use mercadopago ou asaas.',
                    ],
                ];
            }

            $isActive = $this->boolish($body['is_active'] ?? false);
            $existente = $this->credentials->obterPorTenantProvider($tenantId, $provider);
            $temTokenSandbox = ! empty($body['access_token_test']) || ! empty($existente['has_token_test']);
            $temTokenProd = ! empty($body['access_token_prod']) || ! empty($existente['has_token_prod']);

            if ($isActive) {
                if ($environment === 'sandbox' && ! $temTokenSandbox) {
                    return [
                        'status' => 400,
                        'body' => [
                            'success' => false,
                            'message' => $provider === 'asaas'
                                ? 'Informe a API key de sandbox do Asaas.'
                                : 'Informe o Access Token de teste do Mercado Pago.',
                        ],
                    ];
                }
                if ($environment === 'production' && ! $temTokenProd) {
                    return [
                        'status' => 400,
                        'body' => [
                            'success' => false,
                            'message' => $provider === 'asaas'
                                ? 'Informe a API key de produção do Asaas.'
                                : 'Informe o Access Token de produção do Mercado Pago.',
                        ],
                    ];
                }
            }

            $plainTest = isset($body['access_token_test']) && $body['access_token_test'] !== ''
                ? AsaasApiKey::normalizar((string) $body['access_token_test'])
                : '';
            $plainProd = isset($body['access_token_prod']) && $body['access_token_prod'] !== ''
                ? AsaasApiKey::normalizar((string) $body['access_token_prod'])
                : '';

            if ($provider === 'asaas' && $plainTest !== '' && ! AsaasApiKey::valida($plainTest, 'sandbox')) {
                return [
                    'status' => 400,
                    'body' => [
                        'success' => false,
                        'message' => AsaasApiKey::mensagemInvalida('sandbox'),
                    ],
                ];
            }
            if ($provider === 'asaas' && $plainProd !== '' && ! AsaasApiKey::valida($plainProd, 'production')) {
                return [
                    'status' => 400,
                    'body' => [
                        'success' => false,
                        'message' => AsaasApiKey::mensagemInvalida('production'),
                    ],
                ];
            }

            $accessTokenTest = $plainTest !== '' ? $this->encryption->encrypt($plainTest) : null;
            $accessTokenProd = $plainProd !== '' ? $this->encryption->encrypt($plainProd) : null;
            $webhookSecret = isset($body['webhook_secret']) && ! empty($body['webhook_secret'])
                ? $this->encryption->encrypt($body['webhook_secret'])
                : null;

            $metodos = $this->metodosDoBody($body, $existente);
            $sobretaxa = $this->sobretaxaDoBody($provider, $body, $existente);
            $dados = [
                'provider' => $provider,
                'environment' => $environment,
                'public_key_test' => $body['public_key_test'] ?? ($existente['public_key_test'] ?? null),
                'public_key_prod' => $body['public_key_prod'] ?? ($existente['public_key_prod'] ?? null),
                'is_active' => $isActive ? 1 : 0,
                'access_token_test' => $accessTokenTest,
                'access_token_prod' => $accessTokenProd,
                'webhook_secret' => $webhookSecret,
                'enable_pix' => $metodos['enable_pix'],
                'enable_boleto' => $metodos['enable_boleto'],
                'enable_credit_card' => $metodos['enable_credit_card'],
                'enable_sobretaxa_antecipacao' => $sobretaxa['enable_sobretaxa_antecipacao'],
                'sobretaxa_antecipacao_avista_percentual' => $sobretaxa['sobretaxa_antecipacao_avista_percentual'],
                'sobretaxa_antecipacao_parcelada_percentual' => $sobretaxa['sobretaxa_antecipacao_parcelada_percentual'],
                'enable_antecipacao_automatica' => $sobretaxa['enable_antecipacao_automatica'],
                'sobretaxa_folga_percentual' => $sobretaxa['sobretaxa_folga_percentual'],
                'usar_taxa_antecipacao_asaas' => $sobretaxa['usar_taxa_antecipacao_asaas'],
                'conciliar_somente_vinculados' => $this->somenteVinculadosDoBody($provider, $body, $existente),
                'enable_pix_recorrencia' => $this->pixRecorrenciaDoBody($provider, $body, $existente),
                'max_parcelas_cartao' => AsaasTarifaCartao::maxParcelas($body['max_parcelas_cartao'] ?? $existente['max_parcelas_cartao'] ?? null),
            ];

            if ($this->credentials->existePorTenantProvider($tenantId, $provider)) {
                $update = [
                    'environment' => $dados['environment'],
                    'public_key_test' => array_key_exists('public_key_test', $body)
                        ? $body['public_key_test']
                        : ($existente['public_key_test'] ?? null),
                    'public_key_prod' => array_key_exists('public_key_prod', $body)
                        ? $body['public_key_prod']
                        : ($existente['public_key_prod'] ?? null),
                    'is_active' => $dados['is_active'],
                ];

                if ($accessTokenTest !== null) {
                    $update['access_token_test'] = $accessTokenTest;
                }
                if ($accessTokenProd !== null) {
                    $update['access_token_prod'] = $accessTokenProd;
                }
                if ($webhookSecret !== null) {
                    $update['webhook_secret'] = $webhookSecret;
                }

                $update['enable_pix'] = $metodos['enable_pix'];
                $update['enable_boleto'] = $metodos['enable_boleto'];
                $update['enable_credit_card'] = $metodos['enable_credit_card'];
                $update['enable_sobretaxa_antecipacao'] = $sobretaxa['enable_sobretaxa_antecipacao'];
                $update['sobretaxa_antecipacao_avista_percentual'] = $sobretaxa['sobretaxa_antecipacao_avista_percentual'];
                $update['sobretaxa_antecipacao_parcelada_percentual'] = $sobretaxa['sobretaxa_antecipacao_parcelada_percentual'];
                $update['enable_antecipacao_automatica'] = $sobretaxa['enable_antecipacao_automatica'];
                $update['sobretaxa_folga_percentual'] = $sobretaxa['sobretaxa_folga_percentual'];
                $update['usar_taxa_antecipacao_asaas'] = $sobretaxa['usar_taxa_antecipacao_asaas'];
                $update['conciliar_somente_vinculados'] = $dados['conciliar_somente_vinculados'];
                $update['enable_pix_recorrencia'] = $dados['enable_pix_recorrencia'];
                $update['max_parcelas_cartao'] = $dados['max_parcelas_cartao'];

                $this->credentials->atualizar($tenantId, $provider, $update);
                $message = 'Credenciais atualizadas com sucesso';
            } else {
                $this->credentials->inserir($tenantId, $dados);
                $message = 'Credenciais cadastradas com sucesso';
            }

            if ($provider === 'asaas' && $isActive) {
                $this->garantirWebhookAsaas($tenantId);
            }

            $this->sincronizarParametrosMetodos($tenantId);

            $aviso = $provider === 'asaas'
                ? $this->aplicarAntecipacaoNoAsaas($tenantId, $existente, (bool) $sobretaxa['enable_antecipacao_automatica'])
                : null;

            return [
                'status' => 200,
                'body' => array_filter([
                    'success' => true,
                    'message' => $message,
                    'aviso' => $aviso,
                ], fn ($v) => $v !== null),
            ];
        } catch (Throwable $e) {
            error_log('[PaymentCredentials] Erro ao salvar: '.$e->getMessage());

            return [
                'status' => 500,
                'body' => [
                    'success' => false,
                    'message' => 'Erro ao salvar credenciais: '.$e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Troca sandbox ↔ produção sem reenviar chaves (permanecem criptografadas no banco).
     *
     * @param  array<string, mixed>  $body
     * @return array{status: int, body: array<string, mixed>}
     */
    public function alterarAmbiente(int $tenantId, array $body): array
    {
        try {
            $provider = strtolower((string) ($body['provider'] ?? ''));
            $environment = strtolower(trim((string) ($body['environment'] ?? '')));

            if (! in_array($provider, self::PROVIDERS, true)) {
                return [
                    'status' => 400,
                    'body' => [
                        'success' => false,
                        'message' => 'Provider inválido. Use mercadopago ou asaas.',
                    ],
                ];
            }

            if (! in_array($environment, ['sandbox', 'production'], true)) {
                return [
                    'status' => 400,
                    'body' => [
                        'success' => false,
                        'message' => 'Ambiente inválido. Use sandbox ou production.',
                    ],
                ];
            }

            $existente = $this->credentials->obterPorTenantProvider($tenantId, $provider);
            if (! $existente) {
                return [
                    'status' => 400,
                    'body' => [
                        'success' => false,
                        'message' => 'Salve as credenciais deste provedor antes de trocar o ambiente.',
                    ],
                ];
            }

            $temToken = $this->credentials->temTokenAmbiente($tenantId, $provider, $environment);

            if (! $temToken) {
                $rotulo = $environment === 'production' ? 'produção' : 'sandbox';
                $campo = $provider === 'asaas' ? 'API key' : 'Access Token';
                $nome = $provider === 'asaas' ? 'Asaas' : 'Mercado Pago';

                return [
                    'status' => 400,
                    'body' => [
                        'success' => false,
                        'code' => 'SEM_TOKEN_AMBIENTE',
                        'message' => "Não há {$campo} de {$rotulo} do {$nome} salvo nesta academia. "
                            .'Cole a chave na seção correspondente, clique em Salvar e só então mude o ambiente. '
                            .'A troca não reutiliza o token do outro ambiente.',
                    ],
                ];
            }

            if (strtolower((string) ($existente['environment'] ?? '')) !== $environment) {
                $this->credentials->atualizarAmbiente($tenantId, $provider, $environment);
            }

            if ($provider === 'asaas' && $this->boolish($existente['is_active'] ?? false)) {
                $this->garantirWebhookAsaas($tenantId);
            }

            $atualizado = $this->credentials->obterPorTenantProvider($tenantId, $provider) ?? $existente;

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'message' => $environment === 'production'
                        ? 'Ambiente de produção ativado. As chaves criptografadas no banco foram mantidas.'
                        : 'Ambiente sandbox ativado. As chaves criptografadas no banco foram mantidas.',
                    'data' => $this->mascararLinha($atualizado),
                ],
            ];
        } catch (Throwable $e) {
            error_log('[PaymentCredentials] Erro ao alterar ambiente: '.$e->getMessage());

            return [
                'status' => 500,
                'body' => [
                    'success' => false,
                    'message' => 'Erro ao alterar ambiente: '.$e->getMessage(),
                ],
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{status: int, body: array<string, mixed>}
     */
    public function testar(int $tenantId, array $body = []): array
    {
        try {
            $provider = strtolower((string) ($body['provider'] ?? ''));
            if ($provider === '') {
                $row = $this->credentials->obterPorTenantProvider($tenantId, 'asaas')
                    ?? $this->credentials->obterPorTenantProvider($tenantId, 'mercadopago');
                $provider = strtolower((string) ($row['provider'] ?? 'mercadopago'));
            }

            if ($provider === 'asaas') {
                $asaas = new AsaasService($tenantId);
                $resultado = $asaas->testarConexao();
                $this->garantirWebhookAsaas($tenantId);

                return [
                    'status' => 200,
                    'body' => [
                        'success' => true,
                        'message' => $resultado['message'],
                        'data' => $resultado['account'] ?? [],
                    ],
                ];
            }

            $mercadoPago = new MercadoPagoService($tenantId);
            $publicKey = $mercadoPago->getPublicKey();

            if (empty($publicKey)) {
                return [
                    'status' => 400,
                    'body' => [
                        'success' => false,
                        'message' => 'Credenciais não configuradas ou inválidas',
                    ],
                ];
            }

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'message' => 'Conexão com Mercado Pago OK',
                    'data' => [
                        'public_key_prefix' => substr($publicKey, 0, 15).'...',
                    ],
                ],
            ];
        } catch (Throwable $e) {
            return [
                'status' => 500,
                'body' => [
                    'success' => false,
                    'message' => 'Erro ao testar conexão: '.$e->getMessage(),
                ],
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mascararLinha(array $row): array
    {
        $provider = strtolower((string) ($row['provider'] ?? ''));
        $row['nome'] = $provider === 'asaas' ? 'Asaas' : 'Mercado Pago';
        $row['is_active'] = $this->boolish($row['is_active'] ?? false);
        $row['enable_pix'] = $this->boolish($row['enable_pix'] ?? true);
        $row['enable_boleto'] = $this->boolish($row['enable_boleto'] ?? true);
        $row['enable_credit_card'] = $this->boolish($row['enable_credit_card'] ?? true);
        $row['enable_antecipacao_automatica'] = $this->boolish($row['enable_antecipacao_automatica'] ?? $row['enable_sobretaxa_antecipacao'] ?? false);
        $row['enable_sobretaxa_antecipacao'] = $this->boolish($row['enable_sobretaxa_antecipacao'] ?? false);
        $row['conciliar_somente_vinculados'] = $this->boolish($row['conciliar_somente_vinculados'] ?? $provider === 'mercadopago');
        $row['enable_pix_recorrencia'] = $this->boolish($row['enable_pix_recorrencia'] ?? false);
        $row['max_parcelas_cartao'] = AsaasTarifaCartao::maxParcelas($row['max_parcelas_cartao'] ?? null);
        $row['sobretaxa_antecipacao_avista_percentual'] = round((float) ($row['sobretaxa_antecipacao_avista_percentual'] ?? 1.15), 2);
        $row['sobretaxa_antecipacao_parcelada_percentual'] = round((float) ($row['sobretaxa_antecipacao_parcelada_percentual'] ?? 1.60), 2);
        $row['sobretaxa_folga_percentual'] = round((float) ($row['sobretaxa_folga_percentual'] ?? 1.00), 2);
        $row['usar_taxa_antecipacao_asaas'] = $this->boolish($row['usar_taxa_antecipacao_asaas'] ?? false);
        $row['taxa_antecipacao_asaas_avista'] ??= null;
        $row['taxa_antecipacao_asaas_parcelada'] ??= null;
        $row['has_token_test'] = $this->boolish($row['has_token_test'] ?? false);
        $row['has_token_prod'] = $this->boolish($row['has_token_prod'] ?? false);
        $row['public_key_test_masked'] = $this->mascarar($row['public_key_test'] ?? null);
        $row['public_key_prod_masked'] = $this->mascarar($row['public_key_prod'] ?? null);
        unset($row['public_key_test'], $row['public_key_prod']);

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function slotVazio(string $provider): array
    {
        return [
            'provider' => $provider,
            'nome' => $provider === 'asaas' ? 'Asaas' : 'Mercado Pago',
            'environment' => 'sandbox',
            'is_active' => false,
            'enable_pix' => true,
            'enable_boleto' => true,
            'enable_credit_card' => true,
            'enable_antecipacao_automatica' => false,
            'conciliar_somente_vinculados' => $provider === 'mercadopago',
            'enable_pix_recorrencia' => false,
            'max_parcelas_cartao' => AsaasTarifaCartao::MAX_PARCELAS_PADRAO,
            'enable_sobretaxa_antecipacao' => false,
            'sobretaxa_antecipacao_avista_percentual' => 1.15,
            'sobretaxa_antecipacao_parcelada_percentual' => 1.60,
            'sobretaxa_folga_percentual' => 1.00,
            'usar_taxa_antecipacao_asaas' => false,
            'taxa_antecipacao_asaas_avista' => null,
            'taxa_antecipacao_asaas_parcelada' => null,
            'has_token_test' => false,
            'has_token_prod' => false,
            'public_key_test_masked' => null,
            'public_key_prod_masked' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>|null  $existente
     * @return array{enable_antecipacao_automatica: int, enable_sobretaxa_antecipacao: int, sobretaxa_antecipacao_avista_percentual: float, sobretaxa_antecipacao_parcelada_percentual: float, sobretaxa_folga_percentual: float, usar_taxa_antecipacao_asaas: int}
     */
    private function sobretaxaDoBody(string $provider, array $body, ?array $existente): array
    {
        $avista = $this->percentualOuPadrao(
            $body['sobretaxa_antecipacao_avista_percentual'] ?? $existente['sobretaxa_antecipacao_avista_percentual'] ?? 1.15,
            1.15
        );
        $parcelada = $this->percentualOuPadrao(
            $body['sobretaxa_antecipacao_parcelada_percentual'] ?? $existente['sobretaxa_antecipacao_parcelada_percentual'] ?? 1.60,
            1.60
        );
        $antecipacao = $provider === 'asaas'
            && $this->boolish($body['enable_antecipacao_automatica'] ?? $existente['enable_antecipacao_automatica'] ?? false);
        $habilitada = $antecipacao
            && $this->boolish($body['enable_sobretaxa_antecipacao'] ?? $existente['enable_sobretaxa_antecipacao'] ?? false);

        return [
            'enable_antecipacao_automatica' => $antecipacao ? 1 : 0,
            'enable_sobretaxa_antecipacao' => $habilitada ? 1 : 0,
            'sobretaxa_antecipacao_avista_percentual' => $avista,
            'sobretaxa_antecipacao_parcelada_percentual' => $parcelada,
            'sobretaxa_folga_percentual' => $this->percentualOuPadrao(
                $body['sobretaxa_folga_percentual'] ?? $existente['sobretaxa_folga_percentual'] ?? 1.00,
                1.00
            ),
            'usar_taxa_antecipacao_asaas' => $habilitada
                && $this->boolish($body['usar_taxa_antecipacao_asaas'] ?? $existente['usar_taxa_antecipacao_asaas'] ?? false) ? 1 : 0,
        ];
    }

    /** Conciliação só com recebimentos ligados a matrícula; padrão ligado no Mercado Pago. */
    private function somenteVinculadosDoBody(string $provider, array $body, ?array $existente): int
    {
        $valor = $body['conciliar_somente_vinculados'] ?? $existente['conciliar_somente_vinculados'] ?? $provider === 'mercadopago';

        return $this->boolish($valor) ? 1 : 0;
    }

    private function percentualOuPadrao(mixed $valor, float $padrao): float
    {
        if (! is_numeric($valor)) {
            return $padrao;
        }
        $v = round((float) $valor, 2);
        if ($v < 0 || $v > 30) {
            return $padrao;
        }

        return $v;
    }

    /** PIX na assinatura mensal: só no Asaas (único gateway da recorrência) e com PIX ligado. */
    private function pixRecorrenciaDoBody(string $provider, array $body, ?array $existente): int
    {
        if ($provider !== 'asaas') {
            return 0;
        }
        $pix = $this->boolish($body['enable_pix'] ?? $existente['enable_pix'] ?? true);

        return $pix && $this->boolish($body['enable_pix_recorrencia'] ?? $existente['enable_pix_recorrencia'] ?? false) ? 1 : 0;
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>|null  $existente
     * @return array{enable_pix: int, enable_boleto: int, enable_credit_card: int}
     */
    private function metodosDoBody(array $body, ?array $existente): array
    {
        return [
            'enable_pix' => $this->boolish($body['enable_pix'] ?? $existente['enable_pix'] ?? true) ? 1 : 0,
            'enable_boleto' => $this->boolish($body['enable_boleto'] ?? $existente['enable_boleto'] ?? true) ? 1 : 0,
            'enable_credit_card' => $this->boolish($body['enable_credit_card'] ?? $existente['enable_credit_card'] ?? true) ? 1 : 0,
        ];
    }

    private function sincronizarParametrosMetodos(int $tenantId): void
    {
        $pix = false;
        $boleto = false;
        $cartao = false;
        $temAtivo = false;
        foreach ($this->credentials->listarPorTenant($tenantId) as $row) {
            if (! $this->boolish($row['is_active'] ?? false)) {
                continue;
            }
            $temAtivo = true;
            $pix = $pix || $this->boolish($row['enable_pix'] ?? true);
            $boleto = $boleto || $this->boolish($row['enable_boleto'] ?? true);
            $cartao = $cartao || $this->boolish($row['enable_credit_card'] ?? true);
        }
        if (! $temAtivo) {
            return;
        }

        try {
            $parametro = new Parametro(DB::connection()->getPdo());
            $parametro->set($tenantId, 'habilitar_pix', $pix);
            $parametro->set($tenantId, 'habilitar_boleto', $boleto);
            $parametro->set($tenantId, 'habilitar_cartao_credito', $cartao);
        } catch (Throwable $e) {
            error_log('[PaymentCredentials] Parâmetros de método: '.$e->getMessage());
        }
    }

    /**
     * A antecipação pode ser ligada ou desligada direto no Asaas: o estado da conta prevalece.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function antecipacaoDaConta(int $tenantId, array $row): array
    {
        $ambiente = ($row['environment'] ?? 'sandbox') === 'production' ? 'has_token_prod' : 'has_token_test';
        if (! $this->boolish($row['is_active'] ?? false) || ! $this->boolish($row[$ambiente] ?? false)) {
            return $row;
        }

        try {
            $naConta = ($this->asaas)($tenantId)->antecipacaoAutomaticaNaConta();
        } catch (Throwable) {
            $naConta = null;
        }
        $local = $this->boolish($row['enable_antecipacao_automatica'] ?? false);
        if ($naConta !== null && $naConta !== $local) {
            $this->credentials->definirAntecipacaoAutomatica($tenantId, 'asaas', $naConta);
            $row['enable_antecipacao_automatica'] = $naConta;
        }

        // Taxas que o Asaas informa para a conta: o painel mostra ao lado dos campos
        try {
            $taxas = ($this->asaas)($tenantId)->taxasAntecipacaoInformadas();
            $row['taxa_antecipacao_asaas_avista'] = $taxas['avista'];
            $row['taxa_antecipacao_asaas_parcelada'] = $taxas['parcelada'];
        } catch (Throwable) {
            // sem as taxas da conta o painel segue só com os campos
        }

        return $row;
    }

    /**
     * Leva a escolha da antecipação automática para a conta Asaas. Se o Asaas recusar,
     * volta a flag para o valor anterior e devolve o aviso para o painel.
     *
     * @param  array<string, mixed>|null  $existente
     */
    private function aplicarAntecipacaoNoAsaas(int $tenantId, ?array $existente, bool $ligar): ?string
    {
        $antes = $this->boolish($existente['enable_antecipacao_automatica'] ?? false);
        if ($ligar === $antes) {
            return null;
        }

        try {
            $confirmado = ($this->asaas)($tenantId)->configurarAntecipacaoAutomatica($ligar);
            if ($confirmado === $ligar) {
                return null;
            }
            $motivo = 'o Asaas não confirmou a alteração';
        } catch (Throwable $e) {
            $motivo = preg_replace('/^Asaas:\s*/', '', $e->getMessage());
        }

        $this->credentials->definirAntecipacaoAutomatica($tenantId, 'asaas', $antes);

        return ($ligar
            ? 'As outras alterações foram salvas, mas a antecipação automática não foi ligada no Asaas: '
            : 'As outras alterações foram salvas, mas a antecipação automática não foi desligada no Asaas: ').$motivo.'.';
    }

    private function garantirWebhookAsaas(int $tenantId): void
    {
        try {
            (new AsaasService($tenantId))->garantirWebhook();
        } catch (Throwable $e) {
            error_log('[PaymentCredentials] Webhook Asaas: '.$e->getMessage());
        }
    }

    private function boolish(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) || $value === 1 || $value === '1';
    }

    private function mascarar(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $length = strlen($value);
        if ($length <= 10) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 6).str_repeat('*', $length - 10).substr($value, -4);
    }
}
