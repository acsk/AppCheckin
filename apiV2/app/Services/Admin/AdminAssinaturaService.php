<?php

namespace App\Services\Admin;

use App\Repositories\AdminAssinaturaRepository;
use App\Repositories\AdminMatriculaRepository;
use Throwable;

/**
 * Assinaturas admin — paridade com painel assinaturaService.js.
 */
class AdminAssinaturaService
{
    public function __construct(
        private readonly AdminAssinaturaRepository $assinaturas,
        private readonly AdminMatriculaRepository $matriculas,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listar(int $tenantId, array $params): array
    {
        return $this->listarPaginado($tenantId, $params);
    }

    /**
     * Super Admin: filtra por tenant_id no query (obrigatório para listagem por academia).
     *
     * @param  array<string, mixed>  $params
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listarTodas(array $params): array
    {
        $tenantId = ! empty($params['tenant_id']) ? (int) $params['tenant_id'] : null;
        if ($tenantId === null) {
            return $this->erro('tenant_id é obrigatório', 400);
        }

        return $this->listarPaginado($tenantId, $params);
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function buscar(int $id, int $tenantId): array
    {
        $row = $this->assinaturas->findById($id, $tenantId);
        if (! $row) {
            return $this->erro('Assinatura não encontrada', 404);
        }

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'assinatura' => $this->assinaturas->mapearDetalhe($row),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function criar(int $tenantId, ?int $adminId, array $data): array
    {
        if (! empty($data['matricula_id'])) {
            $matriculaId = (int) $data['matricula_id'];
            $matricula = $this->matriculas->findBasicoComStatus($matriculaId, $tenantId);
            if (! $matricula) {
                return $this->erro('Matrícula não encontrada', 404);
            }

            if ($this->assinaturas->findByMatriculaId($matriculaId, $tenantId)) {
                return $this->erro('Matrícula já possui assinatura vinculada', 400);
            }

            $result = $this->assinaturas->criarParaMatricula(
                $tenantId,
                $matriculaId,
                (int) $matricula['aluno_id'],
                (int) $matricula['plano_id'],
                $matricula,
                $data,
                $adminId
            );

            if ($result === null) {
                return $this->erro('Erro ao criar assinatura', 500);
            }

            return [
                'status' => 201,
                'body' => [
                    'success' => true,
                    'message' => 'Assinatura criada com sucesso',
                    'assinatura' => $this->assinaturas->mapearDetalhe($result),
                ],
            ];
        }

        $alunoId = (int) ($data['aluno_id'] ?? 0);
        $planoId = (int) ($data['plano_id'] ?? 0);
        if ($alunoId <= 0 || $planoId <= 0) {
            return $this->erro('Informe matricula_id ou aluno_id + plano_id', 422);
        }

        if (! empty($data['criar_matricula'])) {
            $matriculaService = app(AdminMatriculaService::class);
            $criar = $matriculaService->criar($tenantId, $adminId, array_merge($data, [
                'aluno_id' => $alunoId,
                'plano_id' => $planoId,
            ]));

            if ($criar['status'] >= 400) {
                return $criar;
            }

            $matriculaId = (int) ($criar['body']['matricula']['id'] ?? $criar['body']['matriculas'][0]['id'] ?? 0);
            if ($matriculaId <= 0) {
                return $this->erro('Matrícula criada sem ID retornado', 500);
            }

            $matricula = $this->matriculas->findBasicoComStatus($matriculaId, $tenantId);
            $result = $this->assinaturas->criarParaMatricula(
                $tenantId,
                $matriculaId,
                $alunoId,
                $planoId,
                $matricula ?? ['aluno_id' => $alunoId, 'plano_id' => $planoId],
                $data,
                $adminId
            );
        } else {
            $result = $this->assinaturas->criarParaMatricula(
                $tenantId,
                0,
                $alunoId,
                $planoId,
                [
                    'aluno_id' => $alunoId,
                    'plano_id' => $planoId,
                    'valor' => $data['valor'] ?? 0,
                    'data_inicio' => $data['data_inicio'] ?? date('Y-m-d'),
                ],
                $data,
                $adminId
            );
        }

        if ($result === null) {
            return $this->erro('Erro ao criar assinatura', 500);
        }

        return [
            'status' => 201,
            'body' => [
                'success' => true,
                'message' => 'Assinatura criada com sucesso',
                'assinatura' => $this->assinaturas->mapearDetalhe($result),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function atualizar(int $id, int $tenantId, array $data): array
    {
        $row = $this->assinaturas->findById($id, $tenantId);
        if (! $row) {
            return $this->erro('Assinatura não encontrada', 404);
        }

        $fields = [];
        foreach (['valor', 'data_inicio', 'data_fim', 'proxima_cobranca', 'ultima_cobranca'] as $key) {
            if (array_key_exists($key, $data)) {
                $fields[$key] = $data[$key];
            }
        }
        if (! empty($data['status'])) {
            $fields['status_codigo'] = (string) $data['status'];
        }

        $this->assinaturas->atualizar($id, $tenantId, $fields);
        $atualizada = $this->assinaturas->findById($id, $tenantId);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'message' => 'Assinatura atualizada',
                'assinatura' => $this->assinaturas->mapearDetalhe($atualizada ?? $row),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function renovar(int $id, int $tenantId, array $data): array
    {
        $row = $this->assinaturas->findById($id, $tenantId);
        if (! $row) {
            return $this->erro('Assinatura não encontrada', 404);
        }

        $meses = max(1, (int) ($data['meses'] ?? $row['ciclo_meses'] ?? 1));
        $base = $row['proxima_cobranca'] ?? $row['data_fim'] ?? date('Y-m-d');
        $novaProxima = date('Y-m-d', strtotime("+{$meses} months", strtotime((string) $base)));

        $this->assinaturas->atualizar($id, $tenantId, [
            'proxima_cobranca' => $novaProxima,
            'data_fim' => $novaProxima,
            'status_codigo' => 'ativa',
        ]);

        $atualizada = $this->assinaturas->findById($id, $tenantId);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'message' => 'Assinatura renovada',
                'assinatura' => $this->assinaturas->mapearDetalhe($atualizada ?? $row),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function suspender(int $id, int $tenantId, string $motivo = ''): array
    {
        return $this->alterarStatusAssinatura($id, $tenantId, 'pausada', 'paused', $motivo ?: 'Suspensa pelo administrador');
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function cancelar(int $id, int $tenantId, string $motivo = ''): array
    {
        return $this->alterarStatusAssinatura($id, $tenantId, 'cancelada', 'cancelled', $motivo ?: 'Cancelada pelo administrador');
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function reativar(int $id, int $tenantId): array
    {
        return $this->alterarStatusAssinatura($id, $tenantId, 'ativa', 'authorized');
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listarProximasVencer(int $tenantId, int $dias = 30): array
    {
        $rows = $this->assinaturas->listarProximasVencer($tenantId, $dias, 200);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'dias' => $dias,
                'total' => count($rows),
                'assinaturas' => array_map(fn (array $r) => $this->mapearLinha($r), $rows),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listarHistoricoAluno(int $tenantId, int $alunoId): array
    {
        $rows = $this->assinaturas->listarPorAluno($tenantId, $alunoId);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'aluno_id' => $alunoId,
                'total' => count($rows),
                'assinaturas' => array_map(fn (array $r) => $this->mapearLinha($r), $rows),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listarSemMatricula(int $tenantId, array $params): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($params['per_page'] ?? 20)));
        $result = $this->assinaturas->listarSemMatricula($tenantId, $page, $perPage);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'total' => $result['total'],
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int) ceil($result['total'] / $perPage),
                'assinaturas' => array_map(fn (array $r) => $this->mapearLinha($r), $result['rows']),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function relatorio(int $tenantId, array $params): array
    {
        $resumo = $this->assinaturas->relatorioResumo($tenantId);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'resumo' => $resumo,
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function sincronizarComMatricula(int $assinaturaId, int $tenantId): array
    {
        $assinatura = $this->assinaturas->findById($assinaturaId, $tenantId);
        if (! $assinatura) {
            return $this->erro('Assinatura não encontrada', 404);
        }

        $matriculaId = (int) ($assinatura['matricula_id'] ?? 0);
        if ($matriculaId <= 0) {
            return $this->erro('Assinatura não possui matrícula vinculada', 400);
        }

        $matricula = $this->matriculas->findBasicoComStatus($matriculaId, $tenantId);
        if (! $matricula) {
            return $this->erro('Matrícula vinculada não encontrada', 404);
        }

        $statusMatricula = (string) ($matricula['status_codigo'] ?? 'pendente');
        $statusAssinatura = $this->mapearStatusMatriculaParaAssinatura($statusMatricula);

        $this->assinaturas->atualizarStatus(
            $assinaturaId,
            $tenantId,
            $statusAssinatura['codigo'],
            $statusAssinatura['gateway']
        );

        if ($matricula['proxima_data_vencimento'] ?? null) {
            $this->assinaturas->atualizar($assinaturaId, $tenantId, [
                'proxima_cobranca' => $matricula['proxima_data_vencimento'],
                'data_fim' => $matricula['proxima_data_vencimento'],
            ]);
        }

        $atualizada = $this->assinaturas->findById($assinaturaId, $tenantId);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'message' => 'Assinatura sincronizada com matrícula',
                'matricula_status' => $statusMatricula,
                'assinatura' => $this->assinaturas->mapearDetalhe($atualizada ?? $assinatura),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function obterStatusSincronizacao(int $assinaturaId, int $tenantId): array
    {
        $assinatura = $this->assinaturas->findById($assinaturaId, $tenantId);
        if (! $assinatura) {
            return $this->erro('Assinatura não encontrada', 404);
        }

        $matriculaId = (int) ($assinatura['matricula_id'] ?? 0);
        $matricula = $matriculaId > 0
            ? $this->matriculas->findBasicoComStatus($matriculaId, $tenantId)
            : null;

        $statusMatricula = $matricula['status_codigo'] ?? null;
        $statusAssinatura = $assinatura['status_codigo'] ?? $assinatura['status_gateway'] ?? null;
        $esperado = $statusMatricula
            ? $this->mapearStatusMatriculaParaAssinatura((string) $statusMatricula)['codigo']
            : null;

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'sincronizado' => $esperado === null || $statusAssinatura === $esperado,
                'matricula_id' => $matriculaId ?: null,
                'matricula_status' => $statusMatricula,
                'assinatura_status' => $statusAssinatura,
                'status_esperado_assinatura' => $esperado,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{status: int, body: array<string, mixed>}
     */
    private function listarPaginado(int $tenantId, array $params): array
    {
        try {
            $page = max(1, (int) ($params['page'] ?? 1));
            $perPage = min(100, max(1, (int) ($params['per_page'] ?? 20)));

            $filtros = [
                'status' => $params['status'] ?? '',
                'tipo_cobranca' => $params['tipo_cobranca'] ?? '',
                'busca' => $params['busca'] ?? '',
            ];

            $result = $this->assinaturas->listar($tenantId, $filtros, $page, $perPage);
            $totalPages = (int) ceil($result['total'] / $perPage);

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'total' => $result['total'],
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => $totalPages,
                    'assinaturas' => array_map(fn (array $row) => $this->mapearLinha($row), $result['rows']),
                ],
            ];
        } catch (Throwable $e) {
            error_log('[AdminAssinaturaService::listar] Erro: '.$e->getMessage());

            return [
                'status' => 500,
                'body' => [
                    'success' => false,
                    'error' => 'Erro ao listar assinaturas',
                    'detail' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private function alterarStatusAssinatura(
        int $id,
        int $tenantId,
        string $statusCodigo,
        string $statusGateway,
        ?string $motivo = null
    ): array {
        $row = $this->assinaturas->findById($id, $tenantId);
        if (! $row) {
            return $this->erro('Assinatura não encontrada', 404);
        }

        $this->assinaturas->atualizarStatus($id, $tenantId, $statusCodigo, $statusGateway, $motivo);
        $atualizada = $this->assinaturas->findById($id, $tenantId);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'message' => 'Status da assinatura atualizado',
                'assinatura' => $this->assinaturas->mapearDetalhe($atualizada ?? $row),
            ],
        ];
    }

    /**
     * @return array{codigo: string, gateway: string}
     */
    private function mapearStatusMatriculaParaAssinatura(string $statusMatricula): array
    {
        return match ($statusMatricula) {
            'ativa' => ['codigo' => 'ativa', 'gateway' => 'authorized'],
            'bloqueada', 'suspensa' => ['codigo' => 'pausada', 'gateway' => 'paused'],
            'cancelada' => ['codigo' => 'cancelada', 'gateway' => 'cancelled'],
            'vencida', 'finalizada' => ['codigo' => 'expirada', 'gateway' => 'finished'],
            default => ['codigo' => 'pendente', 'gateway' => 'pending'],
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mapearLinha(array $row): array
    {
        return $this->assinaturas->mapearDetalhe($row);
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private function erro(string $message, int $status): array
    {
        return [
            'status' => $status,
            'body' => ['success' => false, 'error' => $message],
        ];
    }
}
