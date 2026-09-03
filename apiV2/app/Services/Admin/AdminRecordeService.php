<?php

namespace App\Services\Admin;

use App\Repositories\RecordePessoalRepository;

class AdminRecordeService
{
    public function __construct(
        private readonly RecordePessoalRepository $recordes,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listarDefinicoes(int $tenantId, array $query): array
    {
        $apenasAtivas = ! isset($query['todas']) || $query['todas'] !== 'true';
        $modalidadeId = isset($query['modalidade_id']) ? (int) $query['modalidade_id'] : null;
        $categoria = $query['categoria'] ?? null;

        return [
            'status' => 200,
            'body' => [
                'definicoes' => $this->recordes->listarDefinicoes($tenantId, $apenasAtivas, $modalidadeId, $categoria),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function buscarDefinicao(int $id, int $tenantId): array
    {
        $definicao = $this->recordes->buscarDefinicao($id, $tenantId);
        if (! $definicao) {
            return $this->error('Definição não encontrada', 404);
        }

        return [
            'status' => 200,
            'body' => ['definicao' => $definicao],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function criarDefinicao(int $tenantId, array $data): array
    {
        if (empty($data['nome'])) {
            return $this->error('Nome da definição é obrigatório', 422);
        }

        if (empty($data['metricas']) || ! is_array($data['metricas'])) {
            return $this->error('Pelo menos uma métrica é obrigatória', 422);
        }

        foreach ($data['metricas'] as $m) {
            if (empty($m['codigo']) || empty($m['nome']) || empty($m['direcao'])) {
                return $this->error('Cada métrica precisa ter codigo, nome e direcao', 422);
            }
        }

        try {
            $data['tenant_id'] = $tenantId;
            $id = $this->recordes->criarDefinicao($data);

            return [
                'status' => 201,
                'body' => [
                    'type' => 'success',
                    'message' => 'Definição criada com sucesso',
                    'definicao' => $this->recordes->buscarDefinicao($id, $tenantId),
                ],
            ];
        } catch (\Throwable $e) {
            return $this->error('Erro ao criar definição: '.$e->getMessage(), 500);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function atualizarDefinicao(int $id, int $tenantId, array $data): array
    {
        if (! $this->recordes->buscarDefinicao($id, $tenantId)) {
            return $this->error('Definição não encontrada', 404);
        }

        if (empty($data['nome'])) {
            return $this->error('Nome da definição é obrigatório', 422);
        }

        try {
            $this->recordes->atualizarDefinicao($id, $tenantId, $data);

            return [
                'status' => 200,
                'body' => [
                    'type' => 'success',
                    'message' => 'Definição atualizada com sucesso',
                    'definicao' => $this->recordes->buscarDefinicao($id, $tenantId),
                ],
            ];
        } catch (\Throwable $e) {
            return $this->error('Erro ao atualizar definição: '.$e->getMessage(), 500);
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function excluirDefinicao(int $id, int $tenantId): array
    {
        if (! $this->recordes->buscarDefinicao($id, $tenantId)) {
            return $this->error('Definição não encontrada', 404);
        }

        try {
            $this->recordes->desativarDefinicao($id, $tenantId);

            return [
                'status' => 200,
                'body' => [
                    'type' => 'success',
                    'message' => 'Definição desativada com sucesso',
                ],
            ];
        } catch (\Throwable $e) {
            return $this->error('Erro ao desativar definição: '.$e->getMessage(), 500);
        }
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listarRecordes(int $tenantId, array $query): array
    {
        $alunoId = isset($query['aluno_id']) ? (int) $query['aluno_id'] : null;
        $definicaoId = isset($query['definicao_id']) ? (int) $query['definicao_id'] : null;
        $origem = $query['origem'] ?? null;
        $modalidadeId = isset($query['modalidade_id']) ? (int) $query['modalidade_id'] : null;

        if ($origem === 'academia') {
            $recordes = $this->recordes->listarRecordesAcademia($tenantId, $definicaoId, $modalidadeId);
        } elseif ($alunoId) {
            $recordes = $this->recordes->listarPorAluno($tenantId, $alunoId, $definicaoId);
        } else {
            $recordes = $this->recordes->listarRecordesAcademia($tenantId, $definicaoId, $modalidadeId);
        }

        return [
            'status' => 200,
            'body' => ['recordes' => $recordes],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function buscarRecorde(int $id, int $tenantId): array
    {
        $recorde = $this->recordes->buscar($id, $tenantId);
        if (! $recorde) {
            return $this->error('Recorde não encontrado', 404);
        }

        return [
            'status' => 200,
            'body' => ['recorde' => $recorde],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function criarRecorde(int $tenantId, int $userId, array $data): array
    {
        $errors = [];
        if (empty($data['definicao_id'])) {
            $errors[] = 'Definição é obrigatória';
        }
        if (empty($data['data_recorde'])) {
            $errors[] = 'Data do recorde é obrigatória';
        }
        if (empty($data['valores']) || ! is_array($data['valores'])) {
            $errors[] = 'Pelo menos um valor é obrigatório';
        }

        if ($errors !== []) {
            return $this->error(implode(', ', $errors), 422);
        }

        if (! $this->recordes->buscarDefinicao((int) $data['definicao_id'], $tenantId)) {
            return $this->error('Definição não encontrada', 404);
        }

        try {
            $data['tenant_id'] = $tenantId;
            $data['origem'] = $data['origem'] ?? 'academia';
            $data['registrado_por'] = $userId;

            $id = $this->recordes->criar($data);

            return [
                'status' => 201,
                'body' => [
                    'type' => 'success',
                    'message' => 'Recorde registrado com sucesso',
                    'recorde' => $this->recordes->buscar($id, $tenantId),
                ],
            ];
        } catch (\Throwable $e) {
            return $this->error('Erro ao registrar recorde: '.$e->getMessage(), 500);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function atualizarRecorde(int $id, int $tenantId, array $data): array
    {
        if (! $this->recordes->buscar($id, $tenantId)) {
            return $this->error('Recorde não encontrado', 404);
        }

        if (empty($data['definicao_id'])) {
            return $this->error('Definição é obrigatória', 422);
        }
        if (empty($data['data_recorde'])) {
            return $this->error('Data do recorde é obrigatória', 422);
        }

        try {
            $this->recordes->atualizar($id, $tenantId, $data);

            return [
                'status' => 200,
                'body' => [
                    'type' => 'success',
                    'message' => 'Recorde atualizado com sucesso',
                    'recorde' => $this->recordes->buscar($id, $tenantId),
                ],
            ];
        } catch (\Throwable $e) {
            return $this->error('Erro ao atualizar recorde: '.$e->getMessage(), 500);
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function excluirRecorde(int $id, int $tenantId): array
    {
        if (! $this->recordes->buscar($id, $tenantId)) {
            return $this->error('Recorde não encontrado', 404);
        }

        try {
            $this->recordes->excluir($id, $tenantId);

            return [
                'status' => 200,
                'body' => [
                    'type' => 'success',
                    'message' => 'Recorde excluído com sucesso',
                ],
            ];
        } catch (\Throwable $e) {
            return $this->error('Erro ao excluir recorde: '.$e->getMessage(), 500);
        }
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{status: int, body: array<string, mixed>}
     */
    public function ranking(int $definicaoId, int $tenantId, array $query): array
    {
        $limit = isset($query['limit']) ? min((int) $query['limit'], 100) : 50;

        $definicao = $this->recordes->buscarDefinicao($definicaoId, $tenantId);
        if (! $definicao) {
            return $this->error('Definição não encontrada', 404);
        }

        return [
            'status' => 200,
            'body' => [
                'definicao' => $definicao,
                'ranking' => $this->recordes->rankingPorDefinicao($tenantId, $definicaoId, $limit),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private function error(string $message, int $status): array
    {
        return [
            'status' => $status,
            'body' => [
                'type' => 'error',
                'message' => $message,
            ],
        ];
    }
}
