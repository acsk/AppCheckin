<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Queries admin de matrículas (painel). Paridade com MatriculaController Slim (Wave A).
 */
class AdminMatriculaRepository
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{rows: list<array<string, mixed>>, meta: ?array{total: int, pagina: int, por_pagina: int, total_paginas: int}}
     */
    public function listar(int $tenantId, array $filters): array
    {
        $incluirInativos = ($filters['incluir_inativos'] ?? null) === 'true';
        $pagina = isset($filters['pagina']) ? max(1, (int) $filters['pagina']) : null;
        $porPagina = isset($filters['por_pagina']) ? max(1, (int) $filters['por_pagina']) : 50;
        $usarPaginacao = $pagina !== null || isset($filters['por_pagina']);

        $query = DB::table('matriculas as m')
            ->join('alunos as a', 'm.aluno_id', '=', 'a.id')
            ->join('usuarios as u', 'a.usuario_id', '=', 'u.id')
            ->join('planos as p', 'm.plano_id', '=', 'p.id')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->leftJoin('modalidades as modalidade', 'p.modalidade_id', '=', 'modalidade.id')
            ->leftJoin('usuarios as admin_criou', 'm.criado_por', '=', 'admin_criou.id')
            ->where('m.tenant_id', $tenantId)
            ->select([
                'm.id',
                'm.aluno_id',
                'a.usuario_id',
                'a.nome as usuario_nome',
                'u.email as usuario_email',
                'm.plano_id',
                'p.nome as plano_nome',
                'm.valor',
                'm.data_inicio',
                'm.proxima_data_vencimento',
                'm.status_id',
                'modalidade.nome as modalidade_nome',
                'modalidade.icone as modalidade_icone',
                'modalidade.cor as modalidade_cor',
                'sm.codigo as status_codigo',
                'sm.nome as status_nome',
            ])
            ->orderByDesc('m.created_at');

        if (! $incluirInativos) {
            $query->where('a.ativo', 1);
        }

        if (isset($filters['aluno_id'])) {
            $query->where('m.aluno_id', (int) $filters['aluno_id']);
        }

        if (isset($filters['status'])) {
            $query->whereRaw(
                'm.status_id = (SELECT id FROM status_matricula WHERE codigo = ? LIMIT 1)',
                [(string) $filters['status']]
            );
        }

        if (! empty($filters['busca'])) {
            $like = '%'.$filters['busca'].'%';
            $query->where(function ($q) use ($like) {
                $q->where('a.nome', 'like', $like)
                    ->orWhere('u.email', 'like', $like);
            });
        }

        $meta = null;
        if ($usarPaginacao) {
            $paginaAtual = $pagina ?? 1;
            $countQuery = clone $query;
            $total = (int) $countQuery->toBase()->getCountForPagination();
            $rows = $query
                ->offset(($paginaAtual - 1) * $porPagina)
                ->limit($porPagina)
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();

            $meta = [
                'total' => $total,
                'pagina' => $paginaAtual,
                'por_pagina' => $porPagina,
                'total_paginas' => $porPagina > 0 ? (int) ceil($total / $porPagina) : 0,
            ];
        } else {
            $rows = $query->get()->map(fn ($r) => (array) $r)->all();
        }

        return ['rows' => $rows, 'meta' => $meta];
    }

    public function findDetalhe(int $id, int $tenantId): ?array
    {
        $row = DB::table('matriculas as m')
            ->join('alunos as a', 'm.aluno_id', '=', 'a.id')
            ->join('usuarios as u', 'a.usuario_id', '=', 'u.id')
            ->join('planos as p', 'm.plano_id', '=', 'p.id')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->leftJoin('modalidades as modalidade', 'p.modalidade_id', '=', 'modalidade.id')
            ->leftJoin('usuarios as admin_criou', 'm.criado_por', '=', 'admin_criou.id')
            ->leftJoin('plano_ciclos as pc', 'pc.id', '=', 'm.plano_ciclo_id')
            ->leftJoin('assinatura_frequencias as af', 'af.id', '=', 'pc.assinatura_frequencia_id')
            ->leftJoin('pacote_contratos as pct', 'pct.id', '=', 'm.pacote_contrato_id')
            ->leftJoin('pacotes as paq', 'paq.id', '=', 'pct.pacote_id')
            ->where('m.id', $id)
            ->where('m.tenant_id', $tenantId)
            ->select([
                'm.*',
                'a.nome as usuario_nome',
                'u.email as usuario_email',
                'a.usuario_id',
                'p.nome as plano_nome',
                'p.valor as plano_valor_base',
                'p.duracao_dias',
                'p.checkins_semanais',
                'modalidade.nome as modalidade_nome',
                'modalidade.icone as modalidade_icone',
                'modalidade.cor as modalidade_cor',
                'sm.codigo as status_codigo',
                'sm.nome as status_nome',
                'admin_criou.nome as criado_por_nome',
                'pc.id as ciclo_id',
                'pc.meses as ciclo_meses',
                'pc.valor as ciclo_valor',
                'pc.valor_mensal_equivalente as ciclo_valor_mensal_equivalente',
                'pc.desconto_percentual as ciclo_desconto_percentual',
                'pc.permite_recorrencia as ciclo_permite_recorrencia',
                'pc.ativo as ciclo_ativo',
                'af.id as ciclo_frequencia_id',
                'af.codigo as ciclo_frequencia_codigo',
                'af.nome as ciclo_frequencia_nome',
                'pct.id as contrato_id',
                'pct.status as contrato_status',
                'pct.data_inicio as contrato_data_inicio',
                'pct.data_fim as contrato_data_fim',
                'pct.valor_total as contrato_valor_total',
                'pct.pagante_usuario_id as contrato_pagante_usuario_id',
                'paq.id as pacote_id',
                'paq.nome as pacote_nome',
                'paq.qtd_beneficiarios as pacote_qtd_beneficiarios',
                'paq.valor_total as pacote_valor_total',
            ])
            ->first();

        return $row ? (array) $row : null;
    }

    public function exists(int $id, int $tenantId): bool
    {
        return DB::table('matriculas')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->exists();
    }

    /**
     * Pagamentos resumidos (aninhados no buscar).
     *
     * @return list<array<string, mixed>>
     */
    public function listarPagamentosResumo(int $matriculaId): array
    {
        return DB::table('pagamentos_plano as pp')
            ->leftJoin('formas_pagamento as fp', 'fp.id', '=', 'pp.forma_pagamento_id')
            ->where('pp.matricula_id', $matriculaId)
            ->orderBy('pp.data_vencimento')
            ->orderBy('pp.id')
            ->selectRaw("
                pp.id,
                CAST(pp.valor AS DECIMAL(10,2)) as valor,
                pp.data_vencimento,
                pp.data_pagamento,
                pp.status_pagamento_id,
                (SELECT nome FROM status_pagamento WHERE id = pp.status_pagamento_id) as status,
                pp.forma_pagamento_id,
                fp.nome as forma_pagamento_nome,
                pp.observacoes
            ")
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * Pagamentos completos (endpoint /pagamentos).
     *
     * @return list<array<string, mixed>>
     */
    public function listarPagamentosCompletos(int $matriculaId, int $tenantId): array
    {
        return DB::table('pagamentos_plano as pp')
            ->join('status_pagamento as sp', 'pp.status_pagamento_id', '=', 'sp.id')
            ->leftJoin('formas_pagamento as fp', 'pp.forma_pagamento_id', '=', 'fp.id')
            ->join('alunos as a', 'pp.aluno_id', '=', 'a.id')
            ->join('planos as pl', 'pp.plano_id', '=', 'pl.id')
            ->leftJoin('usuarios as criador', 'pp.criado_por', '=', 'criador.id')
            ->leftJoin('usuarios as baixador', 'pp.baixado_por', '=', 'baixador.id')
            ->leftJoin('tipos_baixa as tb', 'pp.tipo_baixa_id', '=', 'tb.id')
            ->where('pp.matricula_id', $matriculaId)
            ->where('pp.tenant_id', $tenantId)
            ->orderByDesc('pp.data_vencimento')
            ->select([
                'pp.*',
                'sp.nome as status_pagamento_nome',
                'fp.nome as forma_pagamento_nome',
                'a.nome as aluno_nome',
                'pl.nome as plano_nome',
                'criador.nome as criado_por_nome',
                'baixador.nome as baixado_por_nome',
                'tb.nome as tipo_baixa_nome',
            ])
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    public function findBasicoComStatus(int $id, int $tenantId): ?array
    {
        $row = DB::table('matriculas as m')
            ->leftJoin('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->where('m.id', $id)
            ->where('m.tenant_id', $tenantId)
            ->select('m.*', 'sm.codigo as status_codigo', 'sm.permite_checkin')
            ->first();

        return $row ? (array) $row : null;
    }

    public function atualizarStatusObservacoes(int $id, int $statusId, string $observacoes): void
    {
        DB::table('matriculas')->where('id', $id)->update([
            'status_id' => $statusId,
            'observacoes' => $observacoes,
            'updated_at' => now(),
        ]);
    }

    public function cancelar(int $id, ?int $adminId, string $motivo): void
    {
        DB::update(
            "UPDATE matriculas
             SET status_id = (SELECT id FROM status_matricula WHERE codigo = 'cancelada'),
                 cancelado_por = ?,
                 data_cancelamento = CURDATE(),
                 motivo_cancelamento = ?,
                 updated_at = NOW()
             WHERE id = ?",
            [$adminId, $motivo, $id]
        );
    }

    public function desativarDescontosSeTabelaExiste(int $tenantId, int $matriculaId): void
    {
        try {
            if (! Schema::hasTable('matricula_descontos')) {
                return;
            }

            DB::table('matricula_descontos')
                ->where('tenant_id', $tenantId)
                ->where('matricula_id', $matriculaId)
                ->where('ativo', 1)
                ->update([
                    'ativo' => 0,
                    'updated_at' => now(),
                ]);
        } catch (\Throwable) {
            // Soft-fail: tabela/colunas podem não existir em todos os ambientes
        }
    }

    public function clearUsuarioPlanoSeColunasExistem(int $usuarioId): void
    {
        try {
            if (! Schema::hasColumn('usuarios', 'plano_id')) {
                return;
            }

            $payload = [
                'plano_id' => null,
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('usuarios', 'data_vencimento_plano')) {
                $payload['data_vencimento_plano'] = null;
            }

            DB::table('usuarios')->where('id', $usuarioId)->update($payload);
        } catch (\Throwable) {
            // Soft-fail
        }
    }

    public function statusIdPorCodigo(string $codigo): ?int
    {
        $id = DB::table('status_matricula')->where('codigo', $codigo)->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function findResposta(int $id): ?array
    {
        $row = DB::table('matriculas as m')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->join('alunos as a', 'm.aluno_id', '=', 'a.id')
            ->join('usuarios as u', 'a.usuario_id', '=', 'u.id')
            ->join('planos as p', 'm.plano_id', '=', 'p.id')
            ->where('m.id', $id)
            ->select([
                'm.*',
                'sm.codigo as status_codigo',
                'sm.nome as status_nome',
                'a.nome as usuario_nome',
                'u.email as usuario_email',
                'p.nome as plano_nome',
            ])
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * @return array{ok: bool}
     */
    public function atualizarProximaDataVencimento(
        int $id,
        int $tenantId,
        string $proximaDataVencimento,
        ?int $statusId,
        bool $atualizarDataVencimento
    ): array {
        $payload = [
            'proxima_data_vencimento' => $proximaDataVencimento,
            'updated_at' => now(),
        ];

        if ($atualizarDataVencimento) {
            $payload['data_vencimento'] = $proximaDataVencimento;
        }

        if ($statusId !== null) {
            $payload['status_id'] = $statusId;
        }

        $affected = DB::table('matriculas')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update($payload);

        return ['ok' => $affected > 0];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function vencimentosHoje(int $tenantId): array
    {
        $hoje = date('Y-m-d');

        return DB::table('matriculas as m')
            ->join('alunos as a', 'm.aluno_id', '=', 'a.id')
            ->join('usuarios as u', 'a.usuario_id', '=', 'u.id')
            ->join('planos as p', 'm.plano_id', '=', 'p.id')
            ->join('status_matricula as sm', 'm.status_id', '=', 'sm.id')
            ->where('m.tenant_id', $tenantId)
            ->where('m.proxima_data_vencimento', $hoje)
            ->where('sm.codigo', 'ativa')
            ->orderBy('a.nome')
            ->get([
                'm.id',
                'm.aluno_id',
                'm.plano_id',
                'm.proxima_data_vencimento',
                'm.valor',
                'm.dia_vencimento',
                'm.periodo_teste',
                'a.nome as aluno_nome',
                'u.email as aluno_email',
                'u.telefone as aluno_telefone',
                'p.nome as plano_nome',
                'p.valor as plano_valor',
                'sm.nome as status_nome',
                'sm.codigo as status_codigo',
            ])
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function proximosVencimentos(int $tenantId, int $dias): array
    {
        $dataLimite = date('Y-m-d', strtotime("+{$dias} days"));

        return DB::table('matriculas as m')
            ->join('alunos as a', 'm.aluno_id', '=', 'a.id')
            ->join('usuarios as u', 'a.usuario_id', '=', 'u.id')
            ->join('planos as p', 'm.plano_id', '=', 'p.id')
            ->join('status_matricula as sm', 'm.status_id', '=', 'sm.id')
            ->where('m.tenant_id', $tenantId)
            ->whereRaw('m.proxima_data_vencimento BETWEEN CURDATE() AND ?', [$dataLimite])
            ->where('sm.codigo', 'ativa')
            ->orderBy('m.proxima_data_vencimento')
            ->orderBy('a.nome')
            ->selectRaw('
                m.id,
                m.aluno_id,
                m.plano_id,
                m.proxima_data_vencimento,
                m.valor,
                m.dia_vencimento,
                m.periodo_teste,
                DATEDIFF(m.proxima_data_vencimento, CURDATE()) as dias_restantes,
                a.nome as aluno_nome,
                u.email as aluno_email,
                u.telefone as aluno_telefone,
                p.nome as plano_nome,
                p.valor as plano_valor,
                sm.nome as status_nome,
                sm.codigo as status_codigo
            ')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function beneficiariosPacote(int $pacoteContratoId, int $tenantId, int $paganteUsuarioId): array
    {
        return DB::table('pacote_beneficiarios as pb')
            ->join('alunos as al', 'al.id', '=', 'pb.aluno_id')
            ->where('pb.pacote_contrato_id', $pacoteContratoId)
            ->where('pb.tenant_id', $tenantId)
            ->orderByRaw('CASE WHEN al.usuario_id = ? THEN 1 ELSE 0 END DESC', [$paganteUsuarioId])
            ->orderBy('al.nome')
            ->select([
                'pb.aluno_id',
                'pb.valor_rateado',
                'pb.status',
                'al.nome as aluno_nome',
            ])
            ->selectRaw('CASE WHEN al.usuario_id = ? THEN 1 ELSE 0 END as is_pagante', [$paganteUsuarioId])
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * @return list<string>
     */
    public function mpPaymentIds(int $matriculaId, int $tenantId): array
    {
        $ids = [];

        try {
            if (Schema::hasTable('pagamentos_mercadopago')) {
                $rows = DB::table('pagamentos_mercadopago')
                    ->where('matricula_id', $matriculaId)
                    ->where('tenant_id', $tenantId)
                    ->whereNotNull('payment_id')
                    ->distinct()
                    ->pluck('payment_id');
                foreach ($rows as $pid) {
                    if ($pid !== null && $pid !== '') {
                        $ids[] = (string) $pid;
                    }
                }
            }

            if (Schema::hasTable('webhook_payloads_mercadopago')) {
                $externalPattern = 'MAT-'.$matriculaId.'-%';
                $rows = DB::table('webhook_payloads_mercadopago')
                    ->where(function ($q) use ($externalPattern, $matriculaId) {
                        $q->where('external_reference', 'like', $externalPattern)
                            ->orWhere('external_reference', 'MAT-'.$matriculaId);
                    })
                    ->whereNotNull('payment_id')
                    ->distinct()
                    ->pluck('payment_id');
                foreach ($rows as $pid) {
                    if ($pid !== null && $pid !== '') {
                        $ids[] = (string) $pid;
                    }
                }
            }
        } catch (\Throwable) {
            return [];
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array{saldo_total: float, creditos_ativos: list<array<string, mixed>>}
     */
    public function creditosAluno(int $tenantId, int $alunoId): array
    {
        try {
            if (! Schema::hasTable('creditos_aluno')) {
                return ['saldo_total' => 0.0, 'creditos_ativos' => []];
            }

            $saldo = (float) DB::table('creditos_aluno')
                ->where('tenant_id', $tenantId)
                ->where('aluno_id', $alunoId)
                ->where('status_credito_id', 1)
                ->whereRaw('(valor - valor_utilizado) > 0')
                ->selectRaw('COALESCE(SUM(valor - valor_utilizado), 0) as saldo')
                ->value('saldo');

            $ativos = DB::table('creditos_aluno as ca')
                ->join('status_creditos_aluno as sca', 'sca.id', '=', 'ca.status_credito_id')
                ->where('ca.tenant_id', $tenantId)
                ->where('ca.aluno_id', $alunoId)
                ->where('ca.status_credito_id', 1)
                ->whereRaw('(ca.valor - ca.valor_utilizado) > 0')
                ->orderBy('ca.created_at')
                ->selectRaw('ca.*, sca.codigo as status, sca.nome as status_nome, (ca.valor - ca.valor_utilizado) as saldo')
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();

            return [
                'saldo_total' => $saldo,
                'creditos_ativos' => $ativos,
            ];
        } catch (\Throwable) {
            return ['saldo_total' => 0.0, 'creditos_ativos' => []];
        }
    }

    public function findUsuarioIdPorAluno(int $alunoId): ?int
    {
        $id = DB::table('alunos')->where('id', $alunoId)->value('usuario_id');

        return $id !== null ? (int) $id : null;
    }

    public function findAlunoIdPorUsuario(int $usuarioId): ?int
    {
        $id = DB::table('alunos')->where('usuario_id', $usuarioId)->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function findNomeUsuario(int $usuarioId): ?string
    {
        $nome = DB::table('usuarios')->where('id', $usuarioId)->value('nome');

        return $nome !== null ? (string) $nome : null;
    }

    /**
     * Usuário com papel aluno (papel_id = 1) em qualquer tenant ativo.
     */
    public function findUsuarioAluno(int $usuarioId): ?array
    {
        $row = DB::table('usuarios as u')
            ->join('tenant_usuario_papel as tup', function ($join) {
                $join->on('tup.usuario_id', '=', 'u.id')
                    ->where('tup.ativo', 1)
                    ->where('tup.papel_id', 1);
            })
            ->where('u.id', $usuarioId)
            ->select('u.*')
            ->first();

        return $row ? (array) $row : null;
    }

    public function ensureVinculoAlunoTenant(int $usuarioId, int $tenantId): void
    {
        $vinculo = DB::table('tenant_usuario_papel')
            ->where('usuario_id', $usuarioId)
            ->where('tenant_id', $tenantId)
            ->where('papel_id', 1)
            ->first();

        if (! $vinculo) {
            DB::table('tenant_usuario_papel')->insert([
                'usuario_id' => $usuarioId,
                'tenant_id' => $tenantId,
                'papel_id' => 1,
                'ativo' => 1,
                'created_at' => now(),
            ]);

            return;
        }

        if ((int) $vinculo->ativo !== 1) {
            DB::table('tenant_usuario_papel')
                ->where('usuario_id', $usuarioId)
                ->where('tenant_id', $tenantId)
                ->where('papel_id', 1)
                ->update([
                    'ativo' => 1,
                    'updated_at' => now(),
                ]);
        }
    }

    public function findUsuarioAlunoNoTenant(int $usuarioId, int $tenantId): ?array
    {
        $row = DB::table('usuarios as u')
            ->join('tenant_usuario_papel as tup', function ($join) use ($tenantId) {
                $join->on('tup.usuario_id', '=', 'u.id')
                    ->where('tup.ativo', 1)
                    ->where('tup.tenant_id', $tenantId)
                    ->where('tup.papel_id', 1);
            })
            ->where('u.id', $usuarioId)
            ->select('u.*')
            ->first();

        return $row ? (array) $row : null;
    }

    public function findPlano(int $planoId, int $tenantId): ?array
    {
        $row = DB::table('planos')
            ->where('id', $planoId)
            ->where('tenant_id', $tenantId)
            ->first();

        return $row ? (array) $row : null;
    }

    public function findPlanoCicloAtivo(int $cicloId, int $planoId, int $tenantId): ?array
    {
        $row = DB::table('plano_ciclos as pc')
            ->leftJoin('assinatura_frequencias as af', 'af.id', '=', 'pc.assinatura_frequencia_id')
            ->where('pc.id', $cicloId)
            ->where('pc.plano_id', $planoId)
            ->where('pc.tenant_id', $tenantId)
            ->where('pc.ativo', 1)
            ->select([
                'pc.*',
                'af.codigo as frequencia_codigo',
                'af.nome as frequencia_nome',
                'af.meses as frequencia_meses',
            ])
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * Matrículas ativas ainda válidas do aluno (para checagem de modalidade).
     *
     * @return list<array<string, mixed>>
     */
    public function matriculasAtivasValidas(int $alunoId, int $tenantId): array
    {
        return DB::table('matriculas as m')
            ->join('planos as p', 'p.id', '=', 'm.plano_id')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->where('m.aluno_id', $alunoId)
            ->where('m.tenant_id', $tenantId)
            ->where('sm.codigo', 'ativa')
            ->whereRaw('COALESCE(m.proxima_data_vencimento, m.data_vencimento) >= CURDATE()')
            ->orderByDesc('m.created_at')
            ->select('m.*', 'p.modalidade_id')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * Bloqueia criar duplicata ativa/pendente com mesmo plano+ciclo+modalidade.
     * Vencida/cancelada devem ser reutilizadas, não bloqueadas.
     */
    public function buscarMatriculaDuplicadaMesmoPlanoCiclo(
        int $tenantId,
        int $alunoId,
        int $planoId,
        ?int $planoCicloId,
        ?int $modalidadeId
    ): ?array {
        if ($modalidadeId === null) {
            return null;
        }

        $query = DB::table('matriculas as m')
            ->join('planos as p', 'p.id', '=', 'm.plano_id')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->where('m.tenant_id', $tenantId)
            ->where('m.aluno_id', $alunoId)
            ->where('m.plano_id', $planoId)
            ->where('p.modalidade_id', $modalidadeId)
            ->whereIn('sm.codigo', ['ativa', 'pendente']);

        if ($planoCicloId !== null) {
            $query->where('m.plano_ciclo_id', $planoCicloId);
        } else {
            $query->whereNull('m.plano_ciclo_id');
        }

        $row = $query
            ->orderByDesc('m.updated_at')
            ->orderByDesc('m.id')
            ->first(['m.id', 'sm.codigo as status_codigo', 'm.proxima_data_vencimento', 'm.data_vencimento']);

        return $row ? (array) $row : null;
    }

    /**
     * Última matrícula do aluno na modalidade (para reuso vencida/cancelada).
     */
    public function ultimaMatriculaNaModalidade(int $alunoId, int $tenantId, int $modalidadeId): ?array
    {
        $row = DB::table('matriculas as m')
            ->join('planos as p', 'p.id', '=', 'm.plano_id')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->where('m.aluno_id', $alunoId)
            ->where('m.tenant_id', $tenantId)
            ->where('p.modalidade_id', $modalidadeId)
            ->orderByDesc('m.updated_at')
            ->orderByDesc('m.id')
            ->select('m.*', 'p.modalidade_id', 'sm.codigo as status_codigo')
            ->first();

        return $row ? (array) $row : null;
    }

    public function marcarStatusMatricula(int $id, int $tenantId, int $statusId): void
    {
        DB::table('matriculas')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update([
                'status_id' => $statusId,
                'updated_at' => now(),
            ]);
    }

    public function finalizarMatricula(int $id): void
    {
        DB::update(
            "UPDATE matriculas
             SET status_id = (SELECT id FROM status_matricula WHERE codigo = 'finalizada'),
                 updated_at = NOW()
             WHERE id = ?",
            [$id]
        );
    }

    public function countParcelasEmAtraso(int $matriculaId): int
    {
        return (int) DB::table('pagamentos_plano')
            ->where('matricula_id', $matriculaId)
            ->where('status_pagamento_id', 1)
            ->whereRaw('data_vencimento < CURDATE()')
            ->count();
    }

    public function countPagamentoAtivoContasReceber(int $usuarioId, int $tenantId): int
    {
        try {
            if (! Schema::hasTable('contas_receber')) {
                return 0;
            }

            return (int) DB::table('contas_receber')
                ->where('usuario_id', $usuarioId)
                ->where('tenant_id', $tenantId)
                ->where('status', 'pago')
                ->whereRaw('data_vencimento <= CURDATE()')
                ->whereRaw('DATE_ADD(data_vencimento, INTERVAL intervalo_dias DAY) >= CURDATE()')
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function motivoIdPorCodigo(string $codigo): int
    {
        $id = DB::table('motivo_matricula')->where('codigo', $codigo)->value('id');

        return $id !== null ? (int) $id : 1;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function atualizarMatriculaReuse(int $id, int $tenantId, array $payload): void
    {
        $payload['updated_at'] = now();
        DB::table('matriculas')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function inserirMatricula(array $payload): int
    {
        $payload['created_at'] = $payload['created_at'] ?? now();
        $payload['updated_at'] = $payload['updated_at'] ?? now();

        return (int) DB::table('matriculas')->insertGetId($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function inserirHistoricoPlanos(array $payload): void
    {
        try {
            DB::table('historico_planos')->insert($payload);
        } catch (\Throwable) {
            // Soft-fail: tabela/colunas podem variar
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function inserirPagamentoPlano(array $payload): int
    {
        $payload['created_at'] = $payload['created_at'] ?? now();
        $payload['updated_at'] = $payload['updated_at'] ?? now();

        return (int) DB::table('pagamentos_plano')->insertGetId($payload);
    }

    /**
     * Resposta de criar (formato Slim).
     */
    public function findMatriculaCriada(int $id): ?array
    {
        $row = DB::table('matriculas as m')
            ->join('alunos as a', 'm.aluno_id', '=', 'a.id')
            ->join('usuarios as u', 'a.usuario_id', '=', 'u.id')
            ->join('planos as p', 'm.plano_id', '=', 'p.id')
            ->leftJoin('usuarios as admin', 'm.criado_por', '=', 'admin.id')
            ->where('m.id', $id)
            ->select([
                'm.*',
                'a.nome as aluno_nome',
                'u.email as aluno_email',
                'p.nome as plano_nome',
                'p.duracao_dias',
                'admin.nome as criado_por_nome',
            ])
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * Pagamentos resumidos no formato do criar Slim.
     *
     * @return list<array<string, mixed>>
     */
    public function listarPagamentosCriar(int $matriculaId): array
    {
        return DB::table('pagamentos_plano')
            ->where('matricula_id', $matriculaId)
            ->orderBy('data_vencimento')
            ->selectRaw("
                id,
                CAST(valor AS DECIMAL(10,2)) as valor,
                data_vencimento,
                data_pagamento,
                status_pagamento_id,
                (SELECT nome FROM status_pagamento WHERE id = status_pagamento_id) as status,
                observacoes
            ")
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    public function findPagamentoParaBaixa(int $pagamentoId, int $tenantId): ?array
    {
        $row = DB::table('pagamentos_plano as pp')
            ->join('matriculas as m', 'pp.matricula_id', '=', 'm.id')
            ->join('planos as p', 'pp.plano_id', '=', 'p.id')
            ->leftJoin('plano_ciclos as pc', 'pc.id', '=', 'm.plano_ciclo_id')
            ->leftJoin('assinatura_frequencias as af', 'af.id', '=', 'pc.assinatura_frequencia_id')
            ->where('pp.id', $pagamentoId)
            ->where('pp.tenant_id', $tenantId)
            ->select([
                'pp.*',
                'm.plano_id',
                'm.plano_ciclo_id',
                'p.duracao_dias',
                'm.valor as matricula_valor',
                'pc.meses as ciclo_meses',
                'af.meses as frequencia_meses',
            ])
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function atualizarPagamentoBaixa(int $pagamentoId, array $payload): void
    {
        $payload['updated_at'] = now();
        DB::table('pagamentos_plano')->where('id', $pagamentoId)->update($payload);
    }

    public function ativarMatriculaSePendente(int $matriculaId): void
    {
        DB::update(
            "UPDATE matriculas
             SET status_id = (SELECT id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1),
                 updated_at = NOW()
             WHERE id = ?
             AND status_id = (SELECT id FROM status_matricula WHERE codigo = 'pendente' LIMIT 1)",
            [$matriculaId]
        );
    }

    public function atualizarProximaDataVencimentoSimples(int $matriculaId, string $proximaData): void
    {
        DB::table('matriculas')->where('id', $matriculaId)->update([
            'proxima_data_vencimento' => $proximaData,
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array{status_codigo: ?string, status_nome: ?string}
     */
    public function statusAtual(int $matriculaId): array
    {
        $row = DB::table('matriculas as m')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->where('m.id', $matriculaId)
            ->first(['sm.codigo as status_codigo', 'sm.nome as status_nome']);

        return [
            'status_codigo' => $row->status_codigo ?? null,
            'status_nome' => $row->status_nome ?? null,
        ];
    }

    /**
     * Matrícula + plano para alterarPlano.
     *
     * @return array<string, mixed>|null
     */
    public function findParaAlterarPlano(int $id, int $tenantId): ?array
    {
        $row = DB::table('matriculas as m')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->join('planos as p', 'p.id', '=', 'm.plano_id')
            ->where('m.id', $id)
            ->where('m.tenant_id', $tenantId)
            ->select([
                'm.*',
                'sm.codigo as status_codigo',
                'p.modalidade_id',
                'p.nome as plano_nome',
                'p.valor as plano_valor',
                'p.duracao_dias',
            ])
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findCicloDoPlano(int $cicloId, int $planoId, int $tenantId): ?array
    {
        $row = DB::table('plano_ciclos as pc')
            ->leftJoin('assinatura_frequencias as af', 'af.id', '=', 'pc.assinatura_frequencia_id')
            ->where('pc.id', $cicloId)
            ->where('pc.plano_id', $planoId)
            ->where('pc.tenant_id', $tenantId)
            ->select(['pc.*', 'af.meses as frequencia_meses'])
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * Status ativa (ou ativo, legado).
     */
    public function statusIdAtiva(): ?int
    {
        return $this->statusIdPorCodigo('ativa') ?? $this->statusIdPorCodigo('ativo');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function atualizarAposAlterarPlano(int $id, int $tenantId, array $payload): void
    {
        $payload['cancelado_por'] = null;
        $payload['data_cancelamento'] = null;
        $payload['motivo_cancelamento'] = null;
        $payload['updated_at'] = now();

        DB::table('matriculas')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update($payload);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findAposAlterarPlano(int $id): ?array
    {
        $row = DB::table('matriculas as m')
            ->join('planos as p', 'p.id', '=', 'm.plano_id')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->where('m.id', $id)
            ->select([
                'm.*',
                'p.nome as plano_nome',
                'sm.codigo as status_codigo',
                'sm.nome as status_nome',
            ])
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function ultimoPagamentoPago(int $matriculaId, int $tenantId): ?array
    {
        $row = DB::table('pagamentos_plano')
            ->where('matricula_id', $matriculaId)
            ->where('tenant_id', $tenantId)
            ->where('status_pagamento_id', 2)
            ->orderByDesc('data_vencimento')
            ->first(['id', 'valor', 'data_pagamento', 'data_vencimento']);

        return $row ? (array) $row : null;
    }

    public function cancelarPagamentoComoCredito(int $pagamentoId, int $tenantId): void
    {
        DB::update(
            "UPDATE pagamentos_plano
             SET status_pagamento_id = 4,
                 observacoes = CONCAT(COALESCE(observacoes, ''), ' [Convertido em crédito na alteração de plano]'),
                 updated_at = NOW()
             WHERE id = ? AND tenant_id = ? AND status_pagamento_id = 2",
            [$pagamentoId, $tenantId]
        );
    }

    /**
     * Soft-fail se tabela creditos_aluno não existir.
     *
     * @param  array<string, mixed>  $payload
     */
    public function inserirCredito(array $payload): ?int
    {
        try {
            if (! Schema::hasTable('creditos_aluno')) {
                return null;
            }

            $payload['created_at'] = $payload['created_at'] ?? now();
            $payload['updated_at'] = $payload['updated_at'] ?? now();
            if (! isset($payload['status_credito_id'])) {
                $payload['status_credito_id'] = 1;
            }
            if (! isset($payload['valor_utilizado'])) {
                $payload['valor_utilizado'] = 0;
            }

            return (int) DB::table('creditos_aluno')->insertGetId($payload);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Soft-fail se tabela não existir.
     */
    public function utilizarCredito(int $creditoId, float $valorUtilizar): bool
    {
        try {
            if (! Schema::hasTable('creditos_aluno')) {
                return false;
            }

            $credito = DB::table('creditos_aluno')->where('id', $creditoId)->first();
            if (! $credito) {
                return false;
            }

            $saldo = (float) $credito->valor - (float) $credito->valor_utilizado;
            if ($valorUtilizar > $saldo) {
                $valorUtilizar = $saldo;
            }

            $novoUtilizado = (float) $credito->valor_utilizado + $valorUtilizar;
            $novoSaldo = (float) $credito->valor - $novoUtilizado;
            $novoStatusId = $novoSaldo <= 0.001 ? 2 : 1;

            DB::table('creditos_aluno')->where('id', $creditoId)->update([
                'valor_utilizado' => round($novoUtilizado, 2),
                'status_credito_id' => $novoStatusId,
                'updated_at' => now(),
            ]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function marcarCreditoUtilizadoParcial(int $creditoId, float $valorUtilizado, int $statusCreditoId): void
    {
        try {
            if (! Schema::hasTable('creditos_aluno')) {
                return;
            }

            DB::table('creditos_aluno')->where('id', $creditoId)->update([
                'valor_utilizado' => round($valorUtilizado, 2),
                'status_credito_id' => $statusCreditoId,
                'updated_at' => now(),
            ]);
        } catch (\Throwable) {
            // Soft-fail
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findParaDeletePreview(int $id, int $tenantId): ?array
    {
        $row = DB::table('matriculas as m')
            ->join('alunos as a', 'm.aluno_id', '=', 'a.id')
            ->join('usuarios as u', 'a.usuario_id', '=', 'u.id')
            ->join('planos as p', 'm.plano_id', '=', 'p.id')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->leftJoin('motivo_matricula as mm', 'mm.id', '=', 'm.motivo_id')
            ->leftJoin('modalidades as modalidade', 'p.modalidade_id', '=', 'modalidade.id')
            ->where('m.id', $id)
            ->where('m.tenant_id', $tenantId)
            ->select([
                'm.id',
                'm.tenant_id',
                'm.aluno_id',
                'a.usuario_id',
                'a.nome as aluno_nome',
                'u.email as aluno_email',
                'u.telefone as aluno_telefone',
                'm.plano_id',
                'm.plano_ciclo_id',
                'm.tipo_cobranca',
                'm.data_matricula',
                'm.data_inicio',
                'm.data_vencimento',
                'm.dia_vencimento',
                'm.periodo_teste',
                'm.data_inicio_cobranca',
                'm.proxima_data_vencimento',
                'm.valor',
                'm.status_id',
                'sm.codigo as status_codigo',
                'sm.nome as status_nome',
                'm.motivo_id',
                'mm.codigo as motivo_codigo',
                'mm.nome as motivo_nome',
                'm.matricula_anterior_id',
                'm.plano_anterior_id',
                'm.observacoes',
                'm.created_at',
                'm.updated_at',
                'p.nome as plano_nome',
                'p.valor as plano_valor',
                'p.duracao_dias',
                'p.checkins_semanais',
                'modalidade.id as modalidade_id',
                'modalidade.nome as modalidade_nome',
                'modalidade.icone as modalidade_icone',
                'modalidade.cor as modalidade_cor',
            ])
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarPagamentosDeletePreview(int $matriculaId, int $tenantId): array
    {
        return DB::table('pagamentos_plano as pp')
            ->leftJoin('status_pagamento as sp', 'sp.id', '=', 'pp.status_pagamento_id')
            ->leftJoin('formas_pagamento as fp', 'fp.id', '=', 'pp.forma_pagamento_id')
            ->where('pp.matricula_id', $matriculaId)
            ->where('pp.tenant_id', $tenantId)
            ->orderBy('pp.data_vencimento')
            ->selectRaw("
                pp.id,
                CAST(pp.valor AS DECIMAL(10,2)) as valor,
                pp.data_vencimento,
                pp.data_pagamento,
                pp.status_pagamento_id,
                sp.nome as status,
                pp.forma_pagamento_id,
                fp.nome as forma_pagamento_nome,
                pp.observacoes,
                pp.created_at,
                pp.updated_at
            ")
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * Soft-fail se tabela assinaturas não existir.
     *
     * @return list<array<string, mixed>>
     */
    public function listarAssinaturasDeletePreview(int $matriculaId, int $tenantId): array
    {
        try {
            if (! Schema::hasTable('assinaturas')) {
                return [];
            }

            return DB::table('assinaturas as a')
                ->leftJoin('assinatura_gateways as g', 'a.gateway_id', '=', 'g.id')
                ->leftJoin('assinatura_status as s', 'a.status_id', '=', 's.id')
                ->leftJoin('assinatura_frequencias as f', 'a.frequencia_id', '=', 'f.id')
                ->leftJoin('metodos_pagamento as mp', 'a.metodo_pagamento_id', '=', 'mp.id')
                ->leftJoin('assinatura_cancelamento_tipos as ct', 'a.cancelado_por_id', '=', 'ct.id')
                ->where('a.matricula_id', $matriculaId)
                ->where('a.tenant_id', $tenantId)
                ->orderByDesc('a.id')
                ->select([
                    'a.id',
                    'a.tenant_id',
                    'a.aluno_id',
                    'a.matricula_id',
                    'a.plano_id',
                    'g.codigo as gateway_codigo',
                    'g.nome as gateway_nome',
                    'a.gateway_assinatura_id',
                    'a.gateway_cliente_id',
                    's.codigo as status_codigo',
                    's.nome as status_nome',
                    's.cor as status_cor',
                    'a.status_gateway',
                    'a.valor',
                    'a.moeda',
                    'f.codigo as frequencia_codigo',
                    'f.nome as frequencia_nome',
                    'f.dias as frequencia_dias',
                    'a.data_inicio',
                    'a.data_fim',
                    'a.proxima_cobranca',
                    'a.ultima_cobranca',
                    'mp.codigo as metodo_pagamento_codigo',
                    'mp.nome as metodo_pagamento_nome',
                    'a.cartao_ultimos_digitos',
                    'a.cartao_bandeira',
                    'a.tentativas_cobranca',
                    'a.motivo_cancelamento',
                    'ct.codigo as cancelado_por_codigo',
                    'ct.nome as cancelado_por_nome',
                    'a.criado_em',
                    'a.atualizado_em',
                ])
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Soft-fail se tabela assinaturas_mercadopago não existir.
     *
     * @return list<array<string, mixed>>
     */
    public function listarAssinaturasMpDeletePreview(int $matriculaId, int $tenantId): array
    {
        try {
            if (! Schema::hasTable('assinaturas_mercadopago')) {
                return [];
            }

            return DB::table('assinaturas_mercadopago')
                ->where('matricula_id', $matriculaId)
                ->where('tenant_id', $tenantId)
                ->orderByDesc('id')
                ->select([
                    'id',
                    'tenant_id',
                    'matricula_id',
                    'aluno_id',
                    'plano_ciclo_id',
                    'mp_preapproval_id',
                    'mp_plan_id',
                    'mp_payer_id',
                    'status',
                    'valor',
                    'moeda',
                    'dia_cobranca',
                    'data_inicio',
                    'data_fim',
                    'proxima_cobranca',
                    'ultima_cobranca',
                    'tentativas_falha',
                    'motivo_cancelamento',
                    'cancelado_por',
                    'data_cancelamento',
                    'created_at',
                    'updated_at',
                ])
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Soft-fail se tabela pagamentos_mercadopago não existir.
     *
     * @return list<array<string, mixed>>
     */
    public function listarPagamentosMpDeletePreview(int $matriculaId, int $tenantId): array
    {
        try {
            if (! Schema::hasTable('pagamentos_mercadopago')) {
                return [];
            }

            return DB::table('pagamentos_mercadopago')
                ->where('matricula_id', $matriculaId)
                ->where('tenant_id', $tenantId)
                ->orderByDesc('date_created')
                ->select([
                    'id',
                    'tenant_id',
                    'matricula_id',
                    'aluno_id',
                    'usuario_id',
                    'payment_id',
                    'external_reference',
                    'preference_id',
                    'status',
                    'status_detail',
                    'transaction_amount',
                    'payment_method_id',
                    'payment_type_id',
                    'installments',
                    'date_approved',
                    'date_created',
                    'payer_email',
                    'payer_identification_type',
                    'payer_identification_number',
                    'created_at',
                    'updated_at',
                ])
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findParaHardDelete(int $id, int $tenantId): ?array
    {
        $row = DB::table('matriculas')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first(['id', 'aluno_id', 'pacote_contrato_id']);

        return $row ? (array) $row : null;
    }

    public function desvincularAssinaturas(int $matriculaId, int $tenantId): void
    {
        try {
            if (! Schema::hasTable('assinaturas')) {
                return;
            }

            DB::table('assinaturas')
                ->where('matricula_id', $matriculaId)
                ->where('tenant_id', $tenantId)
                ->update(['matricula_id' => null]);
        } catch (\Throwable) {
            // Soft-fail
        }
    }

    public function deletarAssinaturasMercadopago(int $matriculaId, int $tenantId): void
    {
        try {
            if (! Schema::hasTable('assinaturas_mercadopago')) {
                return;
            }

            DB::table('assinaturas_mercadopago')
                ->where('matricula_id', $matriculaId)
                ->where('tenant_id', $tenantId)
                ->delete();
        } catch (\Throwable) {
            // Soft-fail
        }
    }

    public function deletarPagamentosMercadopago(int $matriculaId, int $tenantId): void
    {
        try {
            if (! Schema::hasTable('pagamentos_mercadopago')) {
                return;
            }

            DB::table('pagamentos_mercadopago')
                ->where('matricula_id', $matriculaId)
                ->where('tenant_id', $tenantId)
                ->delete();
        } catch (\Throwable) {
            // Soft-fail
        }
    }

    public function deletarPagamentosPlano(int $matriculaId, int $tenantId): void
    {
        DB::table('pagamentos_plano')
            ->where('matricula_id', $matriculaId)
            ->where('tenant_id', $tenantId)
            ->delete();
    }

    public function deletarMatricula(int $id, int $tenantId): void
    {
        DB::table('matriculas')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->delete();
    }
}
