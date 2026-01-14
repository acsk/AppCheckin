<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Wod;
use App\Models\WodResultado;
use PDO;

class WodResultadoController
{
    private Wod $wodModel;
    private WodResultado $wodResultadoModel;
    private PDO $db;

    public function __construct()
    {
        $this->db = require __DIR__ . '/../../config/database.php';
        $this->wodModel = new Wod($this->db);
        $this->wodResultadoModel = new WodResultado($this->db);
    }

    /**
     * Listar resultados de um WOD (Leaderboard)
     * GET /admin/wods/{wodId}/resultados
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

        $resultados = $this->wodResultadoModel->listByWod($wodId, $tenantId);

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'Resultados listados com sucesso',
            'data' => $resultados,
            'total' => count($resultados),
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
    }

    /**
     * Registrar resultado de um aluno em um WOD
     * POST /admin/wods/{wodId}/resultados
     */
    public function create(Request $request, Response $response, array $args): Response
    {
        $tenantId = (int)$request->getAttribute('tenantId');
        $usuarioId = (int)$request->getAttribute('usuarioId');
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
        if (empty($data['usuario_id'])) $erros[] = 'ID do usuário é obrigatório';
        if (empty($data['tipo_score'])) $erros[] = 'Tipo de score é obrigatório';

        if (!empty($erros)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Validação falhou',
                'errors' => $erros,
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(422);
        }

        // Verificar se já existe resultado para esse usuário e WOD
        $existente = $this->wodResultadoModel->findByUsuarioWod($data['usuario_id'], $wodId);

        if ($existente) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Esse aluno já possui resultado registrado para esse WOD',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(409);
        }

        $resultado = [
            'tenant_id' => $tenantId,
            'wod_id' => $wodId,
            'usuario_id' => $data['usuario_id'],
            'variacao_id' => $data['variacao_id'] ?? null,
            'tipo_score' => $data['tipo_score'],
            'valor_num' => $data['valor_num'] ?? null,
            'valor_texto' => $data['valor_texto'] ?? null,
            'observacao' => $data['observacao'] ?? null,
            'registrado_por' => $usuarioId,
        ];

        $resultadoId = $this->wodResultadoModel->create($resultado);

        if (!$resultadoId) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao registrar resultado',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }

        $resultado = $this->wodResultadoModel->findById($resultadoId);

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'Resultado registrado com sucesso',
            'data' => $resultado,
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(201);
    }

    /**
     * Atualizar resultado
     * PUT /admin/wods/{wodId}/resultados/{id}
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $tenantId = (int)$request->getAttribute('tenantId');
        $wodId = (int)($args['wodId'] ?? 0);
        $resultadoId = (int)($args['id'] ?? 0);
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

        $resultado = $this->wodResultadoModel->findById($resultadoId);

        if (!$resultado || $resultado['wod_id'] != $wodId) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Resultado não encontrado',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        if (!$this->wodResultadoModel->update($resultadoId, $data)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao atualizar resultado',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }

        $resultado = $this->wodResultadoModel->findById($resultadoId);

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'Resultado atualizado com sucesso',
            'data' => $resultado,
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
    }

    /**
     * Deletar resultado
     * DELETE /admin/wods/{wodId}/resultados/{id}
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $tenantId = (int)$request->getAttribute('tenantId');
        $wodId = (int)($args['wodId'] ?? 0);
        $resultadoId = (int)($args['id'] ?? 0);

        // Validar que o WOD existe
        $wod = $this->wodModel->findById($wodId, $tenantId);

        if (!$wod) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'WOD não encontrado',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        $resultado = $this->wodResultadoModel->findById($resultadoId);

        if (!$resultado || $resultado['wod_id'] != $wodId) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Resultado não encontrado',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        if (!$this->wodResultadoModel->delete($resultadoId)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao deletar resultado',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'Resultado deletado com sucesso',
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
    }
}
