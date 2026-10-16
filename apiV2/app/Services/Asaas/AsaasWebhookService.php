<?php

namespace App\Services\Asaas;

use App\Services\EncryptionService;
use App\Services\PagamentoPlanoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AsaasWebhookService
{
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
        $event = (string) ($payload['event'] ?? '');
        $payment = is_array($payload['payment'] ?? null) ? $payload['payment'] : [];
        $paymentId = (string) ($payment['id'] ?? '');

        $eventId = (string) ($payload['id'] ?? '');

        if ($event === '' || $paymentId === '') {
            return ['status' => 400, 'body' => ['success' => false, 'received' => false, 'error' => 'Webhook Asaas inválido']];
        }

        if (! in_array($event, ['PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED_IN_CASH'], true)) {
            return ['status' => 200, 'body' => ['success' => true, 'received' => true, 'ignored' => $event, 'id' => $eventId]];
        }

        $pix = DB::table('pagamentos_pix')->where('payment_id', $paymentId)->first();
        $assinatura = null;
        if (! $pix) {
            $assinatura = DB::table('assinaturas')->where('gateway_preference_id', $paymentId)->first();
        }

        $tenantId = (int) ($pix->tenant_id ?? $assinatura->tenant_id ?? 0);
        if ($tenantId <= 0) {
            $ref = (string) ($payment['externalReference'] ?? '');
            if (preg_match('/^MAT-(\d+)-/', $ref, $m)) {
                $tenantId = (int) (DB::table('matriculas')->where('id', (int) $m[1])->value('tenant_id') ?? 0);
            }
        }

        if ($tenantId <= 0) {
            Log::warning('Asaas webhook sem tenant', ['payment_id' => $paymentId, 'event' => $event]);

            return ['status' => 200, 'body' => ['success' => true, 'received' => true, 'ignored' => 'tenant_nao_encontrado']];
        }

        if (! $this->tokenWebhookValido($tenantId, $accessTokenHeader)) {
            return ['status' => 401, 'body' => ['success' => false, 'error' => 'Webhook token inválido']];
        }

        $matriculaId = (int) ($pix->matricula_id ?? $assinatura->matricula_id ?? 0);
        if ($matriculaId <= 0) {
            $ref = (string) ($payment['externalReference'] ?? '');
            if (preg_match('/^MAT-(\d+)-/', $ref, $m)) {
                $matriculaId = (int) $m[1];
            }
        }

        if ($matriculaId <= 0) {
            return ['status' => 200, 'body' => ['success' => true, 'received' => true, 'ignored' => 'matricula_nao_encontrada']];
        }

        if ($pix && ($pix->status ?? '') === 'approved') {
            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'received' => true,
                    'id' => $eventId,
                    'ignored' => 'ja_processado',
                    'payment_id' => $paymentId,
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
            $formaId = DB::table('formas_pagamento')
                ->whereRaw('LOWER(nome) = ?', ['pix'])
                ->value('id');

            DB::table('pagamentos_plano')
                ->where('id', $parcela->id)
                ->where('tenant_id', $tenantId)
                ->update([
                    'status_pagamento_id' => 2,
                    'data_pagamento' => date('Y-m-d'),
                    'forma_pagamento_id' => $formaId,
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

        DB::table('matriculas')
            ->where('id', $matriculaId)
            ->where('tenant_id', $tenantId)
            ->update([
                'data_inicio' => DB::raw('COALESCE(data_inicio, CURDATE())'),
                'updated_at' => now(),
            ]);

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

        $expected = $this->encryption->isEncrypted($stored)
            ? $this->encryption->decrypt($stored)
            : $stored;

        return is_string($headerToken) && hash_equals($expected, $headerToken);
    }
}
