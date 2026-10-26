<?php

namespace App\Services\Pacote;

use App\Repositories\AdminPacoteRepository;
use App\Services\PagamentoPlanoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * O pagante paga o pacote inteiro numa cobrança só, feita na matrícula dele (MAT-…, a mesma baixa dos
 * webhooks e da conferência agendada). Aqui essa baixa vira a do contrato: as parcelas dos demais
 * participantes no mesmo vencimento são quitadas, as matrículas recalculadas e o contrato ativado
 * (ou estendido, na renovação).
 *
 * Só vale para a parcela registrada em pacote_cobrancas (cobrada como o pacote inteiro): baixa manual
 * da parte do pagante numa parcela comum (cada um pagando a sua) não quita ninguém. Idempotente.
 */
class PacoteConciliacaoService
{
    public function __construct(
        private readonly AdminPacoteRepository $pacotes,
        private readonly PagamentoPlanoService $pagamentosPlano,
    ) {}

    /** Matrícula do pagante dentro do contrato (0 se não houver). */
    public function matriculaDoPagante(int $tenantId, int $contratoId): int
    {
        return (int) (DB::table('matriculas as m')
            ->join('alunos as a', 'a.id', '=', 'm.aluno_id')
            ->join('pacote_contratos as pc', 'pc.id', '=', 'm.pacote_contrato_id')
            ->where('m.tenant_id', $tenantId)
            ->where('m.pacote_contrato_id', $contratoId)
            ->whereColumn('a.usuario_id', 'pc.pagante_usuario_id')
            ->orderBy('m.id')
            ->value('m.id') ?? 0);
    }

    /** Contrato da matrícula, se ela for a do pagante (para o webhook decidir se concilia). */
    public function contratoSeForDoPagante(int $tenantId, int $matriculaId): ?int
    {
        $contratoId = (int) (DB::table('matriculas')->where('tenant_id', $tenantId)->where('id', $matriculaId)->value('pacote_contrato_id') ?? 0);

        return $contratoId > 0 && $this->matriculaDoPagante($tenantId, $contratoId) === $matriculaId ? $contratoId : null;
    }

    /** @return bool true se alguma parcela foi quitada ou o contrato mudou */
    public function conciliar(int $tenantId, int $contratoId): bool
    {
        $contrato = DB::table('pacote_contratos')->where('tenant_id', $tenantId)->where('id', $contratoId)->first(['id', 'status', 'data_fim']);
        if (! $contrato || ! in_array($contrato->status, ['pendente', 'ativo'], true)) {
            return false;
        }
        $paganteMatriculaId = $this->matriculaDoPagante($tenantId, $contratoId);
        if ($paganteMatriculaId <= 0) {
            return false;
        }

        // Períodos pagos pela cobrança do pacote: vencimento → como foi pago
        $pagos = DB::table('pagamentos_plano as pp')
            ->join('pacote_cobrancas as pcb', 'pcb.pagamento_plano_id', '=', 'pp.id')
            ->where('pcb.pacote_contrato_id', $contratoId)
            ->where('pp.tenant_id', $tenantId)
            ->where('pp.matricula_id', $paganteMatriculaId)
            ->where('pp.status_pagamento_id', 2)
            ->get(['pp.id', 'pp.data_vencimento', 'pp.data_pagamento', 'pp.forma_pagamento_id', 'pp.tipo_baixa_id'])
            ->keyBy(fn ($p) => substr((string) $p->data_vencimento, 0, 10));
        if ($pagos->isEmpty()) {
            return false;
        }

        $abertas = DB::table('pagamentos_plano as pp')
            ->join('matriculas as m', function ($j) {
                $j->on('m.id', '=', 'pp.matricula_id')->on('m.tenant_id', '=', 'pp.tenant_id');
            })
            ->where('pp.tenant_id', $tenantId)
            ->where('m.pacote_contrato_id', $contratoId)
            ->where('pp.matricula_id', '<>', $paganteMatriculaId)
            ->whereIn('pp.status_pagamento_id', [1, 3])
            ->whereNull('pp.data_pagamento')
            ->whereIn('pp.data_vencimento', $pagos->keys()->all())
            ->get(['pp.id', 'pp.matricula_id', 'pp.data_vencimento', 'pp.observacoes']);

        $afetadas = [$paganteMatriculaId => true];
        DB::transaction(function () use ($abertas, $pagos, $tenantId, $contratoId, &$afetadas) {
            foreach ($abertas as $p) {
                $origem = $pagos[substr((string) $p->data_vencimento, 0, 10)];
                DB::table('pagamentos_plano')->where('id', $p->id)->where('tenant_id', $tenantId)->update([
                    'status_pagamento_id' => 2,
                    'data_pagamento' => $origem->data_pagamento ?? now()->toDateString(),
                    'forma_pagamento_id' => $origem->forma_pagamento_id,
                    'tipo_baixa_id' => $origem->tipo_baixa_id,
                    'observacoes' => trim(((string) $p->observacoes).' [Pago pelo pagante do pacote, parcela #'.$origem->id.']'),
                    'updated_at' => now(),
                ]);
                $afetadas[(int) $p->matricula_id] = true;
            }
        });

        foreach (array_keys($afetadas) as $matriculaId) {
            try {
                $this->pagamentosPlano->atualizarStatusMatricula($tenantId, $matriculaId);
            } catch (\Throwable $e) {
                Log::warning('Pacote: falha ao recalcular matrícula', ['contrato_id' => $contratoId, 'matricula_id' => $matriculaId, 'error' => $e->getMessage()]);
            }
            $this->pacotes->ativarMatriculaSePendenteOuVencida($matriculaId);
        }

        $mudou = $abertas->isNotEmpty();
        if ($contrato->status === 'pendente') {
            $this->pacotes->ativarContratoComVigenciaDasMatriculas($contratoId, $tenantId);
            $this->pacotes->ativarBeneficiariosDoContrato($contratoId, $tenantId);
            $mudou = true;
        }

        // Renovação paga: a vigência do contrato acompanha a das matrículas
        $fim = DB::table('matriculas')->where('tenant_id', $tenantId)->where('pacote_contrato_id', $contratoId)->max('data_vencimento');
        if ($fim && (! $contrato->data_fim || $fim > $contrato->data_fim)) {
            DB::table('pacote_contratos')->where('id', $contratoId)->where('tenant_id', $tenantId)->update(['data_fim' => $fim, 'updated_at' => now()]);
            $mudou = true;
        }

        if ($mudou) {
            Log::info('Pacote: contrato conciliado com o pagamento do pagante', ['tenant_id' => $tenantId, 'contrato_id' => $contratoId, 'parcelas_quitadas' => $abertas->count()]);
        }

        return $mudou;
    }

