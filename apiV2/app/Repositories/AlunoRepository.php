<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class AlunoRepository
{
    public function findIdByUsuario(int $usuarioId): ?int
    {
        $id = DB::table('alunos')->where('usuario_id', $usuarioId)->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function findForTenant(int $usuarioId, int $tenantId): ?array
    {
        $row = DB::table('alunos as a')
            ->join('tenant_usuario_papel as tup', function ($join) use ($tenantId) {
                $join->on('tup.usuario_id', '=', 'a.usuario_id')
                    ->where('tup.tenant_id', $tenantId)
                    ->where('tup.papel_id', 1);
            })
            ->where('a.usuario_id', $usuarioId)
            ->select(['a.id', 'a.foto_caminho'])
            ->first();

        return $row ? (array) $row : null;
    }

    public function findPerfilByUsuario(int $usuarioId, int $tenantId): ?array
    {
        $row = DB::table('alunos as a')
            ->join('tenant_usuario_papel as tup', function ($join) use ($tenantId) {
                $join->on('tup.usuario_id', '=', 'a.usuario_id')
                    ->where('tup.tenant_id', $tenantId)
                    ->where('tup.papel_id', 1);
            })
            ->where('a.usuario_id', $usuarioId)
            ->select([
                'a.id',
                'a.foto_caminho',
                'a.data_nascimento',
                'a.cep',
                'a.logradouro',
                'a.numero',
                'a.complemento',
                'a.bairro',
                'a.cidade',
                'a.estado',
            ])
            ->first();

        return $row ? (array) $row : null;
    }

    public function usuarioTemAcessoTenant(int $usuarioId, int $tenantId): bool
    {
        return DB::table('tenant_usuario_papel')
            ->where('usuario_id', $usuarioId)
            ->where('tenant_id', $tenantId)
            ->where('ativo', 1)
            ->exists();
    }
}
