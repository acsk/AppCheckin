<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class AdminPlanoCicloRepository
{
    public function listarFrequencias(): array
    {
        return DB::table('assinatura_frequencias')
            ->where('ativo', 1)
            ->orderBy('ordem')
            ->get(['id', 'nome', 'codigo', 'meses', 'ordem'])
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'nome' => $r->nome,
                'codigo' => $r->codigo,
                'meses' => (int) $r->meses,
                'ordem' => (int) $r->ordem,
            ])
            ->all();
    }

    public function findFrequencia(int $id): ?array
    {
        $row = DB::table('assinatura_frequencias')
            ->where('id', $id)
            ->where('ativo', 1)
            ->first(['id', 'meses', 'nome', 'codigo']);

        return $row ? (array) $row : null;
    }

    public function listarTodasFrequenciasAtivas(): array
    {
        return DB::table('assinatura_frequencias')
            ->where('ativo', 1)
            ->orderBy('ordem')
            ->get(['id', 'nome', 'codigo', 'meses', 'ordem'])
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    public function listarPorPlano(int $planoId, int $tenantId, ?int $filtroAtivo = null): array
    {
        $query = DB::table('plano_ciclos as pc')
            ->join('assinatura_frequencias as af', 'af.id', '=', 'pc.assinatura_frequencia_id')
            ->where('pc.plano_id', $planoId)
            ->where('pc.tenant_id', $tenantId)
            ->orderBy('af.ordem')
            ->select([
                'pc.id',
                'af.nome',
                'af.codigo',
                'pc.meses',
                'pc.valor',
                'pc.valor_mensal_equivalente',
                'pc.desconto_percentual',
                'pc.permite_recorrencia',
                'pc.permite_reposicao',
                'pc.ativo',
                'af.ordem',
                'pc.assinatura_frequencia_id',
            ]);

        if ($filtroAtivo !== null) {
            $query->where('pc.ativo', $filtroAtivo);
        }

        return $query->get()->map(fn ($r) => (array) $r)->all();
    }

    public function findCiclo(int $cicloId, int $planoId, int $tenantId): ?array
    {
        $row = DB::table('plano_ciclos as pc')
            ->join('planos as p', 'p.id', '=', 'pc.plano_id')
            ->join('assinatura_frequencias as af', 'af.id', '=', 'pc.assinatura_frequencia_id')
            ->where('pc.id', $cicloId)
            ->where('pc.plano_id', $planoId)
            ->where('pc.tenant_id', $tenantId)
            ->select('pc.*', 'p.valor as plano_valor', 'af.meses as tipo_meses')
            ->first();

        return $row ? (array) $row : null;
    }

    public function existeMesmaFrequenciaRecorrencia(
        int $planoId,
        int $frequenciaId,
        int $permiteRecorrencia,
    ): bool {
        return DB::table('plano_ciclos')
            ->where('plano_id', $planoId)
            ->where('assinatura_frequencia_id', $frequenciaId)
            ->where('permite_recorrencia', $permiteRecorrencia)
            ->exists();
    }

    public function create(array $data): int
    {
        return (int) DB::table('plano_ciclos')->insertGetId([
            'tenant_id' => $data['tenant_id'],
            'plano_id' => $data['plano_id'],
            'assinatura_frequencia_id' => $data['assinatura_frequencia_id'],
            'meses' => $data['meses'],
            'valor' => $data['valor'],
            'desconto_percentual' => $data['desconto_percentual'],
            'permite_recorrencia' => $data['permite_recorrencia'],
            'permite_reposicao' => $data['permite_reposicao'],
            'ativo' => $data['ativo'],
        ]);
    }

    public function update(int $cicloId, int $tenantId, array $data): void
    {
        DB::table('plano_ciclos')
            ->where('id', $cicloId)
            ->where('tenant_id', $tenantId)
            ->update([
                'valor' => $data['valor'],
                'desconto_percentual' => $data['desconto_percentual'],
                'permite_recorrencia' => $data['permite_recorrencia'],
                'permite_reposicao' => $data['permite_reposicao'],
                'ativo' => $data['ativo'],
                'updated_at' => now(),
            ]);
    }

    public function countMatriculas(int $cicloId): int
    {
        return (int) DB::table('matriculas')->where('plano_ciclo_id', $cicloId)->count();
    }

    public function delete(int $cicloId): void
    {
        DB::table('plano_ciclos')->where('id', $cicloId)->delete();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function ciclosExistentesComMatriculas(int $planoId, int $tenantId): array
    {
        return DB::table('plano_ciclos as pc')
            ->where('pc.plano_id', $planoId)
            ->where('pc.tenant_id', $tenantId)
            ->select([
                'pc.id',
                'pc.assinatura_frequencia_id',
                'pc.valor',
                'pc.permite_reposicao',
                DB::raw('(SELECT COUNT(*) FROM matriculas m WHERE m.plano_ciclo_id = pc.id) as total_matriculas'),
            ])
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    public function updateGerado(int $cicloId, float $valor, float $desconto, int $meses, ?int $permiteReposicao): void
    {
        $update = [
            'valor' => $valor,
            'desconto_percentual' => $desconto,
            'meses' => $meses,
            'updated_at' => now(),
        ];
        if ($permiteReposicao !== null) {
            $update['permite_reposicao'] = $permiteReposicao;
        }

        DB::table('plano_ciclos')->where('id', $cicloId)->update($update);
    }
}
