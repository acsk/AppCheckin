<?php

namespace App\Http\Controllers\Api\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\MonetizacaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonetizacaoController extends Controller
{
    public function index(Request $request, string $provedor, MonetizacaoService $service): JsonResponse
    {
        return response()->json($service->listar($provedor, (string) $request->query('ambiente', 'development')));
    }

    public function update(Request $request, string $provedor, int $id, MonetizacaoService $service): JsonResponse
    {
        return response()->json($service->salvar($provedor, $id, $request->all(), $request->attributes->get('userId')));
    }
}
