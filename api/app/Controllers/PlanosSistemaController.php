<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\PlanoSistema;

/**
 * Controller para gerenciar Planos do Sistema (CRUD)
 * Planos que as academias podem contratar
 */
class PlanosSistemaController
{
    private PlanoSistema $planoSistemaModel;

    public function __construct()
    {
        $db = require __DIR__ . '/../../config/database.php';
        $this->planoSistemaModel = new PlanoSistema($db);
    }

    /**
     * Listar todos os planos do sistema
     * GET /planos-sistema?ativos=true&apenas_atuais=true
     */
    public function index(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $apenasAtivos = isset($queryParams['ativos']) && $queryParams['ativos'] === 'true';
        $apenasAtuais = isset($queryParams['apenas_atuais']) && $queryParams['apenas_atuais'] === 'true';
        
        $planos = $this->planoSistemaModel->listarTodos($apenasAtivos);

        // Filtrar apenas planos atuais se solicitado
        if ($apenasAtuais) {
            $planos = array_filter($planos, function($plano) {
                return $plano['atual'] == 1 || $plano['atual'] === true;
            });
            $planos = array_values($planos); // Reindexar array
        }

        $response->getBody()->write(json_encode([
            'planos' => $planos,
            'total' => count($planos)
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Listar apenas planos disponíveis para contratação
     * GET /planos-sistema/disponiveis
     */
    public function disponiveis(Request $request, Response $response): Response
    {
        $planos = $this->planoSistemaModel->listarDisponiveis();

        $response->getBody()->write(json_encode([
            'planos' => $planos,
            'total' => count($planos)
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Buscar plano por ID
     * GET /planos-sistema/{id}
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $planoId = (int) $args['id'];
        $plano = $this->planoSistemaModel->buscarPorId($planoId);

        if (!$plano) {
            $response->getBody()->write(json_encode([
                'error' => 'Plano não encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Adicionar informação de contratos ativos
        $plano['contratos_ativos'] = $this->planoSistemaModel->contarContratosAtivos($planoId);

        $response->getBody()->write(json_encode($plano));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Listar academias associadas a um plano
     * GET /planos-sistema/{id}/academias
     */
    public function listarAcademias(Request $request, Response $response, array $args): Response
    {
        $planoId = (int) $args['id'];
        $plano = $this->planoSistemaModel->buscarPorId($planoId);

        if (!$plano) {
            $response->getBody()->write(json_encode([
                'error' => 'Plano não encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $academias = $this->planoSistemaModel->listarAcademias($planoId);

        $response->getBody()->write(json_encode([
            'academias' => $academias,
            'total' => count($academias)
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Criar novo plano do sistema
     * POST /planos-sistema
     */
    public function create(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        // Validações
        $errors = [];
        if (empty($data['nome'])) {
            $errors[] = 'Nome é obrigatório';
        }
        if (!isset($data['valor']) || $data['valor'] < 0) {
            $errors[] = 'Valor deve ser maior ou igual a zero';
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode(['errors' => $errors]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        try {
            $planoId = $this->planoSistemaModel->criar($data);
            $plano = $this->planoSistemaModel->buscarPorId($planoId);

            $response->getBody()->write(json_encode([
                'message' => 'Plano criado com sucesso',
                'plano' => $plano
            ]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'error' => 'Erro ao criar plano: ' . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Atualizar plano do sistema
     * PUT /planos-sistema/{id}
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $planoId = (int) $args['id'];
        $data = $request->getParsedBody();

        $plano = $this->planoSistemaModel->buscarPorId($planoId);
        if (!$plano) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Plano não encontrado'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        try {
            $this->planoSistemaModel->atualizar($planoId, $data);
            $planoAtualizado = $this->planoSistemaModel->buscarPorId($planoId);

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Plano atualizado com sucesso',
                'plano' => $planoAtualizado
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    }

    /**
     * Marcar plano como histórico
     * POST /planos-sistema/{id}/marcar-historico
     */
    public function marcarHistorico(Request $request, Response $response, array $args): Response
    {
        $planoId = (int) $args['id'];

        $plano = $this->planoSistemaModel->buscarPorId($planoId);
        if (!$plano) {
            $response->getBody()->write(json_encode([
                'error' => 'Plano não encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $this->planoSistemaModel->marcarComoHistorico($planoId);

        $response->getBody()->write(json_encode([
            'message' => 'Plano marcado como histórico. Não estará mais disponível para novos contratos.'
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Desativar plano do sistema
     * DELETE /planos-sistema/{id}
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $planoId = (int) $args['id'];

        $plano = $this->planoSistemaModel->buscarPorId($planoId);
        if (!$plano) {
            $response->getBody()->write(json_encode([
                'error' => 'Plano não encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        try {
            $this->planoSistemaModel->desativar($planoId);

            $response->getBody()->write(json_encode([
                'message' => 'Plano desativado com sucesso'
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'error' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    }
}
