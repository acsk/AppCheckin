<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

/**
 * Queries admin de professores (painel).
 *
 * Vínculo professor↔tenant vive em tenant_usuario_papel (papel_id = 2);
 * o cadastro em professores é global (nome, cpf, email, foto, usuario_id).
 */
class AdminProfessorRepository
{
    private const PAPEL_PROFESSOR = 2;

    public function listarPorTenant(int $tenantId, bool $apenasAtivos = false): array
    {
        $sql = 'SELECT DISTINCT
                    p.id,
                    UPPER(p.nome) as nome,
                    p.cpf,
                    p.email,
                    p.foto_url,
                    p.ativo,
                    u.id as usuario_id,
                    u.telefone,
                    tup.ativo as vinculo_ativo,
                    (SELECT COUNT(*) FROM turmas t WHERE t.professor_id = p.id AND t.ativo = 1) as turmas_count
                FROM professores p
                INNER JOIN usuarios u ON u.id = p.usuario_id
                INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = u.id
                    AND tup.tenant_id = ?
                    AND tup.papel_id = '.self::PAPEL_PROFESSOR.'
                WHERE 1=1';

        if ($apenasAtivos) {
            $sql .= ' AND tup.ativo = 1 AND p.ativo = 1';
        }

        $sql .= ' ORDER BY nome ASC';

        return array_map(fn ($row) => (array) $row, DB::select($sql, [$tenantId]));
    }

    public function findById(int $id, ?int $tenantId = null): ?array
    {
        if ($tenantId !== null) {
            $sql = 'SELECT p.id, p.nome, p.cpf, p.email, p.foto_url, p.ativo, p.usuario_id, p.created_at, p.updated_at,
                           u.telefone,
                           tup.ativo as vinculo_ativo
                    FROM professores p
                    INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = p.usuario_id
                        AND tup.tenant_id = ?
                        AND tup.papel_id = '.self::PAPEL_PROFESSOR.'
                    LEFT JOIN usuarios u ON u.id = p.usuario_id
                    WHERE p.id = ?';
            $bindings = [$tenantId, $id];
        } else {
            $sql = 'SELECT p.id, p.nome, p.cpf, p.email, p.foto_url, p.ativo, p.usuario_id, p.created_at, p.updated_at,
                           u.telefone
                    FROM professores p
                    LEFT JOIN usuarios u ON u.id = p.usuario_id
                    WHERE p.id = ?';
            $bindings = [$id];
        }

        $row = DB::selectOne($sql, $bindings);

        return $row ? (array) $row : null;
    }

    /**
     * Busca no tenant: primeiro em professores, com fallback para usuários que
     * têm papel de professor mas ainda não possuem cadastro em professores.
     */
    public function findByCpf(string $cpf, int $tenantId): ?array
    {
        $row = DB::selectOne(
            'SELECT p.id, p.nome, p.cpf, p.email, p.foto_url, p.ativo, p.usuario_id, p.created_at, p.updated_at,
                    u.telefone,
                    tup.ativo as vinculo_ativo,
                    (SELECT COUNT(*) FROM turmas t WHERE t.professor_id = p.id AND t.ativo = 1) as turmas_count
             FROM professores p
             INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = p.usuario_id
                AND tup.tenant_id = ?
                AND tup.papel_id = '.self::PAPEL_PROFESSOR.'
             LEFT JOIN usuarios u ON u.id = p.usuario_id
             WHERE p.cpf = ?',
            [$tenantId, $cpf]
        );

        if ($row) {
            return (array) $row;
        }

        $row = DB::selectOne(
            'SELECT NULL as id, u.nome, u.cpf, u.email, NULL as foto_url, 1 as ativo,
                    u.id as usuario_id, u.created_at, u.updated_at,
                    u.telefone,
                    tup.ativo as vinculo_ativo,
                    0 as turmas_count
             FROM usuarios u
             INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = u.id
                AND tup.tenant_id = ?
                AND tup.papel_id = '.self::PAPEL_PROFESSOR.'
             WHERE u.cpf = ?
               AND NOT EXISTS (
                   SELECT 1 FROM professores p WHERE p.usuario_id = u.id
               )',
            [$tenantId, $cpf]
        );

        return $row ? (array) $row : null;
    }

    public function findByCpfGlobal(string $cpf): ?array
    {
        $row = DB::selectOne(
            'SELECT p.id, p.nome, p.cpf, p.email, p.foto_url, p.ativo, p.usuario_id, p.created_at, p.updated_at,
                    u.telefone
             FROM professores p
             LEFT JOIN usuarios u ON u.id = p.usuario_id
             WHERE p.cpf = ?',
            [$cpf]
        );

        return $row ? (array) $row : null;
    }

    public function findByUsuarioId(int $usuarioId): ?array
    {
        $row = DB::table('professores')->where('usuario_id', $usuarioId)->first();

        return $row ? (array) $row : null;
    }

    public function pertenceAoTenant(int $professorId, int $tenantId): bool
    {
        return DB::table('professores as p')
            ->join('tenant_usuario_papel as tup', function ($join) use ($tenantId) {
                $join->on('tup.usuario_id', '=', 'p.usuario_id')
                    ->where('tup.tenant_id', $tenantId)
                    ->where('tup.papel_id', self::PAPEL_PROFESSOR)
                    ->where('tup.ativo', 1);
            })
            ->where('p.id', $professorId)
            ->exists();
    }

