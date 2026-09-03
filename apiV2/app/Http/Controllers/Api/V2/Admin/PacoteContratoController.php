<?php

namespace App\Http\Controllers\Api\V2\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminPacoteService;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PacoteContratoController extends Controller
{
    public function __construct(
        private readonly AdminPacoteService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $status = (string) $request->query('status', 'pendente');
        $result = $this->service->listarContratos($tenantId, $status);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function gerarMatriculas(Request $request, int $contratoId): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $result = $this->service->gerarMatriculas($tenantId, $contratoId);

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
