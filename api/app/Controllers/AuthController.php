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

        // Validações
        if (empty($data['email']) || empty($data['senha'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'code' => 'MISSING_CREDENTIALS',
                'message' => 'Email e senha são obrigatórios'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        // Buscar usuário por email global (independente de tenant)
        $usuario = $this->usuarioModel->findByEmailGlobal($data['email']);

        if (!$usuario || !password_verify($data['senha'], $usuario['senha_hash'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'Email ou senha inválidos'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        // Inicializar token
        $token = null;

        // Super admin (role_id = 3) não precisa de vínculo com tenant
        if ($usuario['role_id'] == 3) {
            // Super admin: pode acessar sem tenant específico
            $tenants = [];
            
            // Gerar token sem tenant_id
            $token = $this->jwtService->encode([
                'user_id' => $usuario['id'],
                'email' => $usuario['email'],
                'tenant_id' => null,
                'is_super_admin' => true
            ]);
        } else {
            // Buscar todos os tenants/academias do usuário
            $tenants = $this->usuarioModel->getTenantsByUsuario($usuario['id']);

            if (empty($tenants)) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'code' => 'NO_TENANT_ACCESS',
                    'message' => 'Usuário não possui vínculo com nenhuma academia'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }
        }

        // Se usuário tem apenas um tenant, já retorna o token com ele
        // Se tem múltiplos, retorna a lista para o usuário escolher
        $tenantId = null;

        if ($usuario['role_id'] == 3) {
            // Super admin já tem token gerado acima, não precisa fazer nada
        } else if (count($tenants) === 1) {
            $tenantId = $tenants[0]['tenant']['id'];
            
            // Se for Tenant Admin (role_id = 2), verificar contrato ativo
            if ($usuario['role_id'] == 2) {
                $db = require __DIR__ . '/../../config/database.php';
                $stmt = $db->prepare("
                    SELECT COUNT(*) as tem_contrato
                    FROM tenant_planos_sistema
                    WHERE tenant_id = :tenant_id
                    AND status_id = 1
                ");
                $stmt->execute(['tenant_id' => $tenantId]);
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if ($result['tem_contrato'] == 0) {
                    $response->getBody()->write(json_encode([
                        'type' => 'error',
                        'code' => 'NO_ACTIVE_CONTRACT',
                        'message' => 'Sua academia não possui contrato ativo. Entre em contato com o suporte.'
                    ], JSON_UNESCAPED_UNICODE));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
                }
            }
            
            // Gerar token com tenant único
            $token = $this->jwtService->encode([
                'user_id' => $usuario['id'],
                'email' => $usuario['email'],
                'tenant_id' => $tenantId
            ]);
        }

        // Remover senha do retorno
        unset($usuario['senha_hash']);

        $response->getBody()->write(json_encode([
            'message' => 'Login realizado com sucesso',
            'token' => $token, // null se múltiplos tenants
            'user' => [
                'id' => $usuario['id'],
                'nome' => $usuario['nome'],
                'email' => $usuario['email'],
                'email_global' => $usuario['email_global'] ?? $usuario['email'], // fallback para email se email_global não existir
                'foto_base64' => $usuario['foto_base64'] ?? null,
                'role_id' => $usuario['role_id'] ?? null
            ],
            'tenants' => $tenants,
            'requires_tenant_selection' => count($tenants) > 1
        ]));

        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
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

    /**
     * Selecionar tenant/academia após login (quando usuário tem múltiplos contratos)
     */
    public function selectTenant(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $userId = $request->getAttribute('userId'); // Do JWT

        if (empty($data['tenant_id'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'code' => 'MISSING_TENANT_ID',
                'message' => 'tenant_id é obrigatório'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        $tenantId = (int) $data['tenant_id'];

        // Verificar se usuário tem acesso a este tenant
        if (!$this->usuarioModel->temAcessoTenant($userId, $tenantId)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'code' => 'TENANT_ACCESS_DENIED',
                'message' => 'Você não tem acesso a esta academia'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Buscar dados do usuário
        $usuario = $this->usuarioModel->findById($userId);
        
        // Se for Tenant Admin (role_id = 2), verificar contrato ativo
        if ($usuario['role_id'] == 2) {
            $db = require __DIR__ . '/../../config/database.php';
            $stmt = $db->prepare("
                SELECT COUNT(*) as tem_contrato
                FROM tenant_planos_sistema
                WHERE tenant_id = :tenant_id
                AND status_id = 1
            ");
            $stmt->execute(['tenant_id' => $tenantId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($result['tem_contrato'] == 0) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'code' => 'NO_ACTIVE_CONTRACT',
                    'message' => 'Esta academia não possui contrato ativo. Entre em contato com o suporte.'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }
        }

        // Gerar novo token com o tenant selecionado
        $token = $this->jwtService->encode([
            'user_id' => $usuario['id'],
            'email' => $usuario['email'],
            'tenant_id' => $tenantId
        ]);

        // Buscar informações do tenant selecionado
        $tenants = $this->usuarioModel->getTenantsByUsuario($userId);
        $tenantSelecionado = null;
        
        foreach ($tenants as $t) {
            if ($t['tenant']['id'] === $tenantId) {
                $tenantSelecionado = $t;
                break;
            }
        }

        // Remover senha do retorno
        unset($usuario['senha_hash']);

        $response->getBody()->write(json_encode([
            'message' => 'Academia selecionada com sucesso',
            'token' => $token,
            'user' => [
                'id' => $usuario['id'],
                'nome' => $usuario['nome'],
                'email' => $usuario['email'],
                'email_global' => $usuario['email_global'] ?? $usuario['email'],
                'foto_base64' => $usuario['foto_base64'] ?? null,
                'role_id' => $usuario['role_id'] ?? null
            ],
            'tenant' => $tenantSelecionado
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Seleção inicial de tenant durante login (rota pública)
     * Usada quando o login retorna múltiplos tenants sem token
     */
    public function selectTenantPublic(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        // Validações
        if (empty($data['user_id']) || empty($data['email']) || empty($data['tenant_id'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'code' => 'MISSING_REQUIRED_FIELDS',
                'message' => 'user_id, email e tenant_id são obrigatórios'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        $userId = (int) $data['user_id'];
        $email = $data['email'];
        $tenantId = (int) $data['tenant_id'];

        // Buscar usuário e verificar email
        $usuario = $this->usuarioModel->findById($userId);
        
        if (!$usuario || ($usuario['email'] !== $email && ($usuario['email_global'] ?? '') !== $email)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'code' => 'INVALID_USER_DATA',
                'message' => 'Dados inválidos'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        // Verificar se usuário tem acesso a este tenant
        if (!$this->usuarioModel->temAcessoTenant($userId, $tenantId)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'code' => 'TENANT_ACCESS_DENIED',
                'message' => 'Você não tem acesso a esta academia'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Gerar token com o tenant selecionado
        $token = $this->jwtService->encode([
            'user_id' => $usuario['id'],
            'email' => $usuario['email'],
            'tenant_id' => $tenantId
        ]);

        // Buscar informações do tenant selecionado
        $tenants = $this->usuarioModel->getTenantsByUsuario($userId);
        $tenantSelecionado = null;
        
        foreach ($tenants as $t) {
            if ($t['tenant']['id'] === $tenantId) {
                $tenantSelecionado = $t;
                break;
            }
        }

        // Remover senha do retorno
        unset($usuario['senha_hash']);

        $response->getBody()->write(json_encode([
            'message' => 'Academia selecionada com sucesso',
            'token' => $token,
            'user' => [
                'id' => $usuario['id'],
                'nome' => $usuario['nome'],
                'email' => $usuario['email'],
                'email_global' => $usuario['email_global'] ?? $usuario['email'],
                'foto_base64' => $usuario['foto_base64'] ?? null,
                'role_id' => $usuario['role_id'] ?? null
            ],
            'tenant' => $tenantSelecionado,
            'tenants' => $tenants
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Solicitar recuperação de senha
     */
    public function forgotPassword(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        if (empty($data['email'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'code' => 'MISSING_EMAIL',
                'message' => 'Email é obrigatório'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        $email = $data['email'];

        // Buscar usuário por email
        $usuario = $this->usuarioModel->findByEmailGlobal($email);

        if (!$usuario) {
            // Por segurança, não informamos se o email existe ou não
            $response->getBody()->write(json_encode([
                'message' => 'Se o email existe em nossa base de dados, você receberá um link de recuperação'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        }

        // Gerar token único
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + (15 * 60)); // 15 minutos

        // Salvar token no banco
        $db = require __DIR__ . '/../../config/database.php';
        $stmt = $db->prepare("
            UPDATE usuarios
            SET password_reset_token = :token,
                password_reset_expires_at = :expires_at
            WHERE id = :usuario_id
        ");
        $stmt->execute([
            ':token' => $token,
            ':expires_at' => $expiresAt,
            ':usuario_id' => $usuario['id']
        ]);

        // Enviar email
        try {
            $mailService = new \App\Services\MailService();
            $mailService->sendPasswordRecoveryEmail(
                $usuario['email'],
                $usuario['nome'],
                $token,
                15
            );
        } catch (\Exception $e) {
            error_log("Erro ao enviar email de recuperação: " . $e->getMessage());
        }

        $response->getBody()->write(json_encode([
            'message' => 'Se o email existe em nossa base de dados, você receberá um link de recuperação'
        ]));

        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    /**
     * Validar token de recuperação de senha
     */
    public function validatePasswordToken(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        if (empty($data['token'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'code' => 'MISSING_TOKEN',
                'message' => 'Token é obrigatório'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        $token = $data['token'];

        // Buscar usuário pelo token
        $db = require __DIR__ . '/../../config/database.php';
        $stmt = $db->prepare("
            SELECT id, nome, email
            FROM usuarios
            WHERE password_reset_token = :token
            AND password_reset_expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute([':token' => $token]);
        $usuario = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$usuario) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'code' => 'INVALID_OR_EXPIRED_TOKEN',
                'message' => 'Token inválido ou expirado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        $response->getBody()->write(json_encode([
            'message' => 'Token válido',
            'user' => [
                'id' => $usuario['id'],
                'nome' => $usuario['nome'],
                'email' => $usuario['email']
            ]
        ]));

        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    /**
     * Resetar senha com token
     */
    public function resetPassword(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        // Validações
        $errors = [];

        if (empty($data['token'])) {
            $errors[] = 'Token é obrigatório';
        }

        if (empty($data['nova_senha']) || strlen($data['nova_senha']) < 6) {
            $errors[] = 'Nova senha deve ter no mínimo 6 caracteres';
        }

        if (empty($data['confirmacao_senha']) || $data['nova_senha'] !== $data['confirmacao_senha']) {
            $errors[] = 'As senhas não coincidem';
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'code' => 'VALIDATION_ERROR',
                'errors' => $errors
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        $token = $data['token'];
        $novaSenha = $data['nova_senha'];

        // Buscar usuário pelo token
        $db = require __DIR__ . '/../../config/database.php';
        $stmt = $db->prepare("
            SELECT id
            FROM usuarios
            WHERE password_reset_token = :token
            AND password_reset_expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute([':token' => $token]);
        $usuario = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$usuario) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'code' => 'INVALID_OR_EXPIRED_TOKEN',
                'message' => 'Token inválido ou expirado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        // Atualizar senha e limpar token
        $senhaHash = password_hash($novaSenha, PASSWORD_BCRYPT);
        $stmt = $db->prepare("
            UPDATE usuarios
            SET senha_hash = :senha_hash,
                password_reset_token = NULL,
                password_reset_expires_at = NULL
            WHERE id = :usuario_id
        ");
        $stmt->execute([
            ':senha_hash' => $senhaHash,
            ':usuario_id' => $usuario['id']
        ]);

        $response->getBody()->write(json_encode([
            'message' => 'Senha alterada com sucesso. Faça login com sua nova senha.'
        ]));

        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }
}
