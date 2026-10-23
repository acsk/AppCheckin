<?php

namespace App\Repositories;

use App\Support\Papel;
use App\Support\Pessoa;
use App\Support\ProspeccaoStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * Acesso a dados do módulo Prospecção. Todo método recebe o tenant e filtra por ele.
 * SQL portável (MySQL em produção, SQLite nos testes): sem DATEDIFF/DATE_SUB; datas calculadas no PHP.
 */
class ProspeccaoRepository
{
    /** Status de matrícula que significam vínculo em andamento (aluno não é elegível). */
    public const STATUS_MATRICULA_EM_ANDAMENTO = ['ativa', 'pendente', 'vencida', 'bloqueado'];

    // ---------------------------------------------------------------- parâmetros

    public function valorParametro(int $tenantId, string $codigo): ?string
    {
        $row = DB::table('parametros as p')
            ->leftJoin('parametros_tenant as pt', function ($j) use ($tenantId) {
                $j->on('pt.parametro_id', '=', 'p.id')->where('pt.tenant_id', $tenantId)->where('pt.ativo', 1);
            })
            ->where('p.codigo', $codigo)
            ->select('pt.valor', 'p.valor_padrao')
            ->first();

        return $row ? ($row->valor ?? $row->valor_padrao) : null;
    }

    public function salvarParametro(int $tenantId, string $codigo, string $valor, ?int $usuarioId): void
    {
        $parametroId = DB::table('parametros')->where('codigo', $codigo)->value('id');
        if (! $parametroId) {
            throw new \RuntimeException('Parâmetro '.$codigo.' não cadastrado (rode as migrations)');
        }

        DB::table('parametros_tenant')->updateOrInsert(
            ['tenant_id' => $tenantId, 'parametro_id' => $parametroId],
            ['valor' => $valor, 'ativo' => 1, 'atualizado_por' => $usuarioId, 'updated_at' => now()],
        );
    }

    // ---------------------------------------------------------------- sincronização

    /**
     * Matrículas canceladas elegíveis: a mais recente de cada aluno vem primeiro.
     * Exclui diárias avulsas, alunos sem vínculo de aluno no tenant e quem tem matrícula em andamento.
     *
     * @return list<object{id: int, aluno_id: int, data_cancelamento: ?string, data_atualizacao: string}>
     */
    public function matriculasCanceladasElegiveis(int $tenantId, string $dataMinima): array
    {
        $dataEfetiva = 'COALESCE(m.data_cancelamento, DATE(m.updated_at))';

        return DB::table('matriculas as m')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->join('planos as p', 'p.id', '=', 'm.plano_id')
            ->join('alunos as a', 'a.id', '=', 'm.aluno_id')
            ->join('tenant_usuario_papel as tup', function ($j) {
                $j->on('tup.usuario_id', '=', 'a.usuario_id')->on('tup.tenant_id', '=', 'm.tenant_id')->where('tup.papel_id', Papel::ALUNO);
            })
            ->where('m.tenant_id', $tenantId)
            ->where('sm.codigo', 'cancelada')
            ->where(fn ($q) => $q->where('m.tipo_cobranca', '!=', 'avulso')->orWhere('p.duracao_dias', '!=', 1)->orWhereNull('m.tipo_cobranca'))
            ->whereRaw($dataEfetiva.' >= ?', [$dataMinima])
            ->whereNotExists(fn ($q) => $this->matriculaEmAndamento($q, 'm.tenant_id', 'm.aluno_id'))
            ->orderBy('m.aluno_id')
            ->orderByRaw($dataEfetiva.' DESC')
            ->orderByDesc('m.id')
            ->select(['m.id', 'm.aluno_id', 'm.data_cancelamento', DB::raw('DATE(m.updated_at) as data_atualizacao')])
            ->get()
            ->all();
    }

    /**
     * Todas as matrículas canceladas do tenant, com o que decide a elegibilidade (para diagnóstico).
     *
     * @return list<object{id: int, aluno_id: ?int, diaria: int, data_efetiva: ?string, vinculo_aluno: int, em_andamento: int}>
     */
    public function matriculasCanceladasParaDiagnostico(int $tenantId): array
    {
        return DB::table('matriculas as m')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->leftJoin('planos as p', 'p.id', '=', 'm.plano_id')
            ->leftJoin('alunos as a', 'a.id', '=', 'm.aluno_id')
            ->where('m.tenant_id', $tenantId)
            ->where('sm.codigo', 'cancelada')
            ->select([
                'm.id', 'm.aluno_id',
                DB::raw("CASE WHEN m.tipo_cobranca = 'avulso' AND p.duracao_dias = 1 THEN 1 ELSE 0 END as diaria"),
                DB::raw('COALESCE(m.data_cancelamento, DATE(m.updated_at)) as data_efetiva'),
                DB::raw('CASE WHEN EXISTS (SELECT 1 FROM tenant_usuario_papel tup WHERE tup.usuario_id = a.usuario_id AND tup.tenant_id = m.tenant_id AND tup.papel_id = '.Papel::ALUNO.') THEN 1 ELSE 0 END as vinculo_aluno'),
                DB::raw("CASE WHEN EXISTS (SELECT 1 FROM matriculas m2 JOIN status_matricula s2 ON s2.id = m2.status_id WHERE m2.tenant_id = m.tenant_id AND m2.aluno_id = m.aluno_id AND s2.codigo IN ('".implode("','", self::STATUS_MATRICULA_EM_ANDAMENTO)."')) THEN 1 ELSE 0 END as em_andamento"),
            ])
            ->get()
            ->all();
    }

    public function contarOportunidades(int $tenantId, bool $abertas): int
    {
        $q = DB::table('prospeccao_oportunidades')->where('tenant_id', $tenantId);
        if ($abertas) {
            $q->whereNull('encerrada_em');
        }

        return $q->count();
    }

