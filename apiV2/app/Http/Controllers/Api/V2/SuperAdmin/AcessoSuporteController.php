<?php

namespace App\Http\Controllers\Api\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SuperAdmin\AcessoSuporteService;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** "Acessar como academia" (super admin) e encerramento do acesso. */
class AcessoSuporteController extends Controller
{
    public function __construct(
        private readonly AcessoSuporteService $service,
    ) {}

    public function iniciar(Request $request, int $tenantId): JsonResponse
    {
        $result = $this->service->iniciar(
            $tenantId,
            (int) $request->attributes->get('userId', 0),
            $request->input('motivo_id'),
            $request->input('detalhe'),
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function motivos(): JsonResponse
    {
        return response()->json(['motivos' => $this->service->motivos()], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /** Chamado com o token do próprio acesso. */
    public function encerrar(Request $request): JsonResponse
    {
        $acesso = $request->attributes->get('acesso_suporte');
        if (! $acesso) {
            return ApiError::json('Nenhum acesso de suporte em andamento', 'SEM_ACESSO_SUPORTE', 400);
        }
        $result = $this->service->encerrar((int) $acesso['id'], (int) $request->attributes->get('userId', 0));

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }
}
