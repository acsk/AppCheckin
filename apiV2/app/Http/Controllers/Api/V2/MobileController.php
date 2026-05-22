<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\Mobile\MobileAssinaturaService;
use App\Services\Mobile\MobileCheckinService;
use App\Services\Mobile\MobileCompraPlanoService;
use App\Services\Mobile\MobileHistoricoService;
use App\Services\Mobile\MobileHorariosService;
use App\Services\Mobile\MobileMatriculaService;
use App\Services\Mobile\MobilePagamentoService;
use App\Services\Mobile\MobilePerfilService;
use App\Services\Mobile\MobilePlanoService;
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
        private readonly MobilePlanoService $planos,
        private readonly MobileMatriculaService $matriculas,
        private readonly MobileCompraPlanoService $compra,
        private readonly MobilePagamentoService $pagamento,
        private readonly MobileAssinaturaService $assinaturas,
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

    public function planosDisponiveis(Request $request): JsonResponse
    {
        try {
            $modalidade = $request->query('modalidade_id');
            $result = $this->planos->planosDisponiveis(
                $this->userId($request),
                $this->tenantId($request),
                $modalidade !== null && $modalidade !== '' ? (int) $modalidade : null,
            );

            return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Log::error('planosDisponiveis v2: '.$e->getMessage());

            return MobileResponse::serverError('Erro ao buscar planos disponíveis', $e->getMessage());
        }
    }

    public function detalhePlano(Request $request, int $planoId): JsonResponse
    {
        try {
            $result = $this->planos->detalhePlano(
                $this->userId($request),
                $this->tenantId($request),
                $planoId,
            );

            return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Log::error('detalhePlano v2: '.$e->getMessage());

            return MobileResponse::serverError('Erro ao buscar detalhes do plano', $e->getMessage());
        }
    }

    public function planosDoUsuario(Request $request): JsonResponse
    {
        try {
            $todas = $request->query('todas') === 'true';
            $result = $this->planos->planosDoUsuario(
                $this->userId($request),
                $this->tenantId($request),
                $todas,
            );

            return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Log::error('planosDoUsuario v2: '.$e->getMessage());

            return MobileResponse::serverError('Erro ao carregar matrículas', $e->getMessage());
        }
    }

    public function detalheMatricula(Request $request, int $matriculaId): JsonResponse
    {
        try {
            $result = $this->matriculas->detalhe(
                $this->userId($request),
                $this->tenantId($request),
                $matriculaId,
            );

            return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Log::error('detalheMatricula v2: '.$e->getMessage());

            return MobileResponse::serverError('Erro ao carregar detalhes da matrícula', $e->getMessage());
        }
    }

    public function comprarPlano(Request $request): JsonResponse
    {
        try {
            $result = $this->compra->comprar(
                $this->userId($request),
                $this->tenantId($request),
                $request->all(),
            );

            return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Log::error('comprarPlano v2: '.$e->getMessage());

            return MobileResponse::serverError('Erro ao processar compra', $e->getMessage());
        }
    }

    public function gerarPagamentoPix(Request $request): JsonResponse
    {
        try {
            $result = $this->pagamento->gerarPix(
                $this->userId($request),
                $this->tenantId($request),
                $request->all(),
            );

            return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Log::error('gerarPagamentoPix v2: '.$e->getMessage());

            return MobileResponse::serverError('Erro ao gerar PIX', $e->getMessage());
        }
    }

    public function verificarPagamento(Request $request): JsonResponse
    {
        try {
            $result = $this->matriculas->verificarPagamento(
                $this->userId($request),
                $this->tenantId($request),
                $request->all(),
            );

            return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Log::error('verificarPagamento v2: '.$e->getMessage());

            return MobileResponse::serverError('Erro ao verificar pagamento', $e->getMessage());
        }
    }

    public function reabrirPagamentoPendente(Request $request, int $matriculaId): JsonResponse
    {
        try {
            $result = $this->matriculas->reabrirPagamento(
                $this->userId($request),
                (int) $this->tenantId($request),
                $matriculaId,
            );

            return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Log::error('reabrirPagamentoPendente v2: '.$e->getMessage());

            return MobileResponse::serverError('Erro ao reabrir pagamento', $e->getMessage());
        }
    }

    public function minhasAssinaturas(Request $request): JsonResponse
    {
        try {
            $alunoId = $request->attributes->get('aluno_id');
            $result = $this->assinaturas->minhasAssinaturas(
                $this->userId($request),
                $this->tenantId($request),
                $alunoId !== null ? (int) $alunoId : null,
            );

            return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Log::error('minhasAssinaturas v2: '.$e->getMessage());

            return MobileResponse::serverError('Erro ao listar assinaturas', $e->getMessage());
        }
    }

    public function assinaturasAprovadasHoje(Request $request): JsonResponse
    {
        try {
            $result = $this->assinaturas->aprovadasHoje(
                $this->userId($request),
                (int) $this->tenantId($request),
                (int) $request->query('matricula_id', 0),
            );

            return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Log::error('assinaturasAprovadasHoje v2: '.$e->getMessage());

            return MobileResponse::serverError('Erro ao verificar assinatura', $e->getMessage());
        }
    }

    public function cancelarAssinatura(Request $request, int $id): JsonResponse
    {
        try {
            $result = $this->assinaturas->cancelar(
                $this->userId($request),
                (int) $this->tenantId($request),
                $id,
                $request->all(),
            );

            return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Log::error('cancelarAssinatura v2: '.$e->getMessage());

            return MobileResponse::serverError('Erro ao cancelar assinatura', $e->getMessage());
        }
    }

    public function cancelarDiaria(Request $request, int $matriculaId): JsonResponse
    {
        try {
            $result = $this->matriculas->cancelarDiaria(
                $this->userId($request),
                (int) $this->tenantId($request),
                $matriculaId,
            );

            return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Log::error('cancelarDiaria v2: '.$e->getMessage());

            return MobileResponse::serverError('Erro ao cancelar diária', $e->getMessage());
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
