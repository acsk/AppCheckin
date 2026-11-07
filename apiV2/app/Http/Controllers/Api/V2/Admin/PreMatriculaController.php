<?php

namespace App\Http\Controllers\Api\V2\Admin;

use App\Http\Controllers\Controller;
use App\Services\PreMatriculaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PreMatriculaController extends Controller
{
    public function __construct(
        private readonly PreMatriculaService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->service->painelAcademia((int) $request->attributes->get('tenantId')),
            200,
            [],
            JSON_UNESCAPED_UNICODE,
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->service->atualizarSolicitacao(
                (int) $request->attributes->get('tenantId'),
                $id,
                $request->all(),
            ),
            200,
            [],
            JSON_UNESCAPED_UNICODE,
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->service->excluirSolicitacao(
                (int) $request->attributes->get('tenantId'),
                $id,
                (int) $request->attributes->get('userId'),
            ),
            200,
            [],
            JSON_UNESCAPED_UNICODE,
        );
    }

    public function aprovar(Request $request, int $id): JsonResponse
    {
        return $this->avaliar($request, $id, 'aprovada');
    }

    public function reprovar(Request $request, int $id): JsonResponse
    {
        return $this->avaliar($request, $id, 'reprovada');
    }

    private function avaliar(Request $request, int $id, string $decisao): JsonResponse
    {
        $dados = $request->validate([
            'observacao' => 'nullable|string|max:1000',
        ]);

        return response()->json(
            $this->service->avaliar(
                (int) $request->attributes->get('tenantId'),
                $id,
                (int) $request->attributes->get('userId'),
                $decisao,
                $dados['observacao'] ?? null,
            ),
            200,
            [],
            JSON_UNESCAPED_UNICODE,
        );
    }
}
