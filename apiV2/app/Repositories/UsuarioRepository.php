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

    public function findAlunoIdInTenant(int $usuarioId, int $tenantId): ?int
    {
        $row = DB::table('alunos as a')
            ->join('tenant_usuario_papel as tup', function ($join) use ($tenantId) {
                $join->on('tup.usuario_id', '=', 'a.usuario_id')
                    ->where('tup.tenant_id', $tenantId)
                    ->where('tup.papel_id', 1)
                    ->where('tup.ativo', 1);
            })
            ->where('a.usuario_id', $usuarioId)
            ->select('a.id')
            ->first();

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

    /**
     * Email é único no sistema (não por tenant).
     * $tenantId é ignorado (compatibilidade de assinatura).
     */
    public function emailExists(string $email, ?int $excludeId = null, ?int $tenantId = null): bool
    {
        $email = mb_strtolower(trim($email), 'UTF-8');
        if ($email === '') {
            return false;
        }

        $query = DB::table('usuarios')->where(function ($q) use ($email) {
            $q->whereRaw('LOWER(TRIM(email)) = ?', [$email]);
            if ($this->hasColumn('usuarios', 'email_global')) {
                $q->orWhereRaw("LOWER(TRIM(COALESCE(email_global, ''))) = ?", [$email]);
            }
        });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * CPF único no sistema (usuarios.cpf e alunos.cpf).
     */
    public function cpfExists(string $cpf, ?int $excludeUsuarioId = null): bool
    {
        $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf) ?: '';
        if ($cpfLimpo === '' || strlen($cpfLimpo) !== 11) {
            return false;
        }

        $qUsuario = DB::table('usuarios')->where('cpf', $cpfLimpo);
        if ($excludeUsuarioId) {
            $qUsuario->where('id', '!=', $excludeUsuarioId);
        }
        if ($qUsuario->exists()) {
            return true;
        }

        $qAluno = DB::table('alunos')->where('cpf', $cpfLimpo);
        if ($excludeUsuarioId) {
            $qAluno->where(function ($q) use ($excludeUsuarioId) {
                $q->whereNull('usuario_id')->orWhere('usuario_id', '!=', $excludeUsuarioId);
            });
        }

        return $qAluno->exists();
    }

    /**
     * @param  array{email?: string, senha?: string, nome?: string, cpf?: string|null}  $data
     */
    public function updateAuthFields(int $id, array $data): void
    {
        $update = [];
        if (isset($data['email'])) {
            $email = mb_strtolower(trim((string) $data['email']), 'UTF-8');
            $update['email'] = $email;
            if ($this->hasColumn('usuarios', 'email_global')) {
                $update['email_global'] = $email;
            }
        }
        if (! empty($data['senha'])) {
            $update['senha_hash'] = password_hash((string) $data['senha'], PASSWORD_BCRYPT);
        }
        if (isset($data['nome'])) {
            $update['nome'] = mb_strtoupper(trim((string) $data['nome']), 'UTF-8');
        }
        if (array_key_exists('cpf', $data)) {
            $cpf = $data['cpf'];
            $update['cpf'] = $cpf !== null && $cpf !== ''
                ? (preg_replace('/[^0-9]/', '', (string) $cpf) ?: null)
                : null;
        }

        if ($update === []) {
            return;
        }

        DB::table('usuarios')->where('id', $id)->update($update);
    }

    /**
     * Espelha Usuario::listarPorTenant da Slim.
     *
     * A Slim monta a chave `status` a partir de uma coluna que não existe no schema,
     * então o painel sempre recebe null e cai no fallback `ativo`.
     *
     * @return list<array<string, mixed>>
     */
    public function listarPorTenant(int $tenantId, bool $apenasAtivos = false): array
    {
        $sql = '
            SELECT
                u.id,
                u.nome,
                u.email,
                u.telefone,
                u.cpf,
                tup.papel_id,
                u.created_at,
                u.updated_at,
                p_tenant.nome as papel_nome,
                tup.ativo
            FROM usuarios u
            INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = u.id AND tup.ativo = 1
            LEFT JOIN papeis p_tenant ON tup.papel_id = p_tenant.id
            WHERE tup.tenant_id = ?
        ';

        if ($apenasAtivos) {
            $sql .= ' AND tup.ativo = 1';
        }

        $sql .= ' ORDER BY u.nome ASC';

        return array_map(
            fn ($row) => $this->mapLinhaListagem($row),
            DB::select($sql, [$tenantId]),
        );
    }

    /**
     * Espelha Usuario::listarTodos da Slim (dedup por usuário).
     *
     * @return list<array<string, mixed>>
     */
    public function listarTodos(bool $isSuperAdmin = false, ?int $tenantId = null, bool $apenasAtivos = false): array
    {
        $sql = '
            SELECT
                u.id,
                u.nome,
                u.email,
                u.telefone,
                u.cpf,
                tup.papel_id,
                u.created_at,
                u.updated_at,
                p_tenant.nome as papel_nome,
                tup.ativo
            FROM usuarios u
            INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = u.id AND tup.ativo = 1
            LEFT JOIN papeis p_tenant ON tup.papel_id = p_tenant.id
        ';

        $conditions = [];
        $bindings = [];

        if (! $isSuperAdmin && $tenantId !== null) {
            $conditions[] = 'tup.tenant_id = ?';
            $bindings[] = $tenantId;
        }

        if ($apenasAtivos) {
            $conditions[] = 'tup.ativo = 1';
        }

        if ($conditions !== []) {
            $sql .= ' WHERE '.implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY u.id ASC, tup.ativo DESC';

        $usuarios = [];
        foreach (DB::select($sql, $bindings) as $row) {
            $usuarioId = (int) $row->id;
            if (isset($usuarios[$usuarioId])) {
                continue;
            }
            $usuarios[$usuarioId] = $this->mapLinhaListagem($row);
        }

        return array_values($usuarios);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapLinhaListagem(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'nome' => $row->nome,
            'email' => $row->email,
            'telefone' => $row->telefone ?? null,
            'cpf' => $row->cpf ?? null,
            'papel_id' => $row->papel_id !== null ? (int) $row->papel_id : null,
            'papel_nome' => $row->papel_nome,
            'ativo' => (bool) $row->ativo,
            'status' => null,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    /**
     * Espelha Usuario::listarAdmins usada em GET /admin/admins da Slim.
     *
     * @return list<array<string, mixed>>
     */
    public function listarAdminsDoTenant(int $tenantId): array
    {
        $rows = DB::select('
            SELECT DISTINCT u.id, u.nome, u.email, p.nome as papel
            FROM usuarios u
            INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = u.id AND tup.ativo = 1
            INNER JOIN papeis p ON p.id = tup.papel_id
            WHERE tup.tenant_id = ?
              AND tup.papel_id IN (3, 4)
            ORDER BY u.nome ASC
        ', [$tenantId]);

        return array_map(fn ($row) => (array) $row, $rows);
    }

    /**
     * Espelha Usuario::criarUsuarioCompleto da Slim: grava só em `usuarios`
     * (sem criar registro em `alunos`) e o papel em `tenant_usuario_papel`.
     *
     * @param  array<string, mixed>  $data
     */
    public function criarUsuarioCompleto(array $data, int $tenantId): ?int
    {
        $cpfLimpo = isset($data['cpf']) ? preg_replace('/[^0-9]/', '', (string) $data['cpf']) : null;
        $emailNorm = mb_strtolower(trim((string) ($data['email'] ?? '')), 'UTF-8');
        $nome = isset($data['nome']) ? mb_strtoupper(trim((string) $data['nome']), 'UTF-8') : null;
        $senha = self::normalizarSenhaCpf((string) ($data['senha'] ?? ''), $cpfLimpo);

        try {
            $usuarioId = (int) DB::table('usuarios')->insertGetId([
                'nome' => $nome,
                'email' => $emailNorm,
                'email_global' => $emailNorm,
                'senha_hash' => password_hash($senha, PASSWORD_BCRYPT),
                'telefone' => $data['telefone'] ?? null,
                'cpf' => $cpfLimpo ?: null,
                'ativo' => 1,
            ]);
        } catch (\Throwable) {
            return null;
        }

        if ($usuarioId <= 0) {
            return null;
        }

        try {
            DB::statement(
                'INSERT INTO tenant_usuario_papel (tenant_id, usuario_id, papel_id, ativo)
                 VALUES (?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE papel_id = ?, ativo = 1',
                [$tenantId, $usuarioId, (int) ($data['papel_id'] ?? 1), (int) ($data['papel_id'] ?? 1)],
            );
        } catch (\Throwable) {
            // A Slim trata falha ao gravar o papel como não crítica.
        }

        return $usuarioId;
    }

    /**
     * Espelha Usuario::update da Slim. Retorna false apenas quando nenhum campo
     * conhecido foi enviado — a Slim não diferencia "0 linhas afetadas".
     *
     * @param  array<string, mixed>  $data
     */
    public function atualizarPerfil(int $id, array $data): bool
    {
        $update = [];

        if (isset($data['nome'])) {
            $update['nome'] = mb_strtoupper(trim((string) $data['nome']), 'UTF-8');
        }

        if (isset($data['senha'])) {
            $cpfParaSenha = $data['cpf'] ?? null;
            if ($cpfParaSenha === null) {
                try {
                    $cpfParaSenha = DB::table('usuarios')->where('id', $id)->value('cpf') ?: null;
                } catch (\Throwable) {
                    $cpfParaSenha = null;
                }
            }
            $update['senha_hash'] = password_hash(
                self::normalizarSenhaCpf((string) $data['senha'], $cpfParaSenha !== null ? (string) $cpfParaSenha : null),
                PASSWORD_BCRYPT,
            );
        }

        if (isset($data['email'])) {
            $email = mb_strtolower(trim((string) $data['email']), 'UTF-8');
            $update['email'] = $email;
            if ($this->hasColumn('usuarios', 'email_global')) {
                $update['email_global'] = $email;
            }
        }

        if (isset($data['foto_base64'])) {
            $update['foto_base64'] = $data['foto_base64'];
        }

        if (isset($data['telefone'])) {
            $update['telefone'] = preg_replace('/[^0-9]/', '', (string) $data['telefone']);
        }

        foreach (['cpf', 'cep'] as $campo) {
            if (isset($data[$campo])) {
                $update[$campo] = preg_replace('/[^0-9]/', '', (string) $data[$campo]) ?: null;
            }
        }

        if (isset($data['numero'])) {
            $update['numero'] = $data['numero'];
        }

        foreach (['logradouro', 'complemento', 'bairro', 'cidade', 'estado'] as $campo) {
            if (isset($data[$campo])) {
                $update[$campo] = mb_strtoupper(trim((string) $data[$campo]), 'UTF-8');
            }
        }

        if ($update === []) {
            return false;
        }

        DB::table('usuarios')->where('id', $id)->update($update);
        $this->sincronizarAluno($id, $data);

        return true;
    }

    /**
     * Espelha Usuario::toggleStatusUsuarioTenant da Slim.
     */
    public function toggleStatusUsuarioTenant(int $usuarioId, int $tenantId): bool
    {
        $vinculo = DB::table('tenant_usuario_papel')
            ->where('usuario_id', $usuarioId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $vinculo) {
            return false;
        }

        $novoAtivo = ((int) $vinculo->ativo === 1) ? 0 : 1;

        DB::table('tenant_usuario_papel')
            ->where('usuario_id', $usuarioId)
            ->where('tenant_id', $tenantId)
            ->update(['ativo' => $novoAtivo, 'updated_at' => DB::raw('NOW()')]);

        try {
            DB::table('usuarios')
                ->where('id', $usuarioId)
                ->update(['ativo' => $novoAtivo, 'updated_at' => DB::raw('NOW()')]);
        } catch (\Throwable) {
            // A Slim segue adiante quando `usuarios.ativo` não existe.
        }

        return true;
    }

    /**
     * Busca global por CPF (usuarios.cpf com fallback em alunos.cpf).
     *
     * @return array<string, mixed>|null
     */
    public function findByCpfGlobal(string $cpf): ?array
    {
        $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf) ?: '';
        if ($cpfLimpo === '') {
            return null;
        }

        $row = DB::table('usuarios')->where('cpf', $cpfLimpo)->first();
        if ($row) {
            return (array) $row;
        }

        $viaAluno = DB::table('alunos as a')
            ->join('usuarios as u', 'u.id', '=', 'a.usuario_id')
            ->where('a.cpf', $cpfLimpo)
            ->select('u.*')
            ->first();

        return $viaAluno ? (array) $viaAluno : null;
    }

    /**
     * Diferente de temAcessoTenant: a Slim não filtra `ativo` aqui, para que um
     * vínculo desativado ainda conte como "já associado".
     */
    public function isAssociatedWithTenant(int $usuarioId, int $tenantId): bool
    {
        return DB::table('tenant_usuario_papel')
            ->where('usuario_id', $usuarioId)
            ->where('tenant_id', $tenantId)
            ->exists();
    }

    public function associateToTenant(int $usuarioId, int $tenantId, string $status = 'ativo'): bool
    {
        $ativo = $status === 'ativo' ? 1 : 0;

        try {
            if ($this->isAssociatedWithTenant($usuarioId, $tenantId)) {
                DB::table('tenant_usuario_papel')
                    ->where('usuario_id', $usuarioId)
                    ->where('tenant_id', $tenantId)
                    ->update(['ativo' => $ativo, 'updated_at' => DB::raw('NOW()')]);
            } else {
                DB::table('tenant_usuario_papel')->insert([
                    'usuario_id' => $usuarioId,
                    'tenant_id' => $tenantId,
                    'papel_id' => 1,
                    'ativo' => $ativo,
                    'created_at' => DB::raw('NOW()'),
                    'updated_at' => DB::raw('NOW()'),
                ]);
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getEstatisticas(int $userId, ?int $tenantId = null): ?array
    {
        $usuario = $this->findProfile($userId, $tenantId);
        if (! $usuario) {
            return null;
        }

        $totalCheckins = DB::table('checkins as c')
            ->join('alunos as a', 'a.id', '=', 'c.aluno_id')
            ->where('a.usuario_id', $userId)
            ->count();

        return [
            'id' => $usuario['id'],
            'nome' => $usuario['nome'],
            'email' => $usuario['email'],
            'foto_url' => $usuario['foto_base64'] ?? null,
            'total_checkins' => (int) $totalCheckins,
            'total_prs' => 0,
            'created_at' => $usuario['created_at'],
            'updated_at' => $usuario['updated_at'],
        ];
    }

    /**
     * Senha inicial igual ao CPF é gravada só com os dígitos (paridade com a Slim).
     */
    public static function normalizarSenhaCpf(?string $senha, ?string $cpf): string
    {
        $senha = (string) ($senha ?? '');
        $cpfDigitos = preg_replace('/[^0-9]/', '', (string) ($cpf ?? '')) ?: '';
        $senhaDigitos = preg_replace('/[^0-9]/', '', $senha) ?: '';

        if ($cpfDigitos !== '' && strlen($cpfDigitos) === 11 && $senhaDigitos === $cpfDigitos) {
            return $cpfDigitos;
        }

        return $senha;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sincronizarAluno(int $usuarioId, array $data): void
    {
        $camposPerfil = ['nome', 'telefone', 'whatsapp', 'cpf', 'cep', 'logradouro', 'numero',
            'complemento', 'bairro', 'cidade', 'estado', 'foto_base64'];

        $update = [];
        foreach ($camposPerfil as $campo) {
            if (! array_key_exists($campo, $data)) {
                continue;
            }

            $valor = $data[$campo];
            if ($campo === 'cpf' || $campo === 'cep') {
                $valor = $valor ? preg_replace('/[^0-9]/', '', (string) $valor) : null;
            } elseif (in_array($campo, ['nome', 'logradouro', 'complemento', 'bairro', 'cidade', 'estado'], true)) {
                $valor = $valor ? mb_strtoupper(trim((string) $valor), 'UTF-8') : null;
            }

            $update[$campo] = $valor;
        }

        if ($update === []) {
            return;
        }

        $update['updated_at'] = DB::raw('CURRENT_TIMESTAMP');

        try {
            DB::table('alunos')->where('usuario_id', $usuarioId)->update($update);
        } catch (\Throwable) {
            // A Slim loga e segue: falha de sincronia não aborta a atualização.
        }
    }

    /**
     * Cadastro público mobile: senha = CPF, tenant opcional, perfil completo em alunos.
     *
     * @param  array<string, mixed>  $data
     */
    public function createMobileAluno(array $data, ?int $tenantId = null): ?int
    {
        try {
            return DB::transaction(function () use ($data, $tenantId) {
                $email = mb_strtolower(trim((string) ($data['email'] ?? '')), 'UTF-8');
                $nome = mb_strtoupper(trim((string) ($data['nome'] ?? '')), 'UTF-8');
                $cpf = preg_replace('/[^0-9]/', '', (string) ($data['cpf'] ?? '')) ?: null;
                $cep = isset($data['cep']) ? preg_replace('/[^0-9]/', '', (string) $data['cep']) : null;
                $telefone = isset($data['telefone']) ? preg_replace('/[^0-9]/', '', (string) $data['telefone']) : null;
                $whatsapp = isset($data['whatsapp']) ? preg_replace('/[^0-9]/', '', (string) $data['whatsapp']) : null;
                $senha = self::normalizarSenhaCpf((string) ($data['senha'] ?? ''), $cpf);

                $usuarioId = (int) DB::table('usuarios')->insertGetId([
                    'nome' => $nome,
                    'email' => $email,
                    'email_global' => $email,
                    'senha_hash' => password_hash($senha, PASSWORD_BCRYPT),
                    'cpf' => $cpf,
                    'cep' => $cep ?: null,
                    'logradouro' => isset($data['logradouro']) ? mb_strtoupper(trim((string) $data['logradouro']), 'UTF-8') : null,
                    'numero' => $data['numero'] ?? null,
                    'complemento' => isset($data['complemento']) ? mb_strtoupper(trim((string) $data['complemento']), 'UTF-8') : null,
                    'bairro' => isset($data['bairro']) ? mb_strtoupper(trim((string) $data['bairro']), 'UTF-8') : null,
                    'cidade' => isset($data['cidade']) ? mb_strtoupper(trim((string) $data['cidade']), 'UTF-8') : null,
                    'estado' => isset($data['estado'])
                        ? mb_substr(mb_strtoupper(trim((string) $data['estado']), 'UTF-8'), 0, 2)
                        : null,
                    'telefone' => $telefone,
                    'ativo' => $data['ativo'] ?? 1,
                ]);

                DB::table('alunos')->insert([
                    'usuario_id' => $usuarioId,
                    'nome' => $nome,
                    'telefone' => $telefone,
                    'whatsapp' => $whatsapp,
                    'cpf' => $cpf,
                    'data_nascimento' => $data['data_nascimento'] ?? null,
                    'cep' => $cep ?: null,
                    'logradouro' => isset($data['logradouro']) ? mb_strtoupper(trim((string) $data['logradouro']), 'UTF-8') : null,
                    'numero' => $data['numero'] ?? null,
                    'complemento' => isset($data['complemento']) ? mb_strtoupper(trim((string) $data['complemento']), 'UTF-8') : null,
                    'bairro' => isset($data['bairro']) ? mb_strtoupper(trim((string) $data['bairro']), 'UTF-8') : null,
                    'cidade' => isset($data['cidade']) ? mb_strtoupper(trim((string) $data['cidade']), 'UTF-8') : null,
                    'estado' => isset($data['estado'])
                        ? mb_substr(mb_strtoupper(trim((string) $data['estado']), 'UTF-8'), 0, 2)
                        : null,
                    'ativo' => $data['ativo'] ?? 1,
                ]);

                if ($tenantId !== null && $tenantId > 0) {
                    DB::table('tenant_usuario_papel')->insertOrIgnore([
                        'tenant_id' => $tenantId,
                        'usuario_id' => $usuarioId,
                        'papel_id' => (int) ($data['papel_id'] ?? 1),
                        'ativo' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                return $usuarioId;
            });
        } catch (\Throwable) {
            return null;
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        return DB::getSchemaBuilder()->hasColumn($table, $column);
    }
}
