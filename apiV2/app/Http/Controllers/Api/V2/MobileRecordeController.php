<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\Mobile\MobileRecordeService;
use App\Support\MobileResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MobileRecordeController extends Controller
{
    public function __construct(
        private readonly MobileRecordeService $recordes,
    ) {}

    public function resumo(Request $request): JsonResponse
    {
        try {
            $result = $this->recordes->resumo($this->userId($request), $this->tenantId($request));

            return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Log::error('recordes resumo v2: '.$e->getMessage());

            return MobileResponse::serverError('Erro ao carregar recordes', $e->getMessage());
        }
    }

    public function ranking(Request $request, int $definicaoId): JsonResponse
    {
        try {
            $result = $this->recordes->ranking(
                $this->userId($request),
                $this->tenantId($request),
                $definicaoId,
                $request->query('sexo'),
                min(100, max(1, (int) $request->query('limit', 50))),
            );

            return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Log::error('recordes ranking v2: '.$e->getMessage());

            return MobileResponse::serverError('Erro ao carregar ranking', $e->getMessage());
        }
    }

    private function userId(Request $request): int
    {
        return (int) $request->attributes->get('userId');
    }

    private function tenantId(Request $request): ?int
    {
        $tenantId = $request->attributes->get('tenantId');

        return $tenantId ? (int) $tenantId : null;
    }
}
