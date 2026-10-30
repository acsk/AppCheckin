<?php

namespace App\Http\Controllers\Api\V2\Admin;

use App\Http\Controllers\Controller;
use App\Services\Bi\BiVisaoGeralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** BI da academia (Inteligência de negócios). Permissão `bi.ver` via App\Support\PermissaoRotas. */
class BiController extends Controller
{
    public function __construct(
        private readonly BiVisaoGeralService $visaoGeral,
    ) {}

    public function visaoGeral(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('tenantId');
        $result = $this->visaoGeral->visaoGeral($tenantId ? (int) $tenantId : null, $request->query('periodo'));

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function detalhe(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('tenantId');
        $result = $this->visaoGeral->detalhe($tenantId ? (int) $tenantId : null, $request->query('indicador'), $request->query('periodo'));

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }
}
