<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\RecordePessoal;

class RecordePessoalController
{
    private RecordePessoal $model;

    public function __construct()
    {
        $db = require __DIR__ . '/../../config/database.php';
        $this->model = new RecordePessoal($db);
    }

    // ========== PROVAS (tipos de evento) ==========

    /**
     * Listar provas
     * GET /admin/recordes/provas
     */
    public function listarProvas(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $queryParams = $request->getQueryParams();
        $apenasAtivas = !isset($queryParams['todas']) || $queryParams['todas'] !== 'true';
        $modalidadeId = isset($queryParams['modalidade_id']) ? (int) $queryParams['modalidade_id'] : null;

        $provas = $this->model->listarProvas($tenantId, $apenasAtivas, $modalidadeId);

        $response->getBody()->write(json_encode([
            'provas' => $provas
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Buscar prova por ID
     * GET /admin/recordes/provas/{id}
     */
    public function buscarProva(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId');

        $prova = $this->model->buscarProva($id, $tenantId);

        if (!$prova) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Prova não encontrada'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        $response->getBody()->write(json_encode([
            'prova' => $prova
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Criar prova
     * POST /admin/recordes/provas
     */
    public function criarProva(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $data = $request->getParsedBody();

        if (empty($data['nome'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Nome da prova é obrigatório'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(422);
        }

        try {
            $data['tenant_id'] = $tenantId;
            $id = $this->model->criarProva($data);
            $prova = $this->model->buscarProva($id, $tenantId);

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Prova criada com sucesso',
                'prova' => $prova
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(201);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao criar prova: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Atualizar prova
     * PUT /admin/recordes/provas/{id}
     */
    public function atualizarProva(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId');
        $data = $request->getParsedBody();

        $prova = $this->model->buscarProva($id, $tenantId);
        if (!$prova) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Prova não encontrada'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        if (empty($data['nome'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Nome da prova é obrigatório'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(422);
        }

        try {
            $this->model->atualizarProva($id, $tenantId, $data);
            $prova = $this->model->buscarProva($id, $tenantId);

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Prova atualizada com sucesso',
                'prova' => $prova
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao atualizar prova: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Desativar prova
     * DELETE /admin/recordes/provas/{id}
     */
    public function excluirProva(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId');

        $prova = $this->model->buscarProva($id, $tenantId);
        if (!$prova) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Prova não encontrada'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        try {
            $this->model->desativarProva($id, $tenantId);

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Prova desativada com sucesso'
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao desativar prova: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    // ========== RECORDES ==========

    /**
     * Listar recordes (com filtros)
     * GET /admin/recordes
     */
    public function listarRecordes(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $queryParams = $request->getQueryParams();

        $alunoId = isset($queryParams['aluno_id']) ? (int) $queryParams['aluno_id'] : null;
        $provaId = isset($queryParams['prova_id']) ? (int) $queryParams['prova_id'] : null;
        $origem = $queryParams['origem'] ?? null;
        $modalidadeId = isset($queryParams['modalidade_id']) ? (int) $queryParams['modalidade_id'] : null;

        if ($origem === 'escola') {
            $recordes = $this->model->listarRecordesEscola($tenantId, $provaId, $modalidadeId);
        } elseif ($alunoId) {
            $recordes = $this->model->listarPorAluno($tenantId, $alunoId, $provaId);
        } else {
            // Listar todos os recordes do tenant
            $recordes = $this->model->listarRecordesEscola($tenantId, $provaId, $modalidadeId);
        }

        $response->getBody()->write(json_encode([
            'recordes' => $recordes
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Buscar recorde por ID
     * GET /admin/recordes/{id}
     */
    public function buscarRecorde(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId');

        $recorde = $this->model->buscar($id, $tenantId);

        if (!$recorde) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Recorde não encontrado'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        $response->getBody()->write(json_encode([
            'recorde' => $recorde
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Criar recorde (admin registra para aluno ou escola)
     * POST /admin/recordes
     */
    public function criarRecorde(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $userId = $request->getAttribute('userId');
        $data = $request->getParsedBody();

        // Validações
        $errors = [];
        if (empty($data['prova_id'])) {
            $errors[] = 'Prova é obrigatória';
        }
        if (empty($data['data_registro'])) {
            $errors[] = 'Data do registro é obrigatória';
        }
        if (empty($data['tempo_segundos']) && empty($data['valor'])) {
            $errors[] = 'Tempo ou valor é obrigatório';
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => implode(', ', $errors)
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(422);
        }

        // Verifica se a prova existe
        $prova = $this->model->buscarProva((int) $data['prova_id'], $tenantId);
        if (!$prova) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Prova não encontrada'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        try {
            $data['tenant_id'] = $tenantId;
            $data['origem'] = $data['origem'] ?? 'escola';
            $data['registrado_por'] = $userId;

            $id = $this->model->criar($data);
            $recorde = $this->model->buscar($id, $tenantId);

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Recorde registrado com sucesso',
                'recorde' => $recorde
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(201);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao registrar recorde: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Atualizar recorde
     * PUT /admin/recordes/{id}
     */
    public function atualizarRecorde(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId');
        $data = $request->getParsedBody();

        $recorde = $this->model->buscar($id, $tenantId);
        if (!$recorde) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Recorde não encontrado'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        // Validações
        if (empty($data['prova_id'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Prova é obrigatória'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(422);
        }
        if (empty($data['data_registro'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Data do registro é obrigatória'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(422);
        }

        try {
            $this->model->atualizar($id, $tenantId, $data);
            $recorde = $this->model->buscar($id, $tenantId);

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Recorde atualizado com sucesso',
                'recorde' => $recorde
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao atualizar recorde: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Excluir recorde
     * DELETE /admin/recordes/{id}
     */
    public function excluirRecorde(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId');

        $recorde = $this->model->buscar($id, $tenantId);
        if (!$recorde) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Recorde não encontrado'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        try {
            $this->model->excluir($id, $tenantId);

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Recorde excluído com sucesso'
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao excluir recorde: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Ranking por prova
     * GET /admin/recordes/ranking/{provaId}
     */
    public function ranking(Request $request, Response $response, array $args): Response
    {
        $provaId = (int) $args['provaId'];
        $tenantId = $request->getAttribute('tenantId');
        $queryParams = $request->getQueryParams();
        $limit = isset($queryParams['limit']) ? min((int) $queryParams['limit'], 100) : 50;

        $prova = $this->model->buscarProva($provaId, $tenantId);
        if (!$prova) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Prova não encontrada'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        $ranking = $this->model->rankingPorProva($tenantId, $provaId, $limit);

        $response->getBody()->write(json_encode([
            'prova' => $prova,
            'ranking' => $ranking
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
