<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\Mobile\MobileCheckinAcessoService;
use App\Support\AppConfigContexto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileCheckinAcessoController extends Controller
{
    public function __invoke(Request $request, MobileCheckinAcessoService $service): JsonResponse
    {
        $ctx = AppConfigContexto::daRequisicao($request);

        return response()->json($service->consultar((int) $ctx['usuario_id'], $ctx['tenant_id']))
            ->header('Cache-Control', 'private, no-store');
    }
}
