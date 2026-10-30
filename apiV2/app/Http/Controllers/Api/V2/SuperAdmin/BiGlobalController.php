<?php

namespace App\Http\Controllers\Api\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\Bi\BiExportacaoService;
use App\Services\Bi\BiGlobalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** BI Global: todas as academias somadas e a receita da plataforma (só superadmin). */
class BiGlobalController extends Controller
{
    public function __construct(
        private readonly BiGlobalService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->service->global($request->query('periodo'));

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    /** CSV do ranking de academias no período */
    public function exportar(Request $request): JsonResponse|Response
    {
        $result = $this->service->global($request->query('periodo'));
        if ($result['status'] !== 200) {
            return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
        }
        $p = $result['body']['data']['periodo'];

        $colunas = ['tenant_id', 'nome', 'ativa', 'alunos_ativos', 'alunos_ativos_anterior', 'novos_alunos', 'saidas', 'checkins', 'receita_recebida', 'estornos', 'valor_vencido'];

        return response(BiExportacaoService::csv($result['body']['data']['academias'], $colunas), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="bi-global-academias-'.$p['inicio'].'-a-'.$p['fim'].'.csv"',
            'Cache-Control' => 'no-store',
        ]);
    }
}
