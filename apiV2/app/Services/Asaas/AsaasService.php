<?php

namespace App\Services\Asaas;

use App\Services\EncryptionService;
use App\Support\AcademyDateTime;
use App\Support\AsaasApiKey;
use App\Support\AsaasSobretaxaAntecipacao;
use App\Support\AsaasTarifaCartao;
use App\Support\ReferenciaExterna;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AsaasService
{
    private string $accessToken = '';
    private string $baseUrl;
    private bool $productionMode = false;
    private ?int $tenantId = null;
    private bool $enablePix = true;
    private bool $enableBoleto = true;
    private bool $enableCreditCard = true;
    private bool $enableSobretaxaAntecipacao = false;
    private float $sobretaxaAvistaPercentual = AsaasSobretaxaAntecipacao::AVISTA_PADRAO;
    private float $sobretaxaParceladaPercentual = AsaasSobretaxaAntecipacao::PARCELADA_PADRAO;
    /** @var array<string, mixed>|null */
    private ?array $taxasContaCache = null;

    public function __construct(?int $tenantId = null)
    {
        $this->tenantId = $tenantId;
        $this->baseUrl = (string) config('appcheckin.asaas.sandbox_url');
        $this->carregarCredenciais();
    }

    public function criarPagamentoPix(array $data): array
    {
        $this->validarCredenciais();
        if (! $this->enablePix) {
            throw new \RuntimeException('PIX não está habilitado nas credenciais Asaas desta academia.');
        }

        $cpf = isset($data['aluno_cpf']) ? preg_replace('/[^0-9]/', '', (string) $data['aluno_cpf']) : '';
        if (strlen($cpf) !== 11) {
            throw new \RuntimeException('CPF válido é obrigatório para pagamento PIX');
        }

        $matriculaId = ReferenciaExterna::normalizarId($data['matricula_id'] ?? null);
        if (! $matriculaId) {
            throw new \RuntimeException('matricula_id é obrigatório para pagamento PIX');
        }
        $externalReference = ReferenciaExterna::matricula($matriculaId);

        $customerId = $this->encontrarOuCriarCliente([
            'name' => trim((string) ($data['aluno_nome'] ?? 'Aluno')),
            'cpfCnpj' => $cpf,
            'email' => $data['aluno_email'] ?? null,
            'mobilePhone' => preg_replace('/[^0-9]/', '', (string) ($data['aluno_telefone'] ?? '')) ?: null,
        ]);

        $dueDate = AcademyDateTime::today();
        $payment = $this->request('POST', '/payments', [
            'customer' => $customerId,
            'billingType' => 'PIX',
            'value' => round((float) $data['valor'], 2),
            'dueDate' => $dueDate,
            'description' => $data['descricao'] ?? $data['plano_nome'] ?? 'Pagamento PIX',
            'externalReference' => $externalReference,
        ]);

        $paymentId = (string) ($payment['id'] ?? '');
        if ($paymentId === '') {
            throw new \RuntimeException('Asaas não retornou o ID da cobrança PIX');
        }

        try {
            $this->garantirWebhook();
        } catch (\Throwable $e) {
            Log::warning('Asaas: falha ao garantir webhook', ['error' => $e->getMessage()]);
        }

        $qr = $this->request('GET', '/payments/'.$paymentId.'/pixQrCode');
        $encoded = (string) ($qr['encodedImage'] ?? '');
        if ($encoded !== '' && ! str_starts_with($encoded, 'data:')) {
            $encoded = 'data:image/png;base64,'.$encoded;
        }

        return [
            'id' => $paymentId,
            'status' => $this->mapStatus((string) ($payment['status'] ?? 'PENDING')),
            'status_detail' => $payment['status'] ?? null,
            'external_reference' => $payment['externalReference'] ?? $externalReference,
            'date_of_expiration' => $qr['expirationDate'] ?? $payment['dueDate'] ?? null,
            'qr_code' => $qr['payload'] ?? null,
            'qr_code_base64' => $encoded !== '' ? $encoded : null,
            'ticket_url' => $payment['invoiceUrl'] ?? $payment['bankSlipUrl'] ?? null,
            'raw' => ['payment' => $payment, 'qr' => $qr],
        ];
    }

    /**
     * Cobrança Asaas com boleto e/ou cartão (invoiceUrl). Vários métodos = UNDEFINED.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function criarCobrancaCheckout(array $data): array
    {
        $this->validarCredenciais();

        $tipos = $this->billingTypesHabilitados();
        $tiposCheckout = array_values(array_filter($tipos, static fn (string $tipo) => $tipo !== 'PIX'));
        if ($tiposCheckout === []) {
            throw new \RuntimeException('Boleto e cartão não estão habilitados nas credenciais Asaas.');
        }

        $cpf = isset($data['aluno_cpf']) ? preg_replace('/[^0-9]/', '', (string) $data['aluno_cpf']) : '';
        if (strlen($cpf) !== 11) {
            throw new \RuntimeException('CPF válido é obrigatório para pagamento Asaas');
        }

        $matriculaId = ReferenciaExterna::normalizarId($data['matricula_id'] ?? null);
        if (! $matriculaId) {
            throw new \RuntimeException('matricula_id é obrigatório para pagamento Asaas');
        }
        $externalReference = ReferenciaExterna::matricula($matriculaId);

        $customerId = $this->encontrarOuCriarCliente([
            'name' => trim((string) ($data['aluno_nome'] ?? 'Aluno')),
            'cpfCnpj' => $cpf,
            'email' => $data['aluno_email'] ?? null,
            'mobilePhone' => preg_replace('/[^0-9]/', '', (string) ($data['aluno_telefone'] ?? '')) ?: null,
        ]);

        $pedido = strtoupper((string) ($data['billing_type'] ?? $data['metodo_pagamento'] ?? ''));
        if ($pedido === 'CARTAO' || $pedido === 'CARD') {
            $pedido = 'CREDIT_CARD';
        }
        if ($pedido === 'BOLETO' && in_array('BOLETO', $tiposCheckout, true)) {
            $billingType = 'BOLETO';
        } elseif ($pedido === 'CREDIT_CARD' && in_array('CREDIT_CARD', $tiposCheckout, true)) {
            $billingType = 'CREDIT_CARD';
        } else {
            $billingType = count($tiposCheckout) === 1 ? $tiposCheckout[0] : 'UNDEFINED';
        }
        $dueDate = AcademyDateTime::today();
        $valorPlano = round((float) $data['valor'], 2);
        $valorCobranca = $valorPlano;
        $tarifaCartao = null;
        $parcelas = (int) ($data['installment_count'] ?? $data['parcelas'] ?? 1);
        if ($parcelas < 1) {
            $parcelas = 1;
        }
        if ($parcelas > 12) {
            $parcelas = 12;
        }
        $descricao = (string) ($data['descricao'] ?? $data['plano_nome'] ?? 'Pagamento');
        if ($billingType === 'CREDIT_CARD') {
            try {
                $tarifaCartao = $this->valorComTaxaCartaoRepassada($valorPlano, $parcelas);
                $valorCobranca = $tarifaCartao['valor_cobrar'];
                $parcelas = (int) ($tarifaCartao['parcela_count'] ?? $parcelas);
                if ($tarifaCartao['tarifa'] > 0 || ($tarifaCartao['sobretaxa'] ?? 0) > 0) {
                    $partes = [];
                    if ($tarifaCartao['tarifa'] > 0) {
                        $partes[] = 'taxa cartão Asaas R$ '.number_format((float) $tarifaCartao['tarifa'], 2, ',', '.');
                    }
                    if (($tarifaCartao['sobretaxa'] ?? 0) > 0.001) {
                        $partes[] = 'antecipação R$ '.number_format((float) $tarifaCartao['sobretaxa'], 2, ',', '.');
                    }
                    $descricao .= ' (inclui '.implode(', ', $partes);
                    if ($parcelas > 1) {
                        $descricao .= ', '.$parcelas.'x';
                    }
                    $descricao .= ')';
                }
            } catch (\Throwable $e) {
                Log::warning('Asaas: simulação de taxa de cartão falhou; cobrando valor do plano', [
                    'tenant_id' => $this->tenantId,
                    'valor' => $valorPlano,
                    'parcelas' => $parcelas,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        $payloadPagamento = [
            'customer' => $customerId,
            'billingType' => $billingType,
            'dueDate' => $dueDate,
            'description' => $descricao,
            'externalReference' => $externalReference,
        ];
        if ($billingType === 'CREDIT_CARD' && $parcelas > 1) {
            $payloadPagamento['installmentCount'] = $parcelas;
            $payloadPagamento['installmentValue'] = AsaasTarifaCartao::valorParcela($valorCobranca, $parcelas);
        } else {
            $payloadPagamento['value'] = $valorCobranca;
        }
        $payment = $this->request('POST', '/payments', $payloadPagamento);

        $paymentId = (string) ($payment['id'] ?? '');
        if ($paymentId === '') {
            throw new \RuntimeException('Asaas não retornou o ID da cobrança');
        }

        try {
            $this->garantirWebhook();
        } catch (\Throwable $e) {
            Log::warning('Asaas: falha ao garantir webhook', ['error' => $e->getMessage()]);
        }

        return [
            'id' => $paymentId,
            'status' => $this->mapStatus((string) ($payment['status'] ?? 'PENDING')),
            'status_detail' => $payment['status'] ?? null,
            'external_reference' => $payment['externalReference'] ?? $externalReference,
            'date_of_expiration' => $payment['dueDate'] ?? null,
            'qr_code' => null,
            'qr_code_base64' => null,
            'ticket_url' => $payment['invoiceUrl'] ?? $payment['bankSlipUrl'] ?? null,
            'valor' => $valorCobranca,
            'valor_plano' => $valorPlano,
            'tarifa_cartao' => $tarifaCartao,
            'raw' => ['payment' => $payment, 'billingType' => $billingType],
        ];
    }

    /**
     * Simula taxas Asaas (POST /payments/simulate) e devolve o valor a cobrar no cartão
     * para a academia receber o líquido do plano.
     *
     * @return array{valor_plano: float, valor_cobrar: float, tarifa: float, fee_percentage: float, operation_fee: float, net_value_simulado: ?float, parcela_count: int, valor_parcela: float}
     */
    public function valorComTaxaCartaoRepassada(float $valorLiquidoDesejado, int $parcelas = 1, bool $refinar = true): array
    {
        $valorLiquidoDesejado = round($valorLiquidoDesejado, 2);
        $parcelas = max(1, min(12, $parcelas));
        $sim = $this->simularVenda($valorLiquidoDesejado, ['CREDIT_CARD'], $parcelas);
        $creditCard = is_array($sim['creditCard'] ?? null) ? $sim['creditCard'] : [];
        $repasse = AsaasTarifaCartao::aPartirDaSimulacao($valorLiquidoDesejado, $creditCard);
        if ($repasse['tarifa'] > 0 && $refinar) {
            $sim2 = $this->simularVenda($repasse['valor_cobrar'], ['CREDIT_CARD'], $parcelas);
            $cc2 = is_array($sim2['creditCard'] ?? null) ? $sim2['creditCard'] : [];
            $net2 = isset($cc2['netValue']) ? round((float) $cc2['netValue'], 2) : null;
            if ($net2 !== null) {
                $repasse['valor_cobrar'] = AsaasTarifaCartao::ajustarPeloLiquidoSimulado(
                    $repasse['valor_cobrar'],
                    $valorLiquidoDesejado,
                    $net2,
                    (float) ($cc2['feePercentage'] ?? $repasse['fee_percentage'])
                );
                $repasse['tarifa'] = round($repasse['valor_cobrar'] - $valorLiquidoDesejado, 2);
                $repasse['net_value_simulado'] = $net2;
                $repasse['fee_percentage'] = (float) ($cc2['feePercentage'] ?? $repasse['fee_percentage']);
                $repasse['operation_fee'] = (float) ($cc2['operationFee'] ?? $repasse['operation_fee']);
            }
            $creditCard = $cc2 !== [] ? $cc2 : $creditCard;
        }

        $repasse = AsaasSobretaxaAntecipacao::aplicar(
            $repasse,
            $this->configSobretaxa(),
            $parcelas,
            $creditCard,
            $this->taxasDaConta(),
        );

        return $this->anexarParcela($repasse, $parcelas);
    }

    /**
     * Uma simulação por opção (sem 2ª chamada de ajuste) para o app listar 1x–6x e 12x.
     *
     * @return list<array<string, mixed>>
     */
    public function simularOpcoesParcelasCartao(float $valorLiquidoDesejado): array
    {
        $valorLiquidoDesejado = round($valorLiquidoDesejado, 2);
        $opcoes = [];
        foreach (AsaasTarifaCartao::OPCOES_PARCELAS as $n) {
            if (! AsaasTarifaCartao::cabeEmParcelas($valorLiquidoDesejado, $n)) {
                continue;
            }
            try {
                $opcoes[] = $this->valorComTaxaCartaoRepassada($valorLiquidoDesejado, $n, false);
            } catch (\Throwable $e) {
                Log::info('Asaas: opção de parcela indisponível na simulação', [
                    'tenant_id' => $this->tenantId,
                    'valor' => $valorLiquidoDesejado,
                    'parcelas' => $n,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $opcoes;
    }

    /**
     * @param  array<string, mixed>  $repasse
     * @return array<string, mixed>
     */
    private function anexarParcela(array $repasse, int $parcelas): array
    {
        $total = (float) $repasse['valor_cobrar'];
        $repasse['parcela_count'] = $parcelas;
        $repasse['valor_parcela'] = AsaasTarifaCartao::valorParcela($total, $parcelas);

        return $repasse;
    }

    /**
     * @param  list<string>  $billingTypes
     * @return array<string, mixed>
     */
    public function simularVenda(float $valor, array $billingTypes = ['CREDIT_CARD'], int $parcelas = 1): array
    {
        $this->validarCredenciais();
        $body = [
            'value' => round($valor, 2),
            'billingTypes' => $billingTypes,
        ];
        if ($parcelas > 1) {
            $body['installmentCount'] = $parcelas;
        }

        return $this->request('POST', '/payments/simulate', $body);
    }

    /**
     * @return array{ok: bool, account?: array<string, mixed>, message: string}
     */
    public function testarConexao(): array
    {
        $this->validarCredenciais();
        $account = $this->request('GET', '/myAccount');

        return [
            'ok' => true,
            'account' => [
                'name' => $account['name'] ?? null,
                'email' => $account['email'] ?? null,
                'walletId' => $account['walletId'] ?? null,
            ],
            'message' => 'Conexão com Asaas OK ('.($this->productionMode ? 'produção' : 'sandbox').')',
        ];
    }

    public function credenciaisOficiaisValidas(): bool
    {
        $environment = $this->productionMode ? 'production' : 'sandbox';

        return $this->accessToken !== '' && AsaasApiKey::valida($this->accessToken, $environment);
    }

    /**
     * @return array<string, mixed>
     */
    public function obterPagamento(string $paymentId): array
    {
        $this->validarCredenciais();

        return $this->request('GET', '/payments/'.$paymentId);
    }

    /**
     * Garante webhook de cobrança apontando para a API v2.
     * Baixa manual no painel Asaas dispara PAYMENT_RECEIVED.
     */
    public function garantirWebhook(): void
    {
        $this->validarCredenciais();
        $url = rtrim((string) config('app.url'), '/').'/v2/webhooks/asaas';
        if (! str_starts_with($url, 'https://')) {
            Log::info('Asaas webhook não registrado: APP_URL precisa ser HTTPS público', ['url' => $url]);

            return;
        }
        $lista = $this->request('GET', '/webhooks');

        foreach ($lista['data'] ?? [] as $webhook) {
            if (! is_array($webhook) || ($webhook['url'] ?? '') !== $url) {
                continue;
            }
            $enabled = (bool) ($webhook['enabled'] ?? false);
            $interrupted = (bool) ($webhook['interrupted'] ?? false);
            if ($enabled && ! $interrupted) {
                return;
            }
            $id = (string) ($webhook['id'] ?? '');
            if ($id !== '') {
                $this->request('PUT', '/webhooks/'.$id, [
                    'enabled' => true,
                    'interrupted' => false,
                ]);
            }

            return;
        }

        $auth = $this->webhookSecret() ?: bin2hex(random_bytes(16));
        $this->request('POST', '/webhooks', [
            'name' => 'AppCheckin matrículas',
            'url' => $url,
            'email' => (string) config('mail.from.address', 'mail@appcheckin.com.br'),
            'enabled' => true,
            'interrupted' => false,
            'apiVersion' => 3,
            'authToken' => $auth,
            'sendType' => 'SEQUENTIALLY',
            'events' => [
                'PAYMENT_CONFIRMED',
                'PAYMENT_RECEIVED',
                'PAYMENT_RECEIVED_IN_CASH',
                'PAYMENT_UPDATED',
            ],
        ]);

        if ($this->tenantId && $this->webhookSecret() === null) {
            $encryption = new EncryptionService();
            DB::table('tenant_payment_credentials')
                ->where('tenant_id', $this->tenantId)
                ->where('provider', 'asaas')
                ->update([
                    'webhook_secret' => $encryption->encrypt($auth),
                    'updated_at' => now(),
                ]);
        }
    }

    public function webhookSecret(): ?string
    {
        if (! $this->tenantId) {
            return null;
        }

        $row = DB::table('tenant_payment_credentials')
            ->where('tenant_id', $this->tenantId)
            ->where('provider', 'asaas')
            ->first(['webhook_secret']);

        $secret = (string) ($row->webhook_secret ?? '');
        if ($secret === '') {
            return null;
        }

        return $this->decryptIfNeeded($secret);
    }

    private function encontrarOuCriarCliente(array $dados): string
    {
        $cpf = $dados['cpfCnpj'];
        $busca = $this->request('GET', '/customers', null, [
            'cpfCnpj' => $cpf,
            'limit' => 1,
        ]);
        $existente = $busca['data'][0]['id'] ?? null;
        if (is_string($existente) && $existente !== '') {
            return $existente;
        }

        $payload = [
            'name' => $dados['name'] !== '' ? $dados['name'] : 'Aluno',
            'cpfCnpj' => $cpf,
            'notificationDisabled' => true,
        ];
        if (! empty($dados['email'])) {
            $payload['email'] = $dados['email'];
        }
        if (! empty($dados['mobilePhone']) && strlen($dados['mobilePhone']) >= 10) {
            $payload['mobilePhone'] = $dados['mobilePhone'];
        }

        $criado = $this->request('POST', '/customers', $payload);
        $id = (string) ($criado['id'] ?? '');
        if ($id === '') {
            throw new \RuntimeException('Asaas não retornou o cliente');
        }

        return $id;
    }

    private function mapStatus(string $asaasStatus): string
    {
        return match (strtoupper($asaasStatus)) {
            'RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH' => 'approved',
            'OVERDUE' => 'expired',
            'REFUNDED', 'REFUND_REQUESTED' => 'refunded',
            'DELETED', 'RESTORED' => 'cancelled',
            default => 'pending',
        };
    }

    private function carregarCredenciais(): void
    {
        if (! $this->tenantId) {
            return;
        }

        $credentials = DB::table('tenant_payment_credentials')
            ->where('tenant_id', $this->tenantId)
            ->where('provider', 'asaas')
            ->first();

        if (! $credentials) {
            return;
        }

        $this->productionMode = ($credentials->environment ?? 'sandbox') === 'production';
        $this->baseUrl = $this->productionMode
            ? (string) config('appcheckin.asaas.production_url')
            : (string) config('appcheckin.asaas.sandbox_url');

        $encrypted = $this->productionMode
            ? (string) ($credentials->access_token_prod ?? '')
            : (string) ($credentials->access_token_test ?? '');

        $this->accessToken = AsaasApiKey::normalizar($this->decryptIfNeeded($encrypted));
        $flags = (array) $credentials;
        $this->enablePix = $this->flagCredencial($flags['enable_pix'] ?? true);
        $this->enableBoleto = $this->flagCredencial($flags['enable_boleto'] ?? true);
        $this->enableCreditCard = $this->flagCredencial($flags['enable_credit_card'] ?? true);
        $this->enableSobretaxaAntecipacao = $this->boolCredencial($flags['enable_sobretaxa_antecipacao'] ?? false);
        if (isset($flags['sobretaxa_antecipacao_avista_percentual']) && is_numeric($flags['sobretaxa_antecipacao_avista_percentual'])) {
            $v = round((float) $flags['sobretaxa_antecipacao_avista_percentual'], 2);
            if ($v >= 0 && $v <= 30) {
                $this->sobretaxaAvistaPercentual = $v;
            }
        }
        if (isset($flags['sobretaxa_antecipacao_parcelada_percentual']) && is_numeric($flags['sobretaxa_antecipacao_parcelada_percentual'])) {
            $v = round((float) $flags['sobretaxa_antecipacao_parcelada_percentual'], 2);
            if ($v >= 0 && $v <= 30) {
                $this->sobretaxaParceladaPercentual = $v;
            }
        }
    }

    /**
     * @return array{habilitada: bool, avista: float, parcelada: float}
     */
    private function configSobretaxa(): array
    {
        return [
            'habilitada' => $this->enableSobretaxaAntecipacao,
            'avista' => $this->sobretaxaAvistaPercentual,
            'parcelada' => $this->sobretaxaParceladaPercentual,
        ];
    }

    /**
     * GET /myAccount/fees — taxas oficiais da conta (antecipação). Falha silenciosa.
     *
     * @return array<string, mixed>
     */
    public function taxasDaConta(): array
    {
        if ($this->taxasContaCache !== null) {
            return $this->taxasContaCache;
        }
        if ($this->accessToken === '' || ! $this->enableSobretaxaAntecipacao) {
            $this->taxasContaCache = [];

            return $this->taxasContaCache;
        }
        try {
            $this->taxasContaCache = $this->request('GET', '/myAccount/fees');
        } catch (\Throwable $e) {
            Log::info('Asaas: taxas da conta indisponíveis', ['error' => $e->getMessage()]);
            $this->taxasContaCache = [];
        }

        return $this->taxasContaCache;
    }

    private function boolCredencial(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) || $value === 1 || $value === '1';
    }

    /**
     * @return list<string>
     */
    private function billingTypesHabilitados(): array
    {
        $tipos = [];
        if ($this->enablePix) {
            $tipos[] = 'PIX';
        }
        if ($this->enableBoleto) {
            $tipos[] = 'BOLETO';
        }
        if ($this->enableCreditCard) {
            $tipos[] = 'CREDIT_CARD';
        }

        return $tipos !== [] ? $tipos : ['PIX', 'BOLETO', 'CREDIT_CARD'];
    }

    private function flagCredencial(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN) || $value === 1 || $value === '1';
    }

    private function decryptIfNeeded(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $encryption = new EncryptionService();
        if ($encryption->isEncrypted($value)) {
            try {
                return $encryption->decrypt($value);
            } catch (\Throwable) {
                return $value;
            }
        }

        return $value;
    }

    private function validarCredenciais(): void
    {
        if ($this->accessToken === '') {
            throw new \RuntimeException('Credenciais Asaas não configuradas para este tenant');
        }

        $environment = $this->productionMode ? 'production' : 'sandbox';
        if (! AsaasApiKey::valida($this->accessToken, $environment)) {
            throw new \RuntimeException(AsaasApiKey::mensagemInvalida($environment));
        }
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $body = null, array $query = []): array
    {
        $url = rtrim($this->baseUrl, '/').'/'.ltrim($path, '/');
        $userAgent = 'AppCheckin/2.0 (PHP; '.($this->productionMode ? 'production' : 'sandbox').')';
        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => $userAgent,
            'access_token' => $this->accessToken,
        ];

        // GET não pode ter body (Asaas responde 403). Não usar asJson() no GET.
        $method = strtoupper($method);
        if ($method === 'GET') {
            $response = Http::withHeaders($headers)->timeout(30)->get($url, $query);
        } else {
            $pending = Http::withHeaders($headers)->asJson()->timeout(30);
            $response = $method === 'PUT'
                ? $pending->put($url, $body ?? [])
                : $pending->post($url, $body ?? []);
        }

        $json = $response->json();
        if (! is_array($json)) {
            $json = [];
        }

        if ($response->failed()) {
            $errors = $json['errors'][0]['description'] ?? $json['message'] ?? $response->body();
            Log::warning('Asaas API erro', [
                'status' => $response->status(),
                'path' => $path,
                'errors' => $json['errors'] ?? $errors,
            ]);
            throw new \RuntimeException('Asaas: '.$errors);
        }

        return $json;
    }
}
