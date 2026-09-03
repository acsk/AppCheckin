<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class WodBlocoRepository
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): int
    {
        return (int) DB::table('wod_blocos')->insertGetId([
            'wod_id' => $data['wod_id'],
            'ordem' => $data['ordem'] ?? 1,
            'tipo' => $data['tipo'],
            'titulo' => $data['titulo'] ?? null,
            'conteudo' => $data['conteudo'],
            'tempo_cap' => $data['tempo_cap'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listByWod(int $wodId): array
    {
        return DB::table('wod_blocos')
            ->where('wod_id', $wodId)
            ->orderBy('ordem')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $row = DB::table('wod_blocos')->where('id', $id)->first();

        return $row ? (array) $row : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        foreach (['titulo', 'conteudo', 'tipo', 'ordem', 'tempo_cap'] as $key) {
            if (array_key_exists($key, $data)) {
                $fields[$key] = $data[$key];
            }
        }

        if ($fields === []) {
            return false;
        }

        $fields['updated_at'] = now();

        return DB::table('wod_blocos')->where('id', $id)->update($fields) >= 0;
    }

    public function delete(int $id): bool
    {
        return DB::table('wod_blocos')->where('id', $id)->delete() > 0;
    }
}
