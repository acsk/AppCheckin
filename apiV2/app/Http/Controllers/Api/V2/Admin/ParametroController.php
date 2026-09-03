<?php

namespace App\Http\Controllers\Api\V2\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminParametroService;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParametroController extends Controller
{
    public function __construct(
        private readonly AdminParametroService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $result = $this->service->index($tenantId);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function categorias(): JsonResponse
    {
        $result = $this->service->categorias();

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function resumoPagamentos(Request $request): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $result = $this->service->resumoPagamentos($tenantId);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function byCategoria(Request $request, string $categoria): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $result = $this->service->byCategoria($tenantId, $categoria);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function getValue(Request $request, string $codigo): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $result = $this->service->getValue($tenantId, $codigo);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function updateMultiple(Request $request): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $usuarioId = $request->attributes->get('userId');
        $result = $this->service->updateMultiple(
            $tenantId,
            $request->input('parametros', []),
            $usuarioId !== null ? (int) $usuarioId : null,
        );

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function update(Request $request, string $codigo): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        if (! $request->has('valor')) {
            return response()->json([
                'success' => false,
                'message' => 'Valor não informado',
            ], 400, [], JSON_UNESCAPED_UNICODE);
        }

        $usuarioId = $request->attributes->get('userId');
        $result = $this->service->update(
            $tenantId,
            $codigo,
            $request->input('valor'),
            $usuarioId !== null ? (int) $usuarioId : null,
        );

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function patch(Request $request, string $codigo): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        if (! $request->has('valor')) {
            return response()->json([
                'success' => false,
                'message' => 'Valor não informado',
            ], 400, [], JSON_UNESCAPED_UNICODE);
        }

        $usuarioId = $request->attributes->get('userId');
        $result = $this->service->patch(
            $tenantId,
            $codigo,
            $request->input('valor'),
            $usuarioId !== null ? (int) $usuarioId : null,
        );

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function toggle(Request $request, string $codigo): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $usuarioId = $request->attributes->get('userId');
        $result = $this->service->toggle(
            $tenantId,
            $codigo,
            $usuarioId !== null ? (int) $usuarioId : null,
        );

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
