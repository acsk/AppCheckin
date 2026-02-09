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
        $tenantId = $request->getAttribute('tenantId') ?? 1;
        $planoModel = new Plano($db, $tenantId);
        
        $queryParams = $request->getQueryParams();
        $apenasAtivos = false;
        if (isset($queryParams['ativos'])) {
            // Tratar "true", "1", true como true; "false", "0", false como false
            $apenasAtivos = filter_var($queryParams['ativos'], FILTER_VALIDATE_BOOLEAN);
        }
        $planos = $planoModel->getAll($apenasAtivos);

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
        try {
            $db = require __DIR__ . '/../../config/database.php';
            $tenantId = $request->getAttribute('tenantId') ?? 1;
            $planoModel = new Plano($db, $tenantId);
            
            $planoId = (int) $args['id'];
            $plano = $planoModel->findById($planoId);

            if (!$plano) {
                $response->getBody()->write(json_encode(['error' => 'Plano não encontrado']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $response->getBody()->write(json_encode($plano));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            error_log('Erro ao buscar plano: ' . $e->getMessage());
            $response->getBody()->write(json_encode([
                'error' => 'Erro ao buscar plano: ' . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Criar novo plano
     */
    public function create(Request $request, Response $response): Response
    {
        $db = require __DIR__ . '/../../config/database.php';
        $tenantId = $request->getAttribute('tenantId') ?? 1;
        $planoModel = new Plano($db, $tenantId);
        
        $data = $request->getParsedBody();

        $errors = [];

        if (empty($data['modalidade_id'])) {
            $errors[] = 'Modalidade é obrigatória';
        }

        if (empty($data['nome'])) {
            $errors[] = 'Nome é obrigatório';
        }

        if (!isset($data['valor']) || $data['valor'] < 0) {
            $errors[] = 'Valor deve ser maior ou igual a zero';
        }

        if (empty($data['checkins_semanais']) || $data['checkins_semanais'] < 1) {
            $errors[] = 'Checkins semanais deve ser maior que zero';
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode(['errors' => $errors]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        // Verificar se já existe um plano com as mesmas características
        $stmt = $db->prepare("
            SELECT id FROM planos 
            WHERE tenant_id = ? 
            AND modalidade_id = ? 
            AND nome = ? 
            AND valor = ? 
            AND checkins_semanais = ? 
            AND duracao_dias = ?
        ");
        $stmt->execute([
            $tenantId,
            $data['modalidade_id'],
            $data['nome'],
            $data['valor'],
            $data['checkins_semanais'],
            $data['duracao_dias'] ?? 30
        ]);
        
        if ($stmt->fetch()) {
            $response->getBody()->write(json_encode([
                'error' => 'Já existe um plano com essas características nesta modalidade'
            ]));
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
        $tenantId = $request->getAttribute('tenantId') ?? 1;
        $planoModel = new Plano($db, $tenantId);
        
        $planoId = (int) $args['id'];
        $data = $request->getParsedBody();

        $plano = $planoModel->findById($planoId);

        if (!$plano) {
            $response->getBody()->write(json_encode(['error' => 'Plano não encontrado']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Verificar se o plano possui contratos ativos
        if ($planoModel->possuiContratos($planoId)) {
            $response->getBody()->write(json_encode([
                'error' => 'Não é possível modificar este plano pois existem contratos vinculados a ele. Crie um novo plano ou marque este como histórico.'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Verificar se já existe outro plano com as mesmas características
        if (isset($data['modalidade_id'], $data['nome'], $data['valor'], $data['checkins_semanais'])) {
            $stmt = $db->prepare("
                SELECT id FROM planos 
                WHERE tenant_id = ? 
                AND modalidade_id = ? 
                AND nome = ? 
                AND valor = ? 
                AND checkins_semanais = ? 
                AND duracao_dias = ?
                AND id != ?
            ");
            $stmt->execute([
                $tenantId,
                $data['modalidade_id'],
                $data['nome'],
                $data['valor'],
                $data['checkins_semanais'],
                $data['duracao_dias'] ?? 30,
                $planoId
            ]);
            
            if ($stmt->fetch()) {
                $response->getBody()->write(json_encode([
                    'error' => 'Já existe um plano com essas características nesta modalidade'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
            }
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
        $tenantId = $request->getAttribute('tenantId') ?? 1;
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
