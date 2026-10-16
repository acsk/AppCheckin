<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\Asaas\AsaasWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AsaasWebhookController extends Controller
{
    public function handle(Request $request, AsaasWebhookService $service): JsonResponse
    {
        try {
            $result = $service->processar(
                $request->all(),
                $request->header('asaas-access-token'),
            );

            return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Log::error('Asaas webhook: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Erro ao processar webhook Asaas',
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }
}
