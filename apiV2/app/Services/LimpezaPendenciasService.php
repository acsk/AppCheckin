<?php

namespace App\Services;

use App\Services\Asaas\AsaasService;
use App\Services\Recorrencia\RecorrenciaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Encerra pendências abandonadas pelo app (nada é apagado: o registro fica cancelado/expirado no histórico).
 *
 * 1. Simulação de troca de plano não paga (parcela [MIGRACAO_SIMULADA]): se paga depois, aplicaria a troca
 *    fora de hora; é cancelada no Asaas e aqui.
 * 2. Checkout avulso não pago (assinaturas pendentes): cobrança cancelada no Asaas, assinatura "expirada".
 *    Se a matrícula ficou pendente sem nenhum pagamento (compra abandonada), ela e as parcelas
 *    "Aguardando pagamento…" são canceladas.
 * 3. Assinatura com fidelidade nunca ativada (contrato pendente_ativacao sem mensalidade paga).
 *
 * Antes de cancelar, a cobrança é conferida no Asaas: paga (webhook perdido) nunca é cancelada; fica para
 * a sincronização dar baixa. Cobrança de Mercado Pago (sem consulta aqui) só sai depois de 30 dias.
 */
class LimpezaPendenciasService
{
    private const ABERTAS = ['PENDING', 'OVERDUE', 'AWAITING_RISK_ANALYSIS'];

    private const PAGAS = ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH'];

    private const DIAS_MERCADO_PAGO = 30;

    /** @var \Closure(int): AsaasService */
    private \Closure $asaas;

    public function __construct(
        private readonly RecorrenciaService $recorrencia,
        private readonly MatriculaEncadeamentoService $encadeamento,
        ?\Closure $asaas = null,
    ) {
        $this->asaas = $asaas ?? static fn (int $tenantId) => app(AsaasService::class, ['tenantId' => $tenantId]);
    }

    /**
     * @return array{simulacoes: int, checkouts: int, matriculas: int, assinaturas_fidelidade: int, ignoradas_pagas: int, falhas: int, itens: list<string>}
     */
    public function executar(int $dias = 3, int $diasFidelidade = 7, ?int $tenantId = null, bool $simular = false): array
    {
        $r = ['simulacoes' => 0, 'checkouts' => 0, 'matriculas' => 0, 'assinaturas_fidelidade' => 0, 'ignoradas_pagas' => 0, 'falhas' => 0, 'itens' => []];
        $limite = now()->subDays(max(1, $dias));

        // 1. Simulações de troca de plano
        $simulacoes = DB::table('pagamentos_plano')->whereIn('status_pagamento_id', [1, 3])->whereNull('data_pagamento')
            ->where('observacoes', 'like', '%'.MatriculaMigracaoService::TAG_MIGRACAO_SIMULADA.'%')
            ->where('created_at', '<', $limite)
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get(['id', 'tenant_id', 'matricula_id', 'valor', 'observacoes', 'created_at']);
        foreach ($simulacoes as $p) {
            $estado = $this->encerrarCobranca((int) $p->tenant_id, $this->idCobranca((string) $p->observacoes), (string) $p->created_at, $simular);
            if (! $this->contar($r, $estado, "Simulação #{$p->id} (matrícula #{$p->matricula_id}, R$ {$p->valor})")) {
                continue;
            }
            if (! $simular) {
                DB::table('pagamentos_plano')->where('id', $p->id)->whereNull('data_pagamento')->update([
                    'status_pagamento_id' => 4, 'updated_at' => now(),
                    'observacoes' => DB::raw("CONCAT(COALESCE(observacoes, ''), ' [Simulação abandonada: cancelada automaticamente]')"),
                ]);
            }
            $r['simulacoes']++;
        }

        // 2. Checkouts avulsos pendentes
        $pendente = DB::table('assinatura_status')->where('codigo', 'pendente')->value('id');
        $expirada = DB::table('assinatura_status')->where('codigo', 'expirada')->value('id')
            ?: DB::table('assinatura_status')->where('codigo', 'cancelada')->value('id');
        $porSistema = DB::table('assinatura_cancelamento_tipos')->where('codigo', 'sistema')->value('id');
        if ($pendente && $expirada) {
            $checkouts = DB::table('assinaturas')->where('status_id', $pendente)
                ->where(fn ($q) => $q->where('tipo_cobranca', 'avulso')->orWhereNull('tipo_cobranca'))
                ->whereRaw('COALESCE(atualizado_em, criado_em) < ?', [$limite])
                ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                ->get(['id', 'tenant_id', 'matricula_id', 'valor', 'gateway_preference_id', 'criado_em', 'atualizado_em']);
            foreach ($checkouts as $a) {
                $estado = $this->encerrarCobranca((int) $a->tenant_id, (string) $a->gateway_preference_id, (string) ($a->atualizado_em ?? $a->criado_em), $simular);
                if (! $this->contar($r, $estado, "Checkout #{$a->id} (matrícula #".($a->matricula_id ?? '—').", R$ {$a->valor})")) {
                    continue;
                }
                if (! $simular) {
                    DB::table('assinaturas')->where('id', $a->id)->where('status_id', $pendente)->update([
                        'status_id' => $expirada, 'status_gateway' => 'expired', 'cancelado_por_id' => $porSistema,
                        'motivo_cancelamento' => 'Checkout não pago: encerrado automaticamente', 'atualizado_em' => now(),
                    ]);
                }
                $r['checkouts']++;
                if ($a->matricula_id && $this->encerrarMatriculaAbandonada((int) $a->tenant_id, (int) $a->matricula_id, (string) ($a->atualizado_em ?? $a->criado_em), $simular)) {
                    $r['matriculas']++;
                    $r['itens'][] = "Matrícula #{$a->matricula_id} pendente sem pagamento: cancelada";
                }
            }
        }

        // 3. Assinaturas com fidelidade nunca ativadas
        if (Schema::hasTable('contratos_recorrencia')) {
            $contratos = DB::table('contratos_recorrencia')->where('status', 'pendente_ativacao')
                ->where('created_at', '<', now()->subDays(max(1, $diasFidelidade)))
                ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->get(['id', 'tenant_id']);
            foreach ($contratos as $c) {
                if ($simular) {
                    $r['assinaturas_fidelidade']++;
                    $r['itens'][] = "Assinatura com fidelidade #{$c->id} não ativada";

                    continue;
                }
                if ($this->recorrencia->encerrarNaoAtivado((int) $c->tenant_id, (int) $c->id)) {
                    $r['assinaturas_fidelidade']++;
                    $r['itens'][] = "Assinatura com fidelidade #{$c->id} não ativada: cancelada";
                }
            }
        }

        if (! $simular && ($r['simulacoes'] + $r['checkouts'] + $r['assinaturas_fidelidade']) > 0) {
            Log::info('Limpeza de pendências abandonadas', array_diff_key($r, ['itens' => 1]));
        }

        return $r;
    }

