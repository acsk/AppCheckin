<?php

namespace App\Services\SuperAdmin;

use App\Repositories\PagamentoContratoRepository;
use App\Repositories\PlanoSistemaRepository;
use App\Repositories\TenantPlanoRepository;
use App\Repositories\TenantRepository;

class SuperAdminContratoService
{
    public function __construct(
        private readonly TenantPlanoRepository $contratos,
        private readonly TenantRepository $tenants,
        private readonly PlanoSistemaRepository $planosSistema,
        private readonly PagamentoContratoRepository $pagamentos,
    ) {}

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function index(): array
    {
        $contratos = $this->contratos->listarTodos();

        return [
            'status' => 200,
            'body' => ['contratos' => $contratos, 'total' => count($contratos)],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function show(int $id): array
    {
        $contrato = $this->contratos->buscarPorId($id);
        if (! $contrato) {
            return ['status' => 404, 'body' => ['error' => 'Contrato não encontrado']];
        }

        return ['status' => 200, 'body' => ['contrato' => $contrato]];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function porAcademia(int $tenantId): array
    {
        $academia = $this->tenants->findById($tenantId);
        if (! $academia) {
            return ['status' => 404, 'body' => ['error' => 'Academia não encontrada']];
        }

        $contratos = $this->contratos->listarPorTenant($tenantId);

        return [
            'status' => 200,
            'body' => [
                'academia' => ['id' => $academia['id'], 'nome' => $academia['nome']],
                'contratos' => $contratos,
                'total' => count($contratos),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function contratoAtivo(int $tenantId): array
    {
        $contrato = $this->contratos->buscarContratoAtivo($tenantId);
        if (! $contrato) {
            return [
                'status' => 200,
                'body' => [
                    'message' => 'Academia não possui contrato ativo',
                    'type' => 'warning',
                    'contrato' => null,
                ],
            ];
        }

        return ['status' => 200, 'body' => $contrato];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function associarPlano(int $tenantId, array $data): array
    {
        $academia = $this->tenants->findById($tenantId);
        if (! $academia) {
            return ['status' => 404, 'body' => ['type' => 'error', 'message' => 'Academia não encontrada']];
        }

        $contratoAtivo = $this->contratos->buscarContratoAtivo($tenantId);
        if ($contratoAtivo) {
            return [
                'status' => 409,
                'body' => [
                    'type' => 'error',
                    'message' => 'Esta academia já possui um contrato ativo',
                    'contrato_ativo' => [
                        'id' => $contratoAtivo['id'],
                        'plano' => $contratoAtivo['plano_nome'],
                        'data_inicio' => $contratoAtivo['data_inicio'],
                        'data_vencimento' => $contratoAtivo['data_vencimento'] ?? null,
                    ],
                    'sugestao' => 'Use o endpoint POST /academias/{id}/trocar-plano para trocar de plano, ou cancele/desative o contrato atual',
                ],
            ];
        }

        $errors = [];
        if (empty($data['plano_sistema_id'])) {
            $errors[] = 'Plano do sistema é obrigatório';
        } else {
            $plano = $this->planosSistema->buscarPorId((int) $data['plano_sistema_id']);
            if (! $plano) {
                $errors[] = 'Plano do sistema não encontrado';
            } elseif (! ($plano['ativo'] ?? false)) {
                $errors[] = 'Este plano não está ativo';
            }
        }
        if (empty($data['forma_pagamento_id'])) {
            $errors[] = 'Forma de pagamento é obrigatória';
        }

        if ($errors !== []) {
            return [
                'status' => 422,
                'body' => ['type' => 'error', 'message' => implode(', ', $errors)],
            ];
        }

        try {
            $this->tenants->inicializarFormasPagamento($tenantId);

            $plano = $this->planosSistema->buscarPorId((int) $data['plano_sistema_id']);
            $dataInicio = $data['data_inicio'] ?? date('Y-m-d');

            $contratoId = $this->contratos->criar([
                'tenant_id' => $tenantId,
                'plano_sistema_id' => $data['plano_sistema_id'],
                'status_id' => 2,
                'data_inicio' => $dataInicio,
                'observacoes' => $data['observacoes'] ?? null,
            ]);

            $this->pagamentos->criar([
                'tenant_plano_id' => $contratoId,
                'valor' => $plano['valor'],
                'data_vencimento' => $dataInicio,
                'status_pagamento_id' => 1,
                'forma_pagamento_id' => $data['forma_pagamento_id'],
                'observacoes' => 'Primeiro pagamento do contrato',
            ]);

            return [
                'status' => 201,
                'body' => [
                    'type' => 'success',
                    'message' => 'Contrato criado com sucesso',
                    'contrato' => $this->contratos->buscarPorId($contratoId),
                ],
            ];
        } catch (\Throwable $e) {
            return ['status' => 400, 'body' => ['type' => 'error', 'message' => $e->getMessage()]];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function trocarPlano(int $tenantId, array $data): array
    {
        $academia = $this->tenants->findById($tenantId);
        if (! $academia) {
            return ['status' => 404, 'body' => ['type' => 'error', 'message' => 'Academia não encontrada']];
        }

        $errors = [];
        if (empty($data['plano_sistema_id'])) {
            $errors[] = 'Plano do sistema é obrigatório';
        }
        if (empty($data['forma_pagamento_id'])) {
            $errors[] = 'Forma de pagamento é obrigatória';
        }
        if ($errors !== []) {
            return [
                'status' => 422,
                'body' => ['type' => 'error', 'message' => implode(', ', $errors)],
            ];
        }

        try {
            $resultado = $this->contratos->trocarPlano(
                $tenantId,
                (int) $data['plano_sistema_id'],
                (int) $data['forma_pagamento_id'],
                $data['observacoes'] ?? 'Troca de plano',
            );

            return [
                'status' => 200,
                'body' => [
                    'type' => 'success',
                    'message' => 'Plano trocado com sucesso',
                    'contrato' => $resultado,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 500,
                'body' => ['type' => 'error', 'message' => 'Erro ao trocar plano: '.$e->getMessage()],
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function renovar(int $id, array $data): array
    {
        $contrato = $this->contratos->buscarPorId($id);
        if (! $contrato) {
            return ['status' => 404, 'body' => ['type' => 'error', 'message' => 'Contrato não encontrado']];
        }

        try {
            $novaData = $data['data_vencimento'] ?? date('Y-m-d', strtotime(($contrato['data_vencimento'] ?? date('Y-m-d')).' +30 days'));
            $this->contratos->renovar($id, $novaData);

            return [
                'status' => 200,
                'body' => [
                    'type' => 'success',
                    'message' => 'Contrato renovado com sucesso',
                    'nova_data_vencimento' => $novaData,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 500,
                'body' => ['type' => 'error', 'message' => 'Erro ao renovar contrato: '.$e->getMessage()],
            ];
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function cancelar(int $id): array
    {
        $contrato = $this->contratos->buscarPorId($id);
        if (! $contrato) {
            return ['status' => 404, 'body' => ['type' => 'error', 'message' => 'Contrato não encontrado']];
        }

        $this->contratos->cancelar($id);

        return [
            'status' => 200,
            'body' => ['type' => 'success', 'message' => 'Contrato cancelado com sucesso'],
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{status: int, body: array<string, mixed>}
     */
    public function proximosVencimento(array $query): array
    {
        $dias = (int) ($query['dias'] ?? 7);
        $contratos = $this->contratos->proximosVencimento($dias);

        return [
            'status' => 200,
            'body' => ['contratos' => $contratos, 'total' => count($contratos), 'dias' => $dias],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function vencidos(): array
    {
        $contratos = $this->contratos->vencidos();

        return [
            'status' => 200,
            'body' => ['contratos' => $contratos, 'total' => count($contratos)],
        ];
    }
}
