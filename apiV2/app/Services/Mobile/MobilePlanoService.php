<?php

namespace App\Services\Mobile;

use App\Repositories\PlanoRepository;
use App\Support\PlanoCicloFormatter;

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
        $lista = $this->planos->listarPlanosPagos($tenantId, $modalidadeId);
        $planoIds = array_map(fn ($p) => (int) $p['id'], $lista);
        $ciclosPorPlano = PlanoCicloFormatter::agruparPorPlano(
            $this->planos->listarCiclosAtivosPorPlanos($planoIds),
        );

        $planosFormatados = array_map(function (array $plano) use ($planoAtualId, $ciclosPorPlano) {
            $planoId = (int) $plano['id'];
            $isPlanoAtual = $planoAtualId && $planoId === $planoAtualId;

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
                'label' => $isPlanoAtual ? 'Seu plano atual' : null,
                'ciclos' => $ciclosPorPlano[$planoId] ?? [],
            ];
        }, $lista);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'data' => [
                    'planos' => $planosFormatados,
                    'total' => count($planosFormatados),
                    'plano_atual_id' => $planoAtualId,
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
                    ),
                    'matricula_ativa' => $matriculaAtiva,
                    'is_plano_atual' => $matriculaAtiva !== null,
                ],
            ],
        ];
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