    /**
     * Cancela a cobrança no provedor quando ela ainda está aberta.
     *
     * @return string paga | encerrada (pode marcar aqui) | aguardar (Mercado Pago recente) | falha
     */
    private function encerrarCobranca(int $tenantId, string $paymentId, string $criadaEm, bool $simular): string
    {
        if ($paymentId === '') {
            return 'encerrada';
        }
        if (! str_starts_with($paymentId, 'pay_')) {
            return strtotime($criadaEm) < strtotime('-'.self::DIAS_MERCADO_PAGO.' days') ? 'encerrada' : 'aguardar';
        }
        try {
            $gw = ($this->asaas)($tenantId);
            $pg = $gw->obterPagamento($paymentId);
            $status = strtoupper((string) ($pg['status'] ?? ''));
            if (in_array($status, self::PAGAS, true)) {
                return 'paga';
            }
            if (! $simular && ($pg['deleted'] ?? false) !== true && in_array($status, self::ABERTAS, true)) {
                $gw->cancelarCobranca($paymentId);
            }

            return 'encerrada';
        } catch (\App\Services\Asaas\AsaasNaoEncontradoException) {
            return 'encerrada';
        } catch (\Throwable $e) {
            Log::warning('Limpeza: não deu para conferir a cobrança no Asaas', ['tenant_id' => $tenantId, 'payment_id' => $paymentId, 'error' => $e->getMessage()]);

            return 'falha';
        }
    }

    /**
     * Matrícula pendente cuja compra pelo app foi abandonada. Sem nenhum pagamento: cancelada.
     * Só com pagamentos de ciclos anteriores (renovação na mesma matrícula que não foi paga): vencida.
     * Pagamento desde o checkout: o ciclo foi pago, não mexe.
     */
    private function encerrarMatriculaAbandonada(int $tenantId, int $matriculaId, string $checkoutEm, bool $simular): bool
    {
        $codigo = DB::table('matriculas as m')->join('status_matricula as s', 's.id', '=', 'm.status_id')
            ->where('m.id', $matriculaId)->where('m.tenant_id', $tenantId)->value('s.codigo');
        if ($codigo !== 'pendente') {
            return false;
        }
        $cicloAnteriorPago = $this->encadeamento->temPagamentoConfirmado($matriculaId);
        if ($cicloAnteriorPago && DB::table('pagamentos_plano')->where('matricula_id', $matriculaId)
            ->where('status_pagamento_id', 2)->where('created_at', '>=', $checkoutEm)->exists()) {
            return false;
        }
        // Matrícula de assinatura com fidelidade é encerrada pelo próprio contrato
        if (Schema::hasTable('contratos_recorrencia') && DB::table('contratos_recorrencia')->where('matricula_id', $matriculaId)->exists()) {
            return false;
        }
        if ($simular) {
            return true;
        }
        DB::table('pagamentos_plano')->where('tenant_id', $tenantId)->where('matricula_id', $matriculaId)
            ->whereIn('status_pagamento_id', [1, 3])->whereNull('data_pagamento')
            ->update(['status_pagamento_id' => 4, 'updated_at' => now(),
                'observacoes' => DB::raw("CONCAT(COALESCE(observacoes, ''), ' [Compra abandonada: cancelada automaticamente]')")]);
        $status = $cicloAnteriorPago ? 'vencida' : 'cancelada';
        DB::table('matriculas')->where('id', $matriculaId)->where('tenant_id', $tenantId)->update([
            'status_id' => DB::table('status_matricula')->where('codigo', $status)->value('id'),
            'updated_at' => now(),
        ]);

        return true;
    }

    /** Id da cobrança Asaas anotado na parcela ("[Asaas pay_…]"). */
    private function idCobranca(string $observacoes): string
    {
        return preg_match('/\b(pay_[A-Za-z0-9]+)\b/', $observacoes, $m) ? $m[1] : '';
    }

    /** @param  array<string, mixed>  $r */
    private function contar(array &$r, string $estado, string $rotulo): bool
    {
        if ($estado === 'paga') {
            $r['ignoradas_pagas']++;
            $r['itens'][] = "{$rotulo}: está paga no Asaas, mantida (a sincronização dá baixa)";

            return false;
        }
        if ($estado === 'falha') {
            $r['falhas']++;
            $r['itens'][] = "{$rotulo}: Asaas indisponível, tenta amanhã";

            return false;
        }
        if ($estado === 'aguardar') {
            return false;
        }
        $r['itens'][] = $rotulo;

        return true;
    }
}
