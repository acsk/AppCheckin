<?php

namespace App\Http\Controllers\Api\V2\Admin;

use App\Http\Controllers\Controller;
use App\Services\AcessoAlunoService;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** "Ver app como aluno" (admin da academia ou super admin no "acessar como academia") e encerramento. */
class AcessoAlunoController extends Controller
{
    public function __construct(
        private readonly AcessoAlunoService $service,
    ) {}

    public function iniciar(Request $request, int $alunoId): JsonResponse
    {
        $tenantId = (int) ($request->attributes->get('tenantId') ?? 0);
        if ($tenantId <= 0) {
            return ApiError::json('Entre na academia para ver o app como aluno.', 'TENANT_OBRIGATORIO', 400);
        }
        $result = $this->service->iniciar(
            $tenantId,
            (int) $request->attributes->get('userId', 0),
            $request->attributes->get('acesso_suporte'),
            $alunoId,
            $request->input('motivo'),
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    /** Chamado pelo app com o token do próprio acesso. */
    public function encerrar(Request $request): JsonResponse
    {
        $acesso = $request->attributes->get('acesso_aluno');
        if (! $acesso) {
            return ApiError::json('Nenhuma visualização como aluno em andamento', 'SEM_ACESSO_ALUNO', 400);
        }
        $result = $this->service->encerrar((int) $acesso['id']);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }
}
