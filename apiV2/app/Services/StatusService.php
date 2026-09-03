<?php

namespace App\Services;

use App\Repositories\StatusRepository;

class StatusService
{
    public function __construct(
        private readonly StatusRepository $status,
    ) {}

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listar(string $tipo): array
    {
        if (! $this->status->tipoValido($tipo)) {
            return [
                'status' => 400,
                'body' => [
                    'error' => 'Tipo de status inválido',
                    'tipos_validos' => $this->status->tiposValidos(),
                ],
            ];
        }

        $rows = $this->status->listar($tipo);

        return [
            'status' => 200,
            'body' => [
                'tipo' => $tipo,
                'status' => $rows,
                'total' => count($rows),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function buscar(string $tipo, int $id): array
    {
        if (! $this->status->tipoValido($tipo)) {
            return [
                'status' => 400,
                'body' => ['error' => 'Tipo de status inválido'],
            ];
        }

        $row = $this->status->buscarPorId($tipo, $id);
        if (! $row) {
            return [
                'status' => 404,
                'body' => ['error' => 'Status não encontrado'],
            ];
        }

        return ['status' => 200, 'body' => $row];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function buscarPorCodigo(string $tipo, string $codigo): array
    {
        if (! $this->status->tipoValido($tipo)) {
            return [
                'status' => 400,
                'body' => ['error' => 'Tipo de status inválido'],
            ];
        }

        $row = $this->status->buscarPorCodigo($tipo, $codigo);
        if (! $row) {
            return [
                'status' => 404,
                'body' => ['error' => 'Status não encontrado'],
            ];
        }

        return ['status' => 200, 'body' => $row];
    }
}
