<?php

namespace App\Console\Commands;

use App\Services\ReajustePrecoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Matrícula com reajuste pendente e ciclo pago encerrado (vencida, ou ativa com o acesso acabando hoje): a parcela
 * em aberto, mesmo já vencida, passa ao preço novo. Corrige quem venceu antes do reajuste e roda de hora em hora
 * para quem vence depois dele.
 */
class AplicarReajusteRenovacoesCommand extends Command
{
    protected $signature = 'reajuste:aplicar-renovacoes
        {--tenant= : Só uma academia}
        {--simular : Só lista, sem gravar nada}';

    protected $description = 'Aplica o preço reajustado às parcelas em aberto de matrículas com o ciclo pago encerrado';

    public function handle(ReajustePrecoService $reajustes): int
    {
        if (! $reajustes->disponivel()) {
            $this->warn('Coluna matriculas.valor_renovacao não existe.');

            return self::SUCCESS;
        }
        $simular = (bool) $this->option('simular');
        $hoje = now()->toDateString();
        $matriculas = DB::table('matriculas as m')->join('status_matricula as s', 's.id', '=', 'm.status_id')
            ->leftJoin('alunos as a', 'a.id', '=', 'm.aluno_id')
            ->whereNotNull('m.valor_renovacao')
            ->when($this->option('tenant') !== null, fn ($q) => $q->where('m.tenant_id', (int) $this->option('tenant')))
            ->where(fn ($q) => $q->where('s.codigo', 'vencida')
                ->orWhere(fn ($a) => $a->where('s.codigo', 'ativa')->whereRaw('COALESCE(m.data_vencimento, m.proxima_data_vencimento) <= ?', [$hoje])))
            ->orderBy('m.id')
            ->get(['m.id', 'm.tenant_id', 'm.valor', 'm.valor_renovacao', 'a.nome']);

        $total = 0;
        DB::beginTransaction();
        try {
            foreach ($matriculas as $m) {
                $n = $reajustes->aplicarNaRenovacaoEmAberto((int) $m->tenant_id, (int) $m->id);
                $total += $n;
                $this->line(sprintf(' - #%d %s: R$ %s → R$ %s, %d parcela(s) em aberto', $m->id, $m->nome ?? '',
                    number_format((float) $m->valor, 2, ',', '.'), number_format((float) $m->valor_renovacao, 2, ',', '.'), $n));
            }
            $simular ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        $this->info(sprintf('%s: %d matrícula(s), %d parcela(s) no preço novo.', $simular ? 'Simulação' : 'Aplicado', $matriculas->count(), $total));

        return self::SUCCESS;
    }
}
