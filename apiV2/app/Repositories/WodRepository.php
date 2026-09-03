<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class WodRepository
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    /**
     * @param  bool  $allowGenericFallback  Se false, não busca WOD genérico (modalidade_id null).
     */
    public function findPublishedForDate(
        int $tenantId,
        string $data,
        ?int $modalidadeId,
        bool $allowGenericFallback = true,
    ): ?array {
        if ($modalidadeId) {
            $row = DB::table('wods')
                ->where('tenant_id', $tenantId)
                ->whereRaw('DATE(data) = ?', [$data])
                ->where('status', 'published')
                ->where('modalidade_id', $modalidadeId)
                ->first();

            if ($row) {
                return (array) $row;
            }

            if (! $allowGenericFallback) {
                return null;
            }
        }

        if (! $allowGenericFallback) {
            return null;
        }

        $row = DB::table('wods')
            ->where('tenant_id', $tenantId)
            ->whereRaw('DATE(data) = ?', [$data])
            ->where('status', 'published')
            ->whereNull('modalidade_id')
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPublishedForDate(int $tenantId, string $data): array
    {
        return DB::table('wods as w')
            ->leftJoin('modalidades as m', 'w.modalidade_id', '=', 'm.id')
            ->where('w.tenant_id', $tenantId)
            ->whereRaw('DATE(w.data) = ?', [$data])
            ->where('w.status', 'published')
            ->orderBy('w.modalidade_id')
            ->orderBy('w.created_at')
            ->get([
                'w.*',
                'm.id as modalidade_id_obj',
                'm.nome as modalidade_nome',
                'm.descricao as modalidade_descricao',
                'm.cor as modalidade_cor',
                'm.icone as modalidade_icone',
                'm.ativo as modalidade_ativo',
            ])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listBlocosByWod(int $wodId): array
    {
        return DB::table('wod_blocos')
            ->where('wod_id', $wodId)
            ->orderBy('ordem')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listVariacoesByWod(int $wodId): array
    {
        return DB::table('wod_variacoes')
            ->where('wod_id', $wodId)
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, int $tenantId): int
    {
        return (int) DB::table('wods')->insertGetId([
            'tenant_id' => $tenantId,
            'modalidade_id' => $data['modalidade_id'] ?? null,
            'data' => $data['data'],
            'titulo' => $data['titulo'],
            'descricao' => $data['descricao'] ?? null,
            'status' => $data['status'] ?? self::STATUS_DRAFT,
            'criado_por' => $data['criado_por'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id, int $tenantId): ?array
    {
        $row = DB::table('wods as w')
            ->leftJoin('usuarios as u', 'w.criado_por', '=', 'u.id')
            ->leftJoin('modalidades as m', 'w.modalidade_id', '=', 'm.id')
            ->where('w.id', $id)
            ->where('w.tenant_id', $tenantId)
            ->first([
                'w.*',
                'u.nome as criado_por_nome',
                'm.nome as modalidade_nome',
            ]);

        return $row ? (array) $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByDataModalidade(string $data, int $modalidadeId, int $tenantId): ?array
    {
        $row = DB::table('wods as w')
            ->leftJoin('usuarios as u', 'w.criado_por', '=', 'u.id')
            ->leftJoin('modalidades as m', 'w.modalidade_id', '=', 'm.id')
            ->where('w.data', $data)
            ->where('w.modalidade_id', $modalidadeId)
            ->where('w.tenant_id', $tenantId)
            ->where('w.status', self::STATUS_PUBLISHED)
            ->first([
                'w.*',
                'u.nome as criado_por_nome',
                'm.nome as modalidade_nome',
                'm.cor as modalidade_cor',
            ]);

        return $row ? (array) $row : null;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function listByTenant(int $tenantId, array $filters = []): array
    {
        $query = DB::table('wods as w')
            ->leftJoin('usuarios as u', 'w.criado_por', '=', 'u.id')
            ->leftJoin('modalidades as m', 'w.modalidade_id', '=', 'm.id')
            ->where('w.tenant_id', $tenantId)
            ->orderByDesc('w.data')
            ->select([
                'w.*',
                'u.nome as criado_por_nome',
                'm.nome as modalidade_nome',
            ]);

        if (! empty($filters['status'])) {
            $query->where('w.status', $filters['status']);
        }

        if (! empty($filters['data_inicio']) && ! empty($filters['data_fim'])) {
            $query->whereBetween('w.data', [$filters['data_inicio'], $filters['data_fim']]);
        }

        if (! empty($filters['data'])) {
            $query->whereRaw('DATE(w.data) = ?', [$filters['data']]);
        }

        if (! empty($filters['modalidade_id'])) {
            $query->where('w.modalidade_id', (int) $filters['modalidade_id']);
        }

        if (! empty($filters['limit'])) {
            $query->limit((int) $filters['limit']);
        }

        if (! empty($filters['offset'])) {
            $query->offset((int) $filters['offset']);
        }

        return $query->get()->map(fn ($row) => (array) $row)->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, int $tenantId, array $data): bool
    {
        $fields = [];
        foreach (['titulo', 'descricao', 'status', 'data', 'modalidade_id'] as $key) {
            if (array_key_exists($key, $data)) {
                $fields[$key] = $data[$key];
            }
        }

        if ($fields === []) {
            return false;
        }

        $fields['updated_at'] = now();

        return DB::table('wods')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update($fields) >= 0;
    }

    public function delete(int $id, int $tenantId): bool
    {
        return DB::table('wods')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->delete() > 0;
    }

    public function existePorDataModalidade(
        string $data,
        int $modalidadeId,
        int $tenantId,
        ?int $wodIdExcluir = null,
    ): bool {
        $query = DB::table('wods')
            ->where('tenant_id', $tenantId)
            ->whereRaw('DATE(data) = ?', [$data])
            ->where('modalidade_id', $modalidadeId);

        if ($wodIdExcluir) {
            $query->where('id', '!=', $wodIdExcluir);
        }

        return $query->count() > 0;
    }

    public function publicar(int $id, int $tenantId): bool
    {
        return $this->update($id, $tenantId, ['status' => self::STATUS_PUBLISHED]);
    }

    public function arquivar(int $id, int $tenantId): bool
    {
        return $this->update($id, $tenantId, ['status' => self::STATUS_ARCHIVED]);
    }
}
