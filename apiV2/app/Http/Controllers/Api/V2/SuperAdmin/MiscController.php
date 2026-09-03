<?php

namespace App\Http\Controllers\Api\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SuperAdmin\SuperAdminAssinaturaService;
use App\Services\SuperAdmin\SuperAdminMiscService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MiscController extends Controller
{
    public function __construct(
        private readonly SuperAdminMiscService $misc,
        private readonly SuperAdminAssinaturaService $assinaturas,
    ) {}

    public function papeis(): JsonResponse
    {
        $result = $this->misc->listarPapeis();

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function env(): JsonResponse
    {
        $result = $this->misc->getEnvironmentVariables();

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function planosAlunos(Request $request): JsonResponse
    {
        $result = $this->misc->listarPlanosAlunos($request->query());

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function assinaturas(Request $request): JsonResponse
    {
        $result = $this->assinaturas->listar($request->query());

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }
}