    /**
     * Concilia os contratos da academia com cobrança de pacote paga e ainda não refletida (contrato
     * pendente ou participante com parcela aberta). As listagens do painel e do app chamam isto: cobre
     * o Mercado Pago, que dá baixa fora da apiV2, e a baixa manual.
     */
    public function conciliarAbertos(int $tenantId): void
    {
        try {
            $ids = $this->contratosParaConciliar($tenantId);
        } catch (\Throwable $e) {
            // Nunca derruba a listagem (ex.: migration de pacote_cobrancas ainda não aplicada)
            Log::warning('Pacote: conciliação indisponível', ['tenant_id' => $tenantId, 'error' => $e->getMessage()]);

            return;
        }

        foreach ($ids as $id) {
            try {
                $this->conciliar($tenantId, (int) $id);
            } catch (\Throwable $e) {
                Log::warning('Pacote: falha ao conciliar contrato', ['contrato_id' => (int) $id, 'error' => $e->getMessage()]);
            }
        }
    }

    /** @return \Illuminate\Support\Collection<int, int> */
    private function contratosParaConciliar(int $tenantId)
    {
        return DB::table('pacote_contratos as pc')
            ->where('pc.tenant_id', $tenantId)
            ->whereIn('pc.status', ['pendente', 'ativo'])
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('pacote_cobrancas as pcb')
                ->join('pagamentos_plano as pp', 'pp.id', '=', 'pcb.pagamento_plano_id')
                ->whereColumn('pcb.pacote_contrato_id', 'pc.id')
                ->where('pp.status_pagamento_id', 2))
            ->where(fn ($w) => $w->where('pc.status', 'pendente')
                ->orWhereExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('pagamentos_plano as pp2')
                    ->join('matriculas as m2', 'm2.id', '=', 'pp2.matricula_id')
                    ->whereColumn('m2.pacote_contrato_id', 'pc.id')
                    ->where('pp2.tenant_id', $tenantId)
                    ->whereIn('pp2.status_pagamento_id', [1, 3])
                    ->whereNull('pp2.data_pagamento')))
            ->limit(50)
            ->pluck('pc.id');
    }
}
