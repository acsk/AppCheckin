<?php

namespace App\Http\Controllers\Api\V2\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\TenantRepository;
use App\Services\Admin\AdminMinhaContaService;
use App\Services\EntidadeJuridica\EntidadeJuridicaException;
use App\Services\EntidadeJuridica\EntidadeJuridicaService;
use App\Services\PermissaoService;
use App\Services\SuperAdmin\AcessoSuporteService;
use App\Support\ApiError;
use App\Support\Papel;
use App\Support\Permissao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Minha Conta (painel admin). Perfil e senha valem para qualquer usuário do painel; escola e CNPJ
 * exigem papel Admin ativo no tenant do token — o admin.auth olha o maior papel do usuário em
 * qualquer academia, então a checagem aqui é por tenant.
 */
class MinhaContaController extends Controller
{
    public function __construct(
        private readonly AdminMinhaContaService $service,
        private readonly EntidadeJuridicaService $entidades,
        private readonly TenantRepository $tenants,
        private readonly AcessoSuporteService $acessos,
    ) {}

    public function perfil(Request $request): JsonResponse
    {
        return $this->json($this->service->perfil($this->userId($request)));
    }

    public function atualizarPerfil(Request $request): JsonResponse
    {
        if ($bloqueio = $this->bloquearNoAcessoSuporte($request)) {
            return $bloqueio;
        }

        return $this->json($this->service->atualizarPerfil($this->userId($request), $request->all()));
    }

    public function alterarSenha(Request $request): JsonResponse
    {
        if ($bloqueio = $this->bloquearNoAcessoSuporte($request)) {
            return $bloqueio;
        }

        return $this->json($this->service->alterarSenha($this->userId($request), $request->all()));
    }

    public function escola(Request $request): JsonResponse
    {
        $tenantId = $this->adminDoTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        return $this->json($this->service->escola($tenantId));
    }

    public function atualizarEscola(Request $request): JsonResponse
    {
        $tenantId = $this->adminDoTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        return $this->json($this->service->atualizarEscola($tenantId, $request->all()));
    }

    public function cnpj(Request $request): JsonResponse
    {
        $tenantId = $this->adminDoTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        return $this->json(['status' => 200, 'body' => ['cnpj' => $this->entidades->dadosDoTenant($tenantId)]]);
    }

    public function atualizarCnpj(Request $request): JsonResponse
    {
        $tenantId = $this->adminDoTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        try {
            $dados = $this->entidades->atualizarPeloTenant($tenantId, $request->all(), $this->userId($request), $request->ip());
        } catch (EntidadeJuridicaException $e) {
            return $this->json($e->toResult());
        }

        return $this->json(['status' => 200, 'body' => ['type' => 'success', 'message' => 'Dados do CNPJ atualizados', 'cnpj' => $dados]]);
    }

    /** No acesso de suporte o "meu perfil" seria o do super admin, não de alguém da academia */
    private function bloquearNoAcessoSuporte(Request $request): ?JsonResponse
    {
        return $request->attributes->get('acesso_suporte')
            ? ApiError::json('Durante o acesso de suporte não é possível alterar perfil ou senha.', 'ACESSO_SUPORTE', 403)
            : null;
    }

    public function permissoes(Request $request): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('tenantId', 0);
        $todas = array_keys(Permissao::ROTULOS);
        $minhas = $request->attributes->get('acesso_suporte')
            ? $todas
            : ($tenantId ? app(PermissaoService::class)->doUsuario($tenantId, $this->userId($request)) : []);

        return $this->json(['status' => 200, 'body' => ['permissoes' => $minhas]]);
    }

    public function acessosSuporte(Request $request): JsonResponse
    {
        $tenantId = $this->adminDoTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        return $this->json(['status' => 200, 'body' => ['acessos' => $this->acessos->listarDoTenant($tenantId)]]);
    }

    private function adminDoTenant(Request $request): int|JsonResponse
    {
        $tenantId = (int) $request->attributes->get('tenantId', 0);
        if (! $tenantId) {
            return ApiError::json('Tenant não selecionado', 'MISSING_TENANT', 400);
        }
        if (! $request->attributes->get('acesso_suporte')
            && ! in_array(Papel::ADMIN, $this->tenants->listarPapelIdsAtivos($tenantId, $this->userId($request)), true)) {
            return ApiError::json('Apenas administradores desta academia podem acessar estes dados.', 'FORBIDDEN', 403);
        }

        return $tenantId;
    }

    private function userId(Request $request): int
    {
        return (int) $request->attributes->get('userId', 0);
    }

    /** @param  array{status: int, body: array<string, mixed>}  $result */
    private function json(array $result): JsonResponse
    {
        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }
}
