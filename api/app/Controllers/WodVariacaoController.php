<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Wod;
use App\Models\WodVariacao;
use PDO;

class WodVariacaoController
{
    private Wod $wodModel;
    private WodVariacao $wodVariacaoModel;
    private PDO $db;

    public function __construct()
    {
        $this->db = require __DIR__ . '/../../config/database.php';
        $this->wodModel = new Wod($this->db);
        $this->wodVariacaoModel = new WodVariacao($this->db);
    }

    /**
     * Listar variações de um WOD
     * GET /admin/wods/{wodId}/variacoes
     */
    public function index(Request $request, Response $response, array $args): Response
    {
        $tenantId = (int)$request->getAttribute('tenantId');
        $wodId = (int)($args['wodId'] ?? 0);

        // Validar que o WOD existe
        $wod = $this->wodModel->findById($wodId, $tenantId);

        if (!$wod) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'WOD não encontrado',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        $variacoes = $this->wodVariacaoModel->listByWod($wodId);

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'Variações listadas com sucesso',
            'data' => $variacoes,
            'total' => count($variacoes),
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
    }

    /**
     * Criar nova variação
     * POST /admin/wods/{wodId}/variacoes
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
        if (empty($data['nome'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Validação falhou',
                'errors' => ['Nome é obrigatório'],
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(422);
        }

        // Verificar se já existe variação com esse nome
        $existente = $this->wodVariacaoModel->findByNome($wodId, $data['nome']);

        if ($existente) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Já existe uma variação com esse nome para este WOD',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(409);
        }

        $variacao = [
            'wod_id' => $wodId,
            'nome' => $data['nome'],
            'descricao' => $data['descricao'] ?? null,
        ];

        $variacaoId = $this->wodVariacaoModel->create($variacao);

        if (!$variacaoId) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao criar variação',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }

        $variacao = $this->wodVariacaoModel->findById($variacaoId);

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'Variação criada com sucesso',
            'data' => $variacao,
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(201);
    }

    /**
     * Atualizar variação
     * PUT /admin/wods/{wodId}/variacoes/{id}
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $tenantId = (int)$request->getAttribute('tenantId');
        $wodId = (int)($args['wodId'] ?? 0);
        $variacaoId = (int)($args['id'] ?? 0);
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

        $variacao = $this->wodVariacaoModel->findById($variacaoId);

        if (!$variacao || $variacao['wod_id'] != $wodId) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Variação não encontrada',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        if (!$this->wodVariacaoModel->update($variacaoId, $data)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao atualizar variação',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }

        $variacao = $this->wodVariacaoModel->findById($variacaoId);

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'Variação atualizada com sucesso',
            'data' => $variacao,
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
    }

    /**
     * Deletar variação
     * DELETE /admin/wods/{wodId}/variacoes/{id}
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $tenantId = (int)$request->getAttribute('tenantId');
        $wodId = (int)($args['wodId'] ?? 0);
        $variacaoId = (int)($args['id'] ?? 0);

        // Validar que o WOD existe
        $wod = $this->wodModel->findById($wodId, $tenantId);

        if (!$wod) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'WOD não encontrado',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        $variacao = $this->wodVariacaoModel->findById($variacaoId);

        if (!$variacao || $variacao['wod_id'] != $wodId) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Variação não encontrada',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        if (!$this->wodVariacaoModel->delete($variacaoId)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao deletar variação',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'Variação deletada com sucesso',
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
    }
}
