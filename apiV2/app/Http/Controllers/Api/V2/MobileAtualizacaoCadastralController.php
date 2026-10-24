<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\AtualizacaoCadastralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** App: atualização cadastral pendente do usuário, confirmação do e-mail por código e envio. */
class MobileAtualizacaoCadastralController extends Controller
{
    public function __construct(
        private readonly AtualizacaoCadastralService $service,
    ) {}

    public function pendente(Request $request): JsonResponse
    {
        return $this->json($this->service->pendente($this->userId($request)));
    }

    public function enviarCodigo(Request $request): JsonResponse
    {
        return $this->json($this->service->enviarCodigoEmail($this->userId($request), (string) $request->input('email', '')));
    }

    public function validarCodigo(Request $request): JsonResponse
    {
        return $this->json($this->service->validarCodigoEmail(
            $this->userId($request),
            (string) $request->input('email', ''),
            (string) $request->input('codigo', ''),
        ));
    }

    public function responder(Request $request, int $id): JsonResponse
    {
        return $this->json($this->service->responder($this->userId($request), $id, $request->all()));
    }

    /** @param  array{status: int, body: array<string, mixed>}  $result */
    private function json(array $result): JsonResponse
    {
        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    private function userId(Request $request): int
    {
        return (int) $request->attributes->get('userId');
    }
}