    /**
     * Alunos com ao menos uma matrícula cancelada no tenant (para iniciar prospecção manualmente),
     * com a cancelada mais recente e o que impede/ já existe.
     *
     * @return list<array<string, mixed>>
     */
    public function alunosComCancelamento(int $tenantId, ?string $buscaNome, ?string $buscaDigitos, int $limite): array
    {
        $dataEfetiva = 'COALESCE(m.data_cancelamento, DATE(m.updated_at))';
        $ultima = DB::table('matriculas as mx')
            ->join('status_matricula as sx', 'sx.id', '=', 'mx.status_id')
            ->where('mx.tenant_id', $tenantId)->where('sx.codigo', 'cancelada')->whereNotNull('mx.aluno_id')
            ->groupBy('mx.aluno_id')
            ->select(['mx.aluno_id', DB::raw('MAX(mx.id) as matricula_id')]);

        $q = DB::table('alunos as a')
            ->joinSub($ultima, 'u', 'u.aluno_id', '=', 'a.id')
            ->leftJoin('usuarios as pu', 'pu.id', '=', 'a.usuario_id')
            ->join('matriculas as m', 'm.id', '=', 'u.matricula_id')
            ->join('planos as p', 'p.id', '=', 'm.plano_id')
            ->leftJoin('modalidades as md', 'md.id', '=', 'p.modalidade_id')
            ->leftJoin('prospeccao_oportunidades as o', function ($j) use ($tenantId) {
                $j->on('o.aluno_id', '=', 'a.id')->where('o.tenant_id', $tenantId)->whereNull('o.encerrada_em');
            });
        if ($buscaDigitos) {
            $q->where(fn ($w) => $w->where(Pessoa::col('telefone', 'pu'), 'like', '%'.$buscaDigitos.'%')->orWhere(Pessoa::col('whatsapp', 'pu'), 'like', '%'.$buscaDigitos.'%'));
        } elseif ($buscaNome) {
            $q->where('a.nome', 'like', '%'.$buscaNome.'%');
        }

        return $q->orderByRaw($dataEfetiva.' DESC')->limit($limite)
            ->select([
                'a.id as aluno_id', 'a.nome', Pessoa::select('telefone', 'pu'), Pessoa::select('whatsapp', 'pu'), Pessoa::select('foto_caminho', 'pu'),
                'm.id as matricula_id', DB::raw($dataEfetiva.' as data_cancelamento'), 'p.nome as plano_nome', 'md.nome as modalidade_nome',
                'o.id as oportunidade_aberta_id',
                DB::raw("CASE WHEN EXISTS (SELECT 1 FROM matriculas m2 JOIN status_matricula s2 ON s2.id = m2.status_id WHERE m2.tenant_id = m.tenant_id AND m2.aluno_id = a.id AND s2.codigo IN ('".implode("','", self::STATUS_MATRICULA_EM_ANDAMENTO)."')) THEN 1 ELSE 0 END as em_andamento"),
            ])
            ->get()->map(fn ($r) => (array) $r)->all();
    }

    /** Matrícula cancelada mais recente do aluno no tenant (ou null). */
    public function ultimaCanceladaDoAluno(int $tenantId, int $alunoId): ?object
    {
        return DB::table('matriculas as m')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->where('m.tenant_id', $tenantId)->where('m.aluno_id', $alunoId)->where('sm.codigo', 'cancelada')
            ->orderByRaw('COALESCE(m.data_cancelamento, DATE(m.updated_at)) DESC')->orderByDesc('m.id')
            ->select(['m.id', 'm.data_cancelamento', DB::raw('DATE(m.updated_at) as data_atualizacao')])
            ->first();
    }

    public function oportunidadePorReferencia(int $tenantId, int $matriculaId): ?object
    {
        return DB::table('prospeccao_oportunidades')->where('tenant_id', $tenantId)->where('matricula_referencia_id', $matriculaId)->first();
    }

    private function matriculaEmAndamento(Builder $q, string $colTenant, string $colAluno): void
    {
        $q->select(DB::raw(1))
            ->from('matriculas as m2')
            ->join('status_matricula as s2', 's2.id', '=', 'm2.status_id')
            ->whereColumn('m2.tenant_id', $colTenant)
            ->whereColumn('m2.aluno_id', $colAluno)
            ->whereIn('s2.codigo', self::STATUS_MATRICULA_EM_ANDAMENTO);
    }

    /**
     * @return list<object{id: int, aluno_id: int, matricula_referencia_id: int, status: string, encerrada_em: ?string}>
     */
    public function oportunidadesParaSincronizar(int $tenantId): array
    {
        return DB::table('prospeccao_oportunidades')
            ->where('tenant_id', $tenantId)
            ->select(['id', 'aluno_id', 'matricula_referencia_id', 'status', 'encerrada_em'])
            ->get()
            ->all();
    }

    /** @return array<int, true> aluno_id => true */
    public function alunosComRestricao(int $tenantId): array
    {
        return array_fill_keys(
            DB::table('prospeccao_restricoes_contato')->where('tenant_id', $tenantId)->whereNull('removido_em')->pluck('aluno_id')->map(fn ($id) => (int) $id)->all(),
            true,
        );
    }

    /** @param array<string, mixed> $dados */
    public function inserirOportunidade(array $dados): int
    {
        $dados['aluno_aberto'] = empty($dados['encerrada_em']) ? $dados['aluno_id'] : null;

        return (int) DB::table('prospeccao_oportunidades')->insertGetId($dados + ['created_at' => $dados['created_at'] ?? now(), 'updated_at' => now()]);
    }

    public function atualizarReferencia(int $tenantId, int $id, int $matriculaId, string $data, bool $estimada): void
    {
        DB::table('prospeccao_oportunidades')->where('tenant_id', $tenantId)->where('id', $id)->update([
            'matricula_referencia_id' => $matriculaId,
            'data_cancelamento_referencia' => $data,
            'data_cancelamento_estimada' => $estimada,
            'updated_at' => now(),
        ]);
    }

    /**
     * Oportunidades abertas cujo aluno voltou a ter matrícula ATIVA (nova, ou a própria referência reativada).
     *
     * @return list<object{oportunidade_id: int, status: string, matricula_id: int}>
     */
    public function conversoesPendentes(int $tenantId): array
    {
        return DB::table('prospeccao_oportunidades as o')
            ->join('matriculas as m', function ($j) {
                $j->on('m.tenant_id', '=', 'o.tenant_id')->on('m.aluno_id', '=', 'o.aluno_id');
            })
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->where('o.tenant_id', $tenantId)
            ->whereNull('o.encerrada_em')
            ->where('sm.codigo', 'ativa')
            ->where(fn ($q) => $q->whereColumn('m.id', 'o.matricula_referencia_id')->orWhereColumn('m.data_matricula', '>=', 'o.data_cancelamento_referencia'))
            ->groupBy('o.id', 'o.status')
            ->select(['o.id as oportunidade_id', 'o.status', DB::raw('MIN(m.id) as matricula_id')])
            ->get()
            ->all();
    }

    // ---------------------------------------------------------------- oportunidades

