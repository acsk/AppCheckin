<?php

namespace App\Http\Controllers\Api\V2\Admin;

use App\Http\Controllers\Controller;
use App\Services\Recorrencia\AdminRecorrenciaService;
use App\Services\Recorrencia\RecorrenciaService;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Painel → Financeiro → Recorrências (planos com fidelização). Permissões em App\Support\PermissaoRotas. */
class RecorrenciaController extends Controller
{
    public function __construct(
        private readonly AdminRecorrenciaService $admin,
        private readonly RecorrenciaService $recorrencia,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->comTenant($request, fn (int $t) => $this->admin->listar($t, $request->query()));
    }

    public function indicadores(Request $request): JsonResponse
    {
        return $this->comTenant($request, fn (int $t) => $this->admin->indicadores($t));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->comTenant($request, fn (int $t) => $this->admin->detalhe($t, $id, $request->boolean('gateway')));
    }

    public function simular(Request $request, int $id): JsonResponse
    {
        return $this->comTenant($request, function (int $t) use ($request, $id) {
            $sim = $this->recorrencia->simularCancelamento($t, $id, $request->only(['tipo', 'data_efetiva', 'isencao']));

            return $sim ? ['status' => 200, 'body' => ['success' => true, 'simulacao' => $sim]] : ['status' => 404, 'body' => ['success' => false, 'error' => 'Contrato não encontrado']];
        });
    }

    /** Admin cancela (solicita e confirma de uma vez), com isenção e data efetiva opcionais. */
    public function cancelar(Request $request, int $id): JsonResponse
    {
        return $this->comTenant($request, fn (int $t) => $this->recorrencia->solicitarCancelamento($t, $id, 'admin', $this->userId($request),
            $request->only(['tipo', 'motivo', 'data_efetiva', 'isencao', 'isencao_motivo'])));
    }

    /** Confirma o pedido feito pelo aluno (pode isentar total ou parcialmente). */
    public function confirmarCancelamento(Request $request, int $cancelamentoId): JsonResponse
    {
        return $this->comTenant($request, fn (int $t) => $this->recorrencia->confirmarCancelamento($t, $cancelamentoId, $this->userId($request),
            $request->only(['isencao', 'isencao_motivo'])));
    }

    public function sincronizar(Request $request, int $id): JsonResponse
    {
        return $this->comTenant($request, fn (int $t) => $this->admin->sincronizar($t, $id));
    }

    private function userId(Request $request): ?int
    {
        return ((int) $request->attributes->get('userId', 0)) ?: null;
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
