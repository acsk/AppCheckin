<?php

namespace App\Http\Controllers\Api\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\AtualizacaoCadastralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Super Admin → Atualização cadastral: campanhas que pedem ao usuário do app para revisar o cadastro. */
class AtualizacaoCadastralController extends Controller
{
    public function __construct(
        private readonly AtualizacaoCadastralService $service,
    ) {}

    public function index(): JsonResponse
    {
        return $this->json($this->service->listar());
    }

    public function store(Request $request): JsonResponse
    {
        return $this->json($this->service->salvar(null, $request->all(), $this->userId($request)));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return $this->json($this->service->salvar($id, $request->all(), $this->userId($request)));
    }

    public function encerrar(int $id): JsonResponse
    {
        return $this->json($this->service->encerrar($id));
    }

    public function pendentes(int $id): JsonResponse
    {
        return $this->json($this->service->pendentes($id));
    }

    public function destroy(int $id): JsonResponse
    {
        return $this->json($this->service->excluir($id));
    }

    /** @param  array{status: int, body: array<string, mixed>}  $result */
    private function json(array $result): JsonResponse
    {
        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    private function userId(Request $request): ?int
    {
        return ((int) $request->attributes->get('userId', 0)) ?: null;
    }
}
