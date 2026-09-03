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

    /**
     * @param  list<array<string, mixed>>  $turmas
     * @return list<array<string, mixed>>
     */
    public function anexarFlagNasTurmas(array $turmas, int $tenantId): array
    {
        if ($turmas === []) {
            return [];
        }

        $ids = array_map(static fn (array $t) => (int) ($t['id'] ?? 0), $turmas);
        $bloqueadas = $this->listarTurmaIdsBloqueadas($tenantId, $ids);

        return array_map(static function (array $turma) use ($bloqueadas) {
            $id = (int) ($turma['id'] ?? 0);
            $turma['checkin_bloqueado'] = isset($bloqueadas[$id]);

            return $turma;
        }, $turmas);
    }

    public function removerCheckinsDaTurma(int $turmaId, int $tenantId): int
    {
        return DB::table('checkins')
            ->where('turma_id', $turmaId)
            ->where('tenant_id', $tenantId)
            ->delete();
    }

    /**
     * @return int Quantidade de check-ins removidos ao bloquear
     */
    public function bloquear(int $turmaId, int $tenantId, ?int $usuarioId, ?string $motivo = null): int
    {
        if ($usuarioId !== null && $usuarioId <= 0) {
            $usuarioId = null;
        }

        $motivo = $motivo !== null ? trim($motivo) : null;
        if ($motivo === '') {
            $motivo = null;
        }

        $checkinsRemovidos = $this->removerCheckinsDaTurma($turmaId, $tenantId);

        DB::statement(
            'INSERT INTO turma_checkin_bloqueios (tenant_id, turma_id, bloqueado_por_usuario_id, motivo)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                bloqueado_por_usuario_id = VALUES(bloqueado_por_usuario_id),
                motivo = VALUES(motivo),
                updated_at = NOW()',
            [$tenantId, $turmaId, $usuarioId, $motivo],
        );

        return $checkinsRemovidos;
    }

    public function desbloquear(int $turmaId, int $tenantId): bool
    {
        return DB::table('turma_checkin_bloqueios')
            ->where('turma_id', $turmaId)
            ->where('tenant_id', $tenantId)
            ->delete() > 0;
    }

    /**
     * Admin/super admin: qualquer turma. Professor: apenas turmas em que é o professor vinculado.
     *
     * @param  array{id?: int}|null  $papelRequest
     */
    public function usuarioPodeGerenciarTurma(int $usuarioId, int $tenantId, int $turmaId, ?array $papelRequest): bool
    {
        if ($papelRequest === null) {
            $papelId = (int) (DB::table('tenant_usuario_papel')
                ->where('usuario_id', $usuarioId)
                ->where('tenant_id', $tenantId)
                ->where('ativo', 1)
                ->max('papel_id') ?: 0);
        } else {
            $papelId = (int) ($papelRequest['id'] ?? 0);
        }

        if ($papelId >= 3) {
            return true;
        }

        if ($papelId !== 2) {
            return false;
        }

        return DB::table('turmas as t')
            ->join('professores as pr', 'pr.id', '=', 't.professor_id')
            ->where('t.id', $turmaId)
            ->where('t.tenant_id', $tenantId)
            ->where('pr.usuario_id', $usuarioId)
            ->exists();
    }
}
