<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\StatusService;
use Illuminate\Http\JsonResponse;

class StatusController extends Controller
{
    public function __construct(
        private readonly StatusService $service,
    ) {}

    public function listar(string $tipo): JsonResponse
    {
        $result = $this->service->listar($tipo);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function buscar(string $tipo, int $id): JsonResponse
    {
        $result = $this->service->buscar($tipo, $id);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function buscarPorCodigo(string $tipo, string $codigo): JsonResponse
    {
        $result = $this->service->buscarPorCodigo($tipo, $codigo);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }
}
