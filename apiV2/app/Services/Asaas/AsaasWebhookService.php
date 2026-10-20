<?php

namespace App\Services\Asaas;

use App\Services\EncryptionService;
use App\Services\PagamentoPlanoService;
use App\Services\SuperAdmin\CobrancaContratoService;
use App\Support\AsaasPagamentoStatus;
use App\Support\ReferenciaExterna;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AsaasWebhookService
{
    private const EVENTOS_PAGOS = [
        'PAYMENT_RECEIVED',
        'PAYMENT_CONFIRMED',
        'PAYMENT_RECEIVED_IN_CASH',
        'PAYMENT_ANTICIPATED',
        'ANTICIPATION_PENDING',
        'ANTICIPATION_SCHEDULED',
        'ANTICIPATION_CREDITED',
    ];

    private const STATUS_PAGOS = [
        'RECEIVED',
        'CONFIRMED',
        'RECEIVED_IN_CASH',
    ];

    public function __construct(
        private readonly PagamentoPlanoService $pagamentosPlano,
        private readonly EncryptionService $encryption,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: int, body: array<string, mixed>}
     */
    public function processar(array $payload, ?string $accessTokenHeader): array
    {
        $event = strtoupper((string) ($payload['event'] ?? $payload['type'] ?? ''));
        $payment = $this->extrairPagamento($payload);
        $paymentId = (string) ($payment['id'] ?? '');
        $eventId = (string) ($payload['id'] ?? '');

        if ($event === '' || $paymentId === '') {
            return ['status' => 400, 'body' => ['success' => false, 'received' => false, 'error' => 'Webhook Asaas inválido']];
        }

        $pagamentoContratoId = ReferenciaExterna::pagamentoContrato((string) ($payment['externalReference'] ?? ''));
        if ($pagamentoContratoId !== null
            || (Schema::hasColumn('pagamentos_contrato', 'gateway_payment_id') && DB::table('pagamentos_contrato')->where('gateway_payment_id', $paymentId)->exists())) {
            if (! $this->tokenWebhookValido(CobrancaContratoService::sistemaTenantId(), $accessTokenHeader)) {
                return ['status' => 401, 'body' => ['success' => false, 'error' => 'Webhook token inválido']];
            }

            return app(AsaasContratoWebhook::class)->processar($event, $payment, $pagamentoContratoId, $this->pagamentoEstaPago($event, $payment));
        }

        $pix = DB::table('pagamentos_pix')->where('payment_id', $paymentId)->first();
        $assinatura = DB::table('assinaturas')->where('gateway_preference_id', $paymentId)->first();
        if (! $assinatura && $pix) {
            $assinatura = DB::table('assinaturas')
                ->where('tenant_id', (int) $pix->tenant_id)
                ->where('matricula_id', (int) $pix->matricula_id)
                ->orderByDesc('id')
                ->first();
        }

        $tenantId = (int) ($pix->tenant_id ?? $assinatura->tenant_id ?? 0);
        if ($tenantId <= 0) {
            $tenantId = $this->tenantIdDaReferencia((string) ($payment['externalReference'] ?? ''));
        }

        if ($tenantId <= 0) {
            Log::warning('Asaas webhook sem tenant', ['payment_id' => $paymentId, 'event' => $event]);

            return ['status' => 200, 'body' => ['success' => true, 'received' => true, 'ignored' => 'tenant_nao_encontrado']];
        }

        if (! $this->tokenWebhookValido($tenantId, $accessTokenHeader)) {
            return ['status' => 401, 'body' => ['success' => false, 'error' => 'Webhook token inválido']];
        }

        $payment = $this->completarPagamento(
            $tenantId,
            $payment,
            str_starts_with($event, 'ANTICIPATION_') || $event === 'PAYMENT_ANTICIPATED',
        );
        if ($tenantId <= 0) {
            $tenantId = $this->tenantIdDaReferencia((string) ($payment['externalReference'] ?? ''));
        }

        if (! $this->pagamentoEstaPago($event, $payment)) {
            return ['status' => 200, 'body' => ['success' => true, 'received' => true, 'ignored' => $event, 'id' => $eventId]];
        }

        $matriculaId = (int) ($pix->matricula_id ?? $assinatura->matricula_id ?? 0);
        if ($matriculaId <= 0) {
            $matriculaId = $this->matriculaIdDaReferencia((string) ($payment['externalReference'] ?? ''));
        }
        if (! $assinatura && $matriculaId > 0 && $tenantId > 0) {
            $assinatura = DB::table('assinaturas')
                ->where('tenant_id', $tenantId)
                ->where('matricula_id', $matriculaId)
                ->orderByDesc('id')
                ->first();
        }

        if ($matriculaId <= 0) {
            return ['status' => 200, 'body' => ['success' => true, 'received' => true, 'ignored' => 'matricula_nao_encontrada']];
        }

        DB::table('pagamentos_pix')
            ->where('tenant_id', $tenantId)
            ->where('payment_id', $paymentId)
            ->update(['status' => 'approved']);

        $this->baixarParcelaAberta($tenantId, $matriculaId, $payment);

        $statusPagaId = DB::table('assinatura_status')->where('codigo', 'paga')->value('id')
            ?: DB::table('assinatura_status')->where('codigo', 'ativa')->value('id');

        $assinaturaUpdate = [
            'status_gateway' => 'approved',
            'atualizado_em' => now(),
        ];
        $prefAtual = (string) (DB::table('assinaturas')
            ->where('tenant_id', $tenantId)
            ->where('matricula_id', $matriculaId)
            ->orderByDesc('id')
            ->value('gateway_preference_id') ?? '');
        if ($prefAtual === '' || $prefAtual === $paymentId) {
            $assinaturaUpdate['gateway_preference_id'] = $paymentId;
        }
        if ($statusPagaId) {
            $assinaturaUpdate['status_id'] = $statusPagaId;
        }

        DB::table('assinaturas')
            ->where('tenant_id', $tenantId)
            ->where('matricula_id', $matriculaId)
            ->update($assinaturaUpdate);

        $hoje = now()->toDateString();
        DB::table('matriculas')
            ->where('id', $matriculaId)
            ->where('tenant_id', $tenantId)
            ->whereNull('data_inicio')
            ->update([
                'data_inicio' => $hoje,
                'updated_at' => now(),
            ]);
        DB::table('matriculas')
            ->where('id', $matriculaId)
            ->where('tenant_id', $tenantId)
            ->update(['updated_at' => now()]);

        $this->pagamentosPlano->atualizarStatusMatricula($tenantId, $matriculaId);
        $this->ativarMatriculaPendente($tenantId, $matriculaId);
        $this->registrarFimDoPeriodo($tenantId, $matriculaId);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'received' => true,
                'id' => $eventId,
                'event' => $event,
                'payment_id' => $paymentId,
                'matricula_id' => $matriculaId,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function extrairPagamento(array $payload): array
    {
        if (is_array($payload['payment'] ?? null)) {
            return $payload['payment'];
        }
        if (is_array($payload['data']['payment'] ?? null)) {
            return $payload['data']['payment'];
        }
        $antecipacao = $payload['anticipation'] ?? $payload['data']['anticipation'] ?? null;
        if (is_array($antecipacao)) {
            $payId = (string) ($antecipacao['payment'] ?? '');
            if ($payId !== '') {
                return AsaasPagamentoStatus::aplicarAntecipacao(['id' => $payId], $antecipacao);
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $payment
     * @return array<string, mixed>
     */
    private function completarPagamento(int $tenantId, array $payment, bool $forcarRemoto = false): array
    {
        $id = (string) ($payment['id'] ?? '');
        $status = (string) ($payment['status'] ?? '');
        $ref = (string) ($payment['externalReference'] ?? '');
        $temLiquido = array_key_exists('netValue', $payment)
            && $payment['netValue'] !== null
            && $payment['netValue'] !== '';
        if ($id === '' || (! $forcarRemoto && $status !== '' && $ref !== '' && $temLiquido)) {
            return $payment;
        }

        try {
            $remoto = (new AsaasService($tenantId))->obterPagamento($id);
            if ($remoto !== []) {
                return array_merge($payment, $remoto);
            }
        } catch (\Throwable $e) {
            Log::warning('Asaas webhook: falha ao consultar cobrança', [
                'payment_id' => $id,
                'error' => $e->getMessage(),
            ]);
        }

        return $payment;
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    private function pagamentoEstaPago(string $event, array $payment): bool
    {
        $status = strtoupper((string) ($payment['status'] ?? ''));
        $billing = strtoupper((string) ($payment['billingType'] ?? ''));
        if (in_array($status, self::STATUS_PAGOS, true) || $billing === 'RECEIVED_IN_CASH') {
            return true;
        }

        return in_array($event, self::EVENTOS_PAGOS, true);
    }

    /**
     * Assinatura avulsa paga: o fim do período é até quando a matrícula dá acesso (mesma regra do
     * "sincronizar" do painel). Lido depois da baixa, que é quem avança o vencimento da matrícula.
     */
    private function registrarFimDoPeriodo(int $tenantId, int $matriculaId): void
    {
        $fim = DB::table('matriculas')->where('id', $matriculaId)->where('tenant_id', $tenantId)->value('proxima_data_vencimento');
        if (! $fim) {
            return;
        }

        $assinaturaId = DB::table('assinaturas')
            ->where('tenant_id', $tenantId)
            ->where('matricula_id', $matriculaId)
            ->orderByDesc('id')
            ->value('id');
        if (! $assinaturaId) {
            return;
        }

        DB::table('assinaturas')
            ->where('id', $assinaturaId)
            ->where(fn ($q) => $q->where('tipo_cobranca', 'avulso')->orWhereNull('tipo_cobranca'))
            ->update(['data_fim' => substr((string) $fim, 0, 10)]);
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    private function baixarParcelaAberta(int $tenantId, int $matriculaId, array $payment): void
    {
        $paymentId = (string) ($payment['id'] ?? '');
        if ($paymentId !== '') {
            $jaBaixada = DB::table('pagamentos_plano')
                ->where('tenant_id', $tenantId)
                ->where('matricula_id', $matriculaId)
                ->where('status_pagamento_id', 2)
                ->where('observacoes', 'like', '%'.$paymentId.'%')
                ->exists();
            if ($jaBaixada) {
                $parcelaPaga = DB::table('pagamentos_plano')
                    ->where('tenant_id', $tenantId)
                    ->where('matricula_id', $matriculaId)
                    ->where('status_pagamento_id', 2)
                    ->where('observacoes', 'like', '%'.$paymentId.'%')
                    ->orderByDesc('id')
                    ->first();
                if ($parcelaPaga) {
                    $this->persistirTarifaProvedor((int) $parcelaPaga->id, $tenantId, $payment, (string) ($parcelaPaga->observacoes ?? ''), true);
                }

                return;
            }
        }

        $abertas = function () use ($tenantId, $matriculaId) {
            return DB::table('pagamentos_plano')
                ->where('tenant_id', $tenantId)
                ->where('matricula_id', $matriculaId)
                ->whereIn('status_pagamento_id', [1, 3])
                ->whereNull('data_pagamento');
        };

        $valor = isset($payment['value']) ? round((float) $payment['value'], 2) : 0.0;
        $parcela = null;
        if ($valor > 0) {
            $parcela = $abertas()
                ->where('valor', $valor)
                ->orderByDesc('id')
                ->first();
        }
        if (! $parcela) {
            $parcela = $abertas()
                ->where(function ($q) {
                    $q->where('observacoes', 'like', '%Aguardando pagamento%')
                        ->orWhere('observacoes', 'like', '%Primeiro pagamento%');
                })
                ->orderBy('data_vencimento')
                ->orderBy('id')
                ->first();
        }
        if (! $parcela) {
            $parcela = $abertas()
                ->orderBy('data_vencimento')
                ->orderBy('id')
                ->first();
        }

        if (! $parcela) {
            return;
        }

        $dataPagamento = AsaasPagamentoStatus::dataPagamento($payment)
            ?? substr((string) date('Y-m-d'), 0, 10);
        $obs = trim(($parcela->observacoes ?? '').' [Asaas '.$paymentId.']');

        $update = [
            'status_pagamento_id' => 2,
            'data_pagamento' => $dataPagamento !== '' ? $dataPagamento : date('Y-m-d'),
            'forma_pagamento_id' => $this->formaPagamentoId((string) ($payment['billingType'] ?? 'PIX')),
            'tipo_baixa_id' => DB::table('tipos_baixa')->where('nome', 'Integração')->value('id') ?: 4,
            'observacoes' => $obs,
            'updated_at' => now(),
        ];

        DB::table('pagamentos_plano')
            ->where('id', $parcela->id)
            ->where('tenant_id', $tenantId)
            ->update(array_merge(
                $update,
                $this->camposTarifaProvedor($payment, $obs, true),
            ));
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    private function persistirTarifaProvedor(int $parcelaId, int $tenantId, array $payment, string $observacoes, bool $anexarObs): void
    {
        $campos = $this->camposTarifaProvedor($payment, $observacoes, $anexarObs);
        if ($campos === []) {
            return;
        }

        DB::table('pagamentos_plano')
            ->where('id', $parcelaId)
            ->where('tenant_id', $tenantId)
            ->update(array_merge($campos, ['updated_at' => now()]));
    }

    /**
     * @param  array<string, mixed>  $payment
     * @return array<string, mixed>
     */
    private function camposTarifaProvedor(array $payment, string $observacoes, bool $anexarObs): array
    {
        $tarifa = AsaasPagamentoStatus::tarifaProvedor($payment);
        $campos = [];

        if ($anexarObs && ($tarifa['desconto_provedor'] ?? null) !== null && (float) $tarifa['desconto_provedor'] > 0) {
            $linha = sprintf(
                'valor R$ %s líquido R$ %s desconto_provedor R$ %s',
                number_format($tarifa['valor'], 2, ',', '.'),
                number_format((float) $tarifa['valor_liquido'], 2, ',', '.'),
                number_format((float) $tarifa['desconto_provedor'], 2, ',', '.'),
            );
            if (preg_match('/valor R\$ [\d.,]+ líquido R\$ [\d.,]+ desconto_provedor R\$ [\d.,]+/u', $observacoes) === 1) {
                $campos['observacoes'] = preg_replace(
                    '/valor R\$ [\d.,]+ líquido R\$ [\d.,]+ desconto_provedor R\$ [\d.,]+/u',
                    $linha,
                    $observacoes,
                    1
                );
            } else {
                $campos['observacoes'] = trim($observacoes.' '.$linha);
            }
        }

        if (($tarifa['valor_liquido'] ?? null) === null) {
            return $campos;
        }

        if (Schema::hasColumn('pagamentos_plano', 'valor_liquido')) {
            $campos['valor_liquido'] = $tarifa['valor_liquido'];
        }
        if (Schema::hasColumn('pagamentos_plano', 'desconto_provedor')) {
            $campos['desconto_provedor'] = $tarifa['desconto_provedor'];
        }

        return $campos;
    }

    private function ativarMatriculaPendente(int $tenantId, int $matriculaId): void
    {
        if (! Schema::hasTable('status_matricula') || ! Schema::hasColumn('matriculas', 'status_id')) {
            return;
        }

        $codigo = (string) (DB::table('matriculas as m')
            ->leftJoin('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->where('m.id', $matriculaId)
            ->where('m.tenant_id', $tenantId)
            ->value('sm.codigo') ?? '');

        if ($codigo !== 'pendente' && $codigo !== '') {
            return;
        }

        $ativaId = DB::table('status_matricula')->where('codigo', 'ativa')->value('id');
        if (! $ativaId) {
            return;
        }

        DB::table('matriculas')
            ->where('id', $matriculaId)
            ->where('tenant_id', $tenantId)
            ->update([
                'status_id' => (int) $ativaId,
                'updated_at' => now(),
            ]);
    }

    private function formaPagamentoId(string $billingType): ?int
    {
        foreach (AsaasPagamentoStatus::formaPagamentoBusca($billingType) as $nome) {
            $id = DB::table('formas_pagamento')->whereRaw('LOWER(nome) = ?', [$nome])->value('id');
            if ($id) {
                return (int) $id;
            }
        }

        $billing = strtoupper(trim($billingType));
        if (in_array($billing, ['CREDIT_CARD', 'DEBIT_CARD'], true)) {
            $id = DB::table('formas_pagamento')
                ->whereRaw("LOWER(nome) LIKE '%credito%' OR LOWER(nome) LIKE '%crédito%' OR LOWER(nome) LIKE '%cartao%' OR LOWER(nome) LIKE '%cartão%'")
                ->value('id');
            if ($id) {
                return (int) $id;
            }
        }

        $pixId = DB::table('formas_pagamento')->whereRaw('LOWER(nome) = ?', ['pix'])->value('id');

        return $pixId ? (int) $pixId : null;
    }

    private function matriculaIdDaReferencia(string $ref): int
    {
        if (preg_match('/^MAT-(\d+)-/', $ref, $m)) {
            return (int) $m[1];
        }

        return 0;
    }

    private function tenantIdDaReferencia(string $ref): int
    {
        $matriculaId = $this->matriculaIdDaReferencia($ref);
        if ($matriculaId <= 0) {
            return 0;
        }

        return (int) (DB::table('matriculas')->where('id', $matriculaId)->value('tenant_id') ?? 0);
    }

    private function tokenWebhookValido(int $tenantId, ?string $headerToken): bool
    {
        $row = DB::table('tenant_payment_credentials')
            ->where('tenant_id', $tenantId)
            ->where('provider', 'asaas')
            ->first(['webhook_secret']);

        $stored = (string) ($row->webhook_secret ?? '');
        if ($stored === '') {
            return true;
        }

        $header = is_string($headerToken) ? trim($headerToken) : '';
        if ($header === '') {
            Log::warning('Asaas webhook sem asaas-access-token; processando mesmo assim', [
                'tenant_id' => $tenantId,
            ]);

            return true;
        }

        try {
            $expected = $this->encryption->isEncrypted($stored)
                ? $this->encryption->decrypt($stored)
                : $stored;
        } catch (\Throwable) {
            return true;
        }

        if (! is_string($expected) || $expected === '') {
            return true;
        }

        return hash_equals($expected, $header);
    }
}
