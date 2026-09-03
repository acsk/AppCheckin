<?php

namespace App\Http\Controllers\Api\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Support\LaravelLogReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function __construct(
        private readonly LaravelLogReader $logs,
    ) {}

    /**
     * GET /v2/admin/logs
     */
    public function index(Request $request): JsonResponse
    {
        $arquivos = $this->logs->listarArquivos();
        $arquivo = (string) $request->query('arquivo', $arquivos[0]['nome'] ?? 'laravel.log');
        $linhas = (int) $request->query('linhas', LaravelLogReader::DEFAULT_LINHAS);
        $busca = $request->query('busca');
        $nivel = $request->query('nivel');

        $conteudo = null;
        $erroLeitura = null;

        try {
            $conteudo = $this->logs->lerFinal($arquivo, $linhas, is_string($busca) ? $busca : null, is_string($nivel) ? $nivel : null);
        } catch (\Throwable $e) {
            $erroLeitura = $e->getMessage();
        }

        return response()->json([
            'arquivos' => $arquivos,
            'leitura' => $conteudo,
            'erro_leitura' => $erroLeitura,
            'filtros' => [
                'arquivo' => $arquivo,
                'linhas' => min(LaravelLogReader::MAX_LINHAS, max(1, $linhas)),
                'busca' => is_string($busca) ? $busca : null,
                'nivel' => is_string($nivel) ? $nivel : null,
            ],
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * GET /v2/admin/logs/{arquivo}
     */
    public function show(Request $request, string $arquivo): JsonResponse
    {
        $linhas = (int) $request->query('linhas', LaravelLogReader::DEFAULT_LINHAS);
        $busca = $request->query('busca');
        $nivel = $request->query('nivel');

        try {
            $conteudo = $this->logs->lerFinal(
                $arquivo,
                $linhas,
                is_string($busca) ? $busca : null,
                is_string($nivel) ? $nivel : null
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'erro' => $e->getMessage(),
            ], 422, [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            return response()->json([
                'erro' => $e->getMessage(),
            ], 404, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json($conteudo, 200, [], JSON_UNESCAPED_UNICODE);
    }
}
