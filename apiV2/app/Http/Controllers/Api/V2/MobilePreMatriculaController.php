<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\PreMatriculaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobilePreMatriculaController extends Controller
{
    public function __construct(
        private readonly PreMatriculaService $service,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'pre_matricula' => $this->service->paraAluno(
                (int) $request->attributes->get('tenantId'),
                (int) $request->attributes->get('userId'),
            ),
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function store(Request $request): JsonResponse
    {
        $resultado = $this->service->enviar(
            (int) $request->attributes->get('tenantId'),
            (int) $request->attributes->get('userId'),
            $request->all(),
        );

        return response()->json($resultado, 201, [], JSON_UNESCAPED_UNICODE);
    }
}
