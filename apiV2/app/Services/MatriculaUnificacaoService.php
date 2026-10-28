<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Uma matrícula por aluno e modalidade. Junta na matrícula atual os ciclos que viraram linhas
 * separadas (encadeamento antigo): pagamentos, assinaturas, descontos e histórico passam para ela,
 * a linha antiga sai de `matriculas` e fica registrada em `matriculas_unificadas`.
 *
 * Matrículas de pacote (uma por contrato) ficam de fora.
 */
class MatriculaUnificacaoService
{
    /** Tabelas cujo matricula_id só precisa apontar para a matrícula que fica. */
    private const MOVER = [
        'pagamentos_plano' => 'matricula_id',
        'pagamentos_mercadopago' => 'matricula_id',
        'pagamentos_pix' => 'matricula_id',
        'assinaturas_mercadopago' => 'matricula_id',
        'contratos_recorrencia' => 'matricula_id',
        'matricula_descontos' => 'matricula_id',
        'matriculas_historico' => 'matricula_id',
        'pacote_beneficiarios' => 'matricula_id',
        'creditos_aluno' => 'matricula_origem_id',
        'prospeccao_oportunidades' => 'matricula_conversao_id',
    ];

    /** @var array<string, bool> */
    private array $colunas = [];

    /**
     * Grupos com mais de uma matrícula na mesma modalidade.
     *
     * @return list<array{tenant_id: int, aluno_id: int, modalidade_id: int, matriculas: list<array<string, mixed>>}>
     */
    public function grupos(?int $tenantId = null, ?int $alunoId = null): array
    {
        $query = DB::table('matriculas as m')
            ->join('planos as p', 'p.id', '=', 'm.plano_id')
            ->join('status_matricula as s', 's.id', '=', 'm.status_id')
            ->whereNotNull('p.modalidade_id')
            ->orderBy('m.tenant_id')->orderBy('m.aluno_id')->orderBy('p.modalidade_id')->orderBy('m.id');
        if ($this->temColuna('matriculas', 'pacote_contrato_id')) {
            $query->whereNull('m.pacote_contrato_id');
        }
        if ($tenantId) {
            $query->where('m.tenant_id', $tenantId);
        }
        if ($alunoId) {
            $query->where('m.aluno_id', $alunoId);
        }

        $grupos = [];
        foreach ($query->get(['m.id', 'm.tenant_id', 'm.aluno_id', 'p.modalidade_id', 'm.plano_id', 'm.data_matricula', 'm.data_inicio', 's.codigo as status']) as $m) {
            $chave = "{$m->tenant_id}-{$m->aluno_id}-{$m->modalidade_id}";
            $grupos[$chave] ??= ['tenant_id' => (int) $m->tenant_id, 'aluno_id' => (int) $m->aluno_id, 'modalidade_id' => (int) $m->modalidade_id, 'matriculas' => []];
            $grupos[$chave]['matriculas'][] = (array) $m;
        }

        return array_values(array_filter($grupos, fn (array $g) => count($g['matriculas']) > 1));
    }

    /**
     * A que fica: a única ativa/pendente do grupo, ou a mais recente se todas estão encerradas.
     * Duas ou mais em aberto: null (alguém precisa decidir).
     *
     * @param  list<array<string, mixed>>  $matriculas
     */
    public function atual(array $matriculas): ?int
    {
        $abertas = array_values(array_filter($matriculas, fn (array $m) => in_array($m['status'], ['ativa', 'pendente'], true)));
        if (count($abertas) > 1) {
            return null;
        }

        return (int) ($abertas[0]['id'] ?? max(array_column($matriculas, 'id')));
    }

