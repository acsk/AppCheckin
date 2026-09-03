<?php

namespace App\Http\Controllers\Api\V2\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminTurmaService;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TurmaController extends Controller
{
    public function __construct(
        private readonly AdminTurmaService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $apenasAtivas = $request->query('apenas_ativas') === 'true';
        $data = $request->query('data');
        $diaId = $request->query('dia_id') !== null ? (int) $request->query('dia_id') : null;

        $result = $this->service->index(
            $tenantId,
            is_string($data) ? $data : null,
            $diaId,
            $apenasAtivas,
        );

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $result = $this->service->show($id, $tenantId);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $result = $this->service->create($tenantId, $request->all());

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $result = $this->service->update($id, $tenantId, $request->all());

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $result = $this->service->delete($id, $tenantId);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function destroyPermanente(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $result = $this->service->deletePermanente($id, $tenantId);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function vagas(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $result = $this->service->verificarVagas($id, $tenantId);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function replicar(Request $request): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $result = $this->service->replicarPorDiasSemana($tenantId, $request->all());

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function replicarSemana(Request $request): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $result = $this->service->replicarSemana($tenantId, $request->all());

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function desativar(Request $request): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $result = $this->service->desativarTurma($tenantId, $request->all());

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function bloquearCheckin(Request $request, int $id): JsonResponse
    {
        return $this->alterarBloqueioCheckin($request, $id, true);
    }

    public function desbloquearCheckin(Request $request, int $id): JsonResponse
    {
        return $this->alterarBloqueioCheckin($request, $id, false);
    }

    private function alterarBloqueioCheckin(Request $request, int $id, bool $bloquear): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $userId = (int) $request->attributes->get('userId');
        $usuario = $request->attributes->get('usuario');
        $papel = is_array($usuario) && isset($usuario['papel_id'])
            ? ['id' => (int) $usuario['papel_id']]
            : null;

        $motivo = $bloquear ? ($request->input('motivo') !== null ? (string) $request->input('motivo') : null) : null;

        $result = $this->service->alterarBloqueioCheckin($id, $tenantId, $userId, $papel, $bloquear, $motivo);

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
