<?php

namespace App\Http\Controllers\Api\V2\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminPacoteService;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PacoteController extends Controller
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

        $result = $this->service->listar($tenantId);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $result = $this->service->criar($tenantId, $request->all());

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $result = $this->service->atualizar($tenantId, $id, $request->all());

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function contratar(Request $request, int $pacoteId): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $result = $this->service->contratar($tenantId, $pacoteId, $request->all());

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function definirBeneficiarios(Request $request, int $contratoId): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $beneficiarios = $request->input('beneficiarios', []);
        $result = $this->service->definirBeneficiarios($tenantId, $contratoId, (array) $beneficiarios);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function confirmarPagamento(Request $request, int $contratoId): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $result = $this->service->confirmarPagamento($tenantId, $contratoId, $request->all());

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function excluirContrato(Request $request, int $contratoId): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $result = $this->service->excluirContrato($tenantId, $contratoId);

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
