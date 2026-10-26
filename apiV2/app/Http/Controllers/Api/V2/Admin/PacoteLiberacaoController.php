<?php

namespace App\Http\Controllers\Api\V2\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminPacoteLiberacaoService;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Painel → Pacotes: alunos liberados para contratar um pacote pelo app. */
class PacoteLiberacaoController extends Controller
{
    public function __construct(
        private readonly AdminPacoteLiberacaoService $service,
    ) {}

    public function index(Request $request, int $pacoteId): JsonResponse
    {
        return $this->comTenant($request, fn (int $t) => $this->service->listar($t, $pacoteId));
    }

    public function store(Request $request, int $pacoteId): JsonResponse
    {
        $adminId = ((int) $request->attributes->get('userId', 0)) ?: null;

        return $this->comTenant($request, fn (int $t) => $this->service->liberar($t, $pacoteId, $request->all(), $adminId));
    }

    public function destroy(Request $request, int $pacoteId, int $alunoId): JsonResponse
    {
        return $this->comTenant($request, fn (int $t) => $this->service->revogar($t, $pacoteId, $alunoId));
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
