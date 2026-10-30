<?php

namespace App\Http\Controllers\Api\V2\Admin;

use App\Http\Controllers\Controller;
use App\Services\Bi\BiAlunosService;
use App\Services\Bi\BiFinanceiroService;
use App\Services\Bi\BiFrequenciaService;
use App\Services\Bi\BiVisaoGeralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** BI da academia (Inteligência de negócios). Permissão `bi.ver` via App\Support\PermissaoRotas. */
class BiController extends Controller
{
    public function __construct(
        private readonly BiVisaoGeralService $visaoGeral,
        private readonly BiFinanceiroService $financeiro,
        private readonly BiAlunosService $alunos,
        private readonly BiFrequenciaService $frequencia,
    ) {}

    public function frequencia(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('tenantId');
        $result = $this->frequencia->frequencia($tenantId ? (int) $tenantId : null, $request->query('periodo'));

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function alunos(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('tenantId');
        $result = $this->alunos->alunos($tenantId ? (int) $tenantId : null, $request->query('periodo'));

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function financeiro(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('tenantId');
        $result = $this->financeiro->financeiro($tenantId ? (int) $tenantId : null, $request->query('periodo'));

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

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
