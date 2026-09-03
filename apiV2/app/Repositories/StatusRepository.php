<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class StatusRepository
{
    /** @var array<string, string> */
    private const TABELAS = [
        'conta-receber' => 'status_conta_receber',
        'matricula' => 'status_matricula',
        'pagamento' => 'status_pagamento',
        'checkin' => 'status_checkin',
        'usuario' => 'status_usuario',
        'contrato' => 'status_contrato',
    ];

    public function tipoValido(string $tipo): bool
    {
        return isset(self::TABELAS[$tipo]);
    }

    /**
     * @return array<string>
     */
    public function tiposValidos(): array
    {
        return array_keys(self::TABELAS);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listar(string $tipo): array
    {
        $tabela = self::TABELAS[$tipo];

        return array_map(
            fn ($row) => (array) $row,
            DB::select("SELECT * FROM {$tabela} WHERE ativo = TRUE ORDER BY ordem, nome")
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buscarPorId(string $tipo, int $id): ?array
    {
        $tabela = self::TABELAS[$tipo];
        $row = DB::selectOne(
            "SELECT * FROM {$tabela} WHERE id = ? AND ativo = TRUE",
            [$id]
        );

        return $row ? (array) $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buscarPorCodigo(string $tipo, string $codigo): ?array
    {
        $tabela = self::TABELAS[$tipo];
        $row = DB::selectOne(
            "SELECT * FROM {$tabela} WHERE codigo = ? AND ativo = TRUE",
            [$codigo]
        );

        return $row ? (array) $row : null;
    }
}