    /**
     * @param  array{tenant_id: int, aluno_id: int, modalidade_id: int, matriculas: list<array<string, mixed>>}  $grupo
     * @return array{atual: ?int, removidas: list<int>, linhas_movidas: int, conflito: ?string}
     */
    public function unificar(array $grupo, bool $simular = true): array
    {
        $atual = $this->atual($grupo['matriculas']);
        if ($atual === null) {
            return ['atual' => null, 'removidas' => [], 'linhas_movidas' => 0, 'conflito' => 'mais de uma matrícula ativa ou pendente'];
        }

        $antigas = array_values(array_filter(array_map(fn (array $m) => (int) $m['id'], $grupo['matriculas']), fn (int $id) => $id !== $atual));
        $resultado = ['atual' => $atual, 'removidas' => $antigas, 'linhas_movidas' => $this->contarReferencias($antigas), 'conflito' => null];
        if ($simular) {
            return $resultado;
        }

        DB::transaction(function () use ($grupo, $atual, $antigas) {
            foreach (self::MOVER as $tabela => $coluna) {
                if ($this->temColuna($tabela, $coluna)) {
                    DB::table($tabela)->whereIn($coluna, $antigas)->update([$coluna => $atual]);
                }
            }
            // Únicos por matrícula: só passam se a atual ainda não tem; senão ficam com o vínculo da antiga (SET NULL / cascade)
            if ($this->temColuna('assinaturas', 'matricula_id') && ! DB::table('assinaturas')->where('matricula_id', $atual)->exists()) {
                $id = DB::table('assinaturas')->whereIn('matricula_id', $antigas)->orderByDesc('id')->value('id');
                if ($id) {
                    DB::table('assinaturas')->where('id', $id)->update(['matricula_id' => $atual]);
                }
            }
            if ($this->temColuna('prospeccao_oportunidades', 'matricula_referencia_id')
                && ! DB::table('prospeccao_oportunidades')->where('tenant_id', $grupo['tenant_id'])->where('matricula_referencia_id', $atual)->exists()) {
                $id = DB::table('prospeccao_oportunidades')->whereIn('matricula_referencia_id', $antigas)->orderByDesc('id')->value('id');
                if ($id) {
                    DB::table('prospeccao_oportunidades')->where('id', $id)->update(['matricula_referencia_id' => $atual]);
                }
            }
            if ($this->temColuna('matriculas_unificadas', 'matricula_id')) {
                DB::table('matriculas_unificadas')->whereIn('matricula_id', $antigas)->update(['matricula_id' => $atual]);
                foreach (DB::table('matriculas')->whereIn('id', $antigas)->get() as $linha) {
                    DB::table('matriculas_unificadas')->insert([
                        'tenant_id' => $grupo['tenant_id'],
                        'matricula_antiga_id' => (int) $linha->id,
                        'matricula_id' => $atual,
                        'dados' => json_encode($linha, JSON_UNESCAPED_UNICODE),
                        'created_at' => now(),
                    ]);
                }
            }

            // "Aluno desde": a matrícula que fica herda a data da primeira
            $update = ['matricula_anterior_id' => null, 'updated_at' => now()];
            $datas = array_filter(array_column($grupo['matriculas'], 'data_matricula'));
            if ($datas !== []) {
                $update['data_matricula'] = min($datas);
            }
            DB::table('matriculas')->whereIn('matricula_anterior_id', $antigas)->update(['matricula_anterior_id' => null]);
            DB::table('matriculas')->where('id', $atual)->update($update);
            DB::table('matriculas')->whereIn('id', $antigas)->delete();
        });

        return $resultado;
    }

    /** Id que vale para uma matrícula que pode ter sido unificada (MAT-{id} de cobranças antigas). */
    public function idAtual(int $matriculaId): int
    {
        if ($matriculaId <= 0 || ! $this->temColuna('matriculas_unificadas', 'matricula_id')) {
            return $matriculaId;
        }

        return (int) (DB::table('matriculas_unificadas')->where('matricula_antiga_id', $matriculaId)->value('matricula_id') ?? $matriculaId);
    }

    /** @param  list<int>  $ids */
    private function contarReferencias(array $ids): int
    {
        $total = 0;
        foreach (self::MOVER + ['assinaturas' => 'matricula_id'] as $tabela => $coluna) {
            if ($this->temColuna($tabela, $coluna)) {
                $total += DB::table($tabela)->whereIn($coluna, $ids)->count();
            }
        }

        return $total;
    }

    private function temColuna(string $tabela, string $coluna): bool
    {
        return $this->colunas["{$tabela}.{$coluna}"] ??= Schema::hasTable($tabela) && Schema::hasColumn($tabela, $coluna);
    }
}
