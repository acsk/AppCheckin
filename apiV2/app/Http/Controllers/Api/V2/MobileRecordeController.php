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
        if (($request->query('sexo') !== null && ! is_string($request->query('sexo')))
            || filter_var($request->query('limit', 50), FILTER_VALIDATE_INT) === false) {
            return response()->json(['success' => false, 'error' => 'Filtros de ranking inválidos'], 422);
        }
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

    public function prova(Request $request, int $definicaoId): JsonResponse
    {
        return $this->responder('recordes prova v2', 'Erro ao carregar a prova', fn () => $this->recordes->prova($this->userId($request), $this->tenantId($request), $definicaoId));
    }

    public function criar(Request $request): JsonResponse
    {
        return $this->responder('recordes criar v2', 'Erro ao registrar a marca', fn () => $this->recordes->criar($this->userId($request), $this->tenantId($request), $request->all()));
    }

    public function atualizar(Request $request, int $id): JsonResponse
    {
        return $this->responder('recordes atualizar v2', 'Erro ao atualizar a marca', fn () => $this->recordes->atualizar($this->userId($request), $this->tenantId($request), $id, $request->all()));
    }

    public function excluir(Request $request, int $id): JsonResponse
    {
        return $this->responder('recordes excluir v2', 'Erro ao excluir a marca', fn () => $this->recordes->excluir($this->userId($request), $this->tenantId($request), $id));
    }

    /**
     * @param  callable(): array{status: int, body: array<string, mixed>}  $acao
     */
    private function responder(string $contexto, string $mensagem, callable $acao): JsonResponse
    {
        try {
            $result = $acao();

            return response()->json($result['body'], $result['status'], [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Log::error($contexto.': '.$e->getMessage());

            return MobileResponse::serverError($mensagem, $e->getMessage());
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
