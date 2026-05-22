<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\Mobile\MobileCheckinService;
use App\Services\Mobile\MobileHistoricoService;
use App\Services\Mobile\MobileHorariosService;
use App\Services\Mobile\MobilePerfilService;
use App\Services\Mobile\MobileWodService;
use App\Support\AcademyDateTime;
use App\Support\MobileResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MobileController extends Controller
{
    public function __construct(
        private readonly MobileHorariosService $horarios,
        private readonly MobileCheckinService $checkin,
        private readonly MobilePerfilService $perfil,
        private readonly MobileHistoricoService $historico,
        private readonly MobileWodService $wod,
    ) {}

    public function historicoCheckins(Request $request): JsonResponse
    {
        try {
            $result = $this->historico->historicoCheckins(
                $this->userId($request),
                (int) $request->query('limit', 30),
                (int) $request->query('offset', 0),
            );

            return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Log::error('historicoCheckins v2: '.$e->getMessage());

            return MobileResponse::serverError('Erro ao carregar histórico de check-ins', $e->getMessage());
        }
    }

    public function checkinsPorModalidade(Request $request): JsonResponse
    {
        try {
            $result = $this->historico->checkinsPorModalidade(
                $this->userId($request),
                $this->tenantId($request),
                $request->query('data_referencia'),
                (int) $request->query('offset', 0),
            );

            return $this->jsonWithOptionalHeaders($result);
        } catch (\Throwable $e) {
            Log::error('checkinsPorModalidade v2: '.$e->getMessage());

            return MobileResponse::serverError('Erro ao carregar check-ins por modalidade', $e->getMessage());
        }
    }

    public function rankingMensal(Request $request): JsonResponse
    {
        try {
            $modalidadeId = $request->query('modalidade_id');
            $result = $this->historico->rankingMensal(
                $this->tenantId($request),
                $modalidadeId !== null && $modalidadeId !== '' ? (int) $modalidadeId : null,
            );

            return $this->jsonWithOptionalHeaders($result);
        } catch (\Throwable $e) {
            Log::error('rankingMensal v2: '.$e->getMessage());

            return MobileResponse::serverError('Erro ao carregar ranking', $e->getMessage());
        }
    }

    public function wodHoje(Request $request): JsonResponse
    {
        try {
            $modalidadeQuery = $request->query('modalidade_id');
            $result = $this->wod->wodDoDia(
                $this->userId($request),
                $this->tenantId($request),
                $request->query('data'),
                $modalidadeQuery !== null && $modalidadeQuery !== ''
                    ? (int) $modalidadeQuery
                    : null,
                $modalidadeQuery !== null && $modalidadeQuery !== '',
            );

            return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Log::error('wodHoje v2: '.$e->getMessage());

            return MobileResponse::serverError('Erro ao carregar WOD', $e->getMessage());
        }
    }

    public function wodsHoje(Request $request): JsonResponse
    {
        try {
            $result = $this->wod->wodsDoDia(
                $this->tenantId($request),
                $request->query('data'),
            );

            return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Log::error('wodsHoje v2: '.$e->getMessage());

            return MobileResponse::serverError('Erro ao carregar WODs', $e->getMessage());
        }
    }

    public function perfil(Request $request): JsonResponse
    {
        try {
            $result = $this->perfil->perfil($this->userId($request), $this->tenantId($request));
            $response = response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);

            foreach ($result['headers'] ?? [] as $name => $value) {
                $response->headers->set($name, $value);
            }

            return $response;
        } catch (\Throwable $e) {
            Log::error('perfil v2: '.$e->getMessage());

            return MobileResponse::serverError('Erro ao carregar perfil', $e->getMessage());
        }
    }

    public function verificarAcesso(Request $request): JsonResponse
    {
        try {
            $result = $this->perfil->verificarAcesso($this->userId($request), $this->tenantId($request));

            return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Log::error('verificarAcesso v2: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao verificar acesso',
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }

    public function tenants(Request $request): JsonResponse
    {
        try {
            $result = $this->perfil->tenants($this->userId($request));

            return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Log::error('tenants v2: '.$e->getMessage());

            return MobileResponse::serverError('Erro ao listar academias', $e->getMessage());
        }
    }

    public function horariosDisponiveis(Request $request): JsonResponse
    {
        try {
            $result = $this->horarios->horariosDisponiveis(
                $this->tenantId($request),
                $this->userId($request),
                $request->query('data', AcademyDateTime::today()),
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

    /**
     * @param  array{status: int, body: array<string, mixed>, headers?: array<string, string>}  $result
     */
    private function jsonWithOptionalHeaders(array $result): JsonResponse
    {
        $response = response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);

        foreach ($result['headers'] ?? [] as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }
}
