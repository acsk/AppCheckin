<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Plano;

class PlanoController
{
    /**
     * Listar todos os planos
     */
    public function index(Request $request, Response $response): Response
    {
        $db = require __DIR__ . '/../../config/database.php';
        $tenantId = $request->getAttribute('tenant_id') ?? 1;
        $planoModel = new Plano($db, $tenantId);
        
        $apenasAtivos = $request->getQueryParams()['ativos'] ?? false;
        $planos = $planoModel->getAll((bool) $apenasAtivos);

        $response->getBody()->write(json_encode([
            'planos' => $planos,
            'total' => count($planos)
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Buscar plano por ID
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $db = require __DIR__ . '/../../config/database.php';
        $tenantId = $request->getAttribute('tenant_id') ?? 1;
        $planoModel = new Plano($db, $tenantId);
        
        $planoId = (int) $args['id'];
        $plano = $planoModel->findById($planoId);

        if (!$plano) {
            $response->getBody()->write(json_encode(['error' => 'Plano não encontrado']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode($plano));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Criar novo plano
     */
    public function create(Request $request, Response $response): Response
    {
        $db = require __DIR__ . '/../../config/database.php';
        $tenantId = $request->getAttribute('tenant_id') ?? 1;
        $planoModel = new Plano($db, $tenantId);
        
        $data = $request->getParsedBody();

        $errors = [];

        if (empty($data['nome'])) {
            $errors[] = 'Nome é obrigatório';
        }

        if (!isset($data['valor']) || $data['valor'] < 0) {
            $errors[] = 'Valor deve ser maior ou igual a zero';
        }

        if (empty($data['max_alunos']) || $data['max_alunos'] < 1) {
            $errors[] = 'Capacidade de alunos deve ser maior que zero';
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode(['errors' => $errors]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        $planoId = $planoModel->create($data);
        $plano = $planoModel->findById($planoId);

        $response->getBody()->write(json_encode([
            'message' => 'Plano criado com sucesso',
            'plano' => $plano
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    /**
     * Atualizar plano
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $db = require __DIR__ . '/../../config/database.php';
        $tenantId = $request->getAttribute('tenant_id') ?? 1;
        $planoModel = new Plano($db, $tenantId);
        
        $planoId = (int) $args['id'];
        $data = $request->getParsedBody();

        $plano = $planoModel->findById($planoId);

        if (!$plano) {
            $response->getBody()->write(json_encode(['error' => 'Plano não encontrado']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $updated = $planoModel->update($planoId, $data);

        if (!$updated) {
            $response->getBody()->write(json_encode(['error' => 'Nenhum dado foi atualizado']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $planoAtualizado = $planoModel->findById($planoId);

        $response->getBody()->write(json_encode([
            'message' => 'Plano atualizado com sucesso',
            'plano' => $planoAtualizado
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Desativar plano
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $db = require __DIR__ . '/../../config/database.php';
        $tenantId = $request->getAttribute('tenant_id') ?? 1;
        $planoModel = new Plano($db, $tenantId);
        
        $planoId = (int) $args['id'];

        // Verificar se há usuários usando o plano
        $totalUsuarios = $planoModel->countUsuarios($planoId);

        if ($totalUsuarios > 0) {
            $response->getBody()->write(json_encode([
                'error' => "Não é possível desativar. $totalUsuarios usuário(s) estão usando este plano."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $deleted = $planoModel->delete($planoId);

        if (!$deleted) {
            $response->getBody()->write(json_encode(['error' => 'Erro ao desativar plano']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $response->getBody()->write(json_encode(['message' => 'Plano desativado com sucesso']));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
