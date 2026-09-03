<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class WodResultadoRepository
{
    public const TIPO_TIME = 'time';
    public const TIPO_REPS = 'reps';
    public const TIPO_WEIGHT = 'weight';
    public const TIPO_ROUNDS_REPS = 'rounds_reps';
    public const TIPO_DISTANCE = 'distance';
    public const TIPO_CALORIES = 'calories';
    public const TIPO_POINTS = 'points';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ?int
    {
        $tipoScore = $data['tipo_score'] ?? null;

        $peso = ($tipoScore === self::TIPO_WEIGHT) ? ($data['valor_num'] ?? null) : null;
        $repeticoes = ($tipoScore === self::TIPO_REPS || $tipoScore === self::TIPO_ROUNDS_REPS)
            ? ($data['valor_num'] ?? null)
            : null;
        $tempoTotal = ($tipoScore === self::TIPO_TIME) ? ($data['valor_texto'] ?? null) : null;

        $resultadoTexto = null;
        if (in_array($tipoScore, [self::TIPO_ROUNDS_REPS, self::TIPO_POINTS, self::TIPO_DISTANCE, self::TIPO_CALORIES], true)) {
            $resultadoTexto = $data['valor_texto'] ?? ($data['valor_num'] ?? null);
        } elseif ($tipoScore === null) {
            $resultadoTexto = $data['valor_texto'] ?? null;
        }

        return (int) DB::table('wod_resultados')->insertGetId([
            'tenant_id' => $data['tenant_id'],
            'wod_id' => $data['wod_id'],
            'usuario_id' => $data['usuario_id'],
            'variacao_id' => $data['variacao_id'] ?? null,
            'resultado' => $resultadoTexto,
            'tempo_total' => $tempoTotal,
            'repeticoes' => $repeticoes,
            'peso' => $peso,
            'nota' => $data['observacao'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listByWod(int $wodId, int $tenantId): array
    {
        return DB::table('wod_resultados as wr')
            ->leftJoin('usuarios as u', 'wr.usuario_id', '=', 'u.id')
            ->leftJoin('wod_variacoes as wv', 'wr.variacao_id', '=', 'wv.id')
            ->where('wr.wod_id', $wodId)
            ->where('wr.tenant_id', $tenantId)
            ->orderByRaw('wr.peso IS NULL, CAST(wr.peso AS DECIMAL(10,2)) DESC, wr.created_at DESC')
            ->get([
                'wr.*',
                'u.nome as usuario_nome',
                'wv.nome as variacao_nome',
            ])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByUsuarioWod(int $usuarioId, int $wodId): ?array
    {
        $row = DB::table('wod_resultados as wr')
            ->leftJoin('usuarios as u', 'wr.usuario_id', '=', 'u.id')
            ->leftJoin('wod_variacoes as wv', 'wr.variacao_id', '=', 'wv.id')
            ->where('wr.usuario_id', $usuarioId)
            ->where('wr.wod_id', $wodId)
            ->first([
                'wr.*',
                'u.nome as usuario_nome',
                'wv.nome as variacao_nome',
            ]);

        return $row ? (array) $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $row = DB::table('wod_resultados as wr')
            ->leftJoin('usuarios as u', 'wr.usuario_id', '=', 'u.id')
            ->leftJoin('wod_variacoes as wv', 'wr.variacao_id', '=', 'wv.id')
            ->where('wr.id', $id)
            ->first([
                'wr.*',
                'u.nome as usuario_nome',
                'wv.nome as variacao_nome',
            ]);

        return $row ? (array) $row : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [];

        if (isset($data['resultado'])) {
            $fields['resultado'] = $data['resultado'];
        }
        if (isset($data['tempo_total'])) {
            $fields['tempo_total'] = $data['tempo_total'];
        }
        if (isset($data['repeticoes'])) {
            $fields['repeticoes'] = $data['repeticoes'];
        }
        if (isset($data['peso'])) {
            $fields['peso'] = $data['peso'];
        }
        if (isset($data['observacao'])) {
            $fields['nota'] = $data['observacao'];
        }
        if (isset($data['valor_num']) && ! isset($fields['peso']) && ! isset($fields['repeticoes'])) {
            $fields['peso'] = $data['valor_num'];
        }
        if (isset($data['valor_texto']) && ! isset($fields['resultado'])) {
            $fields['resultado'] = $data['valor_texto'];
        }
        if (isset($data['variacao_id'])) {
            $fields['variacao_id'] = $data['variacao_id'];
        }

        if ($fields === []) {
            return false;
        }

        $fields['updated_at'] = now();

        return DB::table('wod_resultados')->where('id', $id)->update($fields) >= 0;
    }

    public function delete(int $id): bool
    {
        return DB::table('wod_resultados')->where('id', $id)->delete() > 0;
    }
}
