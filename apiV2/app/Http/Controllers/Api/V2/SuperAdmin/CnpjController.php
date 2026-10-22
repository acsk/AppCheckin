<?php

namespace App\Http\Controllers\Api\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\EntidadeJuridica\EntidadeJuridicaException;
use App\Services\EntidadeJuridica\EntidadeJuridicaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Super Admin: política de CNPJ duplicado, exceções e gestor cadastral de cada CNPJ. */
class CnpjController extends Controller
{
    public function __construct(
        private readonly EntidadeJuridicaService $service,
    ) {}

    public function politica(): JsonResponse
    {
        return $this->ok(['politica' => $this->service->politica()]);
    }

    public function definirPolitica(Request $request): JsonResponse
    {
        return $this->executar(fn () => ['politica' => $this->service->definirPolitica(
            (string) $request->input('politica', ''), $this->userId($request), $request->ip(),
        ), 'message' => 'Política atualizada']);
    }

    public function index(Request $request): JsonResponse
    {
        return $this->ok([
            'politica' => $this->service->politica(),
            'cnpjs' => $this->service->listar(
                (string) $request->query('busca', ''),
                filter_var($request->query('compartilhados', false), FILTER_VALIDATE_BOOLEAN),
            ),
        ]);
    }

    public function show(string $cnpj): JsonResponse
    {
        return $this->executar(fn () => $this->service->detalhe($cnpj));
    }

    public function update(Request $request, string $cnpj): JsonResponse
    {
        return $this->executar(fn () => $this->service->atualizarPeloSuperAdmin($cnpj, $request->all(), $this->userId($request), $request->ip())
            + ['message' => 'Dados do CNPJ atualizados']);
    }

    public function autorizarExcecao(Request $request, string $cnpj): JsonResponse
    {
        return $this->executar(fn () => $this->service->autorizarExcecao($cnpj, $request->input('justificativa'), $this->userId($request), $request->ip())
            + ['message' => 'Compartilhamento autorizado'], 201);
    }

    public function revogarExcecao(Request $request, int $id): JsonResponse
    {
        return $this->executar(fn () => $this->service->revogarExcecao($id, $request->input('motivo'), $this->userId($request), $request->ip())
            + ['message' => 'Exceção revogada. Os vínculos atuais foram mantidos.']);
    }

    public function definirGestor(Request $request, string $cnpj): JsonResponse
    {
        return $this->executar(fn () => $this->service->definirGestor(
            $cnpj, (int) $request->input('tenant_id', 0), $request->input('justificativa'), $this->userId($request), $request->ip(),
        ) + ['message' => 'Gestor cadastral definido']);
    }

    /** @param  callable(): array<string, mixed>  $acao */
    private function executar(callable $acao, int $status = 200): JsonResponse
    {
        try {
            return $this->ok($acao(), $status);
        } catch (EntidadeJuridicaException $e) {
            $r = $e->toResult();

            return response()->json($r['body'], $r['status'], [], JSON_UNESCAPED_UNICODE);
        }
    }

    /** @param  array<string, mixed>  $body */
    private function ok(array $body, int $status = 200): JsonResponse
    {
        return response()->json($body, $status, [], JSON_UNESCAPED_UNICODE);
    }

    private function userId(Request $request): ?int
    {
        return ((int) $request->attributes->get('userId', 0)) ?: null;
    }
}
