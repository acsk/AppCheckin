<?php

namespace App\Http\Controllers\Api\V2\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminAuditoriaService;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditoriaController extends Controller
{
    public function __construct(
        private readonly AdminAuditoriaService $service,
    ) {}

    public function pagamentosDuplicados(Request $request): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $result = $this->service->pagamentosDuplicados($tenantId);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function pagamentosDuplicadosDetalhe(Request $request): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $result = $this->service->pagamentosDuplicadosDetalhe(
            $tenantId,
            ! empty($request->query('aluno_id')) ? (int) $request->query('aluno_id') : null,
            ! empty($request->query('matricula_id')) ? (int) $request->query('matricula_id') : null,
            ! empty($request->query('ano')) ? (int) $request->query('ano') : null,
            ! empty($request->query('mes')) ? (int) $request->query('mes') : null,
        );

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function anomaliasDatas(Request $request): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $result = $this->service->anomaliasDatas($tenantId);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function repararProximaDataVencimento(Request $request): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $dryRun = $request->query->has('dry-run') || $request->query->has('dry_run');
        $result = $this->service->repararProximaDataVencimento($tenantId, $dryRun);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function checkinsAcimaDoLimite(Request $request): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $ano = ! empty($request->query('ano')) ? (int) $request->query('ano') : (int) date('Y');
        $mes = ! empty($request->query('mes')) ? (int) $request->query('mes') : (int) date('m');
        $result = $this->service->checkinsAcimaDoLimite($tenantId, $ano, $mes);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function checkinsMultiplosNoDia(Request $request): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $dataInicio = ! empty($request->query('data_inicio')) ? (string) $request->query('data_inicio') : date('Y-m-01');
        $dataFim = ! empty($request->query('data_fim')) ? (string) $request->query('data_fim') : date('Y-m-d');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicio)) {
            $dataInicio = date('Y-m-01');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim)) {
            $dataFim = date('Y-m-d');
        }

        $result = $this->service->checkinsMultiplosNoDia(
            $tenantId,
            $dataInicio,
            $dataFim,
            ! empty($request->query('aluno_id')) ? (int) $request->query('aluno_id') : null,
            ! empty($request->query('modalidade_id')) ? (int) $request->query('modalidade_id') : null,
            $request->query('mesma_modalidade') === '1',
        );

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function creditoMigracaoPlano(Request $request): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $result = $this->service->creditoMigracaoPlano($tenantId);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    public function repararVencimentoMatricula(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->requireTenant($request);
        if ($tenantId instanceof JsonResponse) {
            return $tenantId;
        }

        $result = $this->service->repararVencimentoMatricula($tenantId, $id);

        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    private function requireTenant(Request $request): int|JsonResponse
    {
        $tenantId = $request->attributes->get('tenantId')
            ?? $request->attributes->get('tenant_id');
        if (! $tenantId) {
            return ApiError::json('Tenant não selecionado', 'MISSING_TENANT', 400);
        }

        return (int) $tenantId;
    }
}
