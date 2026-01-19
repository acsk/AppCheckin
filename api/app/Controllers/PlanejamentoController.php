<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\PlanejamentoHorario;

class PlanejamentoController
{
    private PlanejamentoHorario $planejamentoModel;

    public function __construct()
    {
        $db = require __DIR__ . '/../../config/database.php';
        $this->planejamentoModel = new PlanejamentoHorario($db);
    }

    /**
     * GET /admin/planejamentos
     */
    public function index(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $queryParams = $request->getQueryParams();
        $apenasAtivos = isset($queryParams['ativos']) ? (bool) $queryParams['ativos'] : true;

        $planejamentos = $this->planejamentoModel->getAll($tenantId, $apenasAtivos);

        $response->getBody()->write(json_encode([
            'planejamentos' => $planejamentos,
            'total' => count($planejamentos)
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * GET /admin/planejamentos/{id}
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $planejamento = $this->planejamentoModel->findById((int) $args['id'], $tenantId);

        if (!$planejamento) {
            $response->getBody()->write(json_encode([
                'error' => 'Planejamento não encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode($planejamento));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * POST /admin/planejamentos
     */
    public function create(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $data = $request->getParsedBody();

        // Validações
        $errors = [];
        
        if (empty($data['titulo'])) {
            $errors[] = 'Título é obrigatório';
        }

        $diasValidos = ['segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo'];
        if (empty($data['dia_semana']) || !in_array($data['dia_semana'], $diasValidos)) {
            $errors[] = 'Dia da semana inválido';
        }

        if (empty($data['horario_inicio'])) {
            $errors[] = 'Horário de início é obrigatório';
        }

        if (empty($data['horario_fim'])) {
            $errors[] = 'Horário de fim é obrigatório';
        }

        if (empty($data['data_inicio'])) {
            $errors[] = 'Data de início é obrigatória';
        }

        if (!isset($data['vagas']) || $data['vagas'] < 1) {
            $errors[] = 'Número de vagas deve ser maior que 0';
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode(['errors' => $errors]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        $id = $this->planejamentoModel->create($data, $tenantId);

        $response->getBody()->write(json_encode([
            'message' => 'Planejamento criado com sucesso',
            'id' => $id
        ]));

        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    /**
     * PUT /admin/planejamentos/{id}
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $id = (int) $args['id'];
        $data = $request->getParsedBody();

        // Verificar se planejamento existe
        $planejamento = $this->planejamentoModel->findById($id, $tenantId);
        if (!$planejamento) {
            $response->getBody()->write(json_encode([
                'error' => 'Planejamento não encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Validar dia_semana se fornecido
        if (isset($data['dia_semana'])) {
            $diasValidos = ['segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo'];
            if (!in_array($data['dia_semana'], $diasValidos)) {
                $response->getBody()->write(json_encode([
                    'error' => 'Dia da semana inválido'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
            }
        }

        $this->planejamentoModel->update($id, $data, $tenantId);

        $response->getBody()->write(json_encode([
            'message' => 'Planejamento atualizado com sucesso'
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * DELETE /admin/planejamentos/{id}
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $id = (int) $args['id'];

        $planejamento = $this->planejamentoModel->findById($id, $tenantId);
        if (!$planejamento) {
            $response->getBody()->write(json_encode([
                'error' => 'Planejamento não encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $this->planejamentoModel->delete($id, $tenantId);

        $response->getBody()->write(json_encode([
            'message' => 'Planejamento desativado com sucesso'
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * POST /admin/planejamentos/{id}/gerar-horarios
     * Gera dias e horários baseado no planejamento
     */
    public function gerarHorarios(Request $request, Response $response, array $args): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $id = (int) $args['id'];
        $data = $request->getParsedBody();

        if (empty($data['data_inicio']) || empty($data['data_fim'])) {
            $response->getBody()->write(json_encode([
                'error' => 'data_inicio e data_fim são obrigatórios'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        $resultado = $this->planejamentoModel->gerarDiasHorarios(
            $id,
            $tenantId,
            $data['data_inicio'],
            $data['data_fim']
        );

        if (isset($resultado['error'])) {
            $response->getBody()->write(json_encode($resultado));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode([
            'message' => 'Horários gerados com sucesso',
            'resultado' => $resultado
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
