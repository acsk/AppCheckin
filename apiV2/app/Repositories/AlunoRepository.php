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

    public function findAlunoComFotoNoTenant(int $usuarioId, int $tenantId): ?array
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

    public function updateFotoCaminho(int $alunoId, string $caminho): void
    {
        DB::table('alunos')->where('id', $alunoId)->update([
            'foto_caminho' => $caminho,
            'updated_at' => DB::raw('NOW()'),
        ]);
    }

    public function findAlunoNoTenantPorId(int $alunoId, int $tenantId): ?array
    {
        $row = DB::table('alunos as a')
            ->join('usuarios as u', 'u.id', '=', 'a.usuario_id')
            ->join('tenant_usuario_papel as tup', function ($join) use ($tenantId) {
                $join->on('tup.usuario_id', '=', 'a.usuario_id')
                    ->where('tup.tenant_id', $tenantId)
                    ->where('tup.papel_id', 1)
                    ->where('tup.ativo', 1);
            })
            ->where('a.id', $alunoId)
            ->select(['a.id as aluno_id', 'a.usuario_id', 'u.nome', 'u.email'])
            ->first();

        return $row ? (array) $row : null;
    }

    public function findAlunoNoTenantPorUsuarioId(int $usuarioId, int $tenantId): ?array
    {
        $row = DB::table('alunos as a')
            ->join('usuarios as u', 'u.id', '=', 'a.usuario_id')
            ->join('tenant_usuario_papel as tup', function ($join) use ($tenantId) {
                $join->on('tup.usuario_id', '=', 'a.usuario_id')
                    ->where('tup.tenant_id', $tenantId)
                    ->where('tup.papel_id', 1)
                    ->where('tup.ativo', 1);
            })
            ->where('a.usuario_id', $usuarioId)
            ->select(['a.id as aluno_id', 'a.usuario_id', 'u.nome', 'u.email'])
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buscarParaCheckinMobile(
        int $tenantId,
        string $nome,
        string $cpf,
        string $email,
        int $limit,
        int $offset,
    ): array {
        $searchConds = [];
        $params = ['tenant_id' => $tenantId];

        if ($nome !== '') {
            $searchConds[] = '(u.nome LIKE :nome_u OR a.nome LIKE :nome_a)';
            $params['nome_u'] = '%'.$nome.'%';
            $params['nome_a'] = '%'.$nome.'%';
        }

        if ($email !== '') {
            $searchConds[] = 'LOWER(u.email) LIKE :email';
            $params['email'] = '%'.mb_strtolower($email, 'UTF-8').'%';
        }

        $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf) ?? '';
        if ($cpfLimpo !== '') {
            if (strlen($cpfLimpo) === 11) {
                $searchConds[] = 'u.cpf = :cpf';
                $params['cpf'] = $cpfLimpo;
            } else {
                $searchConds[] = 'u.cpf LIKE :cpf_like';
                $params['cpf_like'] = '%'.$cpfLimpo.'%';
            }
        }

        if ($searchConds === []) {
            return [];
        }

        $sql = 'SELECT a.id as aluno_id, u.id as usuario_id,
                COALESCE(NULLIF(a.nome, \'\'), u.nome) as nome,
                u.email, u.cpf, a.foto_caminho, a.data_nascimento
                FROM usuarios u
                INNER JOIN tenant_usuario_papel tup
                    ON tup.usuario_id = u.id
                   AND tup.tenant_id = :tenant_id
                   AND tup.papel_id = 1
                   AND tup.ativo = 1
                LEFT JOIN alunos a ON a.usuario_id = u.id
                WHERE ('.implode(' OR ', $searchConds).')
                ORDER BY u.nome ASC
                LIMIT '.$limit.' OFFSET '.$offset;

        return array_map(
            static fn ($row) => (array) $row,
            DB::select($sql, $params),
        );
    }
}
