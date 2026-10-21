<?php

namespace App\Http\Middleware;

use App\Support\ApiError;
use App\Support\Papel;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Módulo Prospecção: administrador ou comercial ATIVO no tenant do token (não no papel global).
 * Com o parâmetro `admin` (prospeccao.auth:admin), só administradores.
 * Define `prospeccao_admin` (bool) no request para os controllers.
 */
class ProspeccaoAuthenticate
{
    public function handle(Request $request, Closure $next, ?string $nivel = null): Response
    {
        $userId = (int) $request->attributes->get('userId', 0);
        $tenantId = (int) ($request->attributes->get('tenantId') ?? 0);
        if ($userId <= 0) {
            return ApiError::json('Não autenticado', 'UNAUTHENTICATED', 401);
        }
        if ($tenantId <= 0) {
            return ApiError::json('Tenant não selecionado', 'MISSING_TENANT', 400);
        }

        $papeis = (bool) $request->attributes->get('is_super_admin', false)
            ? [Papel::ADMIN]
            : DB::table('tenant_usuario_papel')
                ->where('usuario_id', $userId)->where('tenant_id', $tenantId)->where('ativo', 1)
                ->whereIn('papel_id', [Papel::ADMIN, Papel::COMERCIAL])
                ->pluck('papel_id')->map(fn ($p) => (int) $p)->all();

        $admin = in_array(Papel::ADMIN, $papeis, true);
        if ($papeis === [] || ($nivel === 'admin' && ! $admin)) {
            return ApiError::json(
                $nivel === 'admin' ? 'Apenas administradores podem fazer esta ação' : 'Acesso restrito à equipe comercial e administradores',
                'FORBIDDEN',
                403,
            );
        }

        $request->attributes->set('prospeccao_admin', $admin);

        return $next($request);
    }
}
