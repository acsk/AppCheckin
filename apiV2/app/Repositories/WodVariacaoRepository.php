<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class WodVariacaoRepository
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): int
    {
        return (int) DB::table('wod_variacoes')->insertGetId([
            'wod_id' => $data['wod_id'],
            'nome' => $data['nome'],
            'descricao' => $data['descricao'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listByWod(int $wodId): array
    {
        return DB::table('wod_variacoes')
            ->where('wod_id', $wodId)
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $row = DB::table('wod_variacoes')->where('id', $id)->first();

        return $row ? (array) $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByNome(int $wodId, string $nome): ?array
    {
        $row = DB::table('wod_variacoes')
            ->where('wod_id', $wodId)
            ->where('nome', $nome)
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        foreach (['nome', 'descricao'] as $key) {
            if (array_key_exists($key, $data)) {
                $fields[$key] = $data[$key];
            }
        }

        if ($fields === []) {
            return false;
        }

        $fields['updated_at'] = now();

        return DB::table('wod_variacoes')->where('id', $id)->update($fields) >= 0;
    }

    public function delete(int $id): bool
    {
        return DB::table('wod_variacoes')->where('id', $id)->delete() > 0;
    }

    public function deleteByWod(int $wodId): bool
    {
        return DB::table('wod_variacoes')->where('wod_id', $wodId)->delete() >= 0;
    }
}
