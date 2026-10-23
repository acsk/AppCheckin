<?php

namespace App\Http\Controllers\Api\V2\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminUsuarioService;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsuarioController extends Controller
{
    public function __construct(
        private readonly AdminUsuarioService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $result = $this->service->index($tenantId, $request->query(), $this->usuarioLogado($request));

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $result = $this->service->show($id, $tenantId, $this->usuarioLogado($request));

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }
        if (($negado = $this->somenteSuporteCheckin($request)) !== null) {
            return $negado;
        }

        $result = $this->service->create($tenantId, $request->all(), $request->attributes->get('usuario'));

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }
        if (($negado = $this->somenteSuporteCheckin($request)) !== null) {
            return $negado;
        }

        $result = $this->service->update($id, $tenantId, $request->all(), $this->usuarioLogado($request));

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }
        if (($negado = $this->somenteSuporteCheckin($request)) !== null) {
            return $negado;
        }

        $result = $this->service->delete($id, $tenantId, (int) $request->attributes->get('userId'));

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function buscarPorCpf(Request $request, string $cpf): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $result = $this->service->buscarPorCpf($cpf, $tenantId);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function associar(Request $request): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }
        if (($negado = $this->somenteSuporteCheckin($request)) !== null) {
            return $negado;
        }

        $result = $this->service->associar($tenantId, $request->all());

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function estatisticas(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $result = $this->service->estatisticas($id, $tenantId);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function admins(Request $request): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $result = $this->service->admins($tenantId);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>|null
     */
    /**
     * Criar, associar, editar e desativar pela tela de Usuários é só do super admin (inclusive no
     * "acessar como academia"). A academia cuida da equipe em Equipe e de alunos/professores nas telas deles;
     * a listagem continua liberada (a matrícula usa).
     */
    private function somenteSuporteCheckin(Request $request): ?JsonResponse
    {
        $usuario = $request->attributes->get('usuario');
        $superAdmin = $request->attributes->get('is_super_admin') || (is_array($usuario) && (int) ($usuario['papel_id'] ?? 0) === 4);
        if ($superAdmin || $request->attributes->get('acesso_suporte')) {
            return null;
        }

        return ApiError::json('Usuários são gerenciados pelo suporte do Check-in. Use Equipe, Alunos ou Professores.', 'APENAS_SUPORTE', 403);
    }

    private function usuarioLogado(Request $request): ?array
    {
        $usuario = $request->attributes->get('usuario');
        if (is_array($usuario) && $request->attributes->get('acesso_suporte')) {
            // Super admin no "acessar como academia": age como admin, mas pode ver/editar a equipe toda
            $usuario['acesso_suporte'] = true;
        }

        return is_array($usuario) ? $usuario : null;
    }

    private function requireTenant(Request $request): int|JsonResponse
    {
        $tenantId = $request->attributes->get('tenantId');
        if (! $tenantId) {
            return ApiError::json('Tenant não selecionado', 'MISSING_TENANT', 400);
        }

        return (int) $tenantId;
    }
}
