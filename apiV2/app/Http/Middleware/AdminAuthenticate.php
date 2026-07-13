<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Espelha AdminMiddleware da Slim: apenas papel_id 3 (admin) ou 4 (super_admin).
 */
class AdminAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->attributes->get('usuario');

        if (! $usuario) {
            return response()->json([
                'erro' => 'Não autenticado',
            ], 401, [], JSON_UNESCAPED_UNICODE);
        }

        if ((bool) $request->attributes->get('is_super_admin', false)) {
            return $next($request);
        }

        $papelId = isset($usuario['papel_id']) ? (int) $usuario['papel_id'] : null;

        if ($papelId !== 3 && $papelId !== 4) {
            return response()->json([
                'erro' => 'Acesso negado. Apenas administradores podem acessar este recurso.',
                'papel_necessario' => 'admin ou super_admin',
                'papel_atual' => $papelId,
            ], 403, [], JSON_UNESCAPED_UNICODE);
        }

        return $next($request);
    }
}
