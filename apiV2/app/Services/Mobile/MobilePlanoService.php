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

        $planosFormatados = array_map(function (array $plano) use ($planoAtualId, $ciclosPorPlano, $matriculasPorModalidade) {
            $planoId = (int) $plano['id'];
            $isPlanoAtual = $planoAtualId && $planoId === $planoAtualId;
            $modalidadeId = (int) $plano['modalidade_id'];
            $matriculaModalidade = $matriculasPorModalidade[$modalidadeId] ?? null;
            $podeMigrar = $matriculaModalidade && (int) $matriculaModalidade['plano_id'] !== $planoId;

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
                'label' => $isPlanoAtual ? 'Seu plano atual' : ($podeMigrar ? 'Migrar para este plano' : null),
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
                    'pode_migrar' => ($podeMigrar = $this->resolverPodeMigrar($userId, $tenantId, $planoId, (int) ($plano['modalidade_id'] ?? 0))),
                    'label' => $matriculaAtiva ? 'Seu plano atual' : ($podeMigrar ? 'Migrar para este plano' : null),
                ],
            ],
        ];
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
