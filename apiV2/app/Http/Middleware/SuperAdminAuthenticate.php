<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Apenas Super Admin (papel_id 4 ou claim JWT is_super_admin).
 */
class SuperAdminAuthenticate
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

        if ($papelId !== 4) {
            return response()->json([
                'erro' => 'Acesso negado. Apenas Super Admin pode acessar este recurso.',
                'papel_necessario' => 'super_admin',
                'papel_atual' => $papelId,
            ], 403, [], JSON_UNESCAPED_UNICODE);
        }

        return $next($request);
    }
}
