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
     * Chamado por ação explícita; editar o preço de tabela não chama este método.
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

        $autorizacao = Schema::hasColumn('matriculas', 'reajuste_autorizado_em');
        $matriculas = DB::table('matriculas as m')->join('status_matricula as s', 's.id', '=', 'm.status_id')
            ->where('m.tenant_id', $tenantId)->where('m.plano_ciclo_id', $cicloId)
            ->where('m.tipo_cobranca', 'avulso')->whereNull('m.pacote_contrato_id')
            ->whereIn('s.codigo', ['ativa', 'vencida'])
            // preço de tabela anterior (ou já reajustado antes e ainda não renovado)
            ->where(fn ($q) => $q->where(fn ($a) => $a
                ->where(fn ($v) => $v->whereNull('m.valor_renovacao')->when($autorizacao, fn ($v) => $v->orWhereNull('m.reajuste_autorizado_em')))
                ->whereRaw('ROUND(m.valor * 100) = ?', [$anterior]))
                ->orWhereRaw('ROUND(m.valor_renovacao * 100) = ?', [$anterior]))
            ->when(Schema::hasTable('contratos_recorrencia'), fn ($q) => $q->whereNotExists(
                fn ($c) => $c->selectRaw('1')->from('contratos_recorrencia as cr')->whereColumn('cr.matricula_id', 'm.id')
            ))
            ->get(['m.id', 'm.valor', 's.codigo as status_codigo']);

        $temOriginal = Schema::hasColumn('pagamentos_plano', 'valor_original');
        foreach ($matriculas as $m) {
            $voltou = (int) round((float) $m->valor * 100) === $novo;
            DB::table('matriculas')->where('tenant_id', $tenantId)->where('id', $m->id)->update([
                'valor_renovacao' => $voltou ? null : $valorNovo, 'updated_at' => now(),
                ...($autorizacao ? ['reajuste_autorizado_em' => now()] : []),
            ]);
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

            // Vencida: o ciclo pago acabou, a parcela em aberto (mesmo vencida) já é a renovação no preço novo
            if ($m->status_codigo === 'vencida' && ! $voltou) {
                $r['parcelas'] += $this->aplicarNaRenovacaoEmAberto($tenantId, (int) $m->id);
            }
        }

        return $r;
    }

    /**
     * Ciclo pago encerrado (matrícula vencida ou acesso até hoje) com reajuste pendente: a parcela em aberto é a
     * renovação, então cobra o preço novo, mesmo já vencida (antes só as a vencer eram reajustadas e a matrícula
     * 236 seguia cobrando R$ 70 com o ciclo a R$ 80). O preço novo vira o valor da matrícula. Cobrança antiga no
     * preço velho deixa de ser reaproveitada: o próximo PIX/checkout sai no valor novo e cancela a anterior.
     *
     * @return int parcelas reajustadas
     */
    public function aplicarNaRenovacaoEmAberto(int $tenantId, int $matriculaId): int
    {
        $novo = $this->valorRenovacao($tenantId, $matriculaId);
        if ($novo === null || $novo <= 0) {
            return 0;
        }
        $m = DB::table('matriculas as m')->join('status_matricula as s', 's.id', '=', 'm.status_id')
            ->where('m.id', $matriculaId)->where('m.tenant_id', $tenantId)
            ->first(['m.valor', 'm.data_vencimento', 'm.proxima_data_vencimento', 's.codigo']);
        $acessoAte = $m ? substr((string) ($m->data_vencimento ?? $m->proxima_data_vencimento ?? ''), 0, 10) : '';
        if (! $m || ! ($m->codigo === 'vencida' || ($m->codigo === 'ativa' && $acessoAte !== '' && $acessoAte <= now()->toDateString()))) {
            return 0;
        }

        $anterior = (int) round((float) $m->valor * 100);
        $temOriginal = Schema::hasColumn('pagamentos_plano', 'valor_original');
        $parcelas = DB::table('pagamentos_plano')->where('tenant_id', $tenantId)->where('matricula_id', $matriculaId)
            ->whereIn('status_pagamento_id', [1, 3])->whereNull('data_pagamento')
            ->whereRaw('ROUND('.($temOriginal ? 'COALESCE(valor_original, valor)' : 'valor').' * 100) = ?', [$anterior])
            ->where(fn ($q) => $q->whereNull('observacoes')
                ->orWhere('observacoes', 'not like', '%'.MatriculaMigracaoService::TAG_MIGRACAO_SIMULADA.'%'))
            ->get(['id', 'desconto']);
        foreach ($parcelas as $p) {
            DB::table('pagamentos_plano')->where('id', $p->id)->update(array_merge(
                ['valor' => round(max(0, $novo - (float) ($p->desconto ?? 0)), 2), 'updated_at' => now()],
                $temOriginal ? ['valor_original' => $novo] : [],
            ));
        }
        if ($parcelas->isNotEmpty()) {
            if (Schema::hasTable('pagamentos_pix')) {
                DB::table('pagamentos_pix')->where('tenant_id', $tenantId)->where('matricula_id', $matriculaId)
                    ->whereNotIn('status', ['approved', 'cancelled', 'canceled', 'rejected', 'refunded'])
                    ->update(['expires_at' => now()]);
            }
            // O link no preço velho sai; a referência fica para a cobrança nova cancelar a antiga no provedor
            DB::table('assinaturas')->where('tenant_id', $tenantId)->where('matricula_id', $matriculaId)
                ->where(fn ($q) => $q->whereNull('status_gateway')->orWhere('status_gateway', '<>', 'approved'))
                ->update(['payment_url' => null, 'valor' => $novo, 'atualizado_em' => now()]);
        }
        $this->iniciarCicloNovo($tenantId, $matriculaId);

        return $parcelas->count();
    }

    /** Preço do próximo ciclo da matrícula, quando há reajuste pendente. */
    public function valorRenovacao(int $tenantId, int $matriculaId): ?float
    {
        if (! $this->disponivel()) {
            return null;
        }
        $v = DB::table('matriculas')->where('id', $matriculaId)->where('tenant_id', $tenantId)
            ->when(Schema::hasColumn('matriculas', 'reajuste_autorizado_em'), fn ($q) => $q->whereNotNull('reajuste_autorizado_em'))
            ->value('valor_renovacao');

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
            ->update(['valor' => $novo, 'valor_renovacao' => null, 'updated_at' => now(),
                ...(Schema::hasColumn('matriculas', 'reajuste_autorizado_em') ? ['reajuste_autorizado_em' => null] : []),
            ]);

        return $novo;
    }
}
