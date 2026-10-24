<?php

namespace App\Http\Controllers\Api\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\AppConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Super Admin → Configurações do app: rascunho, publicação, bloqueio emergencial, histórico e rollback. */
class AppConfigController extends Controller
{
    public function __construct(
        private readonly AppConfigService $service,
    ) {}

    public function index(): JsonResponse
    {
        return $this->json($this->service->estado());
    }

    public function definir(Request $request): JsonResponse
    {
        $tenantId = $request->input('tenant_id');

        return $this->json($this->service->definir(
            $tenantId === null || $tenantId === '' ? null : (int) $tenantId,
            (string) $request->input('chave', ''),
            $request->input('valor'),
            $this->userId($request),
        ));
    }

    public function publicar(Request $request): JsonResponse
    {
        return $this->json($this->service->publicar($request->input('nota'), $this->userId($request)));
    }

    public function descartar(Request $request): JsonResponse
    {
        return $this->json($this->service->descartar($this->userId($request)));
    }

    public function bloquear(Request $request, string $funcionalidade): JsonResponse
    {
        return $this->json($this->service->bloquear(
            $funcionalidade,
            filter_var($request->input('bloquear', true), FILTER_VALIDATE_BOOLEAN),
            $request->input('nota'),
            $this->userId($request),
        ));
    }

    public function historico(): JsonResponse
    {
        return $this->json($this->service->historico());
    }

    public function restaurar(Request $request, int $versao): JsonResponse
    {
        return $this->json($this->service->restaurar($versao, $this->userId($request)));
    }

    public function efetiva(Request $request): JsonResponse
    {
        $tenantId = (int) $request->query('tenant_id', 0);

        return $this->json($this->service->efetiva(
            $tenantId ?: null,
            (string) $request->query('plataforma', 'android'),
            ($v = ltrim(trim((string) $request->query('versao', '')), 'vV')) !== '' ? $v : null,
        ));
    }

    /** @param  array{status: int, body: array<string, mixed>}  $result */
    private function json(array $result): JsonResponse
    {
        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    private function userId(Request $request): ?int
    {
        return ((int) $request->attributes->get('userId', 0)) ?: null;
    }
}
