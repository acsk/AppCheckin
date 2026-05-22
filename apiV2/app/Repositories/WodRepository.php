<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class WodRepository
{
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
}
