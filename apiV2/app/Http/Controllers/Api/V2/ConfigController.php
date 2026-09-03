<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\ConfigTenantService;
use App\Services\FormaPagamentoCatalogService;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    public function __construct(
        private readonly ConfigTenantService $config,
        private readonly FormaPagamentoCatalogService $formasCatalog,
    ) {}

    public function listarFormasPagamento(): JsonResponse
    {
        $result = $this->config->listarFormasPagamento();

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function listarStatusConta(): JsonResponse
    {
        $result = $this->config->listarStatusConta();

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function listarFormasPagamentoAtivas(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('tenantId');
        if (! $tenantId) {
            return ApiError::json('Tenant não selecionado', 'MISSING_TENANT', 400);
        }

        $result = $this->config->listarFormasPagamentoAtivas((int) $tenantId);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function formasPagamento(Request $request): JsonResponse
    {
        $result = $this->formasCatalog->index($request);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }
}
