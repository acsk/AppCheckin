<?php

namespace App\Http\Controllers\Api\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SuperAdmin\SuperAdminContratoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContratoController extends Controller
{
    public function __construct(
        private readonly SuperAdminContratoService $service,
    ) {}

    public function proximosVencimento(Request $request): JsonResponse
    {
        $result = $this->service->proximosVencimento($request->query());

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function vencidos(): JsonResponse
    {
        $result = $this->service->vencidos();

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function index(): JsonResponse
    {
        $result = $this->service->index();

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function show(int $id): JsonResponse
    {
        $result = $this->service->show($id);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function porAcademia(int $tenantId): JsonResponse
    {
        $result = $this->service->porAcademia($tenantId);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function contratoAtivo(int $tenantId): JsonResponse
    {
        $result = $this->service->contratoAtivo($tenantId);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function associarPlano(Request $request, int $tenantId): JsonResponse
    {
        $result = $this->service->associarPlano($tenantId, $request->all());

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function trocarPlano(Request $request, int $tenantId): JsonResponse
    {
        $result = $this->service->trocarPlano($tenantId, $request->all());

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function renovar(Request $request, int $id): JsonResponse
    {
        $result = $this->service->renovar($id, $request->all());

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function cancelar(int $id): JsonResponse
    {
        $result = $this->service->cancelar($id);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }
}
