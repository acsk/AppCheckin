<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\Recorrencia\MobileRecorrenciaService;
use App\Services\Recorrencia\RecorrenciaService;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** App → planos com fidelização (assinatura mensal) e "Minha assinatura". */
class MobileRecorrenciaController extends Controller
{
    public function __construct(
        private readonly MobileRecorrenciaService $service,
        private readonly RecorrenciaService $recorrencia,
    ) {}

    public function ofertas(Request $request): JsonResponse
    {
        return $this->comTenant($request, fn (int $t) => $this->service->ofertas($t, (int) $request->query('plano_id', 0)));
    }

    public function taxaCartao(Request $request): JsonResponse
    {
        return $this->comTenant($request, fn (int $t) => $this->service->taxaCartao($t, (int) $request->query('plano_ciclo_id', 0)));
    }

    public function contratar(Request $request): JsonResponse
    {
        return $this->comTenant($request, fn (int $t) => $this->recorrencia->contratar($t, $this->userId($request),
            $request->only(['plano_ciclo_id', 'metodo', 'termos_hash']), $this->origem($request)));
    }

    public function contratos(Request $request): JsonResponse
    {
        return $this->comTenant($request, fn (int $t) => $this->service->contratos($t, $this->userId($request)));
    }

    public function simular(Request $request, int $id): JsonResponse
    {
        return $this->comTenant($request, fn (int $t) => $this->service->simular($t, $this->userId($request), $id));
    }

    public function cancelar(Request $request, int $id): JsonResponse
    {
        return $this->comTenant($request, fn (int $t) => $this->service->cancelar($t, $this->userId($request), $id, $request->input('motivo')));
    }

    public function pagamento(Request $request, int $id): JsonResponse
    {
        return $this->comTenant($request, fn (int $t) => $this->service->pagamento($t, $this->userId($request), $id));
    }

    public function aceitarRenovacao(Request $request, int $id): JsonResponse
    {
        return $this->comTenant($request, fn (int $t) => $this->recorrencia->aceitarNovaFidelizacao($t, $this->userId($request), $id,
            (string) $request->input('termos_hash', ''), $this->origem($request)));
    }

    /** @return array{ip: ?string, user_agent: ?string} evidência do aceite */
    private function origem(Request $request): array
    {
        return ['ip' => $request->ip(), 'user_agent' => (string) $request->userAgent()];
    }

    private function userId(Request $request): int
    {
        return (int) $request->attributes->get('userId', 0);
    }

    /** @param  callable(int): array{status: int, body: array<string, mixed>}  $fn */
    private function comTenant(Request $request, callable $fn): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('tenantId', 0);
        if (! $tenantId) {
            return ApiError::json('Tenant não selecionado', 'MISSING_TENANT', 400);
        }
        $r = $fn($tenantId);

        return response()->json($r['body'], $r['status'], [], JSON_UNESCAPED_UNICODE);
    }
}
