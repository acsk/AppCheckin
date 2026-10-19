<?php

namespace App\Services\SuperAdmin;

use App\Repositories\PagamentoContratoRepository;
use App\Repositories\PlanoSistemaRepository;
use App\Repositories\TenantPlanoRepository;
use App\Repositories\TenantRepository;
use App\Support\TipoCobrancaPlanoSistema;

class SuperAdminContratoService
{
    public function __construct(
        private readonly TenantPlanoRepository $contratos,
        private readonly TenantRepository $tenants,
        private readonly PlanoSistemaRepository $planosSistema,
        private readonly PagamentoContratoRepository $pagamentos,
        private readonly CobrancaTenantService $cobranca,
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
                        'data_fim' => $contratoAtivo['data_fim'] ?? null,
                    ],
                    'sugestao' => 'Use o endpoint POST /academias/{id}/trocar-plano para trocar de plano, ou cancele/desative o contrato atual',
                ],
            ];
        }

        $dataInicio = ! empty($data['data_inicio']) ? (string) $data['data_inicio'] : date('Y-m-d');
        [$plano, $errors] = $this->validarPlanoEPeriodo($data, $dataInicio);

        if ($errors !== []) {
            return [
                'status' => 422,
                'body' => ['type' => 'error', 'message' => implode(', ', $errors)],
            ];
        }

        try {
            $this->tenants->inicializarFormasPagamento($tenantId);

            $gratuito = TipoCobrancaPlanoSistema::isGratuito($plano);

            $contratoId = $this->contratos->criar([
                'tenant_id' => $tenantId,
                'plano_sistema_id' => $plano['id'],
                'status_id' => $gratuito ? 1 : 2,
                'data_inicio' => $dataInicio,
                'data_fim' => $this->dataFim($data),
                'observacoes' => $data['observacoes'] ?? null,
            ]);

            if (! $gratuito) {
                $this->criarPrimeiroPagamento($contratoId, $plano, $tenantId, $dataInicio, (int) $data['forma_pagamento_id']);
            }

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

        $dataInicio = date('Y-m-d');
        [$plano, $errors] = $this->validarPlanoEPeriodo($data, $dataInicio);
        if ($errors !== []) {
            return [
                'status' => 422,
                'body' => ['type' => 'error', 'message' => implode(', ', $errors)],
            ];
        }

        try {
            $gratuito = TipoCobrancaPlanoSistema::isGratuito($plano);

            $resultado = $this->contratos->trocarPlano(
                $tenantId,
                (int) $plano['id'],
                $gratuito ? 1 : 2,
                $this->dataFim($data),
                $data['observacoes'] ?? 'Troca de plano',
                $gratuito ? null : fn (int $contratoId) => $this->criarPrimeiroPagamento(
                    $contratoId,
                    $plano,
                    $tenantId,
                    $dataInicio,
                    (int) $data['forma_pagamento_id'],
                ),
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

        $novaData = $data['data_fim'] ?? $data['data_vencimento'] ?? null;
        if ($novaData === null) {
            $duracaoDias = (int) ($contrato['duracao_dias'] ?? 30);
            $base = max((string) ($contrato['data_fim'] ?? ''), date('Y-m-d'));
            $novaData = date('Y-m-d', strtotime("{$base} +{$duracaoDias} days"));
        } elseif (! $this->dataValida((string) $novaData) || $novaData < $contrato['data_inicio']) {
            return [
                'status' => 422,
                'body' => ['type' => 'error', 'message' => 'Data de fim inválida ou anterior ao início do contrato'],
            ];
        }

        try {
            $this->contratos->renovar($id, (string) $novaData);

            return [
                'status' => 200,
                'body' => [
                    'type' => 'success',
                    'message' => 'Contrato renovado com sucesso',
                    'nova_data_fim' => $novaData,
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

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function estimativaCobranca(int $tenantId): array
    {
        $academia = $this->tenants->findById($tenantId);
        if (! $academia) {
            return ['status' => 404, 'body' => ['type' => 'error', 'message' => 'Academia não encontrada']];
        }

        $contrato = $this->contratos->buscarContratoVigente($tenantId);
        if (! $contrato) {
            return [
                'status' => 200,
                'body' => [
                    'type' => 'warning',
                    'message' => 'Academia não possui contrato vigente',
                    'contrato' => null,
                    'estimativa' => null,
                ],
            ];
        }

        $plano = $this->planosSistema->buscarPorId((int) $contrato['plano_sistema_id']);

        return [
            'status' => 200,
            'body' => [
                'contrato' => [
                    'id' => $contrato['id'],
                    'plano_sistema_id' => $contrato['plano_sistema_id'],
                    'plano_nome' => $contrato['plano_nome'],
                    'status_id' => $contrato['status_id'],
                    'status_nome' => $contrato['status_nome'],
                    'data_inicio' => $contrato['data_inicio'],
                    'data_fim' => $contrato['data_fim'] ?? null,
                ],
                'estimativa' => $this->cobranca->estimativa($plano ?? $contrato, $tenantId),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>|null, 1: list<string>}
     */
    private function validarPlanoEPeriodo(array $data, string $dataInicio): array
    {
        $errors = [];
        $plano = null;

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

        if (empty($data['forma_pagamento_id']) && ! ($plano && TipoCobrancaPlanoSistema::isGratuito($plano))) {
            $errors[] = 'Forma de pagamento é obrigatória';
        }

        if (! $this->dataValida($dataInicio)) {
            $errors[] = 'Data de início inválida';
        }

        $dataFim = $this->dataFim($data);
        if ($dataFim !== null && (! $this->dataValida($dataFim) || $dataFim < $dataInicio)) {
            $errors[] = 'Data de fim deve ser igual ou posterior à data de início';
        }

        return [$plano, $errors];
    }

    /** Aceita data_vencimento por compatibilidade com o painel antigo. Vazio = prazo indeterminado. */
    private function dataFim(array $data): ?string
    {
        $valor = $data['data_fim'] ?? $data['data_vencimento'] ?? null;

        return $valor === null || $valor === '' ? null : (string) $valor;
    }

    private function dataValida(string $data): bool
    {
        $dt = \DateTime::createFromFormat('Y-m-d', $data);

        return $dt !== false && $dt->format('Y-m-d') === $data;
    }

    /**
     * @param  array<string, mixed>  $plano
     */
    private function criarPrimeiroPagamento(int $contratoId, array $plano, int $tenantId, string $dataVencimento, int $formaPagamentoId): void
    {
        $this->pagamentos->criar([
            'tenant_plano_id' => $contratoId,
            'valor' => $this->cobranca->valorMensal($plano, $tenantId),
            'data_vencimento' => $dataVencimento,
            'status_pagamento_id' => 1,
            'forma_pagamento_id' => $formaPagamentoId,
            'observacoes' => 'Primeiro pagamento do contrato',
        ]);
    }
}
