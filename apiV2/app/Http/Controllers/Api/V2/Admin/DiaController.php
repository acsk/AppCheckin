<?php

namespace App\Http\Controllers\Api\V2\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDiaService;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiaController extends Controller
{
    public function __construct(
        private readonly AdminDiaService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $result = $this->service->index();

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function desativar(Request $request): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $result = $this->service->desativarDias($tenantId, $request->all());

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function deletarHorarios(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $result = $this->service->deletarHorariosDoDia($id, $tenantId);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    private function requireTenant(Request $request): int|JsonResponse
    {
        $tenantId = $request->attributes->get('tenantId');
        if (! $tenantId) {
            return ApiError::json('Tenant não selecionado', 'MISSING_TENANT', 400);
        }

        return (int) $tenantId;
    }
}