    public function criar(array $data): int
    {
        return (int) DB::table('professores')->insertGetId([
            'usuario_id' => $data['usuario_id'],
            'nome' => $data['nome'],
            'cpf' => $data['cpf'] ?? null,
            'email' => $data['email'] ?? null,
            'foto_url' => $data['foto_url'] ?? null,
            'ativo' => $data['ativo'] ?? 1,
        ]);
    }

    /**
     * Atualiza professores (nome, cpf, email, foto_url, ativo) e os campos
     * espelhados em usuarios (email, telefone, cpf, nome), como na Slim.
     */
    public function atualizar(int $id, array $data): bool
    {
        $professor = $this->findById($id);
        if (! $professor) {
            return false;
        }

        $professorPayload = $this->apenasCampos($data, ['nome', 'cpf', 'email', 'foto_url', 'ativo']);
        if ($professorPayload !== []) {
            $professorPayload['updated_at'] = DB::raw('CURRENT_TIMESTAMP');
            DB::table('professores')->where('id', $id)->update($professorPayload);
        }

        $usuarioPayload = $this->apenasCampos($data, ['email', 'telefone', 'cpf', 'nome']);
        if ($usuarioPayload !== []) {
            $usuarioPayload['updated_at'] = DB::raw('CURRENT_TIMESTAMP');
            DB::table('usuarios')->where('id', $professor['usuario_id'])->update($usuarioPayload);
        }

        return true;
    }

    public function softDelete(int $id): bool
    {
        DB::table('professores')->where('id', $id)->update([
            'ativo' => 0,
            'updated_at' => DB::raw('CURRENT_TIMESTAMP'),
        ]);

        return true;
    }

    /**
     * Cria (ou reativa) o vínculo papel_id = 2 do professor com o tenant.
     */
    public function associarAoTenant(int $professorId, int $tenantId, bool $ativo = true): bool
    {
        $professor = $this->findById($professorId);
        if (! $professor || ! $professor['usuario_id']) {
            return false;
        }

        $usuarioId = (int) $professor['usuario_id'];
        $vinculo = DB::table('tenant_usuario_papel')
            ->where('tenant_id', $tenantId)
            ->where('usuario_id', $usuarioId)
            ->where('papel_id', self::PAPEL_PROFESSOR);

        if ($vinculo->exists()) {
            DB::table('tenant_usuario_papel')
                ->where('tenant_id', $tenantId)
                ->where('usuario_id', $usuarioId)
                ->where('papel_id', self::PAPEL_PROFESSOR)
                ->update([
                    'ativo' => $ativo ? 1 : 0,
                    'updated_at' => DB::raw('CURRENT_TIMESTAMP'),
                ]);

            return true;
        }

        DB::table('tenant_usuario_papel')->insert([
            'tenant_id' => $tenantId,
            'usuario_id' => $usuarioId,
            'papel_id' => self::PAPEL_PROFESSOR,
            'ativo' => $ativo ? 1 : 0,
        ]);

        return true;
    }

    /**
     * Turmas do professor no tenant (mesma query de Turma::listarPorProfessor).
     */
    public function listarTurmas(int $professorId, int $tenantId, bool $apenasAtivas = false): array
    {
        $sql = "SELECT t.*,
                m.nome as modalidade_nome,
                m.icone as modalidade_icone,
                m.cor as modalidade_cor,
                d.data as dia_data,
                (SELECT COUNT(*) FROM inscricoes_turmas it WHERE it.turma_id = t.id AND it.ativo = 1 AND it.status = 'ativa') as alunos_count
                FROM turmas t
                LEFT JOIN modalidades m ON t.modalidade_id = m.id
                LEFT JOIN dias d ON t.dia_id = d.id
                WHERE t.professor_id = ? AND t.tenant_id = ?";

        if ($apenasAtivas) {
            $sql .= ' AND t.ativo = 1';
        }

        $sql .= ' ORDER BY d.data ASC, t.horario_inicio ASC';

        return array_map(fn ($row) => (array) $row, DB::select($sql, [$professorId, $tenantId]));
    }

    /**
     * Usuário com o CPF em qualquer tenant (fallback legado em alunos.cpf).
     */
    public function findUsuarioByCpfGlobal(string $cpf): ?array
    {
        $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf) ?: '';
        if ($cpfLimpo === '') {
            return null;
        }

        $row = DB::table('usuarios')->where('cpf', $cpfLimpo)->first();
        if ($row) {
            return (array) $row;
        }

        $row = DB::table('alunos as a')
            ->join('usuarios as u', 'u.id', '=', 'a.usuario_id')
            ->where('a.cpf', $cpfLimpo)
            ->select('u.*')
            ->first();

        return $row ? (array) $row : null;
    }

    public function emailEmUsoPorOutroUsuario(string $email, int $usuarioId): bool
    {
        return DB::table('usuarios')
            ->where('email', $email)
            ->where('id', '!=', $usuarioId)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $campos
     * @return array<string, mixed>
     */
    private function apenasCampos(array $data, array $campos): array
    {
        $payload = [];
        foreach ($campos as $campo) {
            if (isset($data[$campo])) {
                $payload[$campo] = $data[$campo];
            }
        }

        return $payload;
    }
}
