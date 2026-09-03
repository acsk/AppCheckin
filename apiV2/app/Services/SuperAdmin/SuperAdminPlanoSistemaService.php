<?php

namespace App\Services\SuperAdmin;

use App\Repositories\PlanoSistemaRepository;

class SuperAdminPlanoSistemaService
{
    public function __construct(
        private readonly PlanoSistemaRepository $planos,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @return array{status: int, body: array<string, mixed>}
     */
    public function index(array $query): array
    {
        $apenasAtivos = isset($query['ativos']) && $query['ativos'] === 'true';
        $apenasAtuais = isset($query['apenas_atuais']) && $query['apenas_atuais'] === 'true';

        $planos = $this->planos->listarTodos($apenasAtivos);

        if ($apenasAtuais) {
            $planos = array_values(array_filter(
                $planos,
                fn (array $plano) => ($plano['atual'] ?? 0) == 1,
            ));
        }

        return [
            'status' => 200,
            'body' => ['planos' => $planos, 'total' => count($planos)],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function disponiveis(): array
    {
        $planos = $this->planos->listarDisponiveis();

        return [
            'status' => 200,
            'body' => ['planos' => $planos, 'total' => count($planos)],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function show(int $id): array
    {
        $plano = $this->planos->buscarPorId($id);
        if (! $plano) {
            return ['status' => 404, 'body' => ['error' => 'Plano não encontrado']];
        }

        $plano['contratos_ativos'] = $this->planos->contarContratosAtivos($id);

        return ['status' => 200, 'body' => $plano];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function academias(int $id): array
    {
        $plano = $this->planos->buscarPorId($id);
        if (! $plano) {
            return ['status' => 404, 'body' => ['error' => 'Plano não encontrado']];
        }

        $academias = $this->planos->listarAcademias($id);

        return [
            'status' => 200,
            'body' => ['academias' => $academias, 'total' => count($academias)],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function create(array $data): array
    {
        $errors = [];
        if (empty($data['nome'])) {
            $errors[] = 'Nome é obrigatório';
        }
        if (! isset($data['valor']) || $data['valor'] < 0) {
            $errors[] = 'Valor deve ser maior ou igual a zero';
        }
        if ($errors !== []) {
            return ['status' => 422, 'body' => ['errors' => $errors]];
        }

        try {
            $planoId = $this->planos->criar($data);

            return [
                'status' => 201,
                'body' => [
                    'message' => 'Plano criado com sucesso',
                    'plano' => $this->planos->buscarPorId($planoId),
                ],
            ];
        } catch (\Throwable $e) {
            return ['status' => 500, 'body' => ['error' => 'Erro ao criar plano: '.$e->getMessage()]];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function update(int $id, array $data): array
    {
        $plano = $this->planos->buscarPorId($id);
        if (! $plano) {
            return [
                'status' => 404,
                'body' => ['type' => 'error', 'message' => 'Plano não encontrado'],
            ];
        }

        try {
            $this->planos->atualizar($id, $data);

            return [
                'status' => 200,
                'body' => [
                    'type' => 'success',
                    'message' => 'Plano atualizado com sucesso',
                    'plano' => $this->planos->buscarPorId($id),
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 400,
                'body' => ['type' => 'error', 'message' => $e->getMessage()],
            ];
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function marcarHistorico(int $id): array
    {
        $plano = $this->planos->buscarPorId($id);
        if (! $plano) {
            return ['status' => 404, 'body' => ['error' => 'Plano não encontrado']];
        }

        $this->planos->marcarComoHistorico($id);

        return [
            'status' => 200,
            'body' => ['message' => 'Plano marcado como histórico. Não estará mais disponível para novos contratos.'],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function delete(int $id): array
    {
        $plano = $this->planos->buscarPorId($id);
        if (! $plano) {
            return ['status' => 404, 'body' => ['error' => 'Plano não encontrado']];
        }

        try {
            $this->planos->desativar($id);

            return ['status' => 200, 'body' => ['message' => 'Plano desativado com sucesso']];
        } catch (\Throwable $e) {
            return ['status' => 400, 'body' => ['error' => $e->getMessage()]];
        }
    }
}
