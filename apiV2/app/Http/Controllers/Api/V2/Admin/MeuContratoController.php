<?php

namespace App\Http\Controllers\Api\V2\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminMeuContratoService;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contrato da academia com o Check-in, pelo admin dela: ver, pagar e trocar de plano.
 */
class MeuContratoController extends Controller
{
    public function __construct(
        private readonly AdminMeuContratoService $service,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        return $this->json($this->service->mostrar($tenantId));
    }

    public function pagar(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        return $this->json($this->service->pagar($tenantId, $id, $request->all()));
    }

    public function trocarPlano(Request $request): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }
        $usuario = (array) $request->attributes->get('usuario', []);

        return $this->json($this->service->trocarPlano($tenantId, $request->all(), $usuario['nome'] ?? null));
    }

    /**
     * @param  array{status: int, body: array<string, mixed>}  $result
     */
    private function json(array $result): JsonResponse
    {
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
