<?php

namespace App\Services\Asaas;

use App\Support\AsaasPagamentoStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AsaasPagamentoSync
{
    public function paymentIdDaMatricula(int $tenantId, int $matriculaId): string
    {
        $pixId = (string) (DB::table('pagamentos_pix')
            ->where('tenant_id', $tenantId)
            ->where('matricula_id', $matriculaId)
            ->where('payment_id', 'like', 'pay_%')
            ->orderByDesc('id')
            ->value('payment_id') ?? '');
        if (str_starts_with($pixId, 'pay_')) {
            return $pixId;
        }

        $pref = (string) (DB::table('assinaturas')
            ->where('tenant_id', $tenantId)
            ->where('matricula_id', $matriculaId)
            ->where('gateway_preference_id', 'like', 'pay_%')
            ->orderByDesc('id')
            ->value('gateway_preference_id') ?? '');

        return str_starts_with($pref, 'pay_') ? $pref : '';
    }

    /**
     * Consulta a cobrança no Asaas (PIX ou cartão) e aplica o webhook local.
     *
     * @return array<string, mixed>
     */
    public function sincronizar(int $tenantId, int $matriculaId): array
    {
        $paymentId = $this->paymentIdDaMatricula($tenantId, $matriculaId);
        if ($paymentId === '') {
            return [];
        }

        try {
            $payment = (new AsaasService($tenantId))->obterPagamento($paymentId);
        } catch (\Throwable $e) {
            Log::warning('Asaas sync: falha ao consultar cobrança', [
                'tenant_id' => $tenantId,
                'matricula_id' => $matriculaId,
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        if ($payment === []) {
            return [];
        }

        $snapshot = AsaasPagamentoStatus::snapshot($payment);
        $status = (string) ($snapshot['status'] ?? '');

        if (AsaasPagamentoStatus::pago($status)) {
            app(AsaasWebhookService::class)->processar([
                'event' => AsaasPagamentoStatus::eventoWebhook($status),
                'id' => 'poll-'.$paymentId,
                'payment' => $payment,
            ], null);
        }

        return $snapshot;
    }
}
