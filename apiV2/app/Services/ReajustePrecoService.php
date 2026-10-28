<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reajuste de preço do ciclo para quem já é aluno no à vista: vale a partir da próxima renovação.
 *
 * O ciclo já pago não muda: `matriculas.valor` continua o preço pago (crédito de troca de plano e
 * quitação dependem dele). O preço novo fica em `matriculas.valor_renovacao` e passa a ser o `valor`
 * quando o ciclo novo começa (renovação por PIX com o ciclo encerrado, ou baixa da parcela no preço novo).
 *
 * Só reajusta quem pagava o preço de tabela anterior: valor negociado, pacote e assinatura com
 * fidelidade (contratos_recorrencia, regra própria) ficam como estão.
 */
class ReajustePrecoService
{
    private ?bool $coluna = null;

    public function disponivel(): bool
    {
        return $this->coluna ??= Schema::hasColumn('matriculas', 'valor_renovacao');
    }

    /**
     * Chamado quando o valor do ciclo muda no painel.
     *
     * @return array{matriculas: int, parcelas: int}
     */
    public function reajustarCiclo(int $tenantId, int $cicloId, float $valorAnterior, float $valorNovo): array
    {
        $r = ['matriculas' => 0, 'parcelas' => 0];
        $anterior = (int) round($valorAnterior * 100);
        $novo = (int) round($valorNovo * 100);
        if (! $this->disponivel() || $anterior === $novo || $novo <= 0) {
            return $r;
        }

        $matriculas = DB::table('matriculas as m')->join('status_matricula as s', 's.id', '=', 'm.status_id')
            ->where('m.tenant_id', $tenantId)->where('m.plano_ciclo_id', $cicloId)
            ->where('m.tipo_cobranca', 'avulso')->whereNull('m.pacote_contrato_id')
            ->whereIn('s.codigo', ['ativa', 'vencida'])
            // preço de tabela anterior (ou já reajustado antes e ainda não renovado)
            ->where(fn ($q) => $q->where(fn ($a) => $a->whereNull('m.valor_renovacao')->whereRaw('ROUND(m.valor * 100) = ?', [$anterior]))
                ->orWhereRaw('ROUND(m.valor_renovacao * 100) = ?', [$anterior]))
            ->when(Schema::hasTable('contratos_recorrencia'), fn ($q) => $q->whereNotExists(
                fn ($c) => $c->selectRaw('1')->from('contratos_recorrencia as cr')->whereColumn('cr.matricula_id', 'm.id')
            ))
            ->get(['m.id', 'm.valor']);

        $temOriginal = Schema::hasColumn('pagamentos_plano', 'valor_original');
        foreach ($matriculas as $m) {
            $voltou = (int) round((float) $m->valor * 100) === $novo;
            DB::table('matriculas')->where('id', $m->id)->update(['valor_renovacao' => $voltou ? null : $valorNovo, 'updated_at' => now()]);
            $r['matriculas']++;

            // Próximas parcelas já geradas (baixa no painel gera a seguinte) e ainda sem cobrança no provedor
            $parcelas = DB::table('pagamentos_plano')->where('tenant_id', $tenantId)->where('matricula_id', $m->id)
                ->whereIn('status_pagamento_id', [1, 3])->whereNull('data_pagamento')
                ->whereDate('data_vencimento', '>=', now()->toDateString())
                ->whereRaw('ROUND('.($temOriginal ? 'COALESCE(valor_original, valor)' : 'valor').' * 100) = ?', [$anterior])
                ->where(fn ($q) => $q->whereNull('observacoes')->orWhere(fn ($o) => $o
                    ->where('observacoes', 'not like', '%Asaas pay%')
                    ->where('observacoes', 'not like', '%Aguardando pagamento%')
                    ->where('observacoes', 'not like', '%'.MatriculaMigracaoService::TAG_MIGRACAO_SIMULADA.'%')))
                ->get(['id', 'desconto']);
            foreach ($parcelas as $p) {
                $desconto = (float) ($p->desconto ?? 0);
                DB::table('pagamentos_plano')->where('id', $p->id)->update(array_merge(
                    ['valor' => round(max(0, $valorNovo - $desconto), 2), 'updated_at' => now()],
                    $temOriginal ? ['valor_original' => $valorNovo] : [],
                ));
                $r['parcelas']++;
            }
        }

        return $r;
    }

    /** Preço do próximo ciclo da matrícula, quando há reajuste pendente. */
    public function valorRenovacao(int $tenantId, int $matriculaId): ?float
    {
        if (! $this->disponivel()) {
            return null;
        }
        $v = DB::table('matriculas')->where('id', $matriculaId)->where('tenant_id', $tenantId)->value('valor_renovacao');

        return $v === null ? null : (float) $v;
    }

    /** O ciclo novo começou: o preço reajustado vira o valor da matrícula. Devolve o valor vigente. */
    public function iniciarCicloNovo(int $tenantId, int $matriculaId): ?float
    {
        $novo = $this->valorRenovacao($tenantId, $matriculaId);
        if ($novo === null) {
            return null;
        }
        DB::table('matriculas')->where('id', $matriculaId)->where('tenant_id', $tenantId)
            ->update(['valor' => $novo, 'valor_renovacao' => null, 'updated_at' => now()]);

        return $novo;
    }
}
