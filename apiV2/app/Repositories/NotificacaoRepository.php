<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class NotificacaoRepository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listUnread(int $tenantId, int $usuarioId, int $limit = 200): array
    {
        $rows = DB::table('notificacoes')
            ->where('tenant_id', $tenantId)
            ->where('usuario_id', $usuarioId)
            ->where('lida', 0)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get([
                'id',
                'tenant_id',
                'usuario_id',
                'tipo',
                'titulo',
                'mensagem',
                'dados',
                'lida',
                'created_at',
                'updated_at',
            ]);

        return $rows->map(fn ($row) => (array) $row)->all();
    }
}