    private function baseOportunidades(int $tenantId): Builder
    {
        return DB::table('prospeccao_oportunidades as o')
            ->join('alunos as a', 'a.id', '=', 'o.aluno_id')
            ->leftJoin('usuarios as pu', 'pu.id', '=', 'a.usuario_id')
            ->join('matriculas as mr', 'mr.id', '=', 'o.matricula_referencia_id')
            ->join('planos as p', 'p.id', '=', 'mr.plano_id')
            ->leftJoin('modalidades as md', 'md.id', '=', 'p.modalidade_id')
            ->leftJoin('usuarios as ur', 'ur.id', '=', 'o.responsavel_id')
            ->leftJoin('prospeccao_restricoes_contato as rc', function ($j) {
                $j->on('rc.tenant_id', '=', 'o.tenant_id')->on('rc.aluno_id', '=', 'o.aluno_id')->whereNull('rc.removido_em');
            })
            ->where('o.tenant_id', $tenantId);
    }

    /** @return list<string|Expression> */
    private function colunasLista(): array
    {
        return [
            'o.id', 'o.aluno_id', 'o.status', 'o.score', 'o.score_confianca', 'o.data_cancelamento_referencia', 'o.data_cancelamento_estimada',
            'o.ultimo_contato_em', 'o.proximo_contato_em', 'o.motivo_categoria', 'o.responsavel_id', 'o.convertida_em', 'o.matricula_referencia_id',
            'a.nome as aluno_nome', Pessoa::select('telefone', 'pu'), Pessoa::select('whatsapp', 'pu'), Pessoa::select('foto_caminho', 'pu'), 'a.usuario_id',
            'mr.data_inicio as matricula_data_inicio', 'mr.data_matricula', 'mr.motivo_cancelamento',
            'p.id as plano_id', 'p.nome as plano_nome', 'md.id as modalidade_id', 'md.nome as modalidade_nome', 'md.cor as modalidade_cor',
            'ur.nome as responsavel_nome',
            DB::raw('CASE WHEN rc.id IS NULL THEN 0 ELSE 1 END as nao_contatar'),
        ];
    }

    /**
     * @param  array<string, mixed>  $f  filtros já validados pelo service
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function listar(int $tenantId, array $f, int $pagina, int $porPagina, string $ordem, string $direcao): array
    {
        $q = $this->baseOportunidades($tenantId);

        if (($f['status'] ?? 'abertas') === 'abertas') {
            $q->whereIn('o.status', ProspeccaoStatus::ABERTOS);
        } elseif (($f['status'] ?? '') !== 'todas') {
            $q->where('o.status', $f['status']);
        }
        if (! empty($f['busca_digitos'])) {
            $like = '%'.$f['busca_digitos'].'%';
            $q->where(fn ($w) => $w->where(Pessoa::col('telefone', 'pu'), 'like', $like)->orWhere(Pessoa::col('whatsapp', 'pu'), 'like', $like));
        } elseif (! empty($f['busca_nome'])) {
            $q->where('a.nome', 'like', '%'.$f['busca_nome'].'%');
        }
        foreach (['modalidade_id' => 'p.modalidade_id', 'plano_id' => 'p.id', 'responsavel_id' => 'o.responsavel_id'] as $chave => $coluna) {
            if (! empty($f[$chave])) {
                $q->where($coluna, (int) $f[$chave]);
            }
        }
        // Janela da academia: só limita quem ainda não foi trabalhado (sem contato, status inicial);
        // oportunidade em andamento continua visível mesmo se a janela for reduzida depois
        if (! empty($f['janela_de']) && ! empty($f['janela_ate'])) {
            $q->where(fn ($w) => $w->whereNotNull('o.ultimo_contato_em')
                ->orWhere('o.status', '!=', ProspeccaoStatus::NAO_CONTATADO)
                ->orWhereBetween('o.data_cancelamento_referencia', [$f['janela_de'], $f['janela_ate']]));
        }
        if (! empty($f['cancelamento_de'])) {
            $q->where('o.data_cancelamento_referencia', '>=', $f['cancelamento_de']);
        }
        if (! empty($f['cancelamento_ate'])) {
            $q->where('o.data_cancelamento_referencia', '<=', $f['cancelamento_ate']);
        }
        if (! empty($f['sem_contato'])) {
            $q->whereNull('o.ultimo_contato_em');
        }
        if (! empty($f['com_retorno'])) {
            $q->whereNotNull('o.proximo_contato_em');
        }
        match ($f['potencial'] ?? null) {
            'alto' => $q->where('o.score', '>=', 80),
            'medio' => $q->whereBetween('o.score', [50, 79]),
            'baixo' => $q->where('o.score', '<', 50),
            default => null,
        };

        $total = (clone $q)->count('o.id');
        $rows = $q->select($this->colunasLista())
            ->orderBy($ordem, $direcao)
            ->orderBy('o.id', $direcao)
            ->offset(($pagina - 1) * $porPagina)
            ->limit($porPagina)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array<string, mixed>|null */
    public function buscar(int $tenantId, int $id): ?array
    {
        $row = $this->baseOportunidades($tenantId)
            ->leftJoin('usuarios as uu', 'uu.id', '=', 'a.usuario_id')
            ->leftJoin('usuarios as uc', 'uc.id', '=', 'o.ultimo_contato_por')
            ->where('o.id', $id)
            ->select(array_merge($this->colunasLista(), [
                'o.interesse', 'o.condicoes_apresentadas', 'o.observacoes', 'o.score_detalhes', 'o.score_calculado_em', 'o.matricula_conversao_id',
                'o.encerrada_em', 'o.created_at', 'o.ultimo_contato_por', 'uc.nome as ultimo_contato_por_nome',
                Pessoa::select('data_nascimento', 'pu'), 'a.ativo as aluno_ativo', 'uu.email', 'rc.motivo as restricao_motivo', 'rc.solicitado_em as restricao_desde',
            ]))
            ->first();

        return $row ? (array) $row : null;
    }

    /** @return array<string, mixed>|null */
    public function buscarSimples(int $tenantId, int $id): ?array
    {
        $row = DB::table('prospeccao_oportunidades')->where('tenant_id', $tenantId)->where('id', $id)->first();

        return $row ? (array) $row : null;
    }

    /** @param array<string, mixed> $campos */
    public function atualizar(int $tenantId, int $id, array $campos): void
    {
        if (array_key_exists('encerrada_em', $campos)) {
            $campos['aluno_aberto'] = $campos['encerrada_em'] === null ? DB::raw('aluno_id') : null;
        }
        DB::table('prospeccao_oportunidades')->where('tenant_id', $tenantId)->where('id', $id)->update($campos + ['updated_at' => now()]);
    }

