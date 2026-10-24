<?php

namespace App\Http\Middleware;

use App\Services\AppConfigService;
use App\Support\ApiError;
use App\Support\AppConfigContexto;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueia a rota quando a funcionalidade está desligada na configuração remota do app
 * (vale também para app desatualizado ou offline com configuração antiga em cache).
 * Uso: ->middleware('funcionalidade:checkin')
 */
class FuncionalidadeAtiva
{
    public function __construct(
        private readonly AppConfigService $config,
    ) {}

    public function handle(Request $request, Closure $next, string $funcionalidade): Response
    {
        if (! $this->config->funcionalidadeAtiva($funcionalidade, AppConfigContexto::daRequisicao($request))) {
            return ApiError::json('Esta função está indisponível no momento. Tente mais tarde ou fale com a academia.', 'FUNCIONALIDADE_DESATIVADA', 403, extra: ['funcionalidade' => $funcionalidade]);
        }

        return $next($request);
    }
}
