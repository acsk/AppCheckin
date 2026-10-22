<?php

namespace App\Http\Controllers\Api\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SuperAdmin\UnificacaoUsuarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Super Admin: unificar dois cadastros da mesma pessoa (prévia + execução). */
class UnificacaoUsuarioController extends Controller
{
    public function __construct(
        private readonly UnificacaoUsuarioService $service,
    ) {}

    public function previa(Request $request): JsonResponse
    {
        // origem/destino: id, CPF ou e-mail
        $r = $this->service->previa($this->service->resolver((string) $request->query('origem', '')), $this->service->resolver((string) $request->query('destino', '')));

        return response()->json($r['body'], $r['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function unificar(Request $request): JsonResponse
    {
        $r = $this->service->unificar(
            $this->service->resolver((string) $request->input('origem', '')),
            $this->service->resolver((string) $request->input('destino', '')),
            ((int) $request->attributes->get('userId', 0)) ?: null,
        );

        return response()->json($r['body'], $r['status'], [], JSON_UNESCAPED_UNICODE);
    }
}
