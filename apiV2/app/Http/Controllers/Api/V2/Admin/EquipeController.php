<?php

namespace App\Http\Controllers\Api\V2\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminEquipeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Equipe e permissões da academia (rotas com permissao:equipe.gerenciar). */
class EquipeController extends Controller
{
    public function __construct(
        private readonly AdminEquipeService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->json($this->service->listar($this->tenantId($request), $this->ctx($request)));
    }

    public function buscar(Request $request): JsonResponse
    {
        return $this->json($this->service->buscar($this->tenantId($request), $request->query('cpf'), $request->query('email')));
    }

    public function store(Request $request): JsonResponse
    {
        return $this->json($this->service->adicionar($this->tenantId($request), $request->all(), $this->ctx($request)));
    }

    public function update(Request $request, int $usuarioId): JsonResponse
    {
        return $this->json($this->service->atualizar($this->tenantId($request), $usuarioId, $request->all(), $this->ctx($request)));
    }

    public function destroy(Request $request, int $usuarioId): JsonResponse
    {
        return $this->json($this->service->remover($this->tenantId($request), $usuarioId, $this->ctx($request)));
    }

    private function tenantId(Request $request): int
    {
        return (int) $request->attributes->get('tenantId', 0);
    }

    /** @return array{usuario_id: int, suporte: bool} */
    private function ctx(Request $request): array
    {
        return ['usuario_id' => (int) $request->attributes->get('userId', 0), 'suporte' => (bool) $request->attributes->get('acesso_suporte')];
    }

    /** @param  array{status: int, body: array<string, mixed>}  $r */
    private function json(array $r): JsonResponse
    {
        return response()->json($r['body'], $r['status'], [], JSON_UNESCAPED_UNICODE);
    }
}
