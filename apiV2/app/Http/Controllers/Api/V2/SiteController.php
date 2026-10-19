<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\Site\CheckoutService;
use App\Services\SuperAdmin\CobrancaContratoService;
use App\Services\SuperAdmin\SuperAdminPlanoSistemaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Rotas públicas do site institucional: planos à venda e assinatura. */
class SiteController extends Controller
{
    public function planos(SuperAdminPlanoSistemaService $planos, CobrancaContratoService $cobrancas): JsonResponse
    {
        $result = $planos->publicos();
        $result['body']['formas_pagamento'] = $cobrancas->formasHabilitadas();

        return response()->json($result['body'], $result['status'], ['Cache-Control' => 'public, max-age=300'], JSON_UNESCAPED_UNICODE);
    }

    public function assinar(Request $request, CheckoutService $checkout): JsonResponse
    {
        $result = $checkout->assinar($request->all(), $this->clientIp($request));

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    private function clientIp(Request $request): string
    {
        foreach (['CF-Connecting-IP', 'X-Real-IP', 'X-Forwarded-For'] as $header) {
            $ip = trim(explode(',', (string) $request->header($header, ''))[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return (string) $request->ip();
    }
}
