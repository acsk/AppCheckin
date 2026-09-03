<?php

namespace App\Http\Controllers\Api\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SuperAdmin\SuperAdminPagamentoContratoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PagamentoContratoController extends Controller
{
    public function __construct(
        private readonly SuperAdminPagamentoContratoService $service,
    ) {}

    public function resumo(Request $request): JsonResponse
    {
        $result = $this->service->resumo($request->query());

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function marcarAtrasados(): JsonResponse
    {
        $result = $this->service->marcarAtrasados();

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function confirmar(Request $request, int $id): JsonResponse
    {
        $result = $this->service->confirmar($id, $request->all());

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $result = $this->service->cancelar($id, $request->all());

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function index(Request $request): JsonResponse
    {
        $result = $this->service->index($request->query());

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function listarPorContrato(int $contratoId): JsonResponse
    {
        $result = $this->service->listarPorContrato($contratoId);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function store(Request $request, int $contratoId): JsonResponse
    {
        $result = $this->service->criar($contratoId, $request->all());

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }
}
