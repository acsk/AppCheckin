<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\AppConfigService;
use App\Support\AppConfigContexto;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Configuração remota do app. /v2/app-config (público: só o global, para antes do login) e
 * /v2/mobile/app-config (com a academia do JWT). ETag + If-None-Match evitam reenviar o mesmo conteúdo.
 */
class AppConfigController extends Controller
{
    public function __construct(
        private readonly AppConfigService $service,
    ) {}

    public function publico(Request $request): Response
    {
        return $this->responder($request, ['tenant_id' => null, 'usuario_id' => null] + AppConfigContexto::daRequisicao($request));
    }

    public function mobile(Request $request): Response
    {
        return $this->responder($request, AppConfigContexto::daRequisicao($request));
    }

    private function responder(Request $request, array $ctx): Response
    {
        $body = $this->service->contrato($ctx);
        $etag = '"'.sha1(json_encode($body)).'"';
        $headers = ['ETag' => $etag, 'Cache-Control' => 'private, no-cache', 'Vary' => 'Authorization, X-App-Platform, X-App-Version'];

        if (trim((string) $request->header('If-None-Match')) === $etag) {
            return response('', 304, $headers);
        }

        return response()->json($body, 200, $headers, JSON_UNESCAPED_UNICODE);
    }
}
