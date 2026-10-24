<?php

namespace App\Support;

use App\Services\AppConfigService;
use Illuminate\Http\Request;

/** Contexto da configuração remota a partir da requisição: academia e usuário do JWT, plataforma e versão do app. */
final class AppConfigContexto
{
    /** @return array{tenant_id: int|null, usuario_id: int|null, plataforma: string, versao_app: string|null} */
    public static function daRequisicao(Request $request): array
    {
        $versao = ltrim(trim((string) $request->header('X-App-Version', '')), 'vV');

        return [
            'tenant_id' => ((int) $request->attributes->get('tenantId', 0)) ?: null,
            'usuario_id' => ((int) $request->attributes->get('userId', 0)) ?: null,
            'plataforma' => AppConfigService::plataforma($request->header('X-App-Platform')),
            'versao_app' => preg_match('/^\d{1,4}(\.\d{1,4}){0,2}$/', $versao) ? $versao : null,
        ];
    }
}
