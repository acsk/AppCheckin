<?php

namespace App\Services\Asaas;

use App\Support\AsaasPagamentoStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AsaasPagamentoSync
{
    public function paymentIdDaMatricula(int $tenantId, int $matriculaId): string
    {
        $ids = $this->paymentIdsDaMatricula($tenantId, $matriculaId);

        return $ids[0] ?? '';
    }

    /**
     * Cobrança atual da assinatura primeiro; PIX antigos depois.
     *
     * @return list<string>
     */
    public function paymentIdsDaMatricula(int $tenantId, int $matriculaId): array
    {
        $ids = [];
        $pref = (string) (DB::table('assinaturas')
            ->where('tenant_id', $tenantId)
            ->where('matricula_id', $matriculaId)
            ->where('gateway_preference_id', 'like', 'pay_%')
            ->orderByDesc('id')
            ->value('gateway_preference_id') ?? '');
        if (str_starts_with($pref, 'pay_')) {
            $ids[] = $pref;
        }

        $pixIds = DB::table('pagamentos_pix')
            ->where('tenant_id', $tenantId)
            ->where('matricula_id', $matriculaId)
            ->where('payment_id', 'like', 'pay_%')
            ->orderByDesc('id')
            ->pluck('payment_id');

        foreach ($pixIds as $pixId) {
            $pixId = (string) $pixId;
            if (str_starts_with($pixId, 'pay_') && ! in_array($pixId, $ids, true)) {
                $ids[] = $pixId;
            }
        }

        $obs = DB::table('pagamentos_plano')
            ->where('tenant_id', $tenantId)
            ->where('matricula_id', $matriculaId)
            ->whereNotNull('observacoes')
            ->pluck('observacoes');
        foreach ($obs as $texto) {
            if (preg_match_all('/pay_[a-zA-Z0-9]+/', (string) $texto, $m) < 1) {
                continue;
            }
            foreach ($m[0] as $payId) {
                if (! in_array($payId, $ids, true)) {
                    $ids[] = $payId;
                }
            }
        }

        return $ids;
    }

    /**
     * Consulta as cobranças Asaas (cartão atual e PIX) e aplica o webhook local.
     *
     * @return array<string, mixed>
     */
    public function sincronizar(int $tenantId, int $matriculaId): array
    {
        $ids = $this->paymentIdsDaMatricula($tenantId, $matriculaId);
        if ($ids === []) {
            return [];
        }

        $principal = [];
        foreach ($ids as $index => $paymentId) {
            $snapshot = $this->sincronizarPagamento($tenantId, $matriculaId, $paymentId);
            if ($index === 0) {
                $principal = $snapshot;
            }
        }

        return $principal;
    }

    /**
     * @return array<string, mixed>
     */
    private function sincronizarPagamento(int $tenantId, int $matriculaId, string $paymentId): array
    {
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
