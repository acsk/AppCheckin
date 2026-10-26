<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\Mobile\MobilePacoteVendaService;
use App\Support\PaymentSandboxAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** App: pacotes à venda, contratação pelo pagante, cobrança (PIX/cartão) e renovação. */
class MobilePacoteVendaController extends Controller
{
    public function __construct(
        private readonly MobilePacoteVendaService $service,
    ) {}

    public function disponiveis(Request $request): JsonResponse
    {
        return $this->json($this->service->disponiveis($this->tenantId($request), $this->userId($request)));
    }

    public function contratar(Request $request, int $pacoteId): JsonResponse
    {
        return $this->json($this->service->contratar($this->tenantId($request), $this->userId($request), $pacoteId));
    }

    public function cobrar(Request $request, int $contratoId): JsonResponse
    {
        return $this->json($this->service->cobrar($this->tenantId($request), $this->userId($request), $contratoId, $request->all(), PaymentSandboxAccess::fromRequest($request)));
    }

    public function renovar(Request $request, int $contratoId): JsonResponse
    {
        return $this->json($this->service->renovar($this->tenantId($request), $this->userId($request), $contratoId));
    }

    /** @param  array{status: int, body: array<string, mixed>}  $result */
    private function json(array $result): JsonResponse
    {
        return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
    }

    private function userId(Request $request): int
    {
        return (int) $request->attributes->get('userId');
    }

    private function tenantId(Request $request): ?int
    {
        return ((int) $request->attributes->get('tenantId', 0)) ?: null;
    }
}
