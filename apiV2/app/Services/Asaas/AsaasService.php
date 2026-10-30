<?php

namespace App\Services\Asaas;

use App\Services\EncryptionService;
use App\Support\AcademyDateTime;
use App\Support\AsaasApiKey;
use App\Support\AsaasPagamentoStatus;
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

    private bool $enableAntecipacaoAutomatica = false;

    private float $sobretaxaAvistaPercentual = AsaasSobretaxaAntecipacao::AVISTA_PADRAO;

    private float $sobretaxaParceladaPercentual = AsaasSobretaxaAntecipacao::PARCELADA_PADRAO;

    private int $maxParcelasCartao = AsaasTarifaCartao::MAX_PARCELAS_PADRAO;

    private float $sobretaxaFolgaPercentual = AsaasSobretaxaAntecipacao::FOLGA_PADRAO;

    private bool $usarTaxaAsaas = false;

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

    public function configurada(): bool
    {
        try {
            $this->validarCredenciais();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Formas que a conta aceita (PIX, BOLETO, CREDIT_CARD), ou nenhuma sem credenciais válidas.
     *
     * @return list<string>
     */
    public function formasHabilitadas(): array
    {
        return $this->configurada() ? $this->billingTypesHabilitados() : [];
    }

    /**
     * Cobrança de um pagamento de contrato do SaaS, na conta Asaas do sistema.
     * O cliente recebe as notificações do Asaas (e-mail e SMS) desta e das próximas cobranças.
     *
     * @param  array{pagamento_id: int, valor: float, billing_type: string, vencimento?: string, descricao?: string, cliente: array{name: string, cpfCnpj: string, email?: ?string, mobilePhone?: ?string}}  $data
     * @return array{id: string, status: string, billing_type: string, vencimento: ?string, url: ?string, boleto_url: ?string, pix_payload: ?string, pix_qr_code: ?string, external_reference: string}
     */
    public function criarCobrancaContrato(array $data): array
    {
        $this->validarCredenciais();

        $billingType = strtoupper((string) $data['billing_type']);
        if (! in_array($billingType, $this->billingTypesHabilitados(), true)) {
            throw new \RuntimeException('Forma de pagamento não habilitada na conta Asaas do sistema.');
        }

        $doc = preg_replace('/[^0-9]/', '', (string) ($data['cliente']['cpfCnpj'] ?? ''));
        if (! in_array(strlen($doc), [11, 14], true)) {
            throw new \RuntimeException('CPF ou CNPJ válido é obrigatório para a cobrança');
        }

        $externalReference = ReferenciaExterna::contrato((int) $data['pagamento_id']);
        $customerId = $this->encontrarOuCriarCliente([
            'name' => trim((string) ($data['cliente']['name'] ?? '')),
            'cpfCnpj' => $doc,
            'email' => $data['cliente']['email'] ?? null,
            'mobilePhone' => preg_replace('/[^0-9]/', '', (string) ($data['cliente']['mobilePhone'] ?? '')) ?: null,
        ], true);

        $payment = $this->request('POST', '/payments', [
            'customer' => $customerId,
            'billingType' => $billingType,
            'value' => round((float) $data['valor'], 2),
            'dueDate' => $data['vencimento'] ?? AcademyDateTime::today(),
            'description' => $data['descricao'] ?? 'AppCheckin',
            'externalReference' => $externalReference,
        ]);

        $paymentId = (string) ($payment['id'] ?? '');
        if ($paymentId === '') {
            throw new \RuntimeException('Asaas não retornou o ID da cobrança');
        }

        try {
            $this->garantirWebhook();
        } catch (\Throwable $e) {
            Log::warning('Asaas: falha ao garantir webhook', ['error' => $e->getMessage()]);
        }

        $pixPayload = null;
        $pixQr = null;
        if ($billingType === 'PIX') {
            try {
                $qr = $this->request('GET', '/payments/'.$paymentId.'/pixQrCode');
                $pixPayload = $qr['payload'] ?? null;
                $encoded = (string) ($qr['encodedImage'] ?? '');
                $pixQr = $encoded === '' ? null : (str_starts_with($encoded, 'data:') ? $encoded : 'data:image/png;base64,'.$encoded);
            } catch (\Throwable $e) {
                Log::warning('Asaas: QR Code PIX indisponível', ['payment_id' => $paymentId, 'error' => $e->getMessage()]);
            }
        }

        return [
            'id' => $paymentId,
            'status' => $this->mapStatus((string) ($payment['status'] ?? 'PENDING')),
            'billing_type' => $billingType,
            'vencimento' => $payment['dueDate'] ?? null,
            'url' => $payment['invoiceUrl'] ?? null,
            'boleto_url' => $payment['bankSlipUrl'] ?? null,
            'pix_payload' => $pixPayload,
            'pix_qr_code' => $pixQr,
            'external_reference' => $payment['externalReference'] ?? $externalReference,
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
        if ($parcelas > $this->maxParcelasCartao) {
            $parcelas = $this->maxParcelasCartao;
        }
        $descricao = (string) ($data['descricao'] ?? $data['plano_nome'] ?? 'Pagamento');
        if ($billingType === 'CREDIT_CARD') {
            try {
                // Mesma simulação do app (sem 2ª chamada): o que o aluno confirmou é o que cobra.
                $tarifaCartao = $this->valorComTaxaCartaoRepassada($valorPlano, $parcelas, false);
                $valorCobranca = $tarifaCartao['valor_cobrar'];
                $parcelas = (int) ($tarifaCartao['parcela_count'] ?? $parcelas);
                $descricao .= ' — R$ '.number_format((float) $valorCobranca, 2, ',', '.');
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
            $payloadPagamento['installmentValue'] = $tarifaCartao['valor_parcela'] ?? AsaasTarifaCartao::valorParcela($valorCobranca, $parcelas);
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
     * Assinatura mensal (plano com fidelização): cada mês o Asaas gera uma cobrança independente do valor
     * da mensalidade. Não é venda parcelada e não reserva o total no limite do cartão. No cartão, o aluno
     * paga a 1ª pela fatura e as seguintes são debitadas no mesmo cartão (tokenizado pelo Asaas).
     *
     * @param  array{contrato_id: int, valor: string, billing_type: string, proximo_vencimento: string, descricao: string, max_pagamentos?: ?int,
     *               cliente: array{name: string, cpfCnpj: string, email?: ?string, mobilePhone?: ?string}}  $data
     * @return array{id: string, customer: string, external_reference: string}
     */
    public function criarAssinatura(array $data): array
    {
        $this->validarCredenciais();
        $billingType = strtoupper((string) $data['billing_type']);
        if (! in_array($billingType, $this->billingTypesHabilitados(), true)) {
            throw new \RuntimeException('Forma de pagamento não habilitada na conta Asaas desta academia.');
        }
        $doc = preg_replace('/[^0-9]/', '', (string) ($data['cliente']['cpfCnpj'] ?? ''));
        if (! in_array(strlen($doc), [11, 14], true)) {
            throw new \RuntimeException('CPF válido é obrigatório para a assinatura');
        }
        $customerId = $this->encontrarOuCriarCliente([
            'name' => trim((string) ($data['cliente']['name'] ?? '')),
            'cpfCnpj' => $doc,
            'email' => $data['cliente']['email'] ?? null,
            'mobilePhone' => preg_replace('/[^0-9]/', '', (string) ($data['cliente']['mobilePhone'] ?? '')) ?: null,
        ]);
        $externalReference = ReferenciaExterna::recorrencia((int) $data['contrato_id']);
        $payload = [
            'customer' => $customerId,
            'billingType' => $billingType,
            'value' => (float) $data['valor'],
            'nextDueDate' => $data['proximo_vencimento'],
            'cycle' => 'MONTHLY',
            'description' => mb_substr($data['descricao'], 0, 500),
            'externalReference' => $externalReference,
        ];
        if (! empty($data['max_pagamentos'])) {
            $payload['maxPayments'] = (int) $data['max_pagamentos'];
        }
        $sub = $this->request('POST', '/subscriptions', $payload);
        $id = (string) ($sub['id'] ?? '');
        if ($id === '') {
            throw new \RuntimeException('Asaas não retornou o ID da assinatura');
        }
        try {
            $this->garantirWebhook();
        } catch (\Throwable $e) {
            Log::warning('Asaas: falha ao garantir webhook', ['error' => $e->getMessage()]);
        }

        return ['id' => $id, 'customer' => $customerId, 'external_reference' => $externalReference];
    }

    /** @return list<array<string, mixed>> cobranças geradas pela assinatura (mais recentes primeiro no Asaas) */
    public function pagamentosDaAssinatura(string $subscriptionId): array
    {
        $this->validarCredenciais();

        return array_values($this->request('GET', '/subscriptions/'.$subscriptionId.'/payments', null, ['limit' => 100])['data'] ?? []);
    }

    /** @return array<string, mixed> */
    public function obterAssinatura(string $subscriptionId): array
    {
        $this->validarCredenciais();

        return $this->request('GET', '/subscriptions/'.$subscriptionId);
    }

    /** Muda valor (ex.: fim da fidelização com política "mensal"); updatePendingPayments ajusta as já geradas. */
    /** $atualizarPendentes = false: cobranças já emitidas mantêm o valor (reajuste vale da próxima em diante). */
    public function atualizarValorAssinatura(string $subscriptionId, string $valor, bool $atualizarPendentes = true): void
    {
        $this->validarCredenciais();
        $this->request('PUT', '/subscriptions/'.$subscriptionId, ['value' => (float) $valor, 'updatePendingPayments' => $atualizarPendentes]);
    }

    /** Remove a assinatura: o Asaas para de gerar cobranças. Já removida conta como sucesso. */
    public function cancelarAssinatura(string $subscriptionId): void
    {
        $this->validarCredenciais();
        try {
            $this->request('DELETE', '/subscriptions/'.$subscriptionId);
        } catch (AsaasNaoEncontradoException) {
            // já não existe
        }
    }

    /** @return array{payload: ?string, qr_code: ?string} */
    public function pixDaCobranca(string $paymentId): array
    {
        $this->validarCredenciais();
        $qr = $this->request('GET', '/payments/'.$paymentId.'/pixQrCode');
        $encoded = (string) ($qr['encodedImage'] ?? '');

        return ['payload' => $qr['payload'] ?? null, 'qr_code' => $encoded === '' ? null : (str_starts_with($encoded, 'data:') ? $encoded : 'data:image/png;base64,'.$encoded)];
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
        $parcelas = max(1, min($this->maxParcelasCartao, $parcelas));
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
            if ($n > $this->maxParcelasCartao || ! AsaasTarifaCartao::cabeEmParcelas($valorLiquidoDesejado, $n)) {
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
        $total = round((float) $repasse['valor_cobrar'], 2);
        // Parcela para cima: o Asaas cobra parcela × N, e esse total não pode ficar abaixo do calculado
        $parcela = $parcelas > 1 ? AsaasTarifaCartao::paraCima($total / $parcelas) : $total;
        $cheio = round($parcela * $parcelas, 2);
        if ($cheio > $total) {
            if (isset($repasse['fator_antecipacao'])) {
                $repasse = AsaasSobretaxaAntecipacao::comValorCobrar(
                    $repasse,
                    $cheio,
                    round($total - (float) $repasse['sobretaxa'], 2),
                    (float) ($repasse['fee_percentage'] ?? 0),
                    (float) ($repasse['operation_fee'] ?? 0),
                    (float) $repasse['fator_antecipacao'],
                );
            } else {
                $repasse['valor_cobrar'] = $cheio;
                $repasse['tarifa'] = round((float) $repasse['tarifa'] + ($cheio - $total), 2);
            }
        }
        $repasse['parcela_count'] = $parcelas;
        $repasse['valor_parcela'] = $parcela;

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
    /**
     * @return list<array<string, mixed>>
     */
    /**
     * Remove uma cobrança ainda não paga (o link deixa de aceitar pagamento).
     */
    public function cancelarCobranca(string $paymentId): bool
    {
        $this->validarCredenciais();
        $res = $this->request('DELETE', '/payments/'.rawurlencode($paymentId));

        return (bool) ($res['deleted'] ?? true);
    }

    public function listarPagamentosPorReferenciaExterna(string $externalReference): array
    {
        $this->validarCredenciais();
        $lista = $this->request('GET', '/payments', null, [
            'externalReference' => $externalReference,
            'limit' => 50,
        ]);
        $itens = is_array($lista['data'] ?? null) ? $lista['data'] : [];

        return array_values(array_filter($itens, 'is_array'));
    }

    /**
     * Cobranças recebidas no período (paymentDate) mais os cartões confirmados que ainda não caíram
     * na conta (CONFIRMED não tem paymentDate; filtra pela data da confirmação). Para a conciliação.
     *
     * @return list<array<string, mixed>>
     */
    public function listarPagamentosDoPeriodo(string $inicio, string $fim, int $maximo = 1000): array
    {
        $this->validarCredenciais();
        $recebidos = $this->paginar(['paymentDate[ge]' => $inicio, 'paymentDate[le]' => $fim], $maximo);
        $confirmados = array_filter(
            $this->paginar(['status' => 'CONFIRMED'], $maximo),
            function ($p) use ($inicio, $fim) {
                $dia = substr((string) ($p['confirmedDate'] ?? ''), 0, 10);

                return $dia !== '' && $dia >= $inicio && $dia <= $fim;
            }
        );

        $porId = [];
        foreach (array_merge($recebidos, $confirmados) as $p) {
            if (($p['deleted'] ?? false) === true || empty($p['id'])) {
                continue;
            }
            $porId[(string) $p['id']] = $p;
        }

        return array_values($porId);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    private function paginar(array $filtros, int $maximo): array
    {
        $itens = [];
        $offset = 0;
        do {
            $pagina = $this->request('GET', '/payments', null, $filtros + ['limit' => 100, 'offset' => $offset]);
            foreach ((array) ($pagina['data'] ?? []) as $item) {
                if (is_array($item)) {
                    $itens[] = $item;
                }
            }
            $offset += 100;
        } while (($pagina['hasMore'] ?? false) === true && $offset < $maximo);

        return $itens;
    }

    public function obterPagamento(string $paymentId): array
    {
        $this->validarCredenciais();
        $payment = $this->request('GET', '/payments/'.$paymentId);
        try {
            $payment = AsaasPagamentoStatus::aplicarAntecipacao(
                $payment,
                $this->obterAntecipacaoAtiva($paymentId),
            );
        } catch (\Throwable $e) {
            Log::info('Asaas: antecipação da cobrança indisponível', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
        }

        return $payment;
    }

    /**
     * Cobranças de um cartão parcelado (uma por parcela), com o líquido da antecipação quando houver.
     *
     * @return list<array<string, mixed>>
     */
    public function pagamentosDoParcelamento(string $installmentId): array
    {
        $this->validarCredenciais();
        $lista = [];
        foreach ($this->paginar(['installment' => $installmentId], 100) as $p) {
            if (($p['deleted'] ?? false) === true) {
                continue;
            }
            if (($p['anticipated'] ?? false) === true) {
                try {
                    $p = AsaasPagamentoStatus::aplicarAntecipacao($p, $this->obterAntecipacaoAtiva((string) $p['id']));
                } catch (\Throwable) {
                    // segue com o netValue da cobrança
                }
            }
            $lista[] = $p;
        }

        return $lista;
    }

    /**
     * Liga ou desliga a antecipação automática das vendas no cartão na conta Asaas.
     * Devolve o estado que o Asaas confirmou.
     */
    public function configurarAntecipacaoAutomatica(bool $ligar): bool
    {
        $this->validarCredenciais();
        $res = $this->request('PUT', '/anticipations/configurations', ['creditCardAutomaticEnabled' => $ligar]);

        return $this->boolCredencial($res['creditCardAutomaticEnabled'] ?? $ligar);
    }

    /**
     * Estado da antecipação automática do cartão na conta Asaas (null se não der para consultar).
     */
    public function antecipacaoAutomaticaNaConta(): ?bool
    {
        if ($this->accessToken === '') {
            return null;
        }
        try {
            $res = $this->request('GET', '/anticipations/configurations');

            return array_key_exists('creditCardAutomaticEnabled', $res) ? $this->boolCredencial($res['creditCardAutomaticEnabled']) : null;
        } catch (\Throwable $e) {
            Log::info('Asaas: configuração de antecipação indisponível', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Antecipação ativa (em análise, agendada ou creditada) da cobrança.
     *
     * @return array<string, mixed>|null
     */
    public function obterAntecipacaoAtiva(string $paymentId): ?array
    {
        $this->validarCredenciais();
        $lista = $this->request('GET', '/anticipations', null, [
            'payment' => $paymentId,
            'limit' => 20,
        ]);
        $itens = is_array($lista['data'] ?? null) ? $lista['data'] : [];
        foreach ($itens as $item) {
            if (! is_array($item)) {
                continue;
            }
            $status = strtoupper((string) ($item['status'] ?? ''));
            if (in_array($status, ['CANCELLED', 'DENIED', 'DEBITED'], true)) {
                continue;
            }
            if (array_key_exists('netValue', $item) && $item['netValue'] !== null && $item['netValue'] !== '') {
                return $item;
            }
        }

        return null;
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
            $id = (string) ($webhook['id'] ?? '');
            $atuais = is_array($webhook['events'] ?? null) ? $webhook['events'] : [];
            $faltando = array_values(array_diff($this->eventosWebhook(), $atuais));
            if ($enabled && ! $interrupted && $faltando === []) {
                return;
            }
            if ($id !== '') {
                try {
                    $this->request('PUT', '/webhooks/'.$id, [
                        'enabled' => true,
                        'interrupted' => false,
                        'events' => $this->eventosWebhook(),
                    ]);
                } catch (\Throwable $e) {
                    if ($enabled && ! $interrupted) {
                        throw $e;
                    }
                    // A lista de eventos foi recusada: ao menos religa a fila, senão nenhum webhook chega
                    $this->request('PUT', '/webhooks/'.$id, ['enabled' => true, 'interrupted' => false]);
                    Log::warning('Asaas: fila do webhook religada sem atualizar os eventos', ['webhook_id' => $id, 'error' => $e->getMessage()]);
                }
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
            'events' => $this->eventosWebhook(),
        ]);

        if ($this->tenantId && $this->webhookSecret() === null) {
            $encryption = new EncryptionService;
            DB::table('tenant_payment_credentials')
                ->where('tenant_id', $this->tenantId)
                ->where('provider', 'asaas')
                ->update([
                    'webhook_secret' => $encryption->encrypt($auth),
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * @return list<string>
     */
    private function eventosWebhook(): array
    {
        // Só eventos que o Asaas aceita em /webhooks: um inválido derruba o PUT inteiro (e com ele a
        // reativação da fila). Baixa em dinheiro chega como PAYMENT_RECEIVED; antecipação, pelos
        // PAYMENT_ANTICIPATED / PAYMENT_UPDATED da cobrança.
        return [
            'PAYMENT_CONFIRMED',
            'PAYMENT_RECEIVED',
            'PAYMENT_UPDATED',
            // Cobranças de contrato: excluída, vencida ou restaurada (ver AsaasContratoWebhook)
            'PAYMENT_DELETED',
            'PAYMENT_OVERDUE',
            'PAYMENT_RESTORED',
            'PAYMENT_ANTICIPATED',
            // Assinaturas mensais (planos com fidelização): cobrança gerada, recusa no cartão, estorno
            'PAYMENT_CREATED',
            'PAYMENT_CREDIT_CARD_CAPTURE_REFUSED',
            'PAYMENT_REFUNDED',
            'PAYMENT_CHARGEBACK_REQUESTED',
            'PAYMENT_CHARGEBACK_DISPUTE',
        ];
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

    private function encontrarOuCriarCliente(array $dados, bool $notificar = false): string
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
            'notificationDisabled' => ! $notificar,
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
        $this->enableAntecipacaoAutomatica = $this->boolCredencial(
            $flags['enable_antecipacao_automatica'] ?? $flags['enable_sobretaxa_antecipacao'] ?? false
        );
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
        $this->maxParcelasCartao = AsaasTarifaCartao::maxParcelas($flags['max_parcelas_cartao'] ?? null);
        if (isset($flags['sobretaxa_folga_percentual']) && is_numeric($flags['sobretaxa_folga_percentual'])) {
            $v = round((float) $flags['sobretaxa_folga_percentual'], 2);
            if ($v >= 0 && $v <= 30) {
                $this->sobretaxaFolgaPercentual = $v;
            }
        }
        $this->usarTaxaAsaas = $this->boolCredencial($flags['usar_taxa_antecipacao_asaas'] ?? false);
    }

    /**
     * @return array{habilitada: bool, avista: float, parcelada: float}
     */
    private function configSobretaxa(): array
    {
        return [
            'habilitada' => $this->enableAntecipacaoAutomatica && $this->enableSobretaxaAntecipacao,
            'avista' => $this->sobretaxaAvistaPercentual,
            'parcelada' => $this->sobretaxaParceladaPercentual,
            'folga' => $this->sobretaxaFolgaPercentual,
            'usar_taxa_asaas' => $this->usarTaxaAsaas,
        ];
    }

    /**
     * GET /myAccount/fees — taxas oficiais da conta (antecipação). Falha silenciosa.
     *
     * @return array<string, mixed>
     */
    /**
     * Taxas mensais de antecipação do cartão que o Asaas informa para a conta (para o painel mostrar).
     *
     * @return array{avista: ?float, parcelada: ?float}
     */
    public function taxasAntecipacaoInformadas(): array
    {
        $this->validarCredenciais();
        $taxas = $this->request('GET', '/myAccount/fees');

        return [
            'avista' => AsaasSobretaxaAntecipacao::taxaOficialConta(1, $taxas),
            'parcelada' => AsaasSobretaxaAntecipacao::taxaOficialConta(2, $taxas),
        ];
    }

    public function taxasDaConta(): array
    {
        if ($this->taxasContaCache !== null) {
            return $this->taxasContaCache;
        }
        if ($this->accessToken === '' || ! $this->configSobretaxa()['habilitada']) {
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

        $encryption = new EncryptionService;
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
            $response = match ($method) {
                'PUT' => $pending->put($url, $body ?? []),
                'DELETE' => $pending->delete($url),
                default => $pending->post($url, $body ?? []),
            };
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
            if ($response->status() === 404) {
                throw new AsaasNaoEncontradoException('Asaas: '.$errors);
            }
            throw new \RuntimeException('Asaas: '.$errors);
        }

        return $json;
    }
}
