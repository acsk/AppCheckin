<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Plano;

class PlanoController
{
    private Plano $planoModel;

    public function __construct()
    {
        $db = require __DIR__ . '/../../config/database.php';
        $this->planoModel = new Plano($db, 1); // TODO: Obter tenant do middleware
    }

    /**
     * Listar todos os planos
     */
    public function index(Request $request, Response $response): Response
    {
        $apenasAtivos = $request->getQueryParams()['ativos'] ?? false;
        $planos = $this->planoModel->getAll((bool) $apenasAtivos);

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
        $planoId = (int) $args['id'];
        $plano = $this->planoModel->findById($planoId);

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
        $data = $request->getParsedBody();

        $errors = [];

        if (empty($data['nome'])) {
            $errors[] = 'Nome é obrigatório';
        }

        if (!isset($data['valor']) || $data['valor'] < 0) {
            $errors[] = 'Valor deve ser maior ou igual a zero';
        }

        if (empty($data['duracao_dias']) || $data['duracao_dias'] < 1) {
            $errors[] = 'Duração deve ser maior que zero';
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode(['errors' => $errors]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        $planoId = $this->planoModel->create($data);
        $plano = $this->planoModel->findById($planoId);

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
        $planoId = (int) $args['id'];
        $data = $request->getParsedBody();

        $plano = $this->planoModel->findById($planoId);

        if (!$plano) {
            $response->getBody()->write(json_encode(['error' => 'Plano não encontrado']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $updated = $this->planoModel->update($planoId, $data);

        if (!$updated) {
            $response->getBody()->write(json_encode(['error' => 'Nenhum dado foi atualizado']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $planoAtualizado = $this->planoModel->findById($planoId);

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
        $planoId = (int) $args['id'];

        // Verificar se há usuários usando o plano
        $totalUsuarios = $this->planoModel->countUsuarios($planoId);

        if ($totalUsuarios > 0) {
            $response->getBody()->write(json_encode([
                'error' => "Não é possível desativar. $totalUsuarios usuário(s) estão usando este plano."
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $deleted = $this->planoModel->delete($planoId);

        if (!$deleted) {
            $response->getBody()->write(json_encode(['error' => 'Erro ao desativar plano']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $response->getBody()->write(json_encode(['message' => 'Plano desativado com sucesso']));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
