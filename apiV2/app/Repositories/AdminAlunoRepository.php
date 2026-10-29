<?php

namespace App\Repositories;

use App\Support\Pessoa;
use Illuminate\Support\Facades\DB;

/**
 * Queries admin de alunos (painel). Mantém o AlunoRepository mobile enxuto.
 */
class AdminAlunoRepository
{
    /**
     * Aluno (alias a) com pagamento em dia: matrícula ativa cujo acesso pago alcança hoje e sem
     * parcela pendente/atrasada já vencida. Vale para qualquer ciclo (mensal, trimestral...).
     * Um ? = tenant_id.
     */
    private const EM_DIA_SQL = "EXISTS (SELECT 1 FROM matriculas m JOIN status_matricula sm ON sm.id = m.status_id
        WHERE m.aluno_id = a.id AND m.tenant_id = ? AND sm.codigo = 'ativa'
        AND COALESCE(m.proxima_data_vencimento, m.data_vencimento) >= CURDATE()
        AND NOT EXISTS (SELECT 1 FROM pagamentos_plano pp WHERE pp.matricula_id = m.id
            AND pp.status_pagamento_id IN (1, 3) AND pp.data_vencimento < CURDATE()))";

    public function listarPorTenant(int $tenantId, bool $apenasAtivos = false): array
    {
        $query = DB::table('alunos as a')
            ->join('tenant_usuario_papel as tup', function ($join) use ($tenantId) {
                $join->on('tup.usuario_id', '=', 'a.usuario_id')
                    ->where('tup.tenant_id', $tenantId)
                    ->where('tup.papel_id', 1);
            })
            ->leftJoin('usuarios as u', 'u.id', '=', 'a.usuario_id')
            ->select([
                'a.id', 'a.nome', 'a.ativo', 'a.usuario_id', 'u.email',
                ...Pessoa::selects(['telefone', 'cpf', 'foto_caminho', ...Pessoa::ENDERECO, 'sexo']),
            ])
            ->orderBy('a.nome');

        if ($apenasAtivos) {
            $query->where('a.ativo', 1)->where('tup.ativo', 1);
        }

        return $query->get()->map(fn ($r) => (array) $r)->all();
    }

    public function listarBasico(int $tenantId): array
    {
        return DB::table('alunos as a')
            ->join('tenant_usuario_papel as tup', function ($join) use ($tenantId) {
                $join->on('tup.usuario_id', '=', 'a.usuario_id')
                    ->where('tup.tenant_id', $tenantId)
                    ->where('tup.papel_id', 1)
                    ->where('tup.ativo', 1);
            })
            ->leftJoin('usuarios as u', 'u.id', '=', 'a.usuario_id')
            ->where('a.ativo', 1)
            ->orderBy('a.nome')
            ->get(['a.id', 'a.nome', 'u.email', 'a.usuario_id', ...Pessoa::selects(['cpf'])])
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    public function listarPaginado(int $tenantId, int $pagina, int $porPagina, ?string $busca): array
    {
        $query = DB::table('alunos as a')
            ->join('tenant_usuario_papel as tup', function ($join) use ($tenantId) {
                $join->on('tup.usuario_id', '=', 'a.usuario_id')
                    ->where('tup.tenant_id', $tenantId)
                    ->where('tup.papel_id', 1);
            })
            ->leftJoin('usuarios as u', 'u.id', '=', 'a.usuario_id')
            ->where('a.ativo', 1)
            ->where('tup.ativo', 1)
            ->select([
                'a.id', 'a.nome', 'a.ativo', 'a.usuario_id', 'u.email',
                ...Pessoa::selects(['telefone', 'cpf', 'foto_caminho']),
            ])
            ->orderBy('a.nome');

        if ($busca) {
            $like = '%'.$busca.'%';
            $query->where(function ($q) use ($like) {
                $q->where('a.nome', 'like', $like)
                    ->orWhere('u.email', 'like', $like)
                    ->orWhere(Pessoa::col('cpf'), 'like', $like);
            });
        }

        return $query
            ->offset(($pagina - 1) * $porPagina)
            ->limit($porPagina)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * Lista do painel paginada no banco: busca (nome, e-mail, CPF/telefone), filtro e contagens das pílulas.
     * filtro: todos | ativos | pendentes (com plano e sem pagamento em dia) | sem_plano | inativos.
     * "Pagamento em dia" segue EM_DIA_SQL (o mesmo de temPagamentoAtivo).
     *
     * @return array{rows: list<array<string, mixed>>, total: int, contagens: array<string, int>}
     */
    public function listarPainel(int $tenantId, string $busca, string $filtro, int $pagina, int $porPagina): array
    {
        $temPlano = 'EXISTS (SELECT 1 FROM matriculas m JOIN status_matricula sm ON sm.id = m.status_id
            WHERE m.aluno_id = a.id AND m.tenant_id = ? AND sm.codigo = \'ativa\')';
        $emDia = self::EM_DIA_SQL;

        $base = DB::table('alunos as a')
            ->join('tenant_usuario_papel as tup', function ($join) use ($tenantId) {
                $join->on('tup.usuario_id', '=', 'a.usuario_id')
                    ->where('tup.tenant_id', $tenantId)
                    ->where('tup.papel_id', 1);
            })
            ->leftJoin('usuarios as u', 'u.id', '=', 'a.usuario_id')
            ->select([
                'a.id', 'a.nome', 'a.ativo', 'a.usuario_id', 'u.email',
                ...Pessoa::selects(['telefone', 'cpf', 'foto_caminho']),
            ])
            ->selectRaw("CASE WHEN {$temPlano} THEN 1 ELSE 0 END as tem_plano", [$tenantId])
            ->selectRaw("CASE WHEN {$emDia} THEN 1 ELSE 0 END as em_dia", [$tenantId]);

        $busca = trim($busca);
        if ($busca !== '') {
            $like = '%'.$busca.'%';
            $digitos = preg_replace('/\D/', '', $busca);
            $base->where(function ($q) use ($like, $digitos) {
                $q->where('a.nome', 'like', $like)->orWhere('u.email', 'like', $like);
                if (strlen($digitos) >= 3) {
                    // CPF e telefone são gravados só com dígitos
                    $q->orWhere(Pessoa::col('cpf'), 'like', '%'.$digitos.'%')->orWhere(Pessoa::col('telefone'), 'like', '%'.$digitos.'%');
                }
            });
        }

        // Mesma regra das pílulas da tela (status vem de alunos.ativo)
        $cond = [
            'todos' => '1 = 1',
            'ativos' => 'x.ativo = 1',
            'inativos' => '(x.ativo = 0 OR x.ativo IS NULL)',
            'sem_plano' => 'x.tem_plano = 0',
            'pendentes' => 'x.tem_plano = 1 AND x.em_dia = 0',
        ];
        $filtro = isset($cond[$filtro]) ? $filtro : 'todos';

        $agg = DB::query()->fromSub($base, 'x')->selectRaw(implode(', ', array_map(
            fn ($k, $c) => "SUM(CASE WHEN {$c} THEN 1 ELSE 0 END) as {$k}", array_keys($cond), $cond,
        )))->first();
        $contagens = [];
        foreach (array_keys($cond) as $k) {
            $contagens[$k] = (int) ($agg->{$k} ?? 0);
        }

        $rows = DB::query()->fromSub($base, 'x')->whereRaw($cond[$filtro])
            ->orderBy('x.nome')->orderBy('x.id')
            ->offset(($pagina - 1) * $porPagina)->limit($porPagina)
            ->get()->map(fn ($r) => (array) $r)->all();

        return ['rows' => $rows, 'total' => $contagens[$filtro], 'contagens' => $contagens];
    }

    /**
     * Plano ativo, pagamento em dia e check-ins de vários alunos de uma vez (lista do painel).
     *
     * @param  list<int>  $alunoIds
     * @return array{matriculas: array<int, array<string, mixed>>, checkins: array<int, array{total: int, ultimo: ?string}>}
     */
    public function resumoEmLote(array $alunoIds, int $tenantId): array
    {
        if ($alunoIds === []) {
            return ['matriculas' => [], 'checkins' => []];
        }
        $matriculas = [];
        // Mais recente primeiro: a primeira de cada aluno é a mesma que matriculaAtiva() devolveria
        foreach (DB::table('matriculas as m')
            ->join('planos as p', 'p.id', '=', 'm.plano_id')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->leftJoin('modalidades as mo', 'mo.id', '=', 'p.modalidade_id')
            ->whereIn('m.aluno_id', $alunoIds)->where('m.tenant_id', $tenantId)->where('sm.codigo', 'ativa')
            ->orderByDesc('m.created_at')
            ->get(['m.*', 'p.nome as plano_nome', 'p.valor as plano_valor', 'mo.nome as modalidade_nome', 'mo.icone as modalidade_icone']) as $m) {
            $matriculas[(int) $m->aluno_id] ??= (array) $m;
        }
        $checkins = DB::table('checkins')->whereIn('aluno_id', $alunoIds)->where('tenant_id', $tenantId)
            ->groupBy('aluno_id')->selectRaw('aluno_id, COUNT(*) as total, MAX(created_at) as ultimo')->get()
            ->mapWithKeys(fn ($r) => [(int) $r->aluno_id => ['total' => (int) $r->total, 'ultimo' => $r->ultimo]])->all();

        return ['matriculas' => $matriculas, 'checkins' => $checkins];
    }

    public function contarPorTenant(int $tenantId, bool $apenasAtivos = true): int
    {
        $query = DB::table('alunos as a')
            ->join('tenant_usuario_papel as tup', function ($join) use ($tenantId) {
                $join->on('tup.usuario_id', '=', 'a.usuario_id')
                    ->where('tup.tenant_id', $tenantId)
                    ->where('tup.papel_id', 1);
            });

        if ($apenasAtivos) {
            $query->where('a.ativo', 1)->where('tup.ativo', 1);
        }

        return (int) $query->distinct('a.id')->count('a.id');
    }

    public function findById(int $id, ?int $tenantId = null): ?array
    {
        $query = DB::table('alunos as a')
            ->leftJoin('usuarios as u', 'u.id', '=', 'a.usuario_id')
            ->where('a.id', $id)
            ->select('a.*', 'u.email', ...Pessoa::selects());

        if ($tenantId !== null) {
            $query->join('tenant_usuario_papel as tup', function ($join) use ($tenantId) {
                $join->on('tup.usuario_id', '=', 'a.usuario_id')
                    ->where('tup.tenant_id', $tenantId)
                    ->where('tup.papel_id', 1);
            });
        }

        $row = $query->first();

        return $row ? (array) $row : null;
    }

    public function findByUsuarioId(int $usuarioId): ?array
    {
        $row = DB::table('alunos as a')
            ->leftJoin('usuarios as u', 'u.id', '=', 'a.usuario_id')
            ->where('a.usuario_id', $usuarioId)
            ->select('a.*', 'u.email', ...Pessoa::selects())
            ->first();

        return $row ? (array) $row : null;
    }

    public function findByUsuarioIdAndTenant(int $usuarioId, int $tenantId): ?array
    {
        $row = DB::table('alunos as a')
            ->join('tenant_usuario_papel as tup', function ($join) use ($tenantId) {
                $join->on('tup.usuario_id', '=', 'a.usuario_id')
                    ->where('tup.tenant_id', $tenantId)
                    ->where('tup.papel_id', 1)
                    ->where('tup.ativo', 1);
            })
            ->leftJoin('usuarios as u', 'u.id', '=', 'a.usuario_id')
            ->where('a.usuario_id', $usuarioId)
            ->select('a.*', 'u.email', ...Pessoa::selects())
            ->first();

        return $row ? (array) $row : null;
    }

    public function findByCpf(string $cpf): ?array
    {
        $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf);
        $row = DB::table('alunos as a')
            ->leftJoin('usuarios as u', 'u.id', '=', 'a.usuario_id')
            ->where(Pessoa::col('cpf'), $cpfLimpo)
            ->select('a.*', 'u.email', ...Pessoa::selects())
            ->first();

        return $row ? (array) $row : null;
    }

    public function findRawById(int $id): ?array
    {
        $row = DB::table('alunos')->where('id', $id)->first();

        return $row ? (array) $row : null;
    }

    /** Dados pessoais vão para usuarios (Pessoa); alunos guarda o vínculo, o nome (espelho) e o ativo. */
    public function create(array $data): int
    {
        $id = (int) DB::table('alunos')->insertGetId(array_diff_key($this->normalizeAlunoPayload($data, true), array_flip(Pessoa::CAMPOS)));
        if (! empty($data['usuario_id'])) {
            app(UsuarioRepository::class)->gravarDadosPessoais((int) $data['usuario_id'], array_intersect_key($data, array_flip(Pessoa::CAMPOS)));
        }

        return $id;
    }

    public function update(int $id, array $data): void
    {
        $payload = array_diff_key($this->normalizeAlunoPayload($data, false), array_flip([...Pessoa::CAMPOS, 'nome']));
        $pessoais = array_intersect_key($data, array_flip([...Pessoa::CAMPOS, 'nome']));
        if ($pessoais !== [] && ($usuarioId = (int) DB::table('alunos')->where('id', $id)->value('usuario_id'))) {
            app(UsuarioRepository::class)->gravarDadosPessoais($usuarioId, $pessoais);
        }
        if ($payload === []) {
            return;
        }
        $payload['updated_at'] = now();
        DB::table('alunos')->where('id', $id)->update($payload);
    }

    public function softDelete(int $id): void
    {
        DB::table('alunos')->where('id', $id)->update([
            'ativo' => 0,
            'updated_at' => now(),
        ]);
    }

    public function desativarPapelAluno(int $usuarioId, int $tenantId): void
    {
        DB::table('tenant_usuario_papel')
            ->where('usuario_id', $usuarioId)
            ->where('tenant_id', $tenantId)
            ->where('papel_id', 1)
            ->update([
                'ativo' => 0,
                'updated_at' => now(),
            ]);
    }

    public function garantirVinculoAluno(int $usuarioId, int $tenantId): void
    {
        $exists = DB::table('tenant_usuario_papel')
            ->where('usuario_id', $usuarioId)
            ->where('tenant_id', $tenantId)
            ->where('papel_id', 1)
            ->exists();

        if ($exists) {
            DB::table('tenant_usuario_papel')
                ->where('usuario_id', $usuarioId)
                ->where('tenant_id', $tenantId)
                ->where('papel_id', 1)
                ->update(['ativo' => 1, 'updated_at' => now()]);

            return;
        }

        DB::table('tenant_usuario_papel')->insert([
            'usuario_id' => $usuarioId,
            'tenant_id' => $tenantId,
            'papel_id' => 1,
            'ativo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function jaAssociadoAoTenant(int $usuarioId, int $tenantId): bool
    {
        return DB::table('tenant_usuario_papel')
            ->where('usuario_id', $usuarioId)
            ->where('tenant_id', $tenantId)
            ->where('papel_id', 1)
            ->exists();
    }

    public function tenantsDoAluno(int $usuarioId): array
    {
        return DB::table('tenant_usuario_papel as tup')
            ->join('tenants as t', 't.id', '=', 'tup.tenant_id')
            ->where('tup.usuario_id', $usuarioId)
            ->where('tup.papel_id', 1)
            ->get(['t.id', 't.nome', 't.slug'])
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    public function matriculaAtiva(int $alunoId, int $tenantId): ?array
    {
        $row = DB::table('matriculas as m')
            ->join('planos as p', 'p.id', '=', 'm.plano_id')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->leftJoin('modalidades as mo', 'mo.id', '=', 'p.modalidade_id')
            ->where('m.aluno_id', $alunoId)
            ->where('m.tenant_id', $tenantId)
            ->where('sm.codigo', 'ativa')
            ->orderByDesc('m.created_at')
            ->select('m.*', 'p.nome as plano_nome', 'p.valor as plano_valor', 'mo.nome as modalidade_nome', 'mo.icone as modalidade_icone')
            ->first();

        return $row ? (array) $row : null;
    }

    public function historicoPlanos(int $usuarioId, int $tenantId): array
    {
        return DB::table('matriculas as m')
            ->join('planos as p', 'p.id', '=', 'm.plano_id')
            ->join('alunos as a', 'a.id', '=', 'm.aluno_id')
            ->leftJoin('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->leftJoin('modalidades as mo', 'mo.id', '=', 'p.modalidade_id')
            ->where('a.usuario_id', $usuarioId)
            ->where('m.tenant_id', $tenantId)
            ->orderByDesc('m.created_at')
            ->select('m.*', 'p.nome as plano_nome', 'p.valor as plano_valor', 'sm.codigo as status_codigo', 'sm.nome as status_nome', 'mo.nome as modalidade_nome', 'mo.icone as modalidade_icone')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * @return array{total: int, ultimo: ?string}
     */
    public function resumoCheckins(int $alunoId, int $tenantId): array
    {
        $row = DB::table('checkins')
            ->where('aluno_id', $alunoId)
            ->where('tenant_id', $tenantId)
            ->selectRaw('COUNT(*) as total, MAX(created_at) as ultimo')
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'ultimo' => $row->ultimo ?? null,
        ];
    }

    public function temPagamentoAtivo(int $alunoId, int $tenantId): bool
    {
        return DB::table('alunos as a')->where('a.id', $alunoId)->whereRaw(self::EM_DIA_SQL, [$tenantId])->exists();
    }

    public function hardDelete(int $id): bool
    {
        return (bool) DB::transaction(function () use ($id) {
            $aluno = DB::table('alunos')->where('id', $id)->first();
            if (! $aluno) {
                return false;
            }

            $usuarioId = (int) $aluno->usuario_id;

            DB::table('checkins')->where('aluno_id', $id)->delete();
            DB::table('matriculas')->where('aluno_id', $id)->delete();
            DB::table('pagamentos_plano')->where('aluno_id', $id)->delete();
            DB::table('wod_resultados')->where('usuario_id', $usuarioId)->delete();
            DB::table('tenant_usuario_papel')->where('usuario_id', $usuarioId)->delete();
            DB::table('email_logs')->where('usuario_id', $usuarioId)->delete();
            DB::table('alunos')->where('id', $id)->delete();
            DB::table('usuarios')->where('id', $usuarioId)->delete();

            return true;
        });
    }

    public function getDeletePreview(int $alunoId): array
    {
        $aluno = $this->findById($alunoId);
        if (! $aluno) {
            return ['error' => 'Aluno não encontrado'];
        }

        $usuarioId = (int) $aluno['usuario_id'];

        $checkins = DB::table('checkins')
            ->where('aluno_id', $alunoId)
            ->selectRaw('COUNT(*) as total, MIN(data_checkin) as primeira_data, MAX(data_checkin) as ultima_data')
            ->first();

        $matriculas = DB::table('matriculas as m')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->where('m.aluno_id', $alunoId)
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN sm.codigo = 'ativa' THEN 1 ELSE 0 END) as ativas")
            ->first();

        $matriculasDetail = DB::table('matriculas as m')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->where('m.aluno_id', $alunoId)
            ->orderByDesc('m.data_inicio')
            ->limit(5)
            ->get(['m.id', 'm.plano_id', 'sm.codigo as status', 'm.data_inicio', 'm.data_vencimento'])
            ->map(fn ($r) => (array) $r)
            ->all();

        $pagamentos = DB::table('pagamentos_plano')
            ->where('aluno_id', $alunoId)
            ->selectRaw('COUNT(*) as total,
                SUM(CASE WHEN status_pagamento_id = 2 THEN 1 ELSE 0 END) as pagos,
                SUM(CASE WHEN status_pagamento_id = 1 THEN 1 ELSE 0 END) as pendentes,
                SUM(valor) as valor_total')
            ->first();

        $wods = DB::table('wod_resultados')->where('usuario_id', $usuarioId)->count();
        $emails = DB::table('email_logs')
            ->where('usuario_id', $usuarioId)
            ->selectRaw("COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as enviados,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as falhados")
            ->first();

        $usuarioData = DB::table('usuarios')
            ->where('id', $usuarioId)
            ->first(['id', 'email', 'email_global', 'cpf', 'telefone', 'ativo', 'created_at']);

        $tenants = DB::table('tenant_usuario_papel')
            ->where('usuario_id', $usuarioId)
            ->get(['tenant_id', 'ativo'])
            ->map(fn ($r) => ['tenant_id' => $r->tenant_id, 'ativo' => $r->ativo])
            ->all();

        $papeis = DB::table('tenant_usuario_papel')
            ->where('usuario_id', $usuarioId)
            ->get(['tenant_id', 'papel_id', 'ativo'])
            ->map(fn ($r) => (array) $r)
            ->all();

        $totalRegistros = (int) ($checkins->total ?? 0)
            + (int) ($matriculas->total ?? 0)
            + (int) ($pagamentos->total ?? 0)
            + (int) $wods
            + (int) ($emails->total ?? 0);

        return [
            'success' => true,
            'resumo' => [
                'aluno_id' => $alunoId,
                'usuario_id' => $usuarioId,
                'total_registros' => $totalRegistros,
                'warning' => ((int) ($matriculas->ativas ?? 0) > 0) ? 'Este aluno tem matrículas ATIVAS' : null,
                'pode_deletar' => (int) ($matriculas->ativas ?? 0) === 0,
            ],
            'aluno' => [
                'id' => $aluno['id'],
                'nome' => $aluno['nome'],
                'email' => $aluno['email'],
                'cpf' => $aluno['cpf'],
                'telefone' => $aluno['telefone'],
                'data_nascimento' => $aluno['data_nascimento'] ?? null,
                'sexo' => $aluno['sexo'] ?? null,
                'ativo' => $aluno['ativo'],
            ],
            'usuario' => $usuarioData ? (array) $usuarioData : null,
            'dados' => [
                'checkins' => [
                    'total' => (int) ($checkins->total ?? 0),
                    'primeira_data' => $checkins->primeira_data ?? null,
                    'ultima_data' => $checkins->ultima_data ?? null,
                ],
                'matriculas' => [
                    'total' => (int) ($matriculas->total ?? 0),
                    'ativas' => (int) ($matriculas->ativas ?? 0),
                    'detalhes' => $matriculasDetail,
                ],
                'pagamentos' => [
                    'total' => (int) ($pagamentos->total ?? 0),
                    'pagos' => (int) ($pagamentos->pagos ?? 0),
                    'pendentes' => (int) ($pagamentos->pendentes ?? 0),
                    'valor_total' => (float) ($pagamentos->valor_total ?? 0),
                ],
                'wod_resultados' => ['total' => (int) $wods],
                'email_logs' => [
                    'total' => (int) ($emails->total ?? 0),
                    'enviados' => (int) ($emails->enviados ?? 0),
                    'falhados' => (int) ($emails->falhados ?? 0),
                ],
            ],
            'vinculos' => [
                'tenants' => $tenants,
                'papeis' => $papeis,
            ],
        ];
    }

    public function checkinsDoMes(int $alunoId, int $tenantId, int $mes, int $ano, ?int $modalidadeId): array
    {
        $dataEfetiva = 'COALESCE(c.data_checkin_date, DATE(c.data_checkin), DATE(d.data))';
        $query = DB::table('checkins as c')
            ->join('turmas as t', 't.id', '=', 'c.turma_id')
            ->join('dias as d', 'd.id', '=', 't.dia_id')
            ->join('modalidades as m', 'm.id', '=', 't.modalidade_id')
            ->join('alunos as a', 'a.id', '=', 'c.aluno_id')
            ->where('c.tenant_id', $tenantId)
            ->where('a.id', $alunoId)
            ->whereRaw("MONTH({$dataEfetiva}) = ?", [$mes])
            ->whereRaw("YEAR({$dataEfetiva}) = ?", [$ano])
            ->orderByRaw("{$dataEfetiva} ASC")
            ->orderBy('t.horario_inicio')
            ->selectRaw("
                c.id,
                {$dataEfetiva} AS data_aula,
                DATE(d.data) AS data_turma,
                c.data_checkin,
                c.data_checkin_date,
                t.horario_inicio,
                t.horario_fim,
                t.nome AS turma_nome,
                m.id AS modalidade_id,
                m.nome AS modalidade,
                c.presente,
                c.registrado_por_admin,
                c.created_at
            ");

        if ($modalidadeId) {
            $query->where('m.id', $modalidadeId);
        }

        return $query->get()->map(fn ($r) => (array) $r)->all();
    }

    public function checkinsResumoMensal(int $alunoId, int $tenantId, ?int $modalidadeId): array
    {
        $dataEfetiva = 'COALESCE(c.data_checkin_date, DATE(c.data_checkin), DATE(d.data))';
        $query = DB::table('checkins as c')
            ->join('turmas as t', 't.id', '=', 'c.turma_id')
            ->join('dias as d', 'd.id', '=', 't.dia_id')
            ->join('modalidades as m', 'm.id', '=', 't.modalidade_id')
            ->join('alunos as a', 'a.id', '=', 'c.aluno_id')
            ->where('c.tenant_id', $tenantId)
            ->where('a.id', $alunoId)
            ->groupByRaw("YEAR({$dataEfetiva}), MONTH({$dataEfetiva})")
            ->orderByRaw("YEAR({$dataEfetiva}) DESC, MONTH({$dataEfetiva}) DESC")
            ->selectRaw("
                YEAR({$dataEfetiva}) AS ano,
                MONTH({$dataEfetiva}) AS mes,
                COUNT(*) AS total,
                SUM(c.presente = 1) AS presentes
            ");

        if ($modalidadeId) {
            $query->where('m.id', $modalidadeId);
        }

        return $query->get()->map(fn ($r) => (array) $r)->all();
    }

    /**
     * Colunas que ficam em alunos: o vínculo com o usuário, o nome (espelho) e o ativo.
     * Os dados pessoais são gravados em usuarios por create()/update().
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeAlunoPayload(array $data, bool $creating): array
    {
        $out = array_intersect_key($data, array_flip(['usuario_id', 'nome', 'ativo']));
        if (isset($out['nome'])) {
            $out['nome'] = mb_strtoupper(trim((string) $out['nome']), 'UTF-8');
        }
        if ($creating && ! isset($out['ativo'])) {
            $out['ativo'] = 1;
        }

        return $out;
    }
}
