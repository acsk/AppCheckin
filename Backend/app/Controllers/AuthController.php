<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Usuario;
use App\Services\JWTService;

class AuthController
{
    private Usuario $usuarioModel;
    private JWTService $jwtService;

    public function __construct()
    {
        $db = require __DIR__ . '/../../config/database.php';
        $this->usuarioModel = new Usuario($db);
        $this->jwtService = new JWTService($_ENV['JWT_SECRET']);
    }

    public function register(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $tenantId = $request->getAttribute('tenantId', 1);

        // Validações
        $errors = [];

        if (empty($data['nome'])) {
            $errors[] = 'Nome é obrigatório';
        }

        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email válido é obrigatório';
        }

        if (empty($data['senha']) || strlen($data['senha']) < 6) {
            $errors[] = 'Senha deve ter no mínimo 6 caracteres';
        }

        // Verificar se email já existe
        if (!empty($data['email']) && $this->usuarioModel->emailExists($data['email'], null, $tenantId)) {
            $errors[] = 'Email já cadastrado';
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode([
                'errors' => $errors
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        // Criar usuário
        $userId = $this->usuarioModel->create($data, $tenantId);

        if (!$userId) {
            $response->getBody()->write(json_encode([
                'error' => 'Erro ao criar usuário'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        // Gerar token
        $token = $this->jwtService->encode([
            'user_id' => $userId,
            'email' => $data['email'],
            'tenant_id' => $tenantId
        ]);

        $usuario = $this->usuarioModel->findById($userId);

        $response->getBody()->write(json_encode([
            'message' => 'Usuário criado com sucesso',
            'token' => $token,
            'user' => $usuario
        ]));

        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $tenantId = $request->getAttribute('tenantId', 1);

        // Validações
        if (empty($data['email']) || empty($data['senha'])) {
            $response->getBody()->write(json_encode([
                'error' => 'Email e senha são obrigatórios'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        // Buscar usuário
        $usuario = $this->usuarioModel->findByEmail($data['email'], $tenantId);

        if (!$usuario || !password_verify($data['senha'], $usuario['senha_hash'])) {
            $response->getBody()->write(json_encode([
                'error' => 'Credenciais inválidas'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        // Gerar token
        $token = $this->jwtService->encode([
            'user_id' => $usuario['id'],
            'email' => $usuario['email'],
            'tenant_id' => $tenantId
        ]);

        // Remover senha do retorno
        unset($usuario['senha_hash']);

        $response->getBody()->write(json_encode([
            'message' => 'Login realizado com sucesso',
            'token' => $token,
            'user' => $usuario
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function logout(Request $request, Response $response): Response
    {
        // No backend stateless (JWT), apenas confirmamos o logout
        // O cliente irá remover o token do localStorage
        $response->getBody()->write(json_encode([
            'message' => 'Logout realizado com sucesso'
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
