<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\Mobile\MobileCheckinService;
use App\Services\Mobile\MobileHorariosService;
use App\Support\MobileResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MobileController extends Controller
{
    public function __construct(
        private readonly MobileHorariosService $horarios,
        private readonly MobileCheckinService $checkin,
    ) {}

    public function horariosDisponiveis(Request $request): JsonResponse
    {
        try {
            $result = $this->horarios->horariosDisponiveis(
                $this->tenantId($request),
                $this->userId($request),
                $request->query('data', date('Y-m-d')),
            );

            return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Log::error('horariosDisponiveis v2: '.$e->getMessage());

            return MobileResponse::serverError(
                'Erro ao carregar horários disponíveis',
                $e->getMessage(),
            );
        }
    }

    public function registrarCheckin(Request $request): JsonResponse
    {
        try {
            $result = $this->checkin->registrar(
                $this->userId($request),
                $this->tenantId($request),
                $request->all(),
                $request->attributes->get('aluno_id') !== null
                    ? (int) $request->attributes->get('aluno_id')
                    : null,
            );

            return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Log::error('registrarCheckin v2: '.$e->getMessage());

            return MobileResponse::serverError(
                'Erro ao registrar check-in',
                $e->getMessage(),
            );
        }
    }

    public function desfazerCheckin(Request $request, int $checkinId): JsonResponse
    {
        try {
            $result = $this->checkin->desfazer(
                $this->userId($request),
                $this->tenantId($request),
                $checkinId,
            );

            return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Log::error('desfazerCheckin v2: '.$e->getMessage());

            return MobileResponse::serverError(
                'Erro ao desfazer check-in',
                $e->getMessage(),
            );
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
