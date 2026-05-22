<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class TurmaCheckinBloqueioService
{
    public function isBloqueada(int $turmaId, int $tenantId): bool
    {
        return DB::table('turma_checkin_bloqueios')
            ->where('turma_id', $turmaId)
            ->where('tenant_id', $tenantId)
            ->exists();
    }

    /**
     * @param  list<int>  $turmaIds
     * @return array<int, true>
     */
    public function listarTurmaIdsBloqueadas(int $tenantId, array $turmaIds): array
    {
        $turmaIds = array_values(array_unique(array_filter(array_map('intval', $turmaIds))));
        if ($turmaIds === []) {
            return [];
        }

        $rows = DB::table('turma_checkin_bloqueios')
            ->where('tenant_id', $tenantId)
            ->whereIn('turma_id', $turmaIds)
            ->pluck('turma_id');

        $map = [];
        foreach ($rows as $id) {
            $map[(int) $id] = true;
        }

        return $map;
    }

    public function usuarioEhStaffNoTenant(int $usuarioId, int $tenantId): bool
    {
        return DB::table('tenant_usuario_papel')
            ->where('usuario_id', $usuarioId)
            ->where('tenant_id', $tenantId)
            ->where('ativo', 1)
            ->whereIn('papel_id', [2, 3, 4])
            ->exists();
    }
}