    public function registrarHistorico(int $tenantId, int $oportunidadeId, ?string $de, string $para, ?int $usuarioId, ?string $observacao): void
    {
        DB::table('prospeccao_historico_status')->insert([
            'tenant_id' => $tenantId,
            'oportunidade_id' => $oportunidadeId,
            'status_anterior' => $de,
            'status_novo' => $para,
            'usuario_id' => $usuarioId,
            'observacao' => $observacao !== null ? mb_substr($observacao, 0, 500) : null,
            'created_at' => now(),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function historicoStatus(int $tenantId, int $oportunidadeId): array
    {
        return DB::table('prospeccao_historico_status as h')
            ->leftJoin('usuarios as u', 'u.id', '=', 'h.usuario_id')
            ->where('h.tenant_id', $tenantId)->where('h.oportunidade_id', $oportunidadeId)
            ->orderByDesc('h.created_at')->orderByDesc('h.id')
            ->select(['h.id', 'h.status_anterior', 'h.status_novo', 'h.observacao', 'h.created_at', 'u.nome as usuario_nome'])
            ->get()->map(fn ($r) => (array) $r)->all();
    }

    /** @return list<array<string, mixed>> outras oportunidades (ciclos) do mesmo aluno */
    public function ciclosDoAluno(int $tenantId, int $alunoId, int $exceto): array
    {
        return DB::table('prospeccao_oportunidades')
            ->where('tenant_id', $tenantId)->where('aluno_id', $alunoId)->where('id', '!=', $exceto)
            ->orderByDesc('data_cancelamento_referencia')
            ->select(['id', 'status', 'data_cancelamento_referencia', 'convertida_em', 'encerrada_em'])
            ->get()->map(fn ($r) => (array) $r)->all();
    }

    // ---------------------------------------------------------------- dados do aluno (ficha)

    /** @return list<array<string, mixed>> */
    public function matriculasDoAluno(int $tenantId, int $alunoId): array
    {
        return DB::table('matriculas as m')
            ->join('planos as p', 'p.id', '=', 'm.plano_id')
            ->leftJoin('modalidades as md', 'md.id', '=', 'p.modalidade_id')
            ->leftJoin('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->where('m.tenant_id', $tenantId)->where('m.aluno_id', $alunoId)
            ->orderByDesc('m.data_inicio')->orderByDesc('m.id')
            ->select([
                'm.id', 'm.data_matricula', 'm.data_inicio', 'm.data_vencimento', 'm.proxima_data_vencimento', 'm.data_cancelamento',
                'm.motivo_cancelamento', 'm.valor', 'm.tipo_cobranca', 'sm.codigo as status_codigo', 'sm.nome as status_nome',
                'p.nome as plano_nome', 'p.duracao_dias', 'md.nome as modalidade_nome', 'md.cor as modalidade_cor',
            ])
            ->get()->map(fn ($r) => (array) $r)->all();
    }

    /** @return array{total: int, ultimo: ?string, por_mes: array<string, int>} */
    public function resumoCheckins(int $tenantId, int $alunoId, string $desdeMes): array
    {
        $base = DB::table('checkins')->where('tenant_id', $tenantId)->where('aluno_id', $alunoId);
        $porMes = (clone $base)->where('data_checkin', '>=', $desdeMes)
            ->groupBy(DB::raw('SUBSTR(data_checkin, 1, 7)'))
            ->select([DB::raw('SUBSTR(data_checkin, 1, 7) as mes'), DB::raw('COUNT(*) as total')])
            ->pluck('total', 'mes')->map(fn ($v) => (int) $v)->all();

        return [
            'total' => (clone $base)->count(),
            'ultimo' => (clone $base)->max('data_checkin'),
            'por_mes' => $porMes,
        ];
    }

    /** @return array{pagas: int, pagas_em_dia: int, em_atraso: int, valor_pago: float, valor_em_atraso: float} */
    public function resumoFinanceiro(int $tenantId, int $alunoId, string $hoje): array
    {
        $r = DB::table('pagamentos_plano as pp')
            ->join('matriculas as m', 'm.id', '=', 'pp.matricula_id')
            ->where('pp.tenant_id', $tenantId)->where('m.tenant_id', $tenantId)->where('m.aluno_id', $alunoId)
            ->selectRaw('SUM(CASE WHEN pp.status_pagamento_id = 2 THEN 1 ELSE 0 END) as pagas')
            ->selectRaw('SUM(CASE WHEN pp.status_pagamento_id = 2 AND pp.data_pagamento <= pp.data_vencimento THEN 1 ELSE 0 END) as pagas_em_dia')
            ->selectRaw('SUM(CASE WHEN pp.status_pagamento_id IN (1, 3) AND pp.data_vencimento < ? THEN 1 ELSE 0 END) as em_atraso', [$hoje])
            ->selectRaw('SUM(CASE WHEN pp.status_pagamento_id = 2 THEN pp.valor ELSE 0 END) as valor_pago')
            ->selectRaw('SUM(CASE WHEN pp.status_pagamento_id IN (1, 3) AND pp.data_vencimento < ? THEN pp.valor ELSE 0 END) as valor_em_atraso', [$hoje])
            ->first();

        return [
            'pagas' => (int) ($r->pagas ?? 0),
            'pagas_em_dia' => (int) ($r->pagas_em_dia ?? 0),
            'em_atraso' => (int) ($r->em_atraso ?? 0),
            'valor_pago' => (float) ($r->valor_pago ?? 0),
            'valor_em_atraso' => (float) ($r->valor_em_atraso ?? 0),
        ];
    }

    public function alunoTemMatriculaEmAndamento(int $tenantId, int $alunoId): bool
    {
        return DB::table('matriculas as m')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->where('m.tenant_id', $tenantId)->where('m.aluno_id', $alunoId)
            ->whereIn('sm.codigo', self::STATUS_MATRICULA_EM_ANDAMENTO)
            ->exists();
    }

    public function vinculoAlunoAtivo(int $tenantId, int $usuarioId): bool
    {
        return DB::table('tenant_usuario_papel')
            ->where('tenant_id', $tenantId)->where('usuario_id', $usuarioId)->where('papel_id', Papel::ALUNO)->where('ativo', 1)
            ->exists();
    }

    public function nomeTenant(int $tenantId): ?string
    {
        return DB::table('tenants')->where('id', $tenantId)->value('nome');
    }

    // ---------------------------------------------------------------- score

    /**
     * Dados de entrada do score por oportunidade (ver ProspeccaoScoreService::calcular).
     *
     * @param  list<int>  $ids
     * @return array<int, array<string, mixed>>
     */
    public function dadosScore(int $tenantId, array $ids, string $hoje, string $limiteInteresse): array
    {
        $ops = DB::table('prospeccao_oportunidades as o')
            ->join('matriculas as mr', 'mr.id', '=', 'o.matricula_referencia_id')
            ->join('planos as p', 'p.id', '=', 'mr.plano_id')
            ->where('o.tenant_id', $tenantId)->whereIn('o.id', $ids)
            ->select(['o.id', 'o.aluno_id', 'o.motivo_categoria', 'o.data_cancelamento_referencia', 'mr.data_inicio', 'p.checkins_semanais'])
            ->get();

        $checkins = DB::table('prospeccao_oportunidades as o')
            ->join('matriculas as mr', 'mr.id', '=', 'o.matricula_referencia_id')
            ->join('checkins as c', function ($j) {
                $j->on('c.tenant_id', '=', 'o.tenant_id')->on('c.aluno_id', '=', 'o.aluno_id');
            })
            ->where('o.tenant_id', $tenantId)->whereIn('o.id', $ids)
            ->whereRaw('DATE(c.data_checkin) >= mr.data_inicio')
            ->whereRaw('DATE(c.data_checkin) <= o.data_cancelamento_referencia')
            ->groupBy('o.id')
            ->select(['o.id', DB::raw('COUNT(c.id) as total')])
            ->pluck('total', 'id');

        $pagamentos = DB::table('prospeccao_oportunidades as o')
            ->join('matriculas as m', function ($j) {
                $j->on('m.tenant_id', '=', 'o.tenant_id')->on('m.aluno_id', '=', 'o.aluno_id');
            })
            ->join('pagamentos_plano as pp', 'pp.matricula_id', '=', 'm.id')
            ->where('o.tenant_id', $tenantId)->whereIn('o.id', $ids)
            ->groupBy('o.id')
            ->select('o.id')
            ->selectRaw('SUM(CASE WHEN pp.status_pagamento_id = 2 AND pp.data_pagamento <= pp.data_vencimento THEN 1 ELSE 0 END) as em_dia')
            ->selectRaw('SUM(CASE WHEN pp.status_pagamento_id = 2 OR (pp.status_pagamento_id IN (1, 3) AND pp.data_vencimento < ?) THEN 1 ELSE 0 END) as avaliadas', [$hoje])
            ->get()->keyBy('id');

        $inicio = DB::table('prospeccao_oportunidades as o')
            ->join('matriculas as m', function ($j) {
                $j->on('m.tenant_id', '=', 'o.tenant_id')->on('m.aluno_id', '=', 'o.aluno_id');
            })
            ->where('o.tenant_id', $tenantId)->whereIn('o.id', $ids)
            ->groupBy('o.id')
            ->select(['o.id', DB::raw('MIN(m.data_inicio) as inicio')])
            ->pluck('inicio', 'id');

        $interesse = DB::table('prospeccao_interacoes')
            ->where('tenant_id', $tenantId)->whereIn('oportunidade_id', $ids)
            ->where('resultado', 'interessado')->where('ocorrido_em', '>=', $limiteInteresse)
            ->distinct()->pluck('oportunidade_id')->map(fn ($id) => (int) $id)->flip();

        $dias = static fn (?string $de, string $ate) => $de ? (int) (new \DateTime(substr($de, 0, 10)))->diff(new \DateTime(substr($ate, 0, 10)))->format('%r%a') : null;
        $out = [];
        foreach ($ops as $o) {
            $id = (int) $o->id;
            $cancel = (string) $o->data_cancelamento_referencia;
            $pg = $pagamentos[$id] ?? null;
            $out[$id] = [
                'checkins' => (int) ($checkins[$id] ?? 0),
                'dias_matricula' => max(0, (int) $dias($o->data_inicio, $cancel)),
                'checkins_semanais' => $o->checkins_semanais !== null ? (int) $o->checkins_semanais : null,
                'dias_desde_cancelamento' => max(0, (int) $dias($cancel, $hoje)),
                'parcelas_em_dia' => (int) ($pg->em_dia ?? 0),
                'parcelas_avaliadas' => (int) ($pg->avaliadas ?? 0),
                'dias_permanencia' => isset($inicio[$id]) ? max(0, (int) $dias($inicio[$id], $cancel)) : null,
                'motivo_categoria' => $o->motivo_categoria,
                'interesse_recente' => isset($interesse[$id]),
            ];
        }

        return $out;
    }

    /** @param list<array<string, mixed>> $criterios */
    public function salvarScore(int $tenantId, int $id, ?int $score, int $confianca, array $criterios, string $agora): void
    {
        DB::table('prospeccao_oportunidades')->where('tenant_id', $tenantId)->where('id', $id)->update([
            'score' => $score,
            'score_confianca' => $confianca,
            'score_detalhes' => json_encode($criterios, JSON_UNESCAPED_UNICODE),
            'score_calculado_em' => $agora,
        ]);
    }

    /** @return list<int> oportunidades abertas sem score do dia */
    public function oportunidadesComScoreDesatualizado(int $tenantId, string $hoje, int $limite): array
    {
        return DB::table('prospeccao_oportunidades')
            ->where('tenant_id', $tenantId)->whereNull('encerrada_em')
            ->where(fn ($q) => $q->whereNull('score_calculado_em')->orWhere('score_calculado_em', '<', $hoje))
            ->orderBy('id')->limit($limite)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    // ---------------------------------------------------------------- interações e restrição

    /** @param array<string, mixed> $dados */
    public function inserirInteracao(array $dados): int
    {
        return (int) DB::table('prospeccao_interacoes')->insertGetId($dados + ['created_at' => now()]);
    }

    /** @return list<array<string, mixed>> */
    public function interacoes(int $tenantId, int $oportunidadeId): array
    {
        return DB::table('prospeccao_interacoes as i')
            ->leftJoin('usuarios as u', 'u.id', '=', 'i.usuario_id')
            ->leftJoin('prospeccao_modelos_mensagem as mm', 'mm.id', '=', 'i.modelo_mensagem_id')
            ->where('i.tenant_id', $tenantId)->where('i.oportunidade_id', $oportunidadeId)
            ->orderByDesc('i.ocorrido_em')->orderByDesc('i.id')
            ->select(['i.id', 'i.canal', 'i.resultado', 'i.mensagem', 'i.observacao', 'i.ocorrido_em', 'u.nome as usuario_nome', 'mm.nome as modelo_nome'])
            ->get()->map(fn ($r) => (array) $r)->all();
    }

    public function restricaoAtiva(int $tenantId, int $alunoId): ?object
    {
        return DB::table('prospeccao_restricoes_contato')->where('tenant_id', $tenantId)->where('aluno_id', $alunoId)->whereNull('removido_em')->first();
    }

    public function inserirRestricao(int $tenantId, int $alunoId, ?string $motivo, ?int $usuarioId, string $agora): void
    {
        DB::table('prospeccao_restricoes_contato')->insert([
            'tenant_id' => $tenantId, 'aluno_id' => $alunoId, 'aluno_ativo' => $alunoId, 'motivo' => $motivo, 'solicitado_em' => $agora,
            'registrado_por' => $usuarioId, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function removerRestricao(int $tenantId, int $alunoId, string $motivo, ?int $usuarioId, string $agora): void
    {
        DB::table('prospeccao_restricoes_contato')->where('tenant_id', $tenantId)->where('aluno_id', $alunoId)->whereNull('removido_em')
            ->update(['removido_em' => $agora, 'aluno_ativo' => null, 'removido_por' => $usuarioId, 'motivo_remocao' => $motivo, 'updated_at' => now()]);
    }

    /** @return list<int> oportunidades em Não contatar do aluno */
    public function oportunidadesNaoContatar(int $tenantId, int $alunoId): array
    {
        return DB::table('prospeccao_oportunidades')
            ->where('tenant_id', $tenantId)->where('aluno_id', $alunoId)->where('status', ProspeccaoStatus::NAO_CONTATAR)
            ->orderByDesc('data_cancelamento_referencia')
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function existeOportunidadeAbertaDoAluno(int $tenantId, int $alunoId, int $exceto): bool
    {
        return DB::table('prospeccao_oportunidades')->where('tenant_id', $tenantId)->where('aluno_id', $alunoId)
            ->where('id', '!=', $exceto)->whereNull('encerrada_em')->exists();
    }

    // ---------------------------------------------------------------- agendamentos

    /** @param array<string, mixed> $dados */
    public function inserirAgendamento(array $dados): int
    {
        return (int) DB::table('prospeccao_agendamentos')->insertGetId($dados + ['created_at' => now(), 'updated_at' => now()]);
    }

    /** @return array<string, mixed>|null */
    public function buscarAgendamento(int $tenantId, int $id): ?array
    {
        $row = DB::table('prospeccao_agendamentos')->where('tenant_id', $tenantId)->where('id', $id)->first();

        return $row ? (array) $row : null;
    }

    /** @param array<string, mixed> $campos */
    public function atualizarAgendamento(int $tenantId, int $id, array $campos): void
    {
        DB::table('prospeccao_agendamentos')->where('tenant_id', $tenantId)->where('id', $id)->update($campos + ['updated_at' => now()]);
    }

    /** Recalcula o cache proximo_contato_em da oportunidade a partir dos agendamentos pendentes. */
    public function sincronizarProximoContato(int $tenantId, int $oportunidadeId): void
    {
        $proximo = DB::table('prospeccao_agendamentos')
            ->where('tenant_id', $tenantId)->where('oportunidade_id', $oportunidadeId)->where('situacao', 'pendente')
            ->min('agendado_para');
        DB::table('prospeccao_oportunidades')->where('tenant_id', $tenantId)->where('id', $oportunidadeId)
            ->update(['proximo_contato_em' => $proximo, 'updated_at' => now()]);
    }

    public function cancelarAgendamentosPendentes(int $tenantId, int $oportunidadeId, ?int $usuarioId, string $agora): void
    {
        DB::table('prospeccao_agendamentos')
            ->where('tenant_id', $tenantId)->where('oportunidade_id', $oportunidadeId)->where('situacao', 'pendente')
            ->update(['situacao' => 'cancelado', 'finalizado_por' => $usuarioId, 'finalizado_em' => $agora, 'updated_at' => now()]);
        $this->sincronizarProximoContato($tenantId, $oportunidadeId);
    }

    /**
     * Agenda de contatos pendentes. $de/$ate em 'Y-m-d H:i:s' (qualquer um pode ser null).
     *
     * @return list<array<string, mixed>>
     */
    public function agenda(int $tenantId, ?string $de, ?string $ate, ?int $responsavelId, ?int $oportunidadeId, int $limite): array
    {
        $q = DB::table('prospeccao_agendamentos as g')
            ->join('prospeccao_oportunidades as o', 'o.id', '=', 'g.oportunidade_id')
            ->join('alunos as a', 'a.id', '=', 'o.aluno_id')
            ->leftJoin('usuarios as pu', 'pu.id', '=', 'a.usuario_id')
            ->join('matriculas as mr', 'mr.id', '=', 'o.matricula_referencia_id')
            ->join('planos as p', 'p.id', '=', 'mr.plano_id')
            ->leftJoin('modalidades as md', 'md.id', '=', 'p.modalidade_id')
            ->leftJoin('usuarios as ur', 'ur.id', '=', 'g.responsavel_id')
            ->leftJoin('prospeccao_restricoes_contato as rc', function ($j) {
                $j->on('rc.tenant_id', '=', 'o.tenant_id')->on('rc.aluno_id', '=', 'o.aluno_id')->whereNull('rc.removido_em');
            })
            ->where('g.tenant_id', $tenantId);

        if ($oportunidadeId) {
            $q->where('g.oportunidade_id', $oportunidadeId);
        } else {
            $q->where('g.situacao', 'pendente');
        }
        if ($de) {
            $q->where('g.agendado_para', '>=', $de);
        }
        if ($ate) {
            $q->where('g.agendado_para', '<', $ate);
        }
        if ($responsavelId) {
            $q->where('g.responsavel_id', $responsavelId);
        }

        return $q->orderBy('g.agendado_para', $oportunidadeId ? 'desc' : 'asc')->limit($limite)
            ->select([
                'g.id', 'g.oportunidade_id', 'g.agendado_para', 'g.tipo_contato', 'g.observacao', 'g.situacao', 'g.responsavel_id', 'g.finalizado_em',
                'ur.nome as responsavel_nome', 'o.status as oportunidade_status', 'o.aluno_id', 'o.score',
                'a.nome as aluno_nome', Pessoa::select('telefone', 'pu'), Pessoa::select('whatsapp', 'pu'), Pessoa::select('foto_caminho', 'pu'), 'p.nome as plano_nome', 'md.nome as modalidade_nome',
                DB::raw('CASE WHEN rc.id IS NULL THEN 0 ELSE 1 END as nao_contatar'),
            ])
            ->get()->map(fn ($r) => (array) $r)->all();
    }

    // ---------------------------------------------------------------- modelos de mensagem

    /** @return list<array<string, mixed>> */
    public function modelos(int $tenantId, bool $apenasAtivos): array
    {
        $q = DB::table('prospeccao_modelos_mensagem')->where('tenant_id', $tenantId);
        if ($apenasAtivos) {
            $q->where('ativo', 1);
        }

        return $q->orderBy('etapa')->orderBy('nome')
            ->select(['id', 'nome', 'etapa', 'conteudo', 'ativo', 'updated_at'])
            ->get()->map(fn ($r) => (array) $r)->all();
    }

    public function contarModelos(int $tenantId): int
    {
        return DB::table('prospeccao_modelos_mensagem')->where('tenant_id', $tenantId)->count();
    }

    /** @param list<array<string, mixed>> $modelos */
    public function inserirModelosPadrao(int $tenantId, array $modelos): void
    {
        DB::table('prospeccao_modelos_mensagem')->insertOrIgnore(array_map(fn ($m) => $m + [
            'tenant_id' => $tenantId, 'ativo' => 1, 'created_at' => now(), 'updated_at' => now(),
        ], $modelos));
    }

    /** @return array<string, mixed>|null */
    public function buscarModelo(int $tenantId, int $id): ?array
    {
        $row = DB::table('prospeccao_modelos_mensagem')->where('tenant_id', $tenantId)->where('id', $id)->first();

        return $row ? (array) $row : null;
    }

    public function nomeModeloEmUso(int $tenantId, string $nome, ?int $exceto): bool
    {
        $q = DB::table('prospeccao_modelos_mensagem')->where('tenant_id', $tenantId)->where('nome', $nome);
        if ($exceto) {
            $q->where('id', '!=', $exceto);
        }

        return $q->exists();
    }

    /** @param array<string, mixed> $dados */
    public function salvarModelo(int $tenantId, ?int $id, array $dados): int
    {
        if ($id) {
            DB::table('prospeccao_modelos_mensagem')->where('tenant_id', $tenantId)->where('id', $id)->update($dados + ['updated_at' => now()]);

            return $id;
        }

        return (int) DB::table('prospeccao_modelos_mensagem')->insertGetId($dados + ['tenant_id' => $tenantId, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function modeloUsado(int $tenantId, int $id): bool
    {
        return DB::table('prospeccao_interacoes')->where('tenant_id', $tenantId)->where('modelo_mensagem_id', $id)->exists();
    }

    public function excluirModelo(int $tenantId, int $id): void
    {
        DB::table('prospeccao_modelos_mensagem')->where('tenant_id', $tenantId)->where('id', $id)->delete();
    }

    // ---------------------------------------------------------------- equipe

    /**
     * Usuários que podem ser responsáveis: admins e comerciais ativos do tenant.
     *
     * @return list<array{id: int, nome: string, email: string, papeis: list<int>}>
     */
    public function equipe(int $tenantId, bool $apenasComercial): array
    {
        $rows = DB::table('tenant_usuario_papel as tup')
            ->join('usuarios as u', 'u.id', '=', 'tup.usuario_id')
            ->where('tup.tenant_id', $tenantId)->where('tup.ativo', 1)
            ->whereIn('tup.papel_id', $apenasComercial ? [Papel::COMERCIAL] : [Papel::ADMIN, Papel::COMERCIAL])
            ->orderBy('u.nome')
            ->select(['u.id', 'u.nome', 'u.email', 'tup.papel_id'])
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[$r->id] ??= ['id' => (int) $r->id, 'nome' => $r->nome, 'email' => $r->email, 'papeis' => []];
            $out[$r->id]['papeis'][] = (int) $r->papel_id;
        }

        return array_values($out);
    }

    public function usuarioDaEquipe(int $tenantId, int $usuarioId): bool
    {
        return DB::table('tenant_usuario_papel')
            ->where('tenant_id', $tenantId)->where('usuario_id', $usuarioId)->where('ativo', 1)
            ->whereIn('papel_id', [Papel::ADMIN, Papel::COMERCIAL])
            ->exists();
    }

    public function usuarioIdPorEmail(string $email): ?int
    {
        $id = DB::table('usuarios')->where('email_global', $email)->orWhere('email', $email)->value('id');

        return $id ? (int) $id : null;
    }

    public function definirPapelComercial(int $tenantId, int $usuarioId, bool $ativo): void
    {
        DB::table('tenant_usuario_papel')->updateOrInsert(
            ['tenant_id' => $tenantId, 'usuario_id' => $usuarioId, 'papel_id' => Papel::COMERCIAL],
            ['ativo' => $ativo ? 1 : 0, 'updated_at' => now()],
        );
    }

    // ---------------------------------------------------------------- dashboard e relatórios

    /** Oportunidades cujo cancelamento de referência está no período (a "coorte"). */
    private function coorte(int $tenantId, string $de, string $ate, ?int $modalidadeId): Builder
    {
        $q = DB::table('prospeccao_oportunidades as o')
            ->join('matriculas as mr', 'mr.id', '=', 'o.matricula_referencia_id')
            ->join('planos as p', 'p.id', '=', 'mr.plano_id')
            ->where('o.tenant_id', $tenantId)
            ->whereBetween('o.data_cancelamento_referencia', [$de, $ate]);
        if ($modalidadeId) {
            $q->where('p.modalidade_id', $modalidadeId);
        }

        return $q;
    }

    /** @return array<string, int> status => alunos distintos */
    public function coortePorStatus(int $tenantId, string $de, string $ate, ?int $modalidadeId): array
    {
        return $this->coorte($tenantId, $de, $ate, $modalidadeId)
            ->groupBy('o.status')
            ->select(['o.status', DB::raw('COUNT(DISTINCT o.aluno_id) as total')])
            ->pluck('total', 'status')
            ->map(fn ($v) => (int) $v)->all();
    }

    public function coorteAlunos(int $tenantId, string $de, string $ate, ?int $modalidadeId, bool $semNaoContatar): int
    {
        $q = $this->coorte($tenantId, $de, $ate, $modalidadeId);
        if ($semNaoContatar) {
            $q->where('o.status', '!=', ProspeccaoStatus::NAO_CONTATAR);
        }

        return (int) $q->count(DB::raw('DISTINCT o.aluno_id'));
    }

    /** @return array<string, int> categoria (ou '') => alunos */
    public function coortePorMotivo(int $tenantId, string $de, string $ate, ?int $modalidadeId): array
    {
        return $this->coorte($tenantId, $de, $ate, $modalidadeId)
            ->groupBy('o.motivo_categoria')
            ->select([DB::raw("COALESCE(o.motivo_categoria, '') as motivo"), DB::raw('COUNT(DISTINCT o.aluno_id) as total')])
            ->pluck('total', 'motivo')
            ->map(fn ($v) => (int) $v)->all();
    }

    /**
     * Valor mensal equivalente das matrículas de conversão da coorte que estão ATIVAS hoje.
     */
    public function receitaRecuperadaAtiva(int $tenantId, string $de, string $ate, ?int $modalidadeId): float
    {
        return (float) $this->coorte($tenantId, $de, $ate, $modalidadeId)
            ->where('o.status', ProspeccaoStatus::RECUPERADO)
            ->join('matriculas as mc', 'mc.id', '=', 'o.matricula_conversao_id')
            ->join('status_matricula as smc', 'smc.id', '=', 'mc.status_id')
            ->join('planos as pc', 'pc.id', '=', 'mc.plano_id')
            ->leftJoin('plano_ciclos as cc', 'cc.id', '=', 'mc.plano_ciclo_id')
            ->where('smc.codigo', 'ativa')
            ->sum(DB::raw('COALESCE(cc.valor_mensal_equivalente, mc.valor * 30.0 / CASE WHEN pc.duracao_dias > 0 THEN pc.duracao_dias ELSE 30 END)'));
    }

    /** @return array<string, int> 'YYYY-MM' => recuperações (pela data de conversão) */
    public function recuperacoesPorMes(int $tenantId, string $de, string $ate, ?int $modalidadeId): array
    {
        $q = DB::table('prospeccao_oportunidades as o')
            ->join('matriculas as mc', 'mc.id', '=', 'o.matricula_conversao_id')
            ->join('planos as pc', 'pc.id', '=', 'mc.plano_id')
            ->where('o.tenant_id', $tenantId)->where('o.status', ProspeccaoStatus::RECUPERADO)
            ->where('o.convertida_em', '>=', $de)->where('o.convertida_em', '<', $ate.' 23:59:60');
        if ($modalidadeId) {
            $q->where('pc.modalidade_id', $modalidadeId);
        }

        return $q->groupBy(DB::raw('SUBSTR(o.convertida_em, 1, 7)'))
            ->orderBy(DB::raw('SUBSTR(o.convertida_em, 1, 7)'))
            ->select([DB::raw('SUBSTR(o.convertida_em, 1, 7) as mes'), DB::raw('COUNT(*) as total')])
            ->pluck('total', 'mes')
            ->map(fn ($v) => (int) $v)->all();
    }

    /** @return array<string, int> 'YYYY-MM' => alunos que cancelaram (coorte por mês) */
    public function cancelamentosPorMes(int $tenantId, string $de, string $ate, ?int $modalidadeId): array
    {
        return $this->coorte($tenantId, $de, $ate, $modalidadeId)
            ->groupBy(DB::raw('SUBSTR(o.data_cancelamento_referencia, 1, 7)'))
            ->select([DB::raw('SUBSTR(o.data_cancelamento_referencia, 1, 7) as mes'), DB::raw('COUNT(DISTINCT o.aluno_id) as total')])
            ->pluck('total', 'mes')
            ->map(fn ($v) => (int) $v)->all();
    }

    /** @return list<array{modalidade_id: ?int, modalidade: ?string, cor: ?string, total: int}> */
    public function recuperacoesPorModalidade(int $tenantId, string $de, string $ate, ?int $modalidadeId): array
    {
        return $this->coorte($tenantId, $de, $ate, $modalidadeId)
            ->where('o.status', ProspeccaoStatus::RECUPERADO)
            ->leftJoin('matriculas as mc', 'mc.id', '=', 'o.matricula_conversao_id')
            ->leftJoin('planos as pc', 'pc.id', '=', 'mc.plano_id')
            ->leftJoin('modalidades as md', 'md.id', '=', DB::raw('COALESCE(pc.modalidade_id, p.modalidade_id)'))
            ->groupBy('md.id', 'md.nome', 'md.cor')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->select(['md.id as modalidade_id', 'md.nome as modalidade', 'md.cor', DB::raw('COUNT(*) as total')])
            ->get()->map(fn ($r) => ['modalidade_id' => $r->modalidade_id ? (int) $r->modalidade_id : null, 'modalidade' => $r->modalidade, 'cor' => $r->cor, 'total' => (int) $r->total])->all();
    }

    /** @return list<array{usuario_id: ?int, nome: ?string, contatos: int, oportunidades: int}> */
    public function contatosPorUsuario(int $tenantId, string $de, string $ate): array
    {
        return DB::table('prospeccao_interacoes as i')
            ->leftJoin('usuarios as u', 'u.id', '=', 'i.usuario_id')
            ->where('i.tenant_id', $tenantId)
            ->where('i.ocorrido_em', '>=', $de)->where('i.ocorrido_em', '<', $ate.' 23:59:60')
            ->groupBy('i.usuario_id', 'u.nome')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->select(['i.usuario_id', 'u.nome', DB::raw('COUNT(*) as contatos'), DB::raw('COUNT(DISTINCT i.oportunidade_id) as oportunidades')])
            ->get()->map(fn ($r) => ['usuario_id' => $r->usuario_id ? (int) $r->usuario_id : null, 'nome' => $r->nome, 'contatos' => (int) $r->contatos, 'oportunidades' => (int) $r->oportunidades])->all();
    }

    /** @return array{hoje: int, atrasados: int} */
    public function contagemAgenda(int $tenantId, string $inicioHoje, string $inicioAmanha): array
    {
        $base = DB::table('prospeccao_agendamentos')->where('tenant_id', $tenantId)->where('situacao', 'pendente');

        return [
            'hoje' => (clone $base)->where('agendado_para', '>=', $inicioHoje)->where('agendado_para', '<', $inicioAmanha)->count(),
            'atrasados' => (clone $base)->where('agendado_para', '<', $inicioHoje)->count(),
        ];
    }

    /** @return list<array{id: int, nome: string}> */
    public function modalidades(int $tenantId): array
    {
        return DB::table('modalidades')->where('tenant_id', $tenantId)->orderBy('nome')
            ->select(['id', 'nome', 'cor'])->get()->map(fn ($r) => (array) $r)->all();
    }

    /** @return list<array{id: int, nome: string, modalidade_id: int}> */
    public function planos(int $tenantId): array
    {
        return DB::table('planos')->where('tenant_id', $tenantId)->orderBy('nome')
            ->select(['id', 'nome', 'modalidade_id'])->get()->map(fn ($r) => (array) $r)->all();
    }

    public function tenantsAtivos(): array
    {
        return DB::table('tenants')->where('ativo', 1)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
