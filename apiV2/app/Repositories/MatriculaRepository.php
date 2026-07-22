<?php

namespace App\Repositories;

use App\Support\AcademyDateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MatriculaRepository
{
    public function findElegivelParaCheckin(int $alunoId, int $tenantId): ?array
    {
        $row = DB::table('matriculas as m')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->where('m.aluno_id', $alunoId)
            ->where('m.tenant_id', $tenantId)
            ->where('sm.permite_checkin', 1)
            ->where('sm.ativo', 1)
            ->orderByDesc('m.created_at')
            ->select([
                'm.id',
                'm.proxima_data_vencimento',
                'm.data_vencimento',
                'm.periodo_teste',
                'sm.codigo as status_codigo',
                'sm.nome as status_nome',
            ])
            ->first();

        return $row ? (array) $row : null;
    }

    public function atualizarStatusMatriculasVencidas(int $userId, int $tenantId): void
    {
        try {
            $hoje = date('Y-m-d');

            $matriculasVencidas = DB::table('matriculas as m')
                ->join('alunos as a', 'a.id', '=', 'm.aluno_id')
                ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
                ->where('a.usuario_id', $userId)
                ->where('m.tenant_id', $tenantId)
                ->where('sm.permite_checkin', 1)
                ->where('sm.ativo', 1)
                ->whereRaw('COALESCE(m.proxima_data_vencimento, m.data_vencimento) < ?', [$hoje])
                ->select([
                    'm.id',
                    DB::raw('DATEDIFF(?, COALESCE(m.proxima_data_vencimento, m.data_vencimento)) as dias_vencido'),
                ])
                ->addBinding($hoje, 'select')
                ->get();

            foreach ($matriculasVencidas as $matricula) {
                $diasVencido = (int) $matricula->dias_vencido;
                $novoStatus = $diasVencido >= 5 ? 'cancelada' : 'vencida';

                DB::table('matriculas')
                    ->where('id', $matricula->id)
                    ->update([
                        'status_id' => DB::table('status_matricula')
                            ->where('codigo', $novoStatus)
                            ->value('id'),
                        'updated_at' => now(),
                    ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[MatriculaRepository] Erro ao atualizar matrículas vencidas: '.$e->getMessage());
        }
    }

    public function buscarMatriculaMaisRecentePorAluno(int $alunoId, int $tenantId): ?array
    {
        $row = DB::table('matriculas as m')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->where('m.aluno_id', $alunoId)
            ->where('m.tenant_id', $tenantId)
            ->orderByDesc('m.created_at')
            ->select([
                'm.id',
                'm.proxima_data_vencimento',
                'm.data_vencimento',
                'sm.codigo as status_codigo',
                'sm.nome as status_nome',
                'sm.permite_checkin',
                'sm.ativo as status_ativo',
            ])
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function montarErroMatriculaIndisponivelCheckin(int $alunoId, int $tenantId): array
    {
        $ultima = $this->buscarMatriculaMaisRecentePorAluno($alunoId, $tenantId);
        $restricao = $this->avaliarRestricaoAcessoMatricula($ultima);

        if ($restricao !== null) {
            $erro = [
                'code' => $restricao['code'],
                'error' => $restricao['mensagem'],
                'matricula_id' => $restricao['matricula_id'],
                'status_codigo' => $restricao['status_codigo'],
            ];
            if (! empty($restricao['data_vencimento'])) {
                $erro['data_vencimento'] = $restricao['data_vencimento'];
            }
            if (! empty($restricao['status'])) {
                $erro['status'] = $restricao['status'];
            }
            if (! empty($restricao['detalhes'])) {
                $erro['detalhes'] = $restricao['detalhes'];
            }

            return $erro;
        }

        return [
            'code' => 'SEM_MATRICULA',
            'error' => 'Você não possui matrícula ativa',
            'matricula_id' => $ultima ? (int) $ultima['id'] : null,
            'status_codigo' => $ultima['status_codigo'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function montarDebugSemMatricula(int $tenantId, ?int $alunoId, ?int $userId, string $origem): array
    {
        $debug = [
            'origem' => $origem,
            'tenant_id' => $tenantId,
            'aluno_id' => $alunoId,
            'usuario_id' => $userId,
            'data_hoje' => date('Y-m-d'),
        ];

        if (empty($alunoId)) {
            $debug['warning'] = 'aluno_id não identificado para diagnóstico detalhado';

            return $debug;
        }

        $debug['total_matriculas_aluno_tenant'] = (int) DB::table('matriculas')
            ->where('tenant_id', $tenantId)
            ->where('aluno_id', $alunoId)
            ->count();

        return $debug;
    }

    /**
     * @param  ?array<string, mixed>  $matricula
     * @return ?array<string, mixed>
     */
    private function avaliarRestricaoAcessoMatricula(?array $matricula): ?array
    {
        if (! $matricula) {
            return [
                'code' => 'SEM_MATRICULA',
                'mensagem' => 'Você não possui matrícula ativa',
                'matricula_id' => null,
                'status_codigo' => null,
            ];
        }

        $statusCodigo = $matricula['status_codigo'] ?? '';
        $statusNome = $matricula['status_nome'] ?? $statusCodigo;
        $matriculaId = (int) ($matricula['id'] ?? 0);

        if ($statusCodigo === 'bloqueado') {
            return [
                'code' => 'MATRICULA_BLOQUEADA',
                'mensagem' => 'Sua matrícula está bloqueada. Entre em contato com a academia.',
                'matricula_id' => $matriculaId,
                'status_codigo' => $statusCodigo,
                'status' => $statusNome,
            ];
        }

        if ((int) ($matricula['permite_checkin'] ?? 0) !== 1 || (int) ($matricula['status_ativo'] ?? 0) !== 1) {
            $acessoAte = $matricula['proxima_data_vencimento'] ?? $matricula['data_vencimento'] ?? null;
            $vencTxt = ($acessoAte && $acessoAte !== '0000-00-00')
                ? ' Vencimento: ' . date('d/m/Y', strtotime((string) $acessoAte)) . '.'
                : '';

            if ($statusCodigo === 'pendente' && $matriculaId > 0) {
                $detalhesLimite = $this->avaliarLimiteCicloMatricula($matriculaId);
                if ($detalhesLimite !== null) {
                    $msgBase = $detalhesLimite['mensagem']
                        ?? 'Você atingiu o limite de check-ins do ciclo do plano';

                    return [
                        'code' => 'LIMITE_CHECKINS_CICLO',
                        'mensagem' => $msgBase.' Regularize o pagamento para renovar o ciclo e continuar fazendo check-in.',
                        'matricula_id' => $matriculaId,
                        'status_codigo' => $statusCodigo,
                        'status' => $statusNome,
                        'data_vencimento' => $acessoAte,
                        'detalhes' => $detalhesLimite,
                    ];
                }
            }

            return [
                'code' => $this->codigoErroPorStatusMatricula($statusCodigo),
                'mensagem' => "Sua matrícula está {$statusNome}.{$vencTxt} Entre em contato com a academia.",
                'matricula_id' => $matriculaId,
                'status_codigo' => $statusCodigo,
                'status' => $statusNome,
                'data_vencimento' => $acessoAte,
            ];
        }

        $hoje = date('Y-m-d');
        $acessoAte = $matricula['proxima_data_vencimento'] ?? $matricula['data_vencimento'] ?? null;
        if ($acessoAte && $acessoAte < $hoje) {
            $dataVencimento = date('d/m/Y', strtotime($acessoAte));

            return [
                'code' => 'MATRICULA_VENCIDA',
                'mensagem' => "Seu acesso expirou em {$dataVencimento}. Por favor, renove sua matrícula.",
                'matricula_id' => $matriculaId,
                'status_codigo' => $statusCodigo,
                'status' => $statusNome,
                'data_vencimento' => $acessoAte,
            ];
        }

        return null;
    }

    /**
     * Avalia se a matrícula empatou o limite mensal do ciclo (reposição).
     * Usado no aviso de check-in quando status já está pendente.
     *
     * @return ?array<string, mixed>
     */
    private function avaliarLimiteCicloMatricula(int $matriculaId): ?array
    {
        $row = DB::table('matriculas as m')
            ->join('planos as p', 'p.id', '=', 'm.plano_id')
            ->join('alunos as a', 'a.id', '=', 'm.aluno_id')
            ->leftJoin('plano_ciclos as pc', function ($join) {
                $join->on('pc.id', '=', 'm.plano_ciclo_id')
                    ->on('pc.tenant_id', '=', 'm.tenant_id');
            })
            ->where('m.id', $matriculaId)
            ->select([
                'a.usuario_id',
                'p.modalidade_id',
                'p.checkins_semanais',
                'p.nome as plano_nome',
                'p.duracao_dias',
                DB::raw("CASE
                    WHEN m.plano_ciclo_id IS NOT NULL THEN COALESCE(pc.permite_reposicao, 0)
                    ELSE COALESCE((
                        SELECT MAX(pc2.permite_reposicao)
                        FROM plano_ciclos pc2
                        WHERE pc2.plano_id = p.id
                          AND pc2.tenant_id = m.tenant_id
                          AND pc2.ativo = 1
                    ), 0)
                END as permite_reposicao"),
            ])
            ->first();

        if (! $row) {
            return null;
        }

        if ((int) ($row->duracao_dias ?? 0) === 1) {
            return null;
        }

        $permiteReposicao = (int) ($row->permite_reposicao ?? 0) === 1;
        $checkinsSemanais = (int) ($row->checkins_semanais ?? 0);
        if (! $permiteReposicao || $checkinsSemanais <= 0) {
            return null;
        }

        $usuarioId = (int) $row->usuario_id;
        $modalidadeId = $row->modalidade_id !== null ? (int) $row->modalidade_id : null;

        $primeiroDiaMes = AcademyDateTime::fromDateAndTime(
            AcademyDateTime::now()->format('Y-m-01'),
            '00:00:00',
        ) ?? AcademyDateTime::now();
        $diaSemanaInicio = (int) $primeiroDiaMes->format('w');
        $diasNoMes = (int) $primeiroDiaMes->format('t');
        $semanasNoMes = (int) ceil(($diasNoMes + $diaSemanaInicio) / 7);
        $bonusCincoSemanas = $semanasNoMes >= 5 ? 1 : 0;
        $limiteMensal = ($checkinsSemanais * 4) + $bonusCincoSemanas;

        $checkinsNoMes = (int) DB::table('checkins as c')
            ->join('alunos as a', 'a.id', '=', 'c.aluno_id')
            ->join('turmas as t', function ($join) {
                $join->on('c.turma_id', '=', 't.id')
                    ->on('t.tenant_id', '=', 'c.tenant_id');
            })
            ->where('a.usuario_id', $usuarioId)
            ->whereRaw('MONTH(COALESCE(c.data_checkin_date, DATE(c.created_at))) = MONTH(CURDATE())')
            ->whereRaw('YEAR(COALESCE(c.data_checkin_date, DATE(c.created_at))) = YEAR(CURDATE())')
            ->where(function ($q) {
                $q->whereNull('c.presente')->orWhere('c.presente', 1);
            })
            ->when($modalidadeId !== null, fn ($q) => $q->where('t.modalidade_id', $modalidadeId))
            ->count();

        if ($checkinsNoMes < $limiteMensal) {
            return null;
        }

        $direito = $limiteMensal;
        $usados = $checkinsNoMes;
        $excesso = max(0, $usados - $direito);
        $mesRef = date('d/m', strtotime(date('Y-m-01'))).' a '.date('d/m', strtotime(date('Y-m-t')));
        $mensagem = sprintf(
            'Você atingiu o limite de check-ins do ciclo do plano (%s). Direito: %d | Usados: %d | Excedeu: %d.',
            $mesRef,
            $direito,
            $usados,
            $excesso
        );

        return [
            'plano' => (string) $row->plano_nome,
            'limite_mensal' => $direito,
            'checkins_mes' => $usados,
            'direito' => $direito,
            'usados' => $usados,
            'excesso' => $excesso,
            'mes_referencia' => $mesRef,
            'permite_reposicao' => true,
            'mensagem' => $mensagem,
        ];
    }

    /**
     * Se o limite do ciclo estiver empatado, marca a matrícula como pendente (só se estiver ativa).
     */
    public function marcarPendenteSeLimiteCicloEsgotado(int $matriculaId): bool
    {
        if ($this->avaliarLimiteCicloMatricula($matriculaId) === null) {
            return false;
        }

        $statusPendenteId = DB::table('status_matricula')->where('codigo', 'pendente')->value('id');
        $statusAtivaId = DB::table('status_matricula')->where('codigo', 'ativa')->value('id');
        if (! $statusPendenteId || ! $statusAtivaId) {
            return false;
        }

        return DB::table('matriculas')
            ->where('id', $matriculaId)
            ->where('status_id', $statusAtivaId)
            ->update([
                'status_id' => $statusPendenteId,
                'updated_at' => now(),
            ]) > 0;
    }

    private function codigoErroPorStatusMatricula(string $statusCodigo): string
    {
        return match ($statusCodigo) {
            'cancelada' => 'MATRICULA_CANCELADA',
            'finalizada' => 'MATRICULA_FINALIZADA',
            'pendente' => 'MATRICULA_PENDENTE',
            'vencida' => 'MATRICULA_VENCIDA',
            default => 'MATRICULA_INATIVA',
        };
    }

    public function findDetalhePorUsuario(int $matriculaId, int $userId, int $tenantId): ?array
    {
        $row = DB::table('matriculas as m')
            ->join('alunos as al', 'm.aluno_id', '=', 'al.id')
            ->leftJoin('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->leftJoin('motivo_matricula as mm', 'mm.id', '=', 'm.motivo_id')
            ->where('m.id', $matriculaId)
            ->where('al.usuario_id', $userId)
            ->where('m.tenant_id', $tenantId)
            ->select([
                'm.id',
                'm.aluno_id',
                'm.plano_id',
                'm.data_matricula',
                'm.data_inicio',
                'm.data_vencimento',
                'm.valor',
                'sm.nome as status',
                'mm.nome as motivo',
                'al.nome as usuario_nome',
            ])
            ->first();

        return $row ? (array) $row : null;
    }

    public function findPlanoResumo(int $planoId): ?array
    {
        $row = DB::table('planos')
            ->where('id', $planoId)
            ->first(['id', 'nome', 'valor', 'duracao_dias', 'checkins_semanais']);

        return $row ? (array) $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarPagamentosMatricula(int $matriculaId): array
    {
        return DB::table('pagamentos_plano as pp')
            ->join('status_pagamento as sp', 'pp.status_pagamento_id', '=', 'sp.id')
            ->leftJoin('formas_pagamento as fp', 'pp.forma_pagamento_id', '=', 'fp.id')
            ->leftJoin('usuarios as criador', 'pp.criado_por', '=', 'criador.id')
            ->leftJoin('usuarios as baixador', 'pp.baixado_por', '=', 'baixador.id')
            ->leftJoin('tipos_baixa as tb', 'pp.tipo_baixa_id', '=', 'tb.id')
            ->where('pp.matricula_id', $matriculaId)
            ->orderByDesc('pp.data_vencimento')
            ->orderByDesc('pp.id')
            ->get([
                'pp.id',
                'pp.valor',
                'pp.data_vencimento',
                'pp.data_pagamento',
                'pp.status_pagamento_id',
                'sp.nome as status_pagamento_nome',
                'fp.nome as forma_pagamento_nome',
                'pp.criado_por',
                'criador.nome as criado_por_nome',
                'pp.baixado_por',
                'baixador.nome as baixado_por_nome',
                'pp.tipo_baixa_id',
                'tb.nome as tipo_baixa_nome',
                'pp.observacoes',
            ])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    public function findPendenteReabrir(int $matriculaId, int $userId, int $tenantId): ?array
    {
        $row = DB::table('matriculas as m')
            ->join('alunos as a', 'a.id', '=', 'm.aluno_id')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->join('planos as p', 'p.id', '=', 'm.plano_id')
            ->leftJoin('modalidades as md', 'md.id', '=', 'p.modalidade_id')
            ->where('m.id', $matriculaId)
            ->where('m.tenant_id', $tenantId)
            ->where('a.usuario_id', $userId)
            ->select([
                'm.id',
                'm.plano_id',
                'm.plano_ciclo_id',
                'm.valor',
                'm.data_inicio',
                'm.data_vencimento',
                'm.proxima_data_vencimento',
                'sm.codigo as status_codigo',
                'p.nome as plano_nome',
                'md.nome as modalidade_nome',
            ])
            ->first();

        return $row ? (array) $row : null;
    }

    public function findComStatusPorUsuario(int $matriculaId, int $userId, int $tenantId): ?array
    {
        $row = DB::table('matriculas as m')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->join('alunos as a', 'm.aluno_id', '=', 'a.id')
            ->where('m.id', $matriculaId)
            ->where('a.usuario_id', $userId)
            ->where('m.tenant_id', $tenantId)
            ->select(['m.*', 'sm.codigo as status_codigo'])
            ->first();

        return $row ? (array) $row : null;
    }

    public function ativarSePendente(int $matriculaId): bool
    {
        $statusPendenteId = DB::table('status_matricula')->where('codigo', 'pendente')->value('id');
        $statusAtivaId = DB::table('status_matricula')->where('codigo', 'ativa')->value('id');

        if ($statusPendenteId === null || $statusAtivaId === null) {
            return false;
        }

        return DB::table('matriculas')
            ->where('id', $matriculaId)
            ->where('status_id', (int) $statusPendenteId)
            ->update([
                'status_id' => (int) $statusAtivaId,
                'updated_at' => now(),
            ]) > 0;
    }

    public function findUltimaAssinatura(int $matriculaId, int $tenantId): ?array
    {
        $row = DB::table('assinaturas')
            ->where('matricula_id', $matriculaId)
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->first([
                'id',
                'gateway_preference_id',
                'payment_url',
                'tipo_cobranca',
                'status_gateway',
            ]);

        return $row ? (array) $row : null;
    }

    public function findUltimoPix(int $tenantId, int $matriculaId): ?array
    {
        $row = DB::table('pagamentos_pix')
            ->where('tenant_id', $tenantId)
            ->where('matricula_id', $matriculaId)
            ->orderByDesc('id')
            ->first([
                'payment_id',
                'ticket_url',
                'qr_code',
                'qr_code_base64',
                'expires_at',
                'status',
            ]);

        return $row ? (array) $row : null;
    }
}

