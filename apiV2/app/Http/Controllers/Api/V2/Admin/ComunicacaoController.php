<?php

namespace App\Http\Controllers\Api\V2\Admin;

use App\Http\Controllers\Controller;
use App\Services\Comunicacao\ComunicacaoRelatorioService;
use App\Services\Comunicacao\ComunicacaoService;
use App\Services\PermissaoService;
use App\Support\ApiError;
use App\Support\Permissao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Painel → Comunicação → Central de Comunicação. Permissões por rota em App\Support\PermissaoRotas. */
class ComunicacaoController extends Controller
{
    private const ANEXO_MAX_BYTES = 10 * 1024 * 1024;

    private const ANEXO_TIPOS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'application/pdf' => 'pdf'];

    public function __construct(
        private readonly ComunicacaoService $service,
        private readonly ComunicacaoRelatorioService $relatorios,
        private readonly PermissaoService $permissoes,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->comTenant($request, fn (int $t) => $this->service->listarComunicacoes($t, $request->query()));
    }

    public function opcoes(Request $request): JsonResponse
    {
        return $this->comTenant($request, fn (int $t) => $this->service->opcoes($t));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->comTenant($request, fn (int $t) => $this->service->obter($t, $id));
    }

    public function store(Request $request): JsonResponse
    {
        return $this->comTenant($request, function (int $t) use ($request) {
            $data = $request->all();
            // Salvar e publicar de uma vez exige também a permissão de publicar
            if (! empty($data['publicar']) && ! $this->pode($request, $t, Permissao::COMUNICACOES_PUBLICAR)) {
                return ['status' => 403, 'body' => ['success' => false, 'error' => 'Você pode salvar o rascunho, mas não tem a permissão "Publicar comunicações".', 'code' => 'PERMISSAO_NEGADA']];
            }

            return $this->service->criarComunicacao($t, $data, $this->userId($request));
        });
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return $this->comTenant($request, fn (int $t) => $this->service->atualizarComunicacao($t, $id, $request->all(), $this->userId($request)));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        return $this->comTenant($request, fn (int $t) => $this->service->excluir($t, $id, $this->userId($request)));
    }

    public function duplicar(Request $request, int $id): JsonResponse
    {
        return $this->comTenant($request, fn (int $t) => $this->service->duplicar($t, $id, $this->userId($request)));
    }

    public function publicar(Request $request, int $id): JsonResponse
    {
        return $this->comTenant($request, fn (int $t) => $this->service->publicarComunicacao($t, $id, $this->userId($request)));
    }

    public function incluirDestinatarios(Request $request, int $id): JsonResponse
    {
        return $this->comTenant($request, fn (int $t) => $this->service->incluirNovosDestinatarios($t, $id, $this->userId($request)));
    }

    public function encerrar(Request $request, int $id): JsonResponse
    {
        return $this->comTenant($request, fn (int $t) => $this->service->encerrarComunicacao($t, $id, $this->userId($request)));
    }

    public function cancelar(Request $request, int $id): JsonResponse
    {
        return $this->comTenant($request, fn (int $t) => $this->service->cancelarComunicacao($t, $id, $this->userId($request)));
    }

    public function previaPublico(Request $request): JsonResponse
    {
        return $this->comTenant($request, fn (int $t) => $this->service->previaPublico($t, $request->input('publico')));
    }

    public function indicadores(Request $request): JsonResponse
    {
        return $this->comTenant($request, fn (int $t) => $this->relatorios->indicadores($t, $request->query('desde'), $request->query('ate')));
    }

    public function destinatarios(Request $request, int $id): JsonResponse
    {
        return $this->comTenant($request, fn (int $t) => $this->relatorios->destinatarios(
            $t, $id, (string) $request->query('situacao', 'todos'), (string) $request->query('busca', ''),
            (int) $request->query('pagina', 1), (int) $request->query('por_pagina', 50),
        ));
    }

    public function resultados(Request $request, int $id): JsonResponse
    {
        return $this->comTenant($request, fn (int $t) => $this->relatorios->resultadosEnquete($t, $id, $this->pode($request, $t, Permissao::COMUNICACOES_RESPOSTAS)));
    }

    public function respostas(Request $request, int $id): JsonResponse
    {
        return $this->comTenant($request, fn (int $t) => $this->relatorios->respostasIndividuais($t, $id));
    }

    /** Imagem ou PDF do comunicado; devolve a URL para gravar em configuracoes. */
    public function anexo(Request $request): JsonResponse
    {
        return $this->comTenant($request, function (int $t) use ($request) {
            $arquivo = $request->file('arquivo');
            if (! $arquivo || ! $arquivo->isValid()) {
                return ['status' => 422, 'body' => ['success' => false, 'error' => 'Envie o arquivo no campo "arquivo".']];
            }
            $mime = (string) $arquivo->getMimeType();
            if (! isset(self::ANEXO_TIPOS[$mime])) {
                return ['status' => 422, 'body' => ['success' => false, 'error' => 'Formato não aceito. Use JPG, PNG, WEBP, GIF ou PDF.']];
            }
            if ($arquivo->getSize() > self::ANEXO_MAX_BYTES) {
                return ['status' => 422, 'body' => ['success' => false, 'error' => 'Arquivo acima de 10 MB.']];
            }
            $nome = Str::random(32).'.'.self::ANEXO_TIPOS[$mime];
            $arquivo->move(self::dirAnexos($t), $nome);

            return ['status' => 201, 'body' => ['success' => true, 'anexo' => [
                'nome' => mb_substr((string) $arquivo->getClientOriginalName(), 0, 120) ?: $nome,
                'url' => url("v2/uploads/comunicacoes/{$t}/{$nome}"),
                'mime' => $mime,
            ]]];
        });
    }

    public static function dirAnexos(int $tenantId): string
    {
        return public_path("uploads/comunicacoes/{$tenantId}");
    }

    private function pode(Request $request, int $tenantId, string $permissao): bool
    {
        return $request->attributes->get('acesso_suporte') || $request->attributes->get('is_super_admin')
            || $this->permissoes->tem($tenantId, (int) $request->attributes->get('userId', 0), $permissao);
    }

    private function userId(Request $request): ?int
    {
        return ((int) $request->attributes->get('userId', 0)) ?: null;
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
