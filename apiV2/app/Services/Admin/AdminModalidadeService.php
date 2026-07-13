<?php

namespace App\Services\Admin;

use App\Repositories\ModalidadeRepository;
use Illuminate\Support\Facades\DB;

class AdminModalidadeService
{
    public function __construct(
        private readonly ModalidadeRepository $modalidades,
    ) {}

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function index(int $tenantId, bool $apenasAtivas): array
    {
        return [
            'status' => 200,
            'body' => [
                'modalidades' => $this->modalidades->listarPorTenant($tenantId, $apenasAtivas),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function show(int $id, int $tenantId): array
    {
        $modalidade = $this->modalidades->buscarPorId($id, $tenantId);
        if (! $modalidade) {
            return $this->error('Modalidade não encontrada', 404);
        }

        return [
            'status' => 200,
            'body' => ['modalidade' => $modalidade],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function create(int $tenantId, array $data): array
    {
        if (empty($data['nome'])) {
            return $this->error('Nome é obrigatório', 422);
        }

        if ($this->modalidades->nomeExiste($tenantId, (string) $data['nome'])) {
            return $this->error('Já existe uma modalidade com este nome', 422);
        }

        try {
            $modalidadeId = DB::transaction(function () use ($tenantId, $data) {
                $id = $this->modalidades->criar([
                    'tenant_id' => $tenantId,
                    'nome' => $data['nome'],
                    'descricao' => $data['descricao'] ?? null,
                    'cor' => $data['cor'] ?? '#f97316',
                    'icone' => $data['icone'] ?? 'activity',
                    'ativo' => $data['ativo'] ?? 1,
                ]);

                if (! empty($data['planos']) && is_array($data['planos'])) {
                    foreach ($data['planos'] as $plano) {
                        $this->modalidades->criarPlano($tenantId, $id, $plano);
                    }
                }

                return $id;
            });

            return [
                'status' => 201,
                'body' => [
                    'type' => 'success',
                    'message' => 'Modalidade criada com sucesso',
                    'modalidade' => $this->modalidades->buscarPorId($modalidadeId, $tenantId),
                ],
            ];
        } catch (\Throwable $e) {
            return $this->error('Erro ao criar modalidade: '.$e->getMessage(), 500);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function update(int $id, int $tenantId, array $data): array
    {
        $modalidade = $this->modalidades->buscarPorId($id, $tenantId);
        if (! $modalidade) {
            return $this->error('Modalidade não encontrada', 404);
        }

        if (empty($data['nome'])) {
            return $this->error('Nome é obrigatório', 422);
        }

        if ($this->modalidades->nomeExiste($tenantId, (string) $data['nome'], $id)) {
            return $this->error('Já existe uma modalidade com este nome', 422);
        }

        try {
            DB::transaction(function () use ($id, $tenantId, $data) {
                $this->modalidades->atualizar($id, $data);

                if (! empty($data['planos']) && is_array($data['planos'])) {
                    $idsExistentes = $this->modalidades->idsPlanosDaModalidade($id);
                    $idsEnviados = [];

                    foreach ($data['planos'] as $plano) {
                        $duracao = (int) ($plano['duracao_dias'] ?? 30);
                        $excludeId = (int) ($plano['id'] ?? 0);

                        if ($this->modalidades->planoDuplicado(
                            $id,
                            (string) $plano['nome'],
                            $plano['valor'],
                            (int) $plano['checkins_semanais'],
                            $duracao,
                            $excludeId,
                        )) {
                            throw new \RuntimeException("Já existe um plano com essas características: {$plano['nome']}");
                        }

                        if (! empty($plano['id'])) {
                            $this->modalidades->atualizarPlano((int) $plano['id'], $id, $plano);
                            $idsEnviados[] = (int) $plano['id'];
                        } else {
                            $this->modalidades->criarPlano($tenantId, $id, $plano);
                        }
                    }

                    $this->modalidades->excluirPlanos(array_values(array_diff($idsExistentes, $idsEnviados)));
                }
            });

            return [
                'status' => 200,
                'body' => [
                    'type' => 'success',
                    'message' => 'Modalidade e planos atualizados com sucesso',
                    'modalidade' => $this->modalidades->buscarPorId($id, $tenantId),
                ],
            ];
        } catch (\Throwable $e) {
            return $this->error('Erro ao atualizar modalidade: '.$e->getMessage(), 500);
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function delete(int $id, int $tenantId): array
    {
        $modalidade = $this->modalidades->buscarPorId($id, $tenantId);
        if (! $modalidade) {
            return $this->error('Modalidade não encontrada', 404);
        }

        if ((int) $modalidade['tenant_id'] !== $tenantId) {
            return $this->error('Acesso negado', 403);
        }

        try {
            $this->modalidades->alternarAtivo($id);
            $acao = ((int) $modalidade['ativo'] === 1) ? 'desativada' : 'ativada';

            return [
                'status' => 200,
                'body' => [
                    'type' => 'success',
                    'message' => "Modalidade {$acao} com sucesso",
                ],
            ];
        } catch (\Throwable $e) {
            return $this->error('Erro ao alterar modalidade: '.$e->getMessage(), 500);
        }
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
