<?php

namespace App\Http\Middleware;

use App\Services\PermissaoService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Painel da academia: super admin, acesso de suporte, ou alguém da equipe (com ao menos uma permissão)
 * NA ACADEMIA DO TOKEN. Antes valia o maior papel do usuário em qualquer academia: admin em A
 * passava como admin em B. O que cada rota exige fica com `permissao.rota` / `permissao:`.
 */
class AdminAuthenticate
{
    public function __construct(
        private readonly PermissaoService $permissoes,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->attributes->get('usuario');

        if (! $usuario) {
            return response()->json([
                'erro' => 'Não autenticado',
            ], 401, [], JSON_UNESCAPED_UNICODE);
        }

        if ((bool) $request->attributes->get('is_super_admin', false) || $request->attributes->get('acesso_suporte')) {
            return $next($request);
        }

        $tenantId = (int) ($request->attributes->get('tenantId') ?? 0);
        $userId = (int) $request->attributes->get('userId', 0);

        if ($tenantId <= 0 || $this->permissoes->efetivas($tenantId, $userId) === []) {
            return response()->json([
                // Mesmo texto de antes (o painel e clientes antigos exibem esta mensagem)
                'erro' => 'Acesso negado. Apenas administradores podem acessar este recurso.',
                'papel_necessario' => 'admin ou super_admin',
                'papel_atual' => isset($usuario['papel_id']) ? (int) $usuario['papel_id'] : null,
            ], 403, [], JSON_UNESCAPED_UNICODE);
        }

        return $next($request);
    }
}
