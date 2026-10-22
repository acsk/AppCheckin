<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

/**
 * Queries admin de professores (painel).
 *
 * Vínculo professor↔tenant vive em tenant_usuario_papel (papel_id = 2);
 * o cadastro em professores é global (nome, cpf, email, foto, usuario_id, CREF…);
 * o que é de cada academia fica em professor_tenant e professor_modalidades.
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
                    p.whatsapp,
                    p.cref,
                    p.cref_validade,
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

        $professores = array_map(fn ($row) => (array) $row, DB::select($sql, [$tenantId]));
        $ids = array_map(fn ($p) => (int) $p['id'], $professores);
        $modalidades = $this->modalidadesPorProfessor($ids, $tenantId);
        $aulas = $this->aulasDaSemana($ids, $tenantId);

        foreach ($professores as &$p) {
            $p['modalidades'] = $modalidades[(int) $p['id']] ?? [];
            $p['aulas_semana'] = count($aulas[(int) $p['id']] ?? []);
            $p['proxima_aula'] = $aulas[(int) $p['id']][0] ?? null;
        }

        return $professores;
    }

    /**
     * Modalidades (desta academia) que cada professor dá.
     *
     * @param  list<int>  $ids
     * @return array<int, list<array{id: int, nome: string}>>
     */
    public function modalidadesPorProfessor(array $ids, int $tenantId): array
    {
        if ($ids === []) {
            return [];
        }

        $porProfessor = [];
        DB::table('professor_modalidades as pm')
            ->join('modalidades as m', 'm.id', '=', 'pm.modalidade_id')
            ->where('m.tenant_id', $tenantId)
            ->whereIn('pm.professor_id', $ids)
            ->orderBy('m.nome')
            ->get(['pm.professor_id', 'm.id', 'm.nome'])
            ->each(function ($r) use (&$porProfessor) {
                $porProfessor[(int) $r->professor_id][] = ['id' => (int) $r->id, 'nome' => $r->nome];
            });

        return $porProfessor;
    }

    /**
     * Aulas de hoje até daqui a 6 dias que ainda não começaram, em ordem: a primeira é a próxima.
     *
     * @param  list<int>  $ids
     * @return array<int, list<array{data: string, horario_inicio: string, horario_fim: ?string, nome: ?string}>>
     */
    private function aulasDaSemana(array $ids, int $tenantId): array
    {
        if ($ids === []) {
            return [];
        }

        $agora = now();
        $porProfessor = [];
        DB::table('turmas as t')
            ->join('dias as d', 'd.id', '=', 't.dia_id')
            ->leftJoin('modalidades as m', 'm.id', '=', 't.modalidade_id')
            ->where('t.tenant_id', $tenantId)
            ->where('t.ativo', 1)
            ->whereIn('t.professor_id', $ids)
            ->whereBetween('d.data', [$agora->toDateString(), $agora->copy()->addDays(6)->toDateString()])
            ->orderBy('d.data')->orderBy('t.horario_inicio')
            ->get(['t.professor_id', 'd.data', 't.horario_inicio', 't.horario_fim', 't.nome', 'm.nome as modalidade'])
            ->each(function ($r) use (&$porProfessor, $agora) {
                $data = substr((string) $r->data, 0, 10);
                if ($data.' '.$r->horario_inicio < $agora->format('Y-m-d H:i:s')) {
                    return;
                }
                $porProfessor[(int) $r->professor_id][] = [
                    'data' => $data,
                    'horario_inicio' => substr((string) $r->horario_inicio, 0, 5),
                    'horario_fim' => $r->horario_fim ? substr((string) $r->horario_fim, 0, 5) : null,
                    'nome' => $r->modalidade ?: $r->nome,
                ];
            });

        return $porProfessor;
    }

    public function findById(int $id, ?int $tenantId = null): ?array
    {
        if ($tenantId !== null) {
            $sql = 'SELECT p.id, p.nome, p.cpf, p.email, p.foto_url, p.ativo, p.usuario_id, p.created_at, p.updated_at,
                           p.data_nascimento, p.whatsapp, p.cref, p.cref_validade,
                           p.emergencia_nome, p.emergencia_telefone, p.emergencia_parentesco,
                           u.telefone, u.cep, u.logradouro, u.numero, u.complemento, u.bairro, u.cidade, u.estado,
                           tup.ativo as vinculo_ativo,
                           pt.vinculo_id, vp.codigo as vinculo_codigo, vp.nome as vinculo_nome, pt.admitido_em, pt.observacoes
                    FROM professores p
                    INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = p.usuario_id
                        AND tup.tenant_id = ?
                        AND tup.papel_id = '.self::PAPEL_PROFESSOR.'
                    LEFT JOIN usuarios u ON u.id = p.usuario_id
                    LEFT JOIN professor_tenant pt ON pt.professor_id = p.id AND pt.tenant_id = tup.tenant_id
                    LEFT JOIN vinculos_professor vp ON vp.id = pt.vinculo_id
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
        if (! $row) {
            return null;
        }

        $professor = (array) $row;
        if ($tenantId !== null) {
            $professor['modalidades'] = $this->modalidadesPorProfessor([$id], $tenantId)[$id] ?? [];
            $professor['modalidade_ids'] = array_column($professor['modalidades'], 'id');
            $professor['turmas_count'] = $this->contarTurmasAtivas($id, $tenantId);
            $aulas = $this->aulasDaSemana([$id], $tenantId)[$id] ?? [];
            $professor['aulas_semana'] = count($aulas);
            $professor['proxima_aula'] = $aulas[0] ?? null;
        }

        return $professor;
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

    /**
     * Perfil de professor (professores) do usuário: reativa o existente ou cria a partir de usuarios.
     * Quem ganha o papel Professor (Equipe, Usuários) precisa dele para aparecer nas aulas.
     */
    public function garantirPerfil(int $usuarioId): ?int
    {
        $existente = DB::table('professores')->where('usuario_id', $usuarioId)->orderBy('id')->first();
        if ($existente) {
            if (! (int) $existente->ativo) {
                DB::table('professores')->where('id', $existente->id)->update(['ativo' => 1, 'updated_at' => DB::raw('CURRENT_TIMESTAMP')]);
            }

            return (int) $existente->id;
        }

        $usuario = DB::table('usuarios')->where('id', $usuarioId)->first(['nome', 'cpf', 'email']);
        if (! $usuario) {
            return null;
        }

        $cpf = preg_replace('/[^0-9]/', '', (string) $usuario->cpf) ?: null;
        if ($cpf !== null && ($porCpf = DB::table('professores')->where('cpf', $cpf)->first())) {
            // Cadastro legado sem login com o mesmo CPF: é a mesma pessoa
            if ($porCpf->usuario_id === null) {
                DB::table('professores')->where('id', $porCpf->id)
                    ->update(['usuario_id' => $usuarioId, 'ativo' => 1, 'updated_at' => DB::raw('CURRENT_TIMESTAMP')]);

                return (int) $porCpf->id;
            }
            $cpf = null; // professores.cpf é UNIQUE e o CPF já está em outro perfil (unificação pelo suporte)
        }

        return $this->criar([
            'usuario_id' => $usuarioId,
            'nome' => $usuario->nome,
            'cpf' => $cpf,
            'email' => $usuario->email,
        ]);
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

    /** Campos que o formulário pode limpar (null apaga). */
    private const PROFESSOR_OPCIONAIS = ['data_nascimento', 'whatsapp', 'cref', 'cref_validade', 'emergencia_nome', 'emergencia_telefone', 'emergencia_parentesco'];

    private const ENDERECO = ['cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'estado'];

    /**
     * Atualiza professores (nome, cpf, email, foto_url, ativo + dados profissionais) e os campos
     * espelhados em usuarios (email, telefone, cpf, nome + endereço), como na Slim.
     */
    public function atualizar(int $id, array $data): bool
    {
        $professor = $this->findById($id);
        if (! $professor) {
            return false;
        }

        $professorPayload = $this->apenasCampos($data, ['nome', 'cpf', 'email', 'foto_url', 'ativo'])
            + array_intersect_key($data, array_flip(self::PROFESSOR_OPCIONAIS));
        if ($professorPayload !== []) {
            $professorPayload['updated_at'] = DB::raw('CURRENT_TIMESTAMP');
            DB::table('professores')->where('id', $id)->update($professorPayload);
        }

        $usuarioPayload = $this->apenasCampos($data, ['email', 'cpf', 'nome'])
            + array_intersect_key($data, array_flip(['telefone', ...self::ENDERECO]));
        if ($usuarioPayload !== []) {
            $usuarioPayload['updated_at'] = DB::raw('CURRENT_TIMESTAMP');
            DB::table('usuarios')->where('id', $professor['usuario_id'])->update($usuarioPayload);
        }

        return true;
    }

    /**
     * Dados do professor nesta academia (professor_tenant) e modalidades que ele dá.
     * Só mexe no que veio em $data; modalidade_ids substitui as modalidades desta academia.
     *
     * @param  array<string, mixed>  $data
     */
    public function salvarDadosNaAcademia(int $professorId, int $tenantId, array $data): void
    {
        $campos = array_intersect_key($data, array_flip(['vinculo_id', 'admitido_em', 'observacoes']));
        if ($campos !== []) {
            $chave = ['tenant_id' => $tenantId, 'professor_id' => $professorId];
            if (DB::table('professor_tenant')->where($chave)->exists()) {
                DB::table('professor_tenant')->where($chave)->update($campos + ['updated_at' => now()]);
            } else {
                DB::table('professor_tenant')->insert($chave + $campos + ['created_at' => now(), 'updated_at' => now()]);
            }
        }

        if (array_key_exists('modalidade_ids', $data)) {
            $this->removerModalidadesDoTenant($professorId, $tenantId);
            $ids = array_values(array_unique(array_map('intval', (array) $data['modalidade_ids'])));
            DB::table('professor_modalidades')->insert(
                array_map(fn ($m) => ['professor_id' => $professorId, 'modalidade_id' => $m], $ids)
            );
        }
    }

    /** Tira o professor desta academia: papel, dados e modalidades daqui. O perfil global fica. */
    public function removerDoTenant(int $professorId, int $tenantId): void
    {
        $usuarioId = DB::table('professores')->where('id', $professorId)->value('usuario_id');
        DB::table('tenant_usuario_papel')
            ->where('tenant_id', $tenantId)
            ->where('usuario_id', $usuarioId)
            ->where('papel_id', self::PAPEL_PROFESSOR)
            ->delete();
        DB::table('professor_tenant')->where(['tenant_id' => $tenantId, 'professor_id' => $professorId])->delete();
        $this->removerModalidadesDoTenant($professorId, $tenantId);
    }

    public function contarTurmasAtivas(int $professorId, int $tenantId): int
    {
        return DB::table('turmas')->where('professor_id', $professorId)->where('tenant_id', $tenantId)->where('ativo', 1)->count();
    }

    /** @return list<array{id: int, codigo: string, nome: string}> */
    public function listarVinculos(): array
    {
        return DB::table('vinculos_professor')->where('ativo', 1)->orderBy('ordem')->orderBy('nome')
            ->get(['id', 'codigo', 'nome'])
            ->map(fn ($v) => ['id' => (int) $v->id, 'codigo' => $v->codigo, 'nome' => $v->nome])->all();
    }

    public function vinculoExiste(int $id): bool
    {
        return DB::table('vinculos_professor')->where('id', $id)->where('ativo', 1)->exists();
    }

    /**
     * @param  list<int>  $ids
     */
    public function modalidadesSaoDoTenant(array $ids, int $tenantId): bool
    {
        $ids = array_values(array_unique($ids));

        return DB::table('modalidades')->where('tenant_id', $tenantId)->whereIn('id', $ids)->count() === count($ids);
    }

    /** Usuário dono do e-mail (para avisar no formulário que já existe cadastro). */
    public function usuarioPorEmail(string $email): ?array
    {
        $u = DB::table('usuarios')->where('email', $email)->first(['id', 'nome']);

        return $u ? ['id' => (int) $u->id, 'nome' => $u->nome] : null;
    }

    private function removerModalidadesDoTenant(int $professorId, int $tenantId): void
    {
        DB::table('professor_modalidades')
            ->where('professor_id', $professorId)
            ->whereIn('modalidade_id', DB::table('modalidades')->where('tenant_id', $tenantId)->select('id'))
            ->delete();
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
