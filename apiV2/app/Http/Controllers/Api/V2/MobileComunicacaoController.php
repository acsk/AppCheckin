<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V2\Admin\ComunicacaoController;
use App\Services\Comunicacao\MobileComunicacaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** App → Central de Comunicação do aluno (sino, comunicados obrigatórios e enquetes). */
class MobileComunicacaoController extends Controller
{
    public function __construct(
        private readonly MobileComunicacaoService $service,
    ) {}

    public function resumo(Request $request): JsonResponse
    {
        return $this->json($this->service->resumo($this->userId($request), $this->tenantId($request)));
    }

    public function index(Request $request): JsonResponse
    {
        return $this->json($this->service->listar($this->userId($request), $this->tenantId($request), (string) $request->query('filtro', 'todas'), (int) $request->query('pagina', 1)));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->json($this->service->detalhe($this->userId($request), $this->tenantId($request), $id));
    }

    public function confirmar(Request $request, int $id): JsonResponse
    {
        return $this->json($this->service->confirmarLeitura($this->userId($request), $this->tenantId($request), $id));
    }

    public function responder(Request $request, int $id): JsonResponse
    {
        return $this->json($this->service->responderEnquete($this->userId($request), $this->tenantId($request), $id, $request->input('respostas', [])));
    }

    /** Anexos (nome aleatório de 32 caracteres; só imagem e PDF). */
    public static function anexo(int $tenantId, string $arquivo): Response
    {
        $caminho = ComunicacaoController::dirAnexos($tenantId).'/'.$arquivo;
        if (! preg_match('/^[A-Za-z0-9]{32}\.(jpg|png|webp|gif|pdf)$/', $arquivo) || ! is_file($caminho)) {
            return response('', 404);
        }

        return response((string) file_get_contents($caminho), 200, [
            'Content-Type' => mime_content_type($caminho) ?: 'application/octet-stream',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    private function userId(Request $request): int
    {
        return (int) $request->attributes->get('userId', 0);
    }

    private function tenantId(Request $request): ?int
    {
        return ((int) $request->attributes->get('tenantId', 0)) ?: null;
    }

    /** @param  array{status: int, body: array<string, mixed>}  $r */
    private function json(array $r): JsonResponse
    {
        return response()->json($r['body'], $r['status'], [], JSON_UNESCAPED_UNICODE);
    }
}
