<?php

namespace App\Services;

use App\Repositories\FormaPagamentoCatalogRepository;
use Illuminate\Support\Facades\DB;

class ConfigTenantService
{
    public function __construct(
        private readonly FormaPagamentoCatalogRepository $formas,
    ) {}

    /**
     * @return array{status: int, body: array<int|string, mixed>|list<array<string, mixed>>}
     */
    public function listarFormasPagamento(): array
    {
        $rows = DB::select(
            'SELECT id, nome, percentual_desconto
             FROM formas_pagamento
             WHERE ativo = 1
             ORDER BY nome'
        );

        return [
            'status' => 200,
            'body' => array_map(fn ($row) => (array) $row, $rows),
        ];
    }

    /**
     * @return array{status: int, body: list<array<string, mixed>>}
     */
    public function listarStatusConta(): array
    {
        $rows = DB::select(
            'SELECT id, nome, cor
             FROM status_conta
             ORDER BY nome'
        );

        return [
            'status' => 200,
            'body' => array_map(fn ($row) => (array) $row, $rows),
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listarFormasPagamentoAtivas(int $tenantId): array
    {
        return [
            'status' => 200,
            'body' => [
                'formas' => $this->formas->listarTodas($tenantId),
            ],
        ];
    }
}
