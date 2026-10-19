<?php

namespace App\Http\Controllers\Api\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SuperAdmin\SuperAdminPlanoSistemaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanoSistemaController extends Controller
{
    public function __construct(
        private readonly SuperAdminPlanoSistemaService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->service->index($request->query());

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function disponiveis(): JsonResponse
    {
        $result = $this->service->disponiveis();

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function show(int $id): JsonResponse
    {
        $result = $this->service->show($id);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function academias(int $id): JsonResponse
    {
        $result = $this->service->academias($id);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function store(Request $request): JsonResponse
    {
        $result = $this->service->create($request->all());

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $result = $this->service->update($id, $request->all());

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function marcarHistorico(int $id): JsonResponse
    {
        $result = $this->service->marcarHistorico($id);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function destroy(int $id): JsonResponse
    {
        $result = $this->service->delete($id);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }
}
