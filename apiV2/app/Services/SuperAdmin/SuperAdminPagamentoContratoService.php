<?php

namespace App\Services\SuperAdmin;

use App\Repositories\PagamentoContratoRepository;
use App\Repositories\TenantPlanoRepository;

class SuperAdminPagamentoContratoService
{
    public function __construct(
        private readonly PagamentoContratoRepository $pagamentos,
        private readonly TenantPlanoRepository $contratos,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @return array{status: int, body: array<string, mixed>}
     */
    public function index(array $query): array
    {
        $filtros = $this->montarFiltros($query);
        $pagamentos = $this->pagamentos->listarTodos($filtros);

        return ['status' => 200, 'body' => ['pagamentos' => $pagamentos]];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{status: int, body: array<string, mixed>}
     */
    public function resumo(array $query): array
    {
        $filtros = $this->montarFiltros($query);

        return [
            'status' => 200,
            'body' => ['resumo' => $this->pagamentos->resumo($filtros)],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listarPorContrato(int $contratoId): array
    {
        return [
            'status' => 200,
            'body' => ['pagamentos' => $this->pagamentos->listarPorContrato($contratoId)],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function criar(int $contratoId, array $data): array
    {
        $contrato = $this->contratos->buscarPorId($contratoId);
        if (! $contrato) {
            return ['status' => 404, 'body' => ['type' => 'error', 'message' => 'Contrato não encontrado']];
        }

        $errors = [];
        if (empty($data['valor']) || ! is_numeric($data['valor']) || $data['valor'] <= 0) {
            $errors[] = 'Valor inválido';
        }
        if (empty($data['data_vencimento'])) {
            $errors[] = 'Data de vencimento é obrigatória';
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
            $pagamentoId = $this->pagamentos->criar([
                'tenant_plano_id' => $contratoId,
                'valor' => $data['valor'],
                'data_vencimento' => $data['data_vencimento'],
                'data_pagamento' => $data['data_pagamento'] ?? null,
                'status_pagamento_id' => $data['status_pagamento_id'] ?? 1,
                'forma_pagamento_id' => $data['forma_pagamento_id'],
                'comprovante' => $data['comprovante'] ?? null,
                'observacoes' => $data['observacoes'] ?? null,
            ]);

            return [
                'status' => 201,
                'body' => [
                    'type' => 'success',
                    'message' => 'Pagamento registrado com sucesso',
                    'pagamento' => $this->pagamentos->buscarPorId($pagamentoId),
                ],
            ];
        } catch (\Throwable $e) {
            return ['status' => 500, 'body' => ['type' => 'error', 'message' => $e->getMessage()]];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function confirmar(int $id, array $data): array
    {
        $pagamento = $this->pagamentos->buscarPorId($id);
        if (! $pagamento) {
            return ['status' => 404, 'body' => ['type' => 'error', 'message' => 'Pagamento não encontrado']];
        }

        try {
            $this->pagamentos->confirmarPagamento(
                $id,
                $data['data_pagamento'] ?? null,
                isset($data['forma_pagamento_id']) ? (int) $data['forma_pagamento_id'] : null,
                $data['comprovante'] ?? null,
                $data['observacoes'] ?? null,
            );

            $contratoId = (int) $pagamento['tenant_plano_id'];
            if (! $this->pagamentos->temPagamentosPendentes($contratoId)) {
                $this->contratos->atualizarStatus($contratoId, 1);
            }

            $contrato = $this->contratos->buscarPorId($contratoId);
            $duracaoDias = (int) ($contrato['duracao_dias'] ?? 30);

            $dataVencimentoAtual = new \DateTime((string) $pagamento['data_vencimento']);
            $proximaData = (clone $dataVencimentoAtual)->modify("+{$duracaoDias} days");

            $jaExistePendente = false;
            foreach ($this->pagamentos->listarPorContrato($contratoId) as $p) {
                if ((int) ($p['status_pagamento_id'] ?? 0) === 1) {
                    $jaExistePendente = true;
                    break;
                }
            }

            if (! $jaExistePendente) {
                $this->pagamentos->criar([
                    'tenant_plano_id' => $contratoId,
                    'valor' => $pagamento['valor'],
                    'data_vencimento' => $proximaData->format('Y-m-d'),
                    'status_pagamento_id' => 1,
                    'forma_pagamento_id' => $pagamento['forma_pagamento_id'],
                    'observacoes' => "Pagamento gerado automaticamente ({$duracaoDias} dias)",
                ]);
            }

            return [
                'status' => 200,
                'body' => [
                    'type' => 'success',
                    'message' => 'Pagamento confirmado com sucesso',
                    'pagamento' => $this->pagamentos->buscarPorId($id),
                ],
            ];
        } catch (\Throwable $e) {
            return ['status' => 500, 'body' => ['type' => 'error', 'message' => $e->getMessage()]];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function cancelar(int $id, array $data): array
    {
        $pagamento = $this->pagamentos->buscarPorId($id);
        if (! $pagamento) {
            return ['status' => 404, 'body' => ['type' => 'error', 'message' => 'Pagamento não encontrado']];
        }

        try {
            $this->pagamentos->cancelar($id, $data['observacoes'] ?? null);

            return [
                'status' => 200,
                'body' => ['type' => 'success', 'message' => 'Pagamento cancelado com sucesso'],
            ];
        } catch (\Throwable $e) {
            return ['status' => 500, 'body' => ['type' => 'error', 'message' => $e->getMessage()]];
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function marcarAtrasados(): array
    {
        try {
            $qtdAtrasados = $this->pagamentos->marcarAtrasados();
            $contratosBloqueados = $this->pagamentos->bloquearContratosComPagamentosAtrasados();

            return [
                'status' => 200,
                'body' => [
                    'type' => 'success',
                    'message' => "Processamento concluído: {$qtdAtrasados} pagamento(s) marcado(s) como atrasado(s), {$contratosBloqueados} contrato(s) bloqueado(s)",
                    'pagamentos_atrasados' => $qtdAtrasados,
                    'contratos_bloqueados' => $contratosBloqueados,
                ],
            ];
        } catch (\Throwable $e) {
            return ['status' => 500, 'body' => ['type' => 'error', 'message' => $e->getMessage()]];
        }
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function montarFiltros(array $query): array
    {
        $filtros = [];
        if (! empty($query['status_pagamento_id'])) {
            $filtros['status_pagamento_id'] = $query['status_pagamento_id'];
        }
        if (! empty($query['tenant_id'])) {
            $filtros['tenant_id'] = $query['tenant_id'];
        }
        if (! empty($query['data_inicio'])) {
            $filtros['data_inicio'] = $query['data_inicio'];
        }
        if (! empty($query['data_fim'])) {
            $filtros['data_fim'] = $query['data_fim'];
        }

        return $filtros;
    }
}
