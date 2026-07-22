<?php

namespace App\Services\Mobile;

use App\Repositories\PlanoRepository;
use App\Support\MobilePagamentoMetodos;
use App\Support\PlanoCicloFormatter;
use Illuminate\Support\Facades\DB;

class MobilePlanoService
{
    public function __construct(
        private readonly PlanoRepository $planos,
    ) {}

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function planosDisponiveis(int $userId, ?int $tenantId, ?int $modalidadeId): array
    {
        if (! $tenantId) {
            return [
                'status' => 400,
                'body' => [
                    'success' => false,
                    'type' => 'error',
                    'code' => 'TENANT_NAO_SELECIONADO',
                    'message' => 'Nenhum tenant selecionado',
                ],
            ];
        }

        $planoAtualId = $this->planos->findPlanoAtivoIdPorUsuario($userId, $tenantId);
        $matriculasAtivas = $this->planos->listarMatriculasAtivasPorUsuario($userId, $tenantId);
        $matriculasPorModalidade = [];
        foreach ($matriculasAtivas as $mat) {
            $mid = (int) $mat['modalidade_id'];
            if (! isset($matriculasPorModalidade[$mid])) {
                $matriculasPorModalidade[$mid] = $mat;
            }
        }

        $renovacaoPorModalidade = [];
        foreach ($matriculasPorModalidade as $mid => $mat) {
            $parcelaVenc = DB::table('pagamentos_plano')
                ->where('tenant_id', $tenantId)
                ->where('matricula_id', (int) $mat['id'])
                ->whereIn('status_pagamento_id', [1, 3])
                ->whereNull('data_pagamento')
                ->orderBy('data_vencimento')
                ->orderBy('id')
                ->value('data_vencimento');

            $renovacaoPorModalidade[(int) $mid] = $this->avaliarLiberacaoRenovacao(
                $userId,
                $tenantId,
                (int) $mid,
                isset($mat['data_vencimento']) ? (string) $mat['data_vencimento'] : null,
                $parcelaVenc ? (string) $parcelaVenc : null,
                (int) ($mat['checkins_semanais'] ?? 0),
                (int) $mat['id']
            );
        }

        $pagamentoFlags = MobilePagamentoMetodos::flags($tenantId);
        $habilitarCartao = $pagamentoFlags['habilitar_cartao_credito'];
        $habilitarPix = $pagamentoFlags['habilitar_pix'];

        $lista = $this->planos->listarPlanosPagos($tenantId, $modalidadeId);
        $planoIds = array_map(fn ($p) => (int) $p['id'], $lista);
        $ciclosPorPlano = PlanoCicloFormatter::agruparPorPlano(
            $this->planos->listarCiclosAtivosPorPlanos($planoIds),
            $habilitarCartao,
            $habilitarPix,
        );

        $planosFormatados = array_map(function (array $plano) use ($planoAtualId, $ciclosPorPlano, $matriculasPorModalidade, $renovacaoPorModalidade) {
            $planoId = (int) $plano['id'];
            $isPlanoAtual = $planoAtualId && $planoId === $planoAtualId;
            $modalidadeId = (int) $plano['modalidade_id'];
            $matriculaModalidade = $matriculasPorModalidade[$modalidadeId] ?? null;
            $podeMigrar = $matriculaModalidade && (int) $matriculaModalidade['plano_id'] !== $planoId;
            $liberacaoMod = $renovacaoPorModalidade[$modalidadeId] ?? null;
            $podeRenovar = $isPlanoAtual
                && is_array($liberacaoMod)
                && ! empty($liberacaoMod['liberar']);

            return [
                'id' => $planoId,
                'nome' => $plano['nome'],
                'descricao' => $plano['descricao'],
                'valor' => (float) $plano['valor'],
                'valor_formatado' => 'R$ '.number_format((float) $plano['valor'], 2, ',', '.'),
                'duracao_dias' => (int) $plano['duracao_dias'],
                'duracao_texto' => PlanoCicloFormatter::formatarDuracao((int) $plano['duracao_dias']),
                'checkins_semanais' => (int) $plano['checkins_semanais'],
                'modalidade' => [
                    'id' => (int) $plano['modalidade_id'],
                    'nome' => $plano['modalidade_nome'],
                ],
                'is_plano_atual' => $isPlanoAtual,
                'pode_migrar' => $podeMigrar,
                'pode_renovar' => $podeRenovar,
                'pode_pagar' => $podeRenovar,
                'motivo_renovacao' => $podeRenovar ? ($liberacaoMod['motivo'] ?? null) : null,
                'label' => $podeRenovar
                    ? 'Renovação disponível'
                    : ($isPlanoAtual ? 'Seu plano atual' : ($podeMigrar ? 'Migrar para este plano' : null)),
                'ciclos' => $ciclosPorPlano[$planoId] ?? [],
            ];
        }, $lista);

        $matriculaAtivaResumo = null;
        if ($matriculasAtivas !== []) {
            $primeira = $matriculasAtivas[0];
            $matriculaAtivaResumo = [
                'id' => (int) $primeira['id'],
                'plano_id' => (int) $primeira['plano_id'],
                'plano_nome' => $primeira['plano_nome'],
                'modalidade_id' => (int) $primeira['modalidade_id'],
                'valor' => (float) $primeira['valor'],
                'data_vencimento' => $primeira['data_vencimento'],
            ];
        }

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'data' => [
                    'planos' => $planosFormatados,
                    'total' => count($planosFormatados),
                    'plano_atual_id' => $planoAtualId,
                    'matricula_ativa' => $matriculaAtivaResumo,
                ],
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function detalhePlano(int $userId, ?int $tenantId, int $planoId): array
    {
        if (! $tenantId) {
            return [
                'status' => 400,
                'body' => [
                    'success' => false,
                    'type' => 'error',
                    'code' => 'TENANT_NAO_SELECIONADO',
                    'message' => 'Nenhum tenant selecionado',
                ],
            ];
        }

        if ($planoId <= 0) {
            return [
                'status' => 400,
                'body' => [
                    'success' => false,
                    'type' => 'error',
                    'code' => 'PLANO_ID_OBRIGATORIO',
                    'message' => 'ID do plano é obrigatório',
                ],
            ];
        }

        $plano = $this->planos->findPlanoDetalhe($planoId, $tenantId);
        if (! $plano) {
            return [
                'status' => 404,
                'body' => [
                    'success' => false,
                    'type' => 'error',
                    'code' => 'PLANO_NAO_ENCONTRADO',
                    'message' => 'Plano não encontrado',
                ],
            ];
        }

        $pagamentoFlags = MobilePagamentoMetodos::flags($tenantId);
        $habilitarCartao = $pagamentoFlags['habilitar_cartao_credito'];
        $habilitarPix = $pagamentoFlags['habilitar_pix'];

        $matriculaAtiva = null;
        $mat = $this->planos->findMatriculaAtivaNoPlano($userId, $planoId, $tenantId);
        if ($mat) {
            $statusCodigoExibicao = $mat['status_codigo'];
            $statusExibicao = $mat['status'];
            $assinaturaGateway = strtolower((string) ($mat['assinatura_status_gateway'] ?? ''));

            if (in_array($assinaturaGateway, ['approved', 'authorized'], true)) {
                $statusCodigoExibicao = 'ativa';
                $statusExibicao = 'Ativa';
            } elseif ($assinaturaGateway === 'pending') {
                $statusCodigoExibicao = 'pendente';
                $statusExibicao = 'Pendente';
            }

            $matriculaAtiva = [
                'id' => (int) $mat['id'],
                'status' => $statusExibicao,
                'status_codigo' => $statusCodigoExibicao,
                'data_inicio' => $mat['data_inicio'],
                'data_vencimento' => $mat['data_vencimento'] ?? $mat['proxima_data_vencimento'],
                'valor' => (float) $mat['valor'],
            ];
        }

        $podeMigrar = $this->resolverPodeMigrar($userId, $tenantId, $planoId, (int) ($plano['modalidade_id'] ?? 0));
        $podeRenovar = false;
        $motivoRenovacao = null;
        if ($matriculaAtiva && ($matriculaAtiva['status_codigo'] ?? '') === 'ativa') {
            $parcelaVenc = DB::table('pagamentos_plano')
                ->where('tenant_id', $tenantId)
                ->where('matricula_id', (int) $matriculaAtiva['id'])
                ->whereIn('status_pagamento_id', [1, 3])
                ->whereNull('data_pagamento')
                ->orderBy('data_vencimento')
                ->orderBy('id')
                ->value('data_vencimento');

            $liberacao = $this->avaliarLiberacaoRenovacao(
                $userId,
                $tenantId,
                isset($plano['modalidade_id']) ? (int) $plano['modalidade_id'] : null,
                isset($matriculaAtiva['data_vencimento']) ? (string) $matriculaAtiva['data_vencimento'] : null,
                $parcelaVenc ? (string) $parcelaVenc : null,
                (int) ($plano['checkins_semanais'] ?? 0),
                (int) $matriculaAtiva['id']
            );
            $podeRenovar = ! empty($liberacao['liberar']);
            $motivoRenovacao = $liberacao['motivo'] ?? null;
        }

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'data' => [
                    'id' => (int) $plano['id'],
                    'nome' => $plano['nome'],
                    'descricao' => $plano['descricao'],
                    'valor' => (float) $plano['valor'],
                    'valor_formatado' => 'R$ '.number_format((float) $plano['valor'], 2, ',', '.'),
                    'duracao_dias' => (int) $plano['duracao_dias'],
                    'duracao_texto' => PlanoCicloFormatter::formatarDuracao((int) $plano['duracao_dias']),
                    'checkins_semanais' => (int) $plano['checkins_semanais'],
                    'ativo' => (bool) $plano['ativo'],
                    'modalidade' => [
                        'id' => $plano['modalidade_id'] ? (int) $plano['modalidade_id'] : null,
                        'nome' => $plano['modalidade_nome'],
                        'cor' => $plano['modalidade_cor'],
                    ],
                    'ciclos' => PlanoCicloFormatter::formatarLista(
                        $this->planos->listarCiclosDoPlano($planoId),
                        $habilitarCartao,
                        $habilitarPix,
                    ),
                    'matricula_ativa' => $matriculaAtiva,
                    'is_plano_atual' => $matriculaAtiva !== null,
                    'pode_migrar' => $podeMigrar,
                    'pode_renovar' => $podeRenovar,
                    'pode_pagar' => $podeRenovar,
                    'motivo_renovacao' => $podeRenovar ? $motivoRenovacao : null,
                    'label' => $podeRenovar
                        ? 'Renovação disponível'
                        : ($matriculaAtiva ? 'Seu plano atual' : ($podeMigrar ? 'Migrar para este plano' : null)),
                ],
            ],
        ];
    }

    /**
     * @return array{liberar: bool, motivo: string}
     */
    private function avaliarLiberacaoRenovacao(
        int $userId,
        int $tenantId,
        ?int $modalidadeId,
        ?string $acessoAte,
        ?string $parcelaVencimento,
        int $checkinsSemanais,
        int $matriculaId
    ): array {
        $hoje = date('Y-m-d');

        if ($acessoAte && $acessoAte !== '0000-00-00' && $acessoAte <= $hoje) {
            return ['liberar' => true, 'motivo' => 'acesso_vencido'];
        }

        if ($parcelaVencimento && $parcelaVencimento <= $hoje) {
            return ['liberar' => true, 'motivo' => 'parcela_vencida'];
        }

        $permiteReposicao = (int) DB::table('matriculas as m')
            ->leftJoin('plano_ciclos as pc', function ($join) {
                $join->on('pc.id', '=', 'm.plano_ciclo_id')
                    ->on('pc.tenant_id', '=', 'm.tenant_id');
            })
            ->where('m.id', $matriculaId)
            ->where('m.tenant_id', $tenantId)
            ->value(DB::raw('CASE WHEN m.plano_ciclo_id IS NOT NULL THEN COALESCE(pc.permite_reposicao, 0) ELSE 1 END'));

        if ($permiteReposicao !== 1 || $checkinsSemanais <= 0 || ! $acessoAte || $acessoAte === '0000-00-00') {
            return ['liberar' => false, 'motivo' => 'ciclo_em_andamento'];
        }

        try {
            $addMonthsAnchor = static function (\DateTime $base, int $months, int $anchorDay): \DateTime {
                $y = (int) $base->format('Y');
                $m = (int) $base->format('m') + $months;
                while ($m > 12) {
                    $m -= 12;
                    $y++;
                }
                while ($m < 1) {
                    $m += 12;
                    $y--;
                }
                $ultimo = (int) (new \DateTime(sprintf('%04d-%02d-01', $y, $m)))->format('t');
                $dt = new \DateTime();
                $dt->setDate($y, $m, min($anchorDay, $ultimo));

                return $dt;
            };

            $vencDt = new \DateTime($acessoAte);
            $anchorDay = (int) $vencDt->format('j');
            $hojeDt = new \DateTime($hoje);
            $fim = clone $vencDt;
            if ($fim <= $hojeDt) {
                while ($fim <= $hojeDt) {
                    $fim = $addMonthsAnchor($fim, 1, $anchorDay);
                }
            } else {
                while (true) {
                    $anterior = $addMonthsAnchor($fim, -1, $anchorDay);
                    if ($anterior > $hojeDt) {
                        $fim = $anterior;
                    } else {
                        break;
                    }
                }
            }
            $inicio = $addMonthsAnchor($fim, -1, $anchorDay);
            $dias = (int) $inicio->diff($fim)->days;
            $semanas = (int) ceil(max(1, $dias) / 7);
            $bonus = ($semanas >= 5) ? 1 : 0;
            $limite = $checkinsSemanais * 4 + $bonus;

            $q = DB::table('checkins as c')
                ->join('alunos as a', 'a.id', '=', 'c.aluno_id')
                ->join('turmas as t', 't.id', '=', 'c.turma_id')
                ->join('dias as d', 'd.id', '=', 't.dia_id')
                ->where('a.usuario_id', $userId)
                ->where('d.data', '>=', $inicio->format('Y-m-d'))
                ->where('d.data', '<', $fim->format('Y-m-d'))
                ->where(function ($w) {
                    $w->whereNull('c.presente')->orWhere('c.presente', 1);
                });
            if ($modalidadeId) {
                $q->where('t.modalidade_id', $modalidadeId);
            }

            if ((int) $q->count() >= $limite) {
                return ['liberar' => true, 'motivo' => 'limite_checkins_ciclo'];
            }
        } catch (\Throwable $e) {
            // mantém ciclo_em_andamento
        }

        return ['liberar' => false, 'motivo' => 'ciclo_em_andamento'];
    }

    private function resolverPodeMigrar(int $userId, int $tenantId, int $planoId, int $modalidadeId): bool
    {
        if ($modalidadeId <= 0) {
            return false;
        }

        $alunoId = DB::table('alunos')->where('usuario_id', $userId)->value('id');
        if (! $alunoId) {
            return false;
        }

        $migracao = new MobileMigracaoPlanoService();
        $matricula = $migracao->buscarMatriculaAtivaModalidade((int) $alunoId, $tenantId, $modalidadeId);

        return $matricula && (int) $matricula['plano_id'] !== $planoId;
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function planosDoUsuario(int $userId, ?int $tenantId, bool $todas): array
    {
        if (! $tenantId) {
            return [
                'status' => 400,
                'body' => [
                    'success' => false,
                    'error' => 'Nenhum tenant selecionado',
                ],
            ];
        }

        $matriculas = $this->planos->listarMatriculasUsuario($userId, $tenantId, ! $todas);
        $modalidades = $this->planos->mapModalidadesPorId();

        $formatadas = array_map(function (array $mat) use ($modalidades) {
            $modalidadeId = (int) $mat['modalidade_id'];
            $modalidade = $modalidades[$modalidadeId] ?? null;

            return [
                'matricula_id' => (int) $mat['id'],
                'plano' => [
                    'id' => (int) $mat['plano_id_ref'],
                    'tenant_id' => (int) $mat['tenant_id'],
                    'nome' => $mat['plano_nome'],
                    'descricao' => $mat['descricao'],
                    'valor' => (float) $mat['plano_valor'],
                    'duracao_dias' => (int) $mat['duracao_dias'],
                    'checkins_semanais' => (int) $mat['checkins_semanais'],
                    'ativo' => (bool) $mat['ativo'],
                    'modalidade' => $modalidade ? [
                        'id' => (int) $modalidade['id'],
                        'nome' => $modalidade['nome'],
                        'cor' => $modalidade['cor'],
                    ] : [
                        'id' => null,
                        'nome' => '',
                        'cor' => '',
                    ],
                ],
                'datas' => [
                    'matricula' => $mat['data_matricula'],
                    'inicio' => $mat['data_inicio'],
                    'vencimento' => $mat['data_vencimento'],
                ],
                'valor' => (float) $mat['valor'],
                'status' => $mat['status'],
                'motivo' => $mat['motivo'],
                'created_at' => $mat['created_at'],
                'updated_at' => $mat['updated_at'],
            ];
        }, $matriculas);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'data' => [
                    'matriculas' => $formatadas,
                    'total' => count($formatadas),
                    'apenas_ativos' => ! $todas,
                ],
            ],
        ];
    }
}
