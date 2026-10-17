<?php

namespace App\Http\Controllers\Api\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminAssinaturaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssinaturaController extends Controller
{
    public function __construct(
        private readonly AdminAssinaturaService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->service->listarSuperAdmin($request->query());

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function cobrancas(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) $request->query('tenant_id', 0);
        if ($tenantId <= 0) {
            return response()->json([
                'success' => false,
                'error' => 'tenant_id é obrigatório',
            ], 400, [], JSON_UNESCAPED_UNICODE);
        }

        $result = $this->service->cobrancas($tenantId, $id);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }
}
