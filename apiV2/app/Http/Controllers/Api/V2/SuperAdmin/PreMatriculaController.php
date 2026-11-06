<?php

namespace App\Http\Controllers\Api\V2\SuperAdmin;

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
            $this->service->painel((int) $request->query('tenant_id')),
            200,
            [],
            JSON_UNESCAPED_UNICODE,
        );
    }

    public function update(Request $request): JsonResponse
    {
        return response()->json([
            'configuracao' => $this->service->salvarConfiguracao($request->all(), (int) $request->attributes->get('userId')),
            'message' => 'Configuração da pré-matrícula salva.',
        ], 200, [], JSON_UNESCAPED_UNICODE);
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
            'tenant_id' => 'required|integer|min:1|exists:tenants,id',
            'observacao' => 'nullable|string|max:1000',
        ]);

        return response()->json(
            $this->service->avaliar(
                (int) $dados['tenant_id'],
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
