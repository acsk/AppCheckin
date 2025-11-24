<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Usuario;

class UsuarioController
{
    private Usuario $usuarioModel;

    public function __construct()
    {
        $db = require __DIR__ . '/../../config/database.php';
        $this->usuarioModel = new Usuario($db);
    }

    public function me(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('userId');
        $tenantId = $request->getAttribute('tenantId', 1);
        
        $usuario = $this->usuarioModel->findById($userId, $tenantId);

        if (!$usuario) {
            $response->getBody()->write(json_encode([
                'error' => 'Usuário não encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode($usuario));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function update(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('userId');
        $tenantId = $request->getAttribute('tenantId', 1);
        $data = $request->getParsedBody();

        $errors = [];

        // Validações
        if (isset($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email inválido';
            } elseif ($this->usuarioModel->emailExists($data['email'], $userId, $tenantId)) {
                $errors[] = 'Email já cadastrado';
            }
        }

        if (isset($data['senha']) && strlen($data['senha']) < 6) {
            $errors[] = 'Senha deve ter no mínimo 6 caracteres';
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode([
                'errors' => $errors
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        // Atualizar
        $updated = $this->usuarioModel->update($userId, $data);

        if (!$updated) {
            $response->getBody()->write(json_encode([
                'error' => 'Nenhum dado foi atualizado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $usuario = $this->usuarioModel->findById($userId, $tenantId);

        $response->getBody()->write(json_encode([
            'message' => 'Usuário atualizado com sucesso',
            'user' => $usuario
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Retorna estatísticas de um usuário específico
     */
    public function estatisticas(Request $request, Response $response, array $args): Response
    {
        $usuarioId = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId', 1);
        
        $estatisticas = $this->usuarioModel->getEstatisticas($usuarioId, $tenantId);

        if (!$estatisticas) {
            $response->getBody()->write(json_encode([
                'error' => 'Usuário não encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode($estatisticas));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
