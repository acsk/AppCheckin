<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class RecordePessoalRepository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listarDefinicoes(
        int $tenantId,
        bool $apenasAtivas = true,
        ?int $modalidadeId = null,
        ?string $categoria = null,
    ): array {
        $query = DB::table('recorde_definicoes as rd')
            ->leftJoin('modalidades as m', 'm.id', '=', 'rd.modalidade_id')
            ->where('rd.tenant_id', $tenantId)
            ->orderBy('rd.ordem')
            ->orderBy('rd.nome')
            ->select(['rd.*', 'm.nome as modalidade_nome']);

        if ($apenasAtivas) {
            $query->where('rd.ativo', 1);
        }
        if ($modalidadeId) {
            $query->where('rd.modalidade_id', $modalidadeId);
        }
        if ($categoria) {
            $query->where('rd.categoria', $categoria);
        }

        $definicoes = $query->get()->map(fn ($row) => (array) $row)->all();

        if ($definicoes === []) {
            return [];
        }

        $ids = array_column($definicoes, 'id');
        $metricas = DB::table('recorde_definicao_metricas')
            ->whereIn('definicao_id', $ids)
            ->orderBy('ordem_comparacao')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        $metricasPorDef = [];
        foreach ($metricas as $m) {
            $metricasPorDef[$m['definicao_id']][] = $m;
        }

        foreach ($definicoes as &$def) {
            $def['metricas'] = $metricasPorDef[$def['id']] ?? [];
        }

        return $definicoes;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buscarDefinicao(int $id, int $tenantId): ?array
    {
        $row = DB::table('recorde_definicoes as rd')
            ->leftJoin('modalidades as m', 'm.id', '=', 'rd.modalidade_id')
            ->where('rd.id', $id)
            ->where('rd.tenant_id', $tenantId)
            ->first(['rd.*', 'm.nome as modalidade_nome']);

        if (! $row) {
            return null;
        }

        $def = (array) $row;
        $def['metricas'] = DB::table('recorde_definicao_metricas')
            ->where('definicao_id', $def['id'])
            ->orderBy('ordem_comparacao')
            ->get()
            ->map(fn ($m) => (array) $m)
            ->all();

        return $def;
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public function criarDefinicao(array $dados): int
    {
        return DB::transaction(function () use ($dados) {
            $defId = (int) DB::table('recorde_definicoes')->insertGetId([
                'tenant_id' => $dados['tenant_id'],
                'modalidade_id' => $dados['modalidade_id'] ?? null,
                'nome' => $dados['nome'],
                'categoria' => $dados['categoria'] ?? 'movimento',
                'descricao' => $dados['descricao'] ?? null,
                'ordem' => $dados['ordem'] ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (! empty($dados['metricas'])) {
                $this->salvarMetricas($defId, $dados['metricas']);
            }

            return $defId;
        });
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public function atualizarDefinicao(int $id, int $tenantId, array $dados): bool
    {
        return DB::transaction(function () use ($id, $tenantId, $dados) {
            DB::table('recorde_definicoes')
                ->where('id', $id)
                ->where('tenant_id', $tenantId)
                ->update([
                    'modalidade_id' => $dados['modalidade_id'] ?? null,
                    'nome' => $dados['nome'],
                    'categoria' => $dados['categoria'] ?? 'movimento',
                    'descricao' => $dados['descricao'] ?? null,
                    'ordem' => $dados['ordem'] ?? 0,
                    'ativo' => $dados['ativo'] ?? 1,
                    'updated_at' => now(),
                ]);

            if (isset($dados['metricas'])) {
                DB::table('recorde_definicao_metricas')->where('definicao_id', $id)->delete();
                $this->salvarMetricas($id, $dados['metricas']);
            }

            return true;
        });
    }

    public function desativarDefinicao(int $id, int $tenantId): bool
    {
        return DB::table('recorde_definicoes')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update(['ativo' => 0, 'updated_at' => now()]) > 0;
    }

    /**
     * @param  list<array<string, mixed>>  $metricas
     */
    private function salvarMetricas(int $definicaoId, array $metricas): void
    {
        foreach ($metricas as $i => $m) {
            DB::table('recorde_definicao_metricas')->insert([
                'definicao_id' => $definicaoId,
                'codigo' => $m['codigo'],
                'nome' => $m['nome'],
                'tipo_valor' => $m['tipo_valor'] ?? 'decimal',
                'unidade' => $m['unidade'] ?? null,
                'ordem_comparacao' => $m['ordem_comparacao'] ?? ($i + 1),
                'direcao' => $m['direcao'],
                'obrigatoria' => $m['obrigatoria'] ?? 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarPorAluno(int $tenantId, int $alunoId, ?int $definicaoId = null): array
    {
        $query = DB::table('recordes as r')
            ->join('recorde_definicoes as rd', 'rd.id', '=', 'r.definicao_id')
            ->leftJoin('modalidades as m', 'm.id', '=', 'rd.modalidade_id')
            ->leftJoin('usuarios as u', 'u.id', '=', 'r.registrado_por')
            ->where('r.tenant_id', $tenantId)
            ->where('r.aluno_id', $alunoId)
            ->where('r.origem', 'aluno')
            ->where('r.valido', 1)
            ->orderBy('rd.ordem')
            ->orderByDesc('r.data_recorde')
            ->select([
                'r.*',
                'rd.nome as definicao_nome',
                'rd.categoria',
                'm.nome as modalidade_nome',
                'u.nome as registrado_por_nome',
            ]);

        if ($definicaoId) {
            $query->where('r.definicao_id', $definicaoId);
        }

        return $this->carregarValoresRecordes(
            $query->get()->map(fn ($row) => (array) $row)->all()
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarRecordesAcademia(int $tenantId, ?int $definicaoId = null, ?int $modalidadeId = null): array
    {
        $query = DB::table('recordes as r')
            ->join('recorde_definicoes as rd', 'rd.id', '=', 'r.definicao_id')
            ->leftJoin('modalidades as m', 'm.id', '=', 'rd.modalidade_id')
            ->leftJoin('alunos as a', 'a.id', '=', 'r.aluno_id')
            ->leftJoin('usuarios as u', 'u.id', '=', 'r.registrado_por')
            ->where('r.tenant_id', $tenantId)
            ->where('r.origem', 'academia')
            ->where('r.valido', 1)
            ->orderBy('rd.ordem')
            ->orderByDesc('r.data_recorde')
            ->select([
                'r.*',
                'rd.nome as definicao_nome',
                'rd.categoria',
                'rd.modalidade_id',
                'm.nome as modalidade_nome',
                'a.nome as aluno_nome',
                'u.nome as registrado_por_nome',
            ]);

        if ($definicaoId) {
            $query->where('r.definicao_id', $definicaoId);
        }
        if ($modalidadeId) {
            $query->where('rd.modalidade_id', $modalidadeId);
        }

        return $this->carregarValoresRecordes(
            $query->get()->map(fn ($row) => (array) $row)->all()
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rankingPorDefinicao(int $tenantId, int $definicaoId, int $limit = 50): array
    {
        $metricaPrincipal = DB::table('recorde_definicao_metricas')
            ->where('definicao_id', $definicaoId)
            ->orderBy('ordem_comparacao')
            ->first();

        if (! $metricaPrincipal) {
            return [];
        }

        $metricaPrincipal = (array) $metricaPrincipal;
        $campoValor = $this->campoValorPorTipo($metricaPrincipal['tipo_valor']);
        $aggFunc = $metricaPrincipal['direcao'] === 'menor_melhor' ? 'MIN' : 'MAX';
        $orderDir = $metricaPrincipal['direcao'] === 'menor_melhor' ? 'ASC' : 'DESC';

        $ranking = DB::select("
            SELECT r.aluno_id, a.nome as aluno_nome,
                   {$aggFunc}(rv.{$campoValor}) as melhor_valor,
                   MAX(r.data_recorde) as data_recorde
            FROM recordes r
            INNER JOIN recorde_valores rv ON rv.recorde_id = r.id AND rv.metrica_id = ?
            INNER JOIN alunos a ON a.id = r.aluno_id
            WHERE r.tenant_id = ? AND r.definicao_id = ? AND r.origem = 'aluno' AND r.valido = 1
            GROUP BY r.aluno_id, a.nome
            ORDER BY melhor_valor {$orderDir}
            LIMIT ?
        ", [$metricaPrincipal['id'], $tenantId, $definicaoId, $limit]);

        $result = array_map(fn ($row) => (array) $row, $ranking);

        foreach ($result as &$item) {
            $item['metrica_codigo'] = $metricaPrincipal['codigo'];
            $item['metrica_nome'] = $metricaPrincipal['nome'];
            $item['metrica_unidade'] = $metricaPrincipal['unidade'];
            $item['metrica_direcao'] = $metricaPrincipal['direcao'];
            $item['metrica_tipo_valor'] = $metricaPrincipal['tipo_valor'];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public function criar(array $dados): int
    {
        return DB::transaction(function () use ($dados) {
            $recordeId = (int) DB::table('recordes')->insertGetId([
                'tenant_id' => $dados['tenant_id'],
                'aluno_id' => $dados['aluno_id'] ?? null,
                'definicao_id' => $dados['definicao_id'],
                'origem' => $dados['origem'] ?? 'aluno',
                'data_recorde' => $dados['data_recorde'],
                'observacoes' => $dados['observacoes'] ?? null,
                'registrado_por' => $dados['registrado_por'] ?? null,
                'valido' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (! empty($dados['valores'])) {
                $this->salvarValores($recordeId, $dados['valores']);
            }

            return $recordeId;
        });
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public function atualizar(int $id, int $tenantId, array $dados): bool
    {
        return DB::transaction(function () use ($id, $tenantId, $dados) {
            DB::table('recordes')
                ->where('id', $id)
                ->where('tenant_id', $tenantId)
                ->update([
                    'definicao_id' => $dados['definicao_id'],
                    'data_recorde' => $dados['data_recorde'],
                    'observacoes' => $dados['observacoes'] ?? null,
                    'updated_at' => now(),
                ]);

            if (isset($dados['valores'])) {
                DB::table('recorde_valores')->where('recorde_id', $id)->delete();
                $this->salvarValores($id, $dados['valores']);
            }

            return true;
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buscar(int $id, int $tenantId): ?array
    {
        $row = DB::table('recordes as r')
            ->join('recorde_definicoes as rd', 'rd.id', '=', 'r.definicao_id')
            ->leftJoin('modalidades as m', 'm.id', '=', 'rd.modalidade_id')
            ->leftJoin('alunos as a', 'a.id', '=', 'r.aluno_id')
            ->where('r.id', $id)
            ->where('r.tenant_id', $tenantId)
            ->first([
                'r.*',
                'rd.nome as definicao_nome',
                'rd.categoria',
                'rd.modalidade_id',
                'm.nome as modalidade_nome',
                'a.nome as aluno_nome',
            ]);

        if (! $row) {
            return null;
        }

        $recordes = $this->carregarValoresRecordes([(array) $row]);

        return $recordes[0];
    }

    public function excluir(int $id, int $tenantId): bool
    {
        return DB::table('recordes')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->delete() > 0;
    }

    /**
     * @param  list<array<string, mixed>>  $valores
     */
    private function salvarValores(int $recordeId, array $valores): void
    {
        foreach ($valores as $v) {
            DB::table('recorde_valores')->insert([
                'recorde_id' => $recordeId,
                'metrica_id' => $v['metrica_id'],
                'valor_int' => $v['valor_int'] ?? null,
                'valor_decimal' => $v['valor_decimal'] ?? null,
                'valor_tempo_ms' => $v['valor_tempo_ms'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $recordes
     * @return list<array<string, mixed>>
     */
    private function carregarValoresRecordes(array $recordes): array
    {
        if ($recordes === []) {
            return [];
        }

        $ids = array_column($recordes, 'id');
        $valores = DB::table('recorde_valores as rv')
            ->join('recorde_definicao_metricas as rdm', 'rdm.id', '=', 'rv.metrica_id')
            ->whereIn('rv.recorde_id', $ids)
            ->orderBy('rdm.ordem_comparacao')
            ->get([
                'rv.*',
                'rdm.codigo',
                'rdm.nome as metrica_nome',
                'rdm.tipo_valor',
                'rdm.unidade',
                'rdm.direcao',
                'rdm.ordem_comparacao',
            ])
            ->map(fn ($row) => (array) $row)
            ->all();

        $valoresPorRecorde = [];
        foreach ($valores as $v) {
            $valoresPorRecorde[$v['recorde_id']][] = $v;
        }

        foreach ($recordes as &$r) {
            $r['valores'] = $valoresPorRecorde[$r['id']] ?? [];
        }

        return $recordes;
    }

    private function campoValorPorTipo(string $tipoValor): string
    {
        return match ($tipoValor) {
            'inteiro' => 'valor_int',
            'tempo_ms' => 'valor_tempo_ms',
            default => 'valor_decimal',
        };
    }
}
