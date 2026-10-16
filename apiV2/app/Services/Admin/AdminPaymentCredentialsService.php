<?php

namespace App\Services\Admin;

use App\Repositories\TenantPaymentCredentialsRepository;
use App\Services\EncryptionService;
use App\Services\MercadoPagoService;
use App\Support\AsaasApiKey;
use Throwable;

/**
 * Credenciais de pagamento do tenant (um registro por provider).
 */
class AdminPaymentCredentialsService
{
    /** @var list<string> */
    private const PROVIDERS = ['mercadopago', 'asaas'];

    public function __construct(
        private readonly TenantPaymentCredentialsRepository $credentials,
        private readonly EncryptionService $encryption,
    ) {}

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function obter(int $tenantId): array
    {
        try {
            $porCodigo = [];
            foreach ($this->credentials->listarPorTenant($tenantId) as $row) {
                $codigo = strtolower((string) ($row['provider'] ?? ''));
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

            $dados = [
                'provider' => $provider,
                'environment' => $environment,
                'public_key_test' => $body['public_key_test'] ?? ($existente['public_key_test'] ?? null),
                'public_key_prod' => $body['public_key_prod'] ?? ($existente['public_key_prod'] ?? null),
                'is_active' => $isActive ? 1 : 0,
                'access_token_test' => $accessTokenTest,
                'access_token_prod' => $accessTokenProd,
                'webhook_secret' => $webhookSecret,
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

                $this->credentials->atualizar($tenantId, $provider, $update);
                $message = 'Credenciais atualizadas com sucesso';
            } else {
                $this->credentials->inserir($tenantId, $dados);
                $message = 'Credenciais cadastradas com sucesso';
            }

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'message' => $message,
                ],
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
                $asaas = new \App\Services\Asaas\AsaasService($tenantId);
                $resultado = $asaas->testarConexao();

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
            'has_token_test' => false,
            'has_token_prod' => false,
            'public_key_test_masked' => null,
            'public_key_prod_masked' => null,
        ];
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
