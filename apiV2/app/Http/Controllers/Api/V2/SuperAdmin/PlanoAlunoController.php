<?php

namespace App\Http\Controllers\Api\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SuperAdmin\SuperAdminPlanoAlunoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanoAlunoController extends Controller
{
    public function __construct(
        private readonly SuperAdminPlanoAlunoService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->service->listarPlanosAlunos($request->query());

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }
}
