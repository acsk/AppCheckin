<?php

namespace App\Services\Asaas;

use App\Support\AsaasPagamentoStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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
        if (Schema::hasTable('pagamentos_plano')) {
            $simObs = DB::table('pagamentos_plano')
                ->where('tenant_id', $tenantId)
                ->where('matricula_id', $matriculaId)
                ->whereIn('status_pagamento_id', [1, 3])
                ->whereNull('data_pagamento')
                ->where('observacoes', 'like', '%[MIGRACAO_SIMULADA%')
                ->orderByDesc('id')
                ->pluck('observacoes');
            foreach ($simObs as $texto) {
                if (preg_match_all('/pay_[a-zA-Z0-9]+/', (string) $texto, $m) < 1) {
                    continue;
                }
                foreach ($m[0] as $payId) {
                    if (! in_array($payId, $ids, true)) {
                        $ids[] = $payId;
                    }
                }
            }
        }
        $pref = (string) (DB::table('assinaturas')
            ->where('tenant_id', $tenantId)
            ->where('matricula_id', $matriculaId)
            ->where('gateway_preference_id', 'like', 'pay_%')
            ->orderByDesc('id')
            ->value('gateway_preference_id') ?? '');
        if (str_starts_with($pref, 'pay_') && ! in_array($pref, $ids, true)) {
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

        if (! Schema::hasTable('pagamentos_plano')) {
            return $ids;
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
     * Rede de segurança do webhook (schedule): cobranças Asaas atuais de matrículas com parcela em aberto.
     * Só a cobrança da assinatura ainda não aprovada e PIX pendentes — nunca o histórico das observações,
     * para uma cobrança antiga já paga não dar baixa na parcela nova.
     *
     * @param  (callable(int, string): array<string, mixed>)|null  $obter  consulta da cobrança (testes)
     * @return array{conferidas: int, pagas: int, erros: int}
     */
    public function sincronizarPendentes(?int $tenantId = null, int $limite = 200, int $dias = 60, ?callable $obter = null): array
    {
        $obter ??= static fn (int $tenant, string $paymentId): array => (new AsaasService($tenant))->obterPagamento($paymentId);
        $desde = now()->subDays(max(1, $dias))->toDateTimeString();

        $comParcelaAberta = static fn ($q) => $q->select(DB::raw(1))
            ->from('pagamentos_plano as pp')
            ->whereColumn('pp.tenant_id', 'x.tenant_id')
            ->whereColumn('pp.matricula_id', 'x.matricula_id')
            ->whereIn('pp.status_pagamento_id', [1, 3])
            ->whereNull('pp.data_pagamento');
        $comAsaas = static fn ($q) => $q->select(DB::raw(1))
            ->from('tenant_payment_credentials as tpc')
            ->whereColumn('tpc.tenant_id', 'x.tenant_id')
            ->where('tpc.provider', 'asaas');
        $naoAprovado = static fn (string $coluna) => static fn ($q) => $q->whereNull($coluna)->orWhereNotIn($coluna, ['approved', 'cancelled', 'canceled']);

        $assinaturas = DB::table('assinaturas as x')
            ->where('x.gateway_preference_id', 'like', 'pay_%')
            ->where($naoAprovado('x.status_gateway'))
            ->where('x.atualizado_em', '>=', $desde)
            ->whereExists($comParcelaAberta)
            ->whereExists($comAsaas)
            ->when($tenantId, fn ($q) => $q->where('x.tenant_id', $tenantId))
            ->orderByDesc('x.atualizado_em')
            ->limit($limite)
            ->get(['x.tenant_id', 'x.matricula_id', 'x.gateway_preference_id as payment_id']);

        $pix = DB::table('pagamentos_pix as x')
            ->where('x.payment_id', 'like', 'pay_%')
            ->where($naoAprovado('x.status'))
            ->where('x.created_at', '>=', $desde)
            ->whereExists($comParcelaAberta)
            ->whereExists($comAsaas)
            ->when($tenantId, fn ($q) => $q->where('x.tenant_id', $tenantId))
            ->orderByDesc('x.created_at')
            ->limit($limite)
            ->get(['x.tenant_id', 'x.matricula_id', 'x.payment_id']);

        $alvos = [];
        foreach ($assinaturas->concat($pix) as $row) {
            $alvos[$row->payment_id] ??= [(int) $row->tenant_id, (int) $row->matricula_id];
        }

        $res = ['conferidas' => 0, 'pagas' => 0, 'erros' => 0];
        foreach (array_slice($alvos, 0, $limite, true) as $paymentId => [$tenant, $matriculaId]) {
            $res['conferidas']++;
            try {
                $payment = $obter($tenant, (string) $paymentId);
                if ($payment === [] || ! AsaasPagamentoStatus::pago((string) ($payment['status'] ?? ''))) {
                    continue;
                }
                $r = app(AsaasWebhookService::class)->processar([
                    'event' => AsaasPagamentoStatus::eventoWebhook((string) $payment['status']),
                    'id' => 'schedule-'.$paymentId,
                    'payment' => $payment,
                ], null);
                if (($r['status'] ?? 500) < 300 && isset($r['body']['matricula_id'])) {
                    $res['pagas']++;
                    Log::info('Asaas schedule: pagamento aplicado', ['tenant_id' => $tenant, 'matricula_id' => $matriculaId, 'payment_id' => $paymentId]);
                }
            } catch (\Throwable $e) {
                $res['erros']++;
                Log::warning('Asaas schedule: falha ao conferir cobrança', [
                    'tenant_id' => $tenant,
                    'matricula_id' => $matriculaId,
                    'payment_id' => $paymentId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $res;
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
