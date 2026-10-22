<?php

namespace App\Http\Middleware;

use App\Services\PermissaoService;
use App\Support\ApiError;
use App\Support\Papel;
use App\Support\Permissao;
use App\Support\PermissaoRotas;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `permissao.rota`: aplica App\Support\PermissaoRotas (rota → permissão) na academia do token.
 * Super admin (fora do "acessar como academia") e acesso de suporte passam.
 * Rota sem regra exige Admin na academia, como antes.
 */
class PermissaoPorRota
{
    public function __construct(
        private readonly PermissaoService $permissoes,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->attributes->get('acesso_suporte') || $request->attributes->get('is_super_admin')) {
            return $next($request);
        }

        $tenantId = (int) ($request->attributes->get('tenantId') ?? 0);
        $userId = (int) $request->attributes->get('userId', 0);
        $exigida = PermissaoRotas::exigida($request->method(), $request->path());

        if ($exigida === PermissaoRotas::LIVRE) {
            return $next($request);
        }
        if ($exigida === null) {
            return in_array(Papel::ADMIN, $this->permissoes->papeisAtivos($tenantId, $userId), true)
                ? $next($request)
                : ApiError::json('Apenas administradores da academia podem fazer isso.', 'PERMISSAO_NEGADA', 403);
        }
        if (! $this->permissoes->tem($tenantId, $userId, $exigida)) {
            $nome = Permissao::CATALOGO[$exigida]['nome'] ?? $exigida;

            return ApiError::json("Você não tem a permissão \"{$nome}\" nesta academia.", 'PERMISSAO_NEGADA', 403);
        }

        return $next($request);
    }
}
