<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Wod;
use App\Models\WodBloco;
use PDO;

class WodBlocoController
{
    private Wod $wodModel;
    private WodBloco $wodBlocoModel;
    private PDO $db;

    public function __construct()
    {
        $this->db = require __DIR__ . '/../../config/database.php';
        $this->wodModel = new Wod($this->db);
        $this->wodBlocoModel = new WodBloco($this->db);
    }

    /**
     * Listar blocos de um WOD
     * GET /admin/wods/{wodId}/blocos
     */
    public function index(Request $request, Response $response, array $args): Response
    {
        $tenantId = (int)$request->getAttribute('tenantId');
        $wodId = (int)($args['wodId'] ?? 0);

        // Validar que o WOD existe e pertence ao tenant
        $wod = $this->wodModel->findById($wodId, $tenantId);

        if (!$wod) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'WOD não encontrado',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        $blocos = $this->wodBlocoModel->listByWod($wodId);

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'Blocos listados com sucesso',
            'data' => $blocos,
            'total' => count($blocos),
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
    }

    /**
     * Criar novo bloco
     * POST /admin/wods/{wodId}/blocos
     */
    public function create(Request $request, Response $response, array $args): Response
    {
        $tenantId = (int)$request->getAttribute('tenantId');
        $wodId = (int)($args['wodId'] ?? 0);
        $data = $request->getParsedBody();

        // Validar que o WOD existe
        $wod = $this->wodModel->findById($wodId, $tenantId);

        if (!$wod) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'WOD não encontrado',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        // Validar campos obrigatórios
        $erros = [];
        if (empty($data['tipo'])) $erros[] = 'Tipo é obrigatório';
        if (empty($data['conteudo'])) $erros[] = 'Conteúdo é obrigatório';

        if (!empty($erros)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Validação falhou',
                'errors' => $erros,
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(422);
        }

        $blocData = [
            'wod_id' => $wodId,
            'ordem' => $data['ordem'] ?? 1,
            'tipo' => $data['tipo'],
            'titulo' => $data['titulo'] ?? null,
            'conteudo' => $data['conteudo'],
            'tempo_cap' => $data['tempo_cap'] ?? null,
        ];

        $blocoId = $this->wodBlocoModel->create($blocData);

        if (!$blocoId) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao criar bloco',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }

        $bloco = $this->wodBlocoModel->findById($blocoId);

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'Bloco criado com sucesso',
            'data' => $bloco,
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(201);
    }

    /**
     * Atualizar bloco
     * PUT /admin/wods/{wodId}/blocos/{id}
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $tenantId = (int)$request->getAttribute('tenantId');
        $wodId = (int)($args['wodId'] ?? 0);
        $blocoId = (int)($args['id'] ?? 0);
        $data = $request->getParsedBody();

        // Validar que o WOD existe
        $wod = $this->wodModel->findById($wodId, $tenantId);

        if (!$wod) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'WOD não encontrado',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        $bloco = $this->wodBlocoModel->findById($blocoId);

        if (!$bloco || $bloco['wod_id'] != $wodId) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Bloco não encontrado',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        if (!$this->wodBlocoModel->update($blocoId, $data)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao atualizar bloco',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }

        $bloco = $this->wodBlocoModel->findById($blocoId);

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'Bloco atualizado com sucesso',
            'data' => $bloco,
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
    }

    /**
     * Deletar bloco
     * DELETE /admin/wods/{wodId}/blocos/{id}
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $tenantId = (int)$request->getAttribute('tenantId');
        $wodId = (int)($args['wodId'] ?? 0);
        $blocoId = (int)($args['id'] ?? 0);

        // Validar que o WOD existe
        $wod = $this->wodModel->findById($wodId, $tenantId);

        if (!$wod) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'WOD não encontrado',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        $bloco = $this->wodBlocoModel->findById($blocoId);

        if (!$bloco || $bloco['wod_id'] != $wodId) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Bloco não encontrado',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        if (!$this->wodBlocoModel->delete($blocoId)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao deletar bloco',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'Bloco deletado com sucesso',
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
    }
}
