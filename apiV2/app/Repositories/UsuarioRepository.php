<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class UsuarioRepository
{
    public function findByEmailGlobal(string $email): ?object
    {
        $email = mb_strtolower(trim($email), 'UTF-8');

        $query = DB::table('usuarios');

        if ($this->hasColumn('usuarios', 'email_global')) {
            $query->where(function ($q) use ($email) {
                $q->where('email_global', $email)->orWhere('email', $email);
            });
        } else {
            $query->where('email', $email);
        }

        $row = $query->first();

        return $row ?: null;
    }

    public function getPapeis(int $usuarioId): array
    {
        $rows = DB::table('tenant_usuario_papel as tup')
            ->leftJoin('papeis as p', 'p.id', '=', 'tup.papel_id')
            ->where('tup.usuario_id', $usuarioId)
            ->where('tup.ativo', 1)
            ->select('tup.papel_id', 'p.nome as papel_nome')
            ->groupBy('tup.papel_id', 'p.nome')
            ->orderByDesc('tup.papel_id')
            ->get();

        $papeis = [];
        foreach ($rows as $row) {
            $papeis[] = [
                'id' => (int) $row->papel_id,
                'nome' => $row->papel_nome,
            ];
        }

        return $papeis;
    }

    public function getTenantsByUsuario(int $usuarioId): array
    {
        $rows = DB::table('tenant_usuario_papel as tup')
            ->join('tenants as t', 't.id', '=', 'tup.tenant_id')
            ->leftJoin('papeis as p', 'p.id', '=', 'tup.papel_id')
            ->where('tup.usuario_id', $usuarioId)
            ->whereIn('tup.papel_id', [1, 2, 3])
            ->where('tup.ativo', 1)
            ->where('t.ativo', 1)
            ->orderByDesc('tup.papel_id')
            ->orderBy('t.nome')
            ->select([
                'tup.id as vinculo_id',
                'tup.papel_id',
                'tup.ativo',
                'tup.created_at as data_inicio',
                't.id as tenant_id',
                't.nome as tenant_nome',
                't.slug as tenant_slug',
                't.email as tenant_email',
                't.telefone as tenant_telefone',
                'p.nome as papel_nome',
            ])
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $tenantId = (int) $row->tenant_id;
            if (! isset($grouped[$tenantId])) {
                $grouped[$tenantId] = [
                    'vinculo_id' => (int) $row->vinculo_id,
                    'ativo' => (int) $row->ativo,
                    'data_inicio' => $row->data_inicio,
                    'tenant' => [
                        'id' => $tenantId,
                        'nome' => $row->tenant_nome,
                        'slug' => $row->tenant_slug,
                        'email' => $row->tenant_email,
                        'telefone' => $row->tenant_telefone,
                    ],
                    'papeis' => [],
                    'plano' => null,
                ];
            }
            $grouped[$tenantId]['papeis'][] = [
                'id' => (int) $row->papel_id,
                'nome' => $row->papel_nome,
            ];
        }

        return array_values($grouped);
    }

    public function findAlunoId(int $usuarioId): ?int
    {
        $row = DB::table('alunos')->where('usuario_id', $usuarioId)->first();

        return $row ? (int) $row->id : null;
    }

    public function findProfile(int $userId, ?int $tenantId): ?array
    {
        $query = DB::table('usuarios as u');

        $join = function ($join) {
            $join->on('tup.usuario_id', '=', 'u.id')->where('tup.ativo', 1);
        };

        if ($tenantId) {
            $query->join('tenant_usuario_papel as tup', $join);
            $query->where('tup.tenant_id', $tenantId);
        } else {
            $query->leftJoin('tenant_usuario_papel as tup', $join);
        }

        $query
            ->leftJoin('papeis as p', 'p.id', '=', 'tup.papel_id')
            ->where('u.id', $userId);

        $user = $query
            ->orderByDesc('tup.papel_id')
            ->select([
                'u.id',
                DB::raw('COALESCE(tup.tenant_id, '.(int) ($tenantId ?? 0).') as tenant_id'),
                'tup.ativo',
                'u.nome',
                'u.email',
                'u.email_global',
                'tup.papel_id',
                'u.foto_base64',
                'u.foto_caminho',
                'u.telefone',
                'u.cpf',
                'u.cep',
                'u.logradouro',
                'u.numero',
                'u.complemento',
                'u.bairro',
                'u.cidade',
                'u.estado',
                'u.created_at',
                'u.updated_at',
                'p.nome as role_nome',
                'p.descricao as role_descricao',
            ])
            ->first();

        if (! $user) {
            return null;
        }

        $data = (array) $user;
        if ($data['papel_id']) {
            $data['role'] = [
                'id' => $data['papel_id'],
                'nome' => $data['role_nome'],
                'descricao' => $data['role_descricao'],
            ];
        }
        unset($data['role_nome'], $data['role_descricao']);

        return $data;
    }

    public function temAcessoTenant(int $usuarioId, int $tenantId): bool
    {
        return DB::table('tenant_usuario_papel')
            ->where('usuario_id', $usuarioId)
            ->where('tenant_id', $tenantId)
            ->where('ativo', 1)
            ->exists();
    }

    public function findById(int $userId, ?int $tenantId = null): ?array
    {
        return $this->findProfile($userId, $tenantId);
    }

    public function findAuthContext(int $userId): ?array
    {
        $usuario = DB::table('usuarios as u')
            ->leftJoin('tenant_usuario_papel as tup', function ($join) {
                $join->on('tup.usuario_id', '=', 'u.id')->where('tup.ativo', 1);
            })
            ->where('u.id', $userId)
            ->orderByDesc('tup.papel_id')
            ->select([
                'u.id',
                'u.nome',
                'u.email',
                'u.email_global',
                'u.foto_base64',
                'tup.tenant_id',
                'tup.ativo as tenant_status',
                'tup.papel_id',
            ])
            ->first();

        return $usuario ? (array) $usuario : null;
    }

    public function isTenantActive(int $tenantId): bool
    {
        return DB::table('tenants')
            ->where('id', $tenantId)
            ->where('ativo', 1)
            ->exists();
    }

    public function createUsuario(array $data, int $tenantId, int $papelId = 1): ?int
    {
        try {
            return DB::transaction(function () use ($data, $tenantId, $papelId) {
                $email = mb_strtolower(trim((string) ($data['email'] ?? '')), 'UTF-8');
                $nome = mb_strtoupper(trim((string) ($data['nome'] ?? '')), 'UTF-8');
                $cpf = isset($data['cpf']) ? preg_replace('/[^0-9]/', '', (string) $data['cpf']) : null;
                $cep = isset($data['cep']) ? preg_replace('/[^0-9]/', '', (string) $data['cep']) : null;
                $telefone = isset($data['telefone']) ? preg_replace('/[^0-9]/', '', (string) $data['telefone']) : null;

                $usuarioId = DB::table('usuarios')->insertGetId([
                    'nome' => $nome,
                    'email' => $email,
                    'email_global' => $email,
                    'senha_hash' => password_hash((string) $data['senha'], PASSWORD_BCRYPT),
                    'cpf' => $cpf ?: null,
                    'cep' => $cep ?: null,
                    'logradouro' => isset($data['logradouro']) ? mb_strtoupper(trim((string) $data['logradouro']), 'UTF-8') : null,
                    'numero' => $data['numero'] ?? null,
                    'complemento' => isset($data['complemento']) ? mb_strtoupper(trim((string) $data['complemento']), 'UTF-8') : null,
                    'bairro' => isset($data['bairro']) ? mb_strtoupper(trim((string) $data['bairro']), 'UTF-8') : null,
                    'cidade' => isset($data['cidade']) ? mb_strtoupper(trim((string) $data['cidade']), 'UTF-8') : null,
                    'estado' => isset($data['estado']) ? mb_substr(mb_strtoupper(trim((string) $data['estado']), 'UTF-8'), 0, 2) : null,
                    'telefone' => $telefone,
                    'ativo' => $data['ativo'] ?? 1,
                ]);

                if (! in_array($papelId, [3, 4], true)) {
                    DB::table('alunos')->insert([
                        'usuario_id' => $usuarioId,
                        'nome' => $nome,
                        'telefone' => $telefone,
                        'cpf' => $cpf ?: null,
                        'cep' => $cep ?: null,
                        'ativo' => $data['ativo'] ?? 1,
                    ]);
                }

                DB::table('tenant_usuario_papel')->insertOrIgnore([
                    'tenant_id' => $tenantId,
                    'usuario_id' => $usuarioId,
                    'papel_id' => $papelId,
                    'ativo' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return (int) $usuarioId;
            });
        } catch (\Throwable) {
            return null;
        }
    }

    public function setPasswordResetToken(int $usuarioId, string $token, int $minutes = 60): void
    {
        if (! $this->hasColumn('usuarios', 'password_reset_token')) {
            return;
        }

        DB::table('usuarios')
            ->where('id', $usuarioId)
            ->update([
                'password_reset_token' => $token,
                'password_reset_expires_at' => DB::raw("DATE_ADD(NOW(), INTERVAL {$minutes} MINUTE)"),
            ]);
    }

    public function findByPasswordResetToken(string $token): ?array
    {
        if (! $this->hasColumn('usuarios', 'password_reset_token')) {
            return null;
        }

        $row = DB::table('usuarios')
            ->where('password_reset_token', $token)
            ->where('password_reset_expires_at', '>', DB::raw('NOW()'))
            ->select('id', 'nome', 'email')
            ->first();

        return $row ? (array) $row : null;
    }

    public function findIdByPasswordResetToken(string $token): ?int
    {
        $user = $this->findByPasswordResetToken($token);

        return $user ? (int) $user['id'] : null;
    }

    public function resetPassword(int $usuarioId, string $novaSenha): void
    {
        $update = [
            'senha_hash' => password_hash($novaSenha, PASSWORD_BCRYPT),
        ];

        if ($this->hasColumn('usuarios', 'password_reset_token')) {
            $update['password_reset_token'] = null;
            $update['password_reset_expires_at'] = null;
        }

        DB::table('usuarios')->where('id', $usuarioId)->update($update);
    }

    private function hasColumn(string $table, string $column): bool
    {
        return DB::getSchemaBuilder()->hasColumn($table, $column);
    }
}
