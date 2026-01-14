<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Wod;
use App\Models\WodBloco;
use App\Models\WodVariacao;
use App\Models\WodResultado;
use PDO;

class WodController
{
    private Wod $wodModel;
    private WodBloco $wodBlocoModel;
    private WodVariacao $wodVariacaoModel;
    private WodResultado $wodResultadoModel;
    private PDO $db;

    public function __construct()
    {
        $this->db = require __DIR__ . '/../../config/database.php';
        $this->wodModel = new Wod($this->db);
        $this->wodBlocoModel = new WodBloco($this->db);
        $this->wodVariacaoModel = new WodVariacao($this->db);
        $this->wodResultadoModel = new WodResultado($this->db);
    }

    /**
     * Listar WODs
     * GET /admin/wods
     * Query params: status=published, data_inicio=2026-01-01, data_fim=2026-01-31, data=2026-01-10
     */
    public function index(Request $request, Response $response): Response
    {
        $tenantId = (int)$request->getAttribute('tenantId');
        $queryParams = $request->getQueryParams();

        $filters = [];
        if (!empty($queryParams['status'])) {
            $filters['status'] = $queryParams['status'];
        }
        if (!empty($queryParams['data_inicio']) && !empty($queryParams['data_fim'])) {
            $filters['data_inicio'] = $queryParams['data_inicio'];
            $filters['data_fim'] = $queryParams['data_fim'];
        }
        if (!empty($queryParams['data'])) {
            $filters['data'] = $queryParams['data'];
        }

        $wods = $this->wodModel->listByTenant($tenantId, $filters);

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'WODs listados com sucesso',
            'data' => $wods,
            'total' => count($wods),
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
    }

    /**
     * Obter detalhes de um WOD
     * GET /admin/wods/{id}
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $tenantId = (int)$request->getAttribute('tenantId');
        $wodId = (int)($args['id'] ?? 0);

        $wod = $this->wodModel->findById($wodId, $tenantId);

        if (!$wod) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'WOD não encontrado',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        // Carregar blocos e variações
        $wod['blocos'] = $this->wodBlocoModel->listByWod($wodId);
        $wod['variacoes'] = $this->wodVariacaoModel->listByWod($wodId);
        $wod['resultados'] = $this->wodResultadoModel->listByWod($wodId, $tenantId);

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'WOD obtido com sucesso',
            'data' => $wod,
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
    }

    /**
     * Criar novo WOD
     * POST /admin/wods
     */
    public function create(Request $request, Response $response): Response
    {
        $tenantId = (int)$request->getAttribute('tenantId');
        $usuarioId = (int)$request->getAttribute('usuarioId');
        $data = $request->getParsedBody();

        // Validar campos obrigatórios
        $erros = [];
        if (empty($data['titulo'])) $erros[] = 'Título é obrigatório';
        if (empty($data['data'])) $erros[] = 'Data é obrigatória';

        if (!empty($erros)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Validação falhou',
                'errors' => $erros,
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(422);
        }

        // Verificar unicidade
        if ($this->wodModel->existePorData($data['data'], $tenantId)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Já existe um WOD para essa data',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(409);
        }

        $wodData = [
            'titulo' => $data['titulo'],
            'descricao' => $data['descricao'] ?? null,
            'data' => $data['data'],
            'status' => $data['status'] ?? Wod::STATUS_DRAFT,
            'criado_por' => $usuarioId,
        ];

        $wodId = $this->wodModel->create($wodData, $tenantId);

        if (!$wodId) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao criar WOD',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }

        $wod = $this->wodModel->findById($wodId, $tenantId);

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'WOD criado com sucesso',
            'data' => $wod,
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(201);
    }

    /**
     * Atualizar WOD
     * PUT /admin/wods/{id}
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $tenantId = (int)$request->getAttribute('tenantId');
        $wodId = (int)($args['id'] ?? 0);
        $data = $request->getParsedBody();

        $wod = $this->wodModel->findById($wodId, $tenantId);

        if (!$wod) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'WOD não encontrado',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        // Validar data única se foi alterada
        if (!empty($data['data']) && $data['data'] !== $wod['data']) {
            if ($this->wodModel->existePorData($data['data'], $tenantId, $wodId)) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Já existe um WOD para essa data',
                ], JSON_UNESCAPED_UNICODE));

                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(409);
            }
        }

        if (!$this->wodModel->update($wodId, $tenantId, $data)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao atualizar WOD',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }

        $wod = $this->wodModel->findById($wodId, $tenantId);

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'WOD atualizado com sucesso',
            'data' => $wod,
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
    }

    /**
     * Deletar WOD
     * DELETE /admin/wods/{id}
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $tenantId = (int)$request->getAttribute('tenantId');
        $wodId = (int)($args['id'] ?? 0);

        $wod = $this->wodModel->findById($wodId, $tenantId);

        if (!$wod) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'WOD não encontrado',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        if (!$this->wodModel->delete($wodId, $tenantId)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao deletar WOD',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'WOD deletado com sucesso',
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
    }

    /**
     * Publicar WOD
     * PATCH /admin/wods/{id}/publish
     */
    public function publish(Request $request, Response $response, array $args): Response
    {
        $tenantId = (int)$request->getAttribute('tenantId');
        $wodId = (int)($args['id'] ?? 0);

        $wod = $this->wodModel->findById($wodId, $tenantId);

        if (!$wod) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'WOD não encontrado',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        if (!$this->wodModel->publicar($wodId, $tenantId)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao publicar WOD',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }

        $wod = $this->wodModel->findById($wodId, $tenantId);

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'WOD publicado com sucesso',
            'data' => $wod,
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
    }

    /**
     * Arquivar WOD
     * PATCH /admin/wods/{id}/archive
     */
    public function archive(Request $request, Response $response, array $args): Response
    {
        $tenantId = (int)$request->getAttribute('tenantId');
        $wodId = (int)($args['id'] ?? 0);

        $wod = $this->wodModel->findById($wodId, $tenantId);

        if (!$wod) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'WOD não encontrado',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        if (!$this->wodModel->arquivar($wodId, $tenantId)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao arquivar WOD',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }

        $wod = $this->wodModel->findById($wodId, $tenantId);

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'WOD arquivado com sucesso',
            'data' => $wod,
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
    }
}
