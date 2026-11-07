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

    public function destroyConfiguracao(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'tenant_id' => 'required|integer|min:1|exists:tenants,id',
        ]);

        return response()->json([
            'configuracao' => $this->service->excluirConfiguracao((int) $dados['tenant_id']),
            'message' => 'Configuração removida. As solicitações já recebidas foram preservadas.',
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function atualizarSolicitacao(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->tenantDaRequisicao($request);

        return response()->json(
            $this->service->atualizarSolicitacao($tenantId, $id, $request->except('tenant_id')),
            200,
            [],
            JSON_UNESCAPED_UNICODE,
        );
    }

    public function excluirSolicitacao(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->tenantDaRequisicao($request);

        return response()->json(
            $this->service->excluirSolicitacao($tenantId, $id, (int) $request->attributes->get('userId')),
            200,
            [],
            JSON_UNESCAPED_UNICODE,
        );
    }

    private function tenantDaRequisicao(Request $request): int
    {
        $dados = validator(
            ['tenant_id' => $request->input('tenant_id', $request->query('tenant_id'))],
            ['tenant_id' => 'required|integer|min:1|exists:tenants,id'],
            ['tenant_id.required' => 'Selecione uma academia.', 'tenant_id.exists' => 'A academia selecionada não foi encontrada.'],
        )->validate();

        return (int) $dados['tenant_id'];
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
