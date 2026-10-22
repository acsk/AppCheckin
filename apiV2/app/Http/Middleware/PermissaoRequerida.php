<?php

namespace App\Http\Middleware;

use App\Services\PermissaoService;
use App\Support\ApiError;
use App\Support\Permissao;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `permissao:<codigo>`: exige a permissão pontual no tenant do token (App\Support\Permissao).
 * O super admin no "acessar como academia" passa sempre.
 */
class PermissaoRequerida
{
    public function __construct(
        private readonly PermissaoService $permissoes,
    ) {}

    public function handle(Request $request, Closure $next, string $permissao): Response
    {
        if ($request->attributes->get('acesso_suporte')) {
            return $next($request);
        }

        $tenantId = (int) ($request->attributes->get('tenantId') ?? 0);
        $userId = (int) $request->attributes->get('userId', 0);
        if ($tenantId <= 0 || $userId <= 0 || ! $this->permissoes->tem($tenantId, $userId, $permissao)) {
            $nome = Permissao::ROTULOS[$permissao]['nome'] ?? $permissao;

            return ApiError::json("Você não tem a permissão \"{$nome}\" nesta academia. Peça ao suporte do Check-in.", 'PERMISSAO_NEGADA', 403);
        }

        return $next($request);
    }
}
