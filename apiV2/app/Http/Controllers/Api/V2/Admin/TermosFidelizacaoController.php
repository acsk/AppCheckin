<?php

namespace App\Http\Controllers\Api\V2\Admin;

use App\Http\Controllers\Controller;
use App\Services\Recorrencia\TermosFidelizacaoService;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Painel → Planos: termos adicionais da fidelização herdados por todos os ciclos da academia. */
class TermosFidelizacaoController extends Controller
{
    public function __construct(
        private readonly TermosFidelizacaoService $service,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return $this->comTenant($request, fn (int $t) => $this->service->obter($t));
    }

    public function update(Request $request): JsonResponse
    {
        $userId = ((int) $request->attributes->get('userId', 0)) ?: null;

        return $this->comTenant($request, fn (int $t) => $this->service->salvar($t, $request->only(['texto', 'aplicar_a_todos']), $userId));
    }

    /** @param  callable(int): array{status: int, body: array<string, mixed>}  $fn */
    private function comTenant(Request $request, callable $fn): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('tenantId', 0);
        if (! $tenantId) {
            return ApiError::json('Tenant não selecionado', 'MISSING_TENANT', 400);
        }
        $r = $fn($tenantId);

        return response()->json($r['body'], $r['status'], [], JSON_UNESCAPED_UNICODE);
    }
}
