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

    // ========== DEFINIÇÕES (tipos de recorde/teste) ==========

    /**
     * Listar definições
     * GET /admin/recordes/definicoes
     */
    public function listarDefinicoes(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $queryParams = $request->getQueryParams();
        $apenasAtivas = !isset($queryParams['todas']) || $queryParams['todas'] !== 'true';
        $modalidadeId = isset($queryParams['modalidade_id']) ? (int) $queryParams['modalidade_id'] : null;
        $categoria = $queryParams['categoria'] ?? null;

        $definicoes = $this->model->listarDefinicoes($tenantId, $apenasAtivas, $modalidadeId, $categoria);

        $response->getBody()->write(json_encode([
            'definicoes' => $definicoes
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Buscar definição por ID
     * GET /admin/recordes/definicoes/{id}
     */
    public function buscarDefinicao(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId');

        $definicao = $this->model->buscarDefinicao($id, $tenantId);

        if (!$definicao) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Definição não encontrada'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        $response->getBody()->write(json_encode([
            'definicao' => $definicao
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Criar definição (com métricas)
     * POST /admin/recordes/definicoes
     */
    public function criarDefinicao(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $data = $request->getParsedBody();

        if (empty($data['nome'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Nome da definição é obrigatório'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(422);
        }

        if (empty($data['metricas']) || !is_array($data['metricas'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Pelo menos uma métrica é obrigatória'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(422);
        }

        foreach ($data['metricas'] as $m) {
            if (empty($m['codigo']) || empty($m['nome']) || empty($m['direcao'])) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Cada métrica precisa ter codigo, nome e direcao'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(422);
            }
        }

        try {
            $data['tenant_id'] = $tenantId;
            $id = $this->model->criarDefinicao($data);
            $definicao = $this->model->buscarDefinicao($id, $tenantId);

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Definição criada com sucesso',
                'definicao' => $definicao
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(201);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao criar definição: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Atualizar definição
     * PUT /admin/recordes/definicoes/{id}
     */
    public function atualizarDefinicao(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId');
        $data = $request->getParsedBody();

        $definicao = $this->model->buscarDefinicao($id, $tenantId);
        if (!$definicao) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Definição não encontrada'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        if (empty($data['nome'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Nome da definição é obrigatório'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(422);
        }

        try {
            $this->model->atualizarDefinicao($id, $tenantId, $data);
            $definicao = $this->model->buscarDefinicao($id, $tenantId);

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Definição atualizada com sucesso',
                'definicao' => $definicao
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao atualizar definição: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Desativar definição
     * DELETE /admin/recordes/definicoes/{id}
     */
    public function excluirDefinicao(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId');

        $definicao = $this->model->buscarDefinicao($id, $tenantId);
        if (!$definicao) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Definição não encontrada'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        try {
            $this->model->desativarDefinicao($id, $tenantId);

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Definição desativada com sucesso'
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao desativar definição: ' . $e->getMessage()
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
        $definicaoId = isset($queryParams['definicao_id']) ? (int) $queryParams['definicao_id'] : null;
        $origem = $queryParams['origem'] ?? null;
        $modalidadeId = isset($queryParams['modalidade_id']) ? (int) $queryParams['modalidade_id'] : null;

        if ($origem === 'academia') {
            $recordes = $this->model->listarRecordesAcademia($tenantId, $definicaoId, $modalidadeId);
        } elseif ($alunoId) {
            $recordes = $this->model->listarPorAluno($tenantId, $alunoId, $definicaoId);
        } else {
            $recordes = $this->model->listarRecordesAcademia($tenantId, $definicaoId, $modalidadeId);
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
     * Criar recorde (admin registra para aluno ou academia)
     * POST /admin/recordes
     */
    public function criarRecorde(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $userId = $request->getAttribute('userId');
        $data = $request->getParsedBody();

        // Validações
        $errors = [];
        if (empty($data['definicao_id'])) {
            $errors[] = 'Definição é obrigatória';
        }
        if (empty($data['data_recorde'])) {
            $errors[] = 'Data do recorde é obrigatória';
        }
        if (empty($data['valores']) || !is_array($data['valores'])) {
            $errors[] = 'Pelo menos um valor é obrigatório';
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => implode(', ', $errors)
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(422);
        }

        // Verifica se a definição existe
        $definicao = $this->model->buscarDefinicao((int) $data['definicao_id'], $tenantId);
        if (!$definicao) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Definição não encontrada'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        try {
            $data['tenant_id'] = $tenantId;
            $data['origem'] = $data['origem'] ?? 'academia';
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

        if (empty($data['definicao_id'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Definição é obrigatória'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(422);
        }
        if (empty($data['data_recorde'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Data do recorde é obrigatória'
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
     * Ranking por definição
     * GET /admin/recordes/ranking/{definicaoId}
     */
    public function ranking(Request $request, Response $response, array $args): Response
    {
        $definicaoId = (int) $args['definicaoId'];
        $tenantId = $request->getAttribute('tenantId');
        $queryParams = $request->getQueryParams();
        $limit = isset($queryParams['limit']) ? min((int) $queryParams['limit'], 100) : 50;

        $definicao = $this->model->buscarDefinicao($definicaoId, $tenantId);
        if (!$definicao) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Definição não encontrada'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }

        $ranking = $this->model->rankingPorDefinicao($tenantId, $definicaoId, $limit);

        $response->getBody()->write(json_encode([
            'definicao' => $definicao,
            'ranking' => $ranking
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
