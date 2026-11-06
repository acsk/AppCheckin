<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\Mobile\MobileDependenteService;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Dependentes do responsável logado (perfis no app). Ver MobileDependenteService. */
class MobileDependenteController extends Controller
{
    public function __construct(
        private readonly MobileDependenteService $dependentes,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->comoResponsavel($request, fn (int $u, int $t) => $this->dependentes->listar($u, $t));
    }

    public function store(Request $request): JsonResponse
    {
        return $this->comoResponsavel($request, fn (int $u, int $t) => $this->dependentes->criar($u, $t, $request->all()));
    }

    public function entrar(Request $request, int $usuarioId): JsonResponse
    {
        return $this->comoResponsavel($request, fn (int $u, int $t) => $this->dependentes->entrar($u, $usuarioId, $t));
    }

    public function update(Request $request, int $usuarioId): JsonResponse
    {
        return $this->comoResponsavel($request, fn (int $u) => $this->dependentes->atualizar($u, $usuarioId, $request->all()));
    }

    public function definirPin(Request $request, int $usuarioId): JsonResponse
    {
        return $this->comoResponsavel($request, fn (int $u) => $this->dependentes->definirPin($u, $usuarioId, (string) $request->input('pin', '')));
    }

    public function removerPin(Request $request, int $usuarioId): JsonResponse
    {
        return $this->comoResponsavel($request, fn (int $u) => $this->dependentes->removerPin($u, $usuarioId));
    }

    /** Chamado com o token do dependente: devolve a sessão do responsável. */
    public function voltar(Request $request): JsonResponse
    {
        $responsavelId = (int) ($request->attributes->get('responsavel_id') ?? 0);
        $tenantId = (int) $request->attributes->get('tenantId');
        if ($responsavelId <= 0 || $tenantId <= 0) {
            return ApiError::json('Você não está no perfil de um dependente', 'NAO_E_DEPENDENTE', 400);
        }

        return $this->json($this->dependentes->voltar((int) $request->attributes->get('userId'), $responsavelId, $tenantId));
    }

    /** @param  callable(int, int): array{status: int, body: array<string, mixed>}  $acao */
    private function comoResponsavel(Request $request, callable $acao): JsonResponse
    {
        if ($request->attributes->get('responsavel_id') || $request->attributes->get('dependente_login')) {
            return ApiError::json('Volte para o seu perfil para gerenciar dependentes', 'PERFIL_DEPENDENTE', 403);
        }
        $tenantId = (int) $request->attributes->get('tenantId');
        if ($tenantId <= 0) {
            return ApiError::json('Tenant não selecionado', 'MISSING_TENANT', 400);
        }

        return $this->json($acao((int) $request->attributes->get('userId'), $tenantId));
    }

    /** @param  array{status: int, body: array<string, mixed>}  $r */
    private function json(array $r): JsonResponse
    {
        return response()->json($r['body'], $r['status'], [], JSON_UNESCAPED_UNICODE);
    }
}
