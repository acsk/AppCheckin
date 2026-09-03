<?php

namespace App\Services\Admin;

use App\Repositories\TenantFormaPagamentoRepository;
use App\Support\FormaPagamentoCalculo;

/**
 * Configuração de formas de pagamento do tenant (paridade Slim TenantFormaPagamentoController).
 */
class AdminFormaPagamentoConfigService
{
    public function __construct(
        private readonly TenantFormaPagamentoRepository $formas,
    ) {}

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listar(int $tenantId, bool $apenasAtivas = false): array
    {
        return [
            'status' => 200,
            'body' => [
                'formas_pagamento' => $this->formas->listar($tenantId, $apenasAtivas),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function buscar(int $id, int $tenantId): array
    {
        $forma = $this->formas->buscar($id, $tenantId);
        if (! $forma) {
            return [
                'status' => 404,
                'body' => ['type' => 'error', 'message' => 'Configuração não encontrada'],
            ];
        }

        return ['status' => 200, 'body' => $forma];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function atualizar(int $id, int $tenantId, array $data): array
    {
        $errors = $this->validarAtualizacao($data);
        if ($errors !== []) {
            return [
                'status' => 422,
                'body' => ['type' => 'error', 'errors' => $errors],
            ];
        }

        $sucesso = $this->formas->atualizar($id, $tenantId, [
            'ativo' => $data['ativo'] ?? 1,
            'taxa_percentual' => $data['taxa_percentual'],
            'taxa_fixa' => $data['taxa_fixa'],
            'aceita_parcelamento' => $data['aceita_parcelamento'] ?? 0,
            'parcelas_minimas' => $data['parcelas_minimas'] ?? 1,
            'parcelas_maximas' => $data['parcelas_maximas'] ?? 1,
            'juros_parcelamento' => $data['juros_parcelamento'] ?? 0.00,
            'parcelas_sem_juros' => $data['parcelas_sem_juros'] ?? 1,
            'dias_compensacao' => $data['dias_compensacao'] ?? 0,
            'valor_minimo' => $data['valor_minimo'] ?? 0.00,
            'observacoes' => $data['observacoes'] ?? null,
        ]);

        if ($sucesso) {
            return [
                'status' => 200,
                'body' => ['type' => 'success', 'message' => 'Configuração atualizada com sucesso'],
            ];
        }

        return [
            'status' => 500,
            'body' => ['type' => 'error', 'message' => 'Erro ao atualizar configuração'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function calcularTaxas(int $tenantId, array $data): array
    {
        $formaPagamentoId = $data['forma_pagamento_id'] ?? null;
        $valorBruto = (float) ($data['valor'] ?? 0);

        if (! $formaPagamentoId || ! $valorBruto) {
            return [
                'status' => 400,
                'body' => ['type' => 'error', 'message' => 'Forma de pagamento e valor são obrigatórios'],
            ];
        }

        $config = $this->formas->buscarTaxasAtivas($tenantId, (int) $formaPagamentoId);
        $calculo = FormaPagamentoCalculo::valorLiquido($config, $valorBruto);

        return ['status' => 200, 'body' => $calculo];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function calcularParcelas(int $tenantId, array $data): array
    {
        $formaPagamentoId = $data['forma_pagamento_id'] ?? null;
        $valorTotal = (float) ($data['valor'] ?? 0);
        $numeroParcelas = (int) ($data['parcelas'] ?? 1);

        if (! $formaPagamentoId || ! $valorTotal) {
            return [
                'status' => 400,
                'body' => ['type' => 'error', 'message' => 'Forma de pagamento e valor são obrigatórios'],
            ];
        }

        $config = $this->formas->buscarConfigAtiva($tenantId, (int) $formaPagamentoId);
        if (! $config) {
            return [
                'status' => 400,
                'body' => ['type' => 'error', 'message' => 'Forma de pagamento não configurada'],
            ];
        }

        $calculo = FormaPagamentoCalculo::parcelas($config, $valorTotal, $numeroParcelas);
        if (isset($calculo['erro'])) {
            return [
                'status' => 400,
                'body' => ['type' => 'error', 'message' => $calculo['erro']],
            ];
        }

        return ['status' => 200, 'body' => $calculo];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function validarAtualizacao(array $data): array
    {
        $errors = [];

        if (! isset($data['taxa_percentual']) || $data['taxa_percentual'] < 0) {
            $errors[] = 'Taxa percentual deve ser maior ou igual a zero';
        }

        if (! isset($data['taxa_fixa']) || $data['taxa_fixa'] < 0) {
            $errors[] = 'Taxa fixa deve ser maior ou igual a zero';
        }

        if ($data['aceita_parcelamento'] ?? false) {
            if (empty($data['parcelas_maximas']) || $data['parcelas_maximas'] < 1) {
                $errors[] = 'Parcelas máximas deve ser maior que zero';
            }
        }

        return $errors;
    }
}
