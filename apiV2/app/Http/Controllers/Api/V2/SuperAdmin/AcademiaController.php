<?php

namespace App\Http\Controllers\Api\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SuperAdmin\SuperAdminAcademiaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcademiaController extends Controller
{
    public function __construct(
        private readonly SuperAdminAcademiaService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->service->listarAcademias($request->query());

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function show(int $id): JsonResponse
    {
        $result = $this->service->buscarAcademia($id);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function store(Request $request): JsonResponse
    {
        $result = $this->service->criarAcademia($request->all());

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $result = $this->service->atualizarAcademia($id, $request->all());

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function destroy(int $id): JsonResponse
    {
        $result = $this->service->excluirAcademia($id);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function listarAdmins(int $tenantId): JsonResponse
    {
        $result = $this->service->listarAdmins($tenantId);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function criarAdmin(Request $request, int $tenantId): JsonResponse
    {
        $result = $this->service->criarAdmin($tenantId, $request->all());

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function atualizarAdmin(Request $request, int $tenantId, int $adminId): JsonResponse
    {
        $result = $this->service->atualizarAdmin($tenantId, $adminId, $request->all());

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function desativarAdmin(Request $request, int $tenantId, int $adminId): JsonResponse
    {
        $result = $this->service->desativarAdmin($tenantId, $adminId, $request->all());

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function reativarAdmin(int $tenantId, int $adminId): JsonResponse
    {
        $result = $this->service->reativarAdmin($tenantId, $adminId);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }
}
