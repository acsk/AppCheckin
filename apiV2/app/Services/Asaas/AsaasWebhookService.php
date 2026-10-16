<?php

namespace App\Services\Asaas;

use App\Services\EncryptionService;
use App\Services\PagamentoPlanoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AsaasWebhookService
{
    private const EVENTOS_PAGOS = [
        'PAYMENT_RECEIVED',
        'PAYMENT_CONFIRMED',
        'PAYMENT_RECEIVED_IN_CASH',
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

        $pix = DB::table('pagamentos_pix')->where('payment_id', $paymentId)->first();
        $assinatura = $pix ? null : DB::table('assinaturas')->where('gateway_preference_id', $paymentId)->first();

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

        $payment = $this->completarPagamento($tenantId, $payment);
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

        if ($matriculaId <= 0) {
            return ['status' => 200, 'body' => ['success' => true, 'received' => true, 'ignored' => 'matricula_nao_encontrada']];
        }

        if ($pix && ($pix->status ?? '') === 'approved') {
            $this->pagamentosPlano->atualizarStatusMatricula($tenantId, $matriculaId);

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'received' => true,
                    'id' => $eventId,
                    'ignored' => 'ja_processado',
                    'payment_id' => $paymentId,
                    'matricula_id' => $matriculaId,
                ],
            ];
        }

        DB::table('pagamentos_pix')
            ->where('tenant_id', $tenantId)
            ->where('payment_id', $paymentId)
            ->update(['status' => 'approved']);

        $parcela = DB::table('pagamentos_plano')
            ->where('tenant_id', $tenantId)
            ->where('matricula_id', $matriculaId)
            ->whereIn('status_pagamento_id', [1, 3])
            ->whereNull('data_pagamento')
            ->orderBy('data_vencimento')
            ->orderBy('id')
            ->first();

        if ($parcela) {
            $formaNome = strtoupper((string) ($payment['billingType'] ?? 'PIX')) === 'RECEIVED_IN_CASH'
                ? 'dinheiro'
                : 'pix';
            $formaId = DB::table('formas_pagamento')
                ->whereRaw('LOWER(nome) = ?', [$formaNome])
                ->value('id')
                ?: DB::table('formas_pagamento')->whereRaw('LOWER(nome) = ?', ['pix'])->value('id');

            $dataPagamento = substr((string) ($payment['paymentDate'] ?? $payment['clientPaymentDate'] ?? date('Y-m-d')), 0, 10);

            DB::table('pagamentos_plano')
                ->where('id', $parcela->id)
                ->where('tenant_id', $tenantId)
                ->update([
                    'status_pagamento_id' => 2,
                    'data_pagamento' => $dataPagamento !== '' ? $dataPagamento : date('Y-m-d'),
                    'forma_pagamento_id' => $formaId,
                    'tipo_baixa_id' => DB::table('tipos_baixa')->where('nome', 'Integração')->value('id') ?: 4,
                    'observacoes' => trim(($parcela->observacoes ?? '').' [Asaas '.$paymentId.']'),
                    'updated_at' => now(),
                ]);
        }

        $statusPagaId = DB::table('assinatura_status')->where('codigo', 'paga')->value('id')
            ?: DB::table('assinatura_status')->where('codigo', 'ativa')->value('id');

        $assinaturaUpdate = [
            'status_gateway' => 'approved',
            'gateway_preference_id' => $paymentId,
            'atualizado_em' => now(),
        ];
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

        return [];
    }

    /**
     * @param  array<string, mixed>  $payment
     * @return array<string, mixed>
     */
    private function completarPagamento(int $tenantId, array $payment): array
    {
        $id = (string) ($payment['id'] ?? '');
        $status = (string) ($payment['status'] ?? '');
        $ref = (string) ($payment['externalReference'] ?? '');
        if ($id === '' || ($status !== '' && $ref !== '')) {
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
