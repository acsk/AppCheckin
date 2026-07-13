<?php

namespace App\Services\Admin;

use App\Repositories\AdminPlanoRepository;

class AdminPlanoService
{
    public function __construct(
        private readonly AdminPlanoRepository $planos,
    ) {}

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function index(int $tenantId, mixed $ativosParam): array
    {
        $apenasAtivos = false;
        if ($ativosParam !== null && $ativosParam !== '') {
            $apenasAtivos = filter_var($ativosParam, FILTER_VALIDATE_BOOLEAN);
        }

        $planos = $this->planos->listar($tenantId, $apenasAtivos);

        return [
            'status' => 200,
            'body' => [
                'planos' => $planos,
                'total' => count($planos),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function show(int $id, int $tenantId): array
    {
        try {
            $plano = $this->planos->findById($id, $tenantId);
            if (! $plano) {
                return ['status' => 404, 'body' => ['error' => 'Plano não encontrado']];
            }

            return ['status' => 200, 'body' => $plano];
        } catch (\Throwable $e) {
            return [
                'status' => 500,
                'body' => ['error' => 'Erro ao buscar plano: '.$e->getMessage()],
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function create(int $tenantId, array $data): array
    {
        $errors = [];
        if (empty($data['modalidade_id'])) {
            $errors[] = 'Modalidade é obrigatória';
        }
        if (empty($data['nome'])) {
            $errors[] = 'Nome é obrigatório';
        }
        if (! isset($data['valor']) || $data['valor'] < 0) {
            $errors[] = 'Valor deve ser maior ou igual a zero';
        }
        if (empty($data['checkins_semanais']) || $data['checkins_semanais'] < 1) {
            $errors[] = 'Checkins semanais deve ser maior que zero';
        }
        if ($errors !== []) {
            return ['status' => 422, 'body' => ['errors' => $errors]];
        }

        if ($this->planos->existeDuplicado(
            $tenantId,
            (int) $data['modalidade_id'],
            (string) $data['nome'],
            $data['valor'],
            (int) $data['checkins_semanais'],
            (int) ($data['duracao_dias'] ?? 30),
        )) {
            return [
                'status' => 422,
                'body' => ['error' => 'Já existe um plano com essas características nesta modalidade'],
            ];
        }

        $id = $this->planos->create($tenantId, $data);
        $plano = $this->planos->findById($id, $tenantId);

        return [
            'status' => 201,
            'body' => [
                'message' => 'Plano criado com sucesso',
                'plano' => $plano,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function update(int $id, int $tenantId, array $data): array
    {
        $plano = $this->planos->findById($id, $tenantId);
        if (! $plano) {
            return ['status' => 404, 'body' => ['error' => 'Plano não encontrado']];
        }

        if ($this->planos->possuiContratos($id, $tenantId)) {
            return [
                'status' => 400,
                'body' => [
                    'error' => 'Não é possível modificar este plano pois existem contratos vinculados a ele. Crie um novo plano ou marque este como histórico.',
                ],
            ];
        }

        if (isset($data['modalidade_id'], $data['nome'], $data['valor'], $data['checkins_semanais'])) {
            if ($this->planos->existeDuplicado(
                $tenantId,
                (int) $data['modalidade_id'],
                (string) $data['nome'],
                $data['valor'],
                (int) $data['checkins_semanais'],
                (int) ($data['duracao_dias'] ?? 30),
                $id,
            )) {
                return [
                    'status' => 422,
                    'body' => ['error' => 'Já existe um plano com essas características nesta modalidade'],
                ];
            }
        }

        if (! $this->planos->update($id, $tenantId, $data)) {
            return ['status' => 400, 'body' => ['error' => 'Nenhum dado foi atualizado']];
        }

        return [
            'status' => 200,
            'body' => [
                'message' => 'Plano atualizado com sucesso',
                'plano' => $this->planos->findById($id, $tenantId),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function delete(int $id, int $tenantId): array
    {
        $total = $this->planos->countUsuariosAtivos($id, $tenantId);
        if ($total > 0) {
            return [
                'status' => 400,
                'body' => [
                    'error' => "Não é possível desativar. {$total} usuário(s) estão usando este plano.",
                ],
            ];
        }

        if (! $this->planos->softDelete($id, $tenantId)) {
            return ['status' => 400, 'body' => ['error' => 'Erro ao desativar plano']];
        }

        return [
            'status' => 200,
            'body' => ['message' => 'Plano desativado com sucesso'],
        ];
    }
}
