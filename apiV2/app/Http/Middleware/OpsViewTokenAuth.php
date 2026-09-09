<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class OpsViewTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('appcheckin.ops_view_token', '');

        if ($expected === '') {
            abort(503, 'Painel de erros não configurado (OPS_VIEW_TOKEN ausente).');
        }

        $provided = (string) ($request->query('token') ?? $request->header('X-Ops-Token', ''));

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            abort(403, 'Token inválido.');
        }

        return $next($request);
    }
}
