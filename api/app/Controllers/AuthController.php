<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Usuario;
use App\Services\JWTService;
use OpenApi\Attributes as OA;

class AuthController
{
    private Usuario $usuarioModel;
    private JWTService $jwtService;
    private ?string $dbInitError = null;

    public function __construct()
    {
        try {
            $db = require __DIR__ . '/../../config/database.php';
            $this->usuarioModel = new Usuario($db);
        } catch (\Throwable $e) {
            // Evitar 500 "vazio" por falha ao instanciar controlador
            $this->dbInitError = $e->getMessage();
            error_log('[AuthController::__construct] DB init error: ' . $this->dbInitError);
        }
        $this->jwtService = new JWTService($_ENV['JWT_SECRET']);
    }

    #[OA\Post(
        path: "/auth/register",
        summary: "Registrar novo usuário",
        description: "Cria um novo usuário no sistema e retorna o token JWT",
        tags: ["Autenticação"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["nome", "email", "senha"],
                properties: [
                    new OA\Property(property: "nome", type: "string", example: "João Silva"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "joao@email.com"),
                    new OA\Property(property: "senha", type: "string", minLength: 6, example: "senha123")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Usuário criado com sucesso",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Usuário criado com sucesso"),
                        new OA\Property(property: "token", type: "string", example: "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."),
                        new OA\Property(property: "user", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Erro de validação"),
            new OA\Response(response: 500, description: "Erro interno")
        ]
    )]
    public function register(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $tenantId = $request->getAttribute('tenantId', 1);
            try {
                // Verificar se a conexão de DB falhou ao iniciar o controlador
                if ($this->dbInitError !== null || !isset($this->usuarioModel)) {
                    $response->getBody()->write(json_encode([
                        'status' => 'error',
                        'code' => 'DATABASE_CONNECTION_FAILED',
                        'message' => 'Falha ao conectar ao banco de dados',
                        'hint' => 'Verifique variáveis de ambiente (DB_HOST, DB_NAME, DB_USER, DB_PASS) e credenciais.',
                    ], JSON_UNESCAPED_UNICODE));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(503);
                }

                // Body parsing robusto (fallback para JSON manual)
                $data = $request->getParsedBody();
                if (!is_array($data)) {
                    $raw = (string)$request->getBody();
                    $decoded = json_decode($raw, true);
                    $data = is_array($decoded) ? $decoded : [];
                }

                // DEBUG: Log para verificar o que está chegando
                error_log("=== LOGIN DEBUG ===");
                error_log("Content-Type: " . ($request->getHeaderLine('Content-Type') ?? 'N/A'));
                error_log("Raw Body: " . (string)$request->getBody());
                error_log("Parsed Body: " . json_encode($data));
                $email = $data['email'] ?? null;
                $senha = $data['senha'] ?? null;
                error_log("Email recebido: " . ($email ?? 'VAZIO'));
                error_log("Senha recebida: " . ($senha !== null ? '[PRESENTE]' : 'VAZIO'));

                // Validações
                if (empty($email) || empty($senha)) {
                    $response->getBody()->write(json_encode([
                        'type' => 'error',
                        'code' => 'MISSING_CREDENTIALS',
                        'message' => 'Email e senha são obrigatórios'
                    ]));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
                }

                // Buscar usuário por email global (independente de tenant)
                $usuario = $this->usuarioModel->findByEmailGlobal($email);

                if (!$usuario || !isset($usuario['senha_hash']) || !password_verify($senha, $usuario['senha_hash'])) {
                    $response->getBody()->write(json_encode([
                        'type' => 'error',
                        'code' => 'INVALID_CREDENTIALS',
                        'message' => 'Email ou senha inválidos'
                    ]));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
                }

                // Inicializar token
                $token = null;

                // Buscar papel_id do usuário via tenant_usuario_papel
                $db = require __DIR__ . '/../../config/database.php';
                $stmtPapel = $db->prepare("\n                SELECT papel_id FROM tenant_usuario_papel \n                WHERE usuario_id = :usuario_id AND ativo = 1 \n                ORDER BY papel_id DESC LIMIT 1\n            ");
                $stmtPapel->execute(['usuario_id' => $usuario['id']]);
                $papelResult = $stmtPapel ? $stmtPapel->fetch(\PDO::FETCH_ASSOC) : null;
                $papelId = $papelResult ? (int)$papelResult['papel_id'] : null;

                // Super admin (papel_id = 4 na tabela papeis) não precisa de vínculo com tenant
                if ($papelId === 4) {
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

                if ($papelId === 4) {
                    // Super admin já tem token gerado acima, não precisa fazer nada
                } else if (count($tenants) === 1) {
                    $tenantId = $tenants[0]['tenant']['id'] ?? ($tenants[0]['tenant_id'] ?? null);
                
                    // Se for Tenant Admin (papel_id = 3), verificar contrato ativo
                    if ($papelId === 3 && $tenantId !== null) {
                        $db = require __DIR__ . '/../../config/database.php';
                        $stmt = $db->prepare("\n                        SELECT COUNT(*) as tem_contrato\n                        FROM tenant_planos_sistema\n                        WHERE tenant_id = :tenant_id\n                        AND status_id = 1\n                    ");
                        $stmt->execute(['tenant_id' => $tenantId]);
                        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
                    
                        if (!$result || (int)$result['tem_contrato'] === 0) {
                            $response->getBody()->write(json_encode([
                                'type' => 'error',
                                'code' => 'NO_ACTIVE_CONTRACT',
                                'message' => 'Sua academia não possui contrato ativo. Entre em contato com o suporte.'
                            ], JSON_UNESCAPED_UNICODE));
                            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
                        }
                    }
                
                    // Buscar aluno_id se o usuário for aluno (papel_id = 1)
                    $alunoId = null;
                    if ($papelId === 1) {
                        $db = require __DIR__ . '/../../config/database.php';
                        $stmtAluno = $db->prepare("SELECT id FROM alunos WHERE usuario_id = ?");
                        $stmtAluno->execute([$usuario['id']]);
                        $aluno = $stmtAluno->fetch(\PDO::FETCH_ASSOC);
                        if ($aluno) {
                            $alunoId = $aluno['id'];
                        }
                    }
                
                    // Gerar token com tenant único
                    $token = $this->jwtService->encode([
                        'user_id' => $usuario['id'],
                        'email' => $usuario['email'],
                        'tenant_id' => $tenantId,
                        'aluno_id' => $alunoId
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
                        'papel_id' => $papelId
                    ],
                    'tenants' => $tenants,
                    'requires_tenant_selection' => count($tenants) > 1
                ]));

                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            } catch (\Throwable $e) {
                error_log('[AuthController::login] EXCEPTION: ' . $e->getMessage());
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'code' => 'LOGIN_INTERNAL_ERROR',
                    'message' => 'Erro interno ao realizar login',
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
        $usuario = $this->usuarioModel->findByEmailGlobal($email);

        if (!$usuario || !password_verify($senha, $usuario['senha_hash'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'Email ou senha inválidos'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        // Inicializar token
        $token = null;

        // Buscar papel_id do usuário via tenant_usuario_papel
        $db = require __DIR__ . '/../../config/database.php';
        $stmtPapel = $db->prepare("
            SELECT papel_id FROM tenant_usuario_papel 
            WHERE usuario_id = :usuario_id AND ativo = 1 
            ORDER BY papel_id DESC LIMIT 1
        ");
        $stmtPapel->execute(['usuario_id' => $usuario['id']]);
        $papelResult = $stmtPapel->fetch(\PDO::FETCH_ASSOC);
        $papelId = $papelResult ? (int)$papelResult['papel_id'] : null;

        // Super admin (papel_id = 4 na tabela papeis) não precisa de vínculo com tenant
        if ($papelId === 4) {
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

        if ($papelId === 4) {
            // Super admin já tem token gerado acima, não precisa fazer nada
        } else if (count($tenants) === 1) {
            $tenantId = $tenants[0]['tenant']['id'];
            
            // Se for Tenant Admin (papel_id = 3), verificar contrato ativo
            if ($papelId === 3) {
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
            
            // Buscar aluno_id se o usuário for aluno (papel_id = 1)
            $alunoId = null;
            if ($papelId === 1) {
                $db = require __DIR__ . '/../../config/database.php';
                $stmtAluno = $db->prepare("SELECT id FROM alunos WHERE usuario_id = ?");
                $stmtAluno->execute([$usuario['id']]);
                $aluno = $stmtAluno->fetch(\PDO::FETCH_ASSOC);
                if ($aluno) {
                    $alunoId = $aluno['id'];
                }
            }
            
            // Gerar token com tenant único
            $token = $this->jwtService->encode([
                'user_id' => $usuario['id'],
                'email' => $usuario['email'],
                'tenant_id' => $tenantId,
                'aluno_id' => $alunoId
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
                'papel_id' => $papelId
            ],
            'tenants' => $tenants,
            'requires_tenant_selection' => count($tenants) > 1
        ]));

        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    #[OA\Post(
        path: "/auth/logout",
        summary: "Logout do usuário",
        description: "Confirma logout no backend. O cliente deve remover o token localmente.",
        tags: ["Autenticação"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Logout realizado com sucesso",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Logout realizado com sucesso")
                    ]
                )
            )
        ]
    )]
    public function logout(Request $request, Response $response): Response
    {
        // No backend stateless (JWT), apenas confirmamos o logout
        // O cliente irá remover o token do localStorage
        $response->getBody()->write(json_encode([
            'message' => 'Logout realizado com sucesso'
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    #[OA\Get(
        path: "/auth/tenants",
        summary: "Listar tenants do usuário",
        description: "Retorna os tenants/academias vinculados ao usuário autenticado e indica se é necessária seleção.",
        tags: ["Autenticação"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Tenants retornados com sucesso",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "tenants", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "requires_tenant_selection", type: "boolean", example: false),
                        new OA\Property(property: "current_tenant_id", type: "integer", nullable: true)
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Não autorizado")
        ]
    )]
    public function listTenants(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('userId');
        if (!$userId) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'code' => 'UNAUTHORIZED',
                'message' => 'Usuário não autenticado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        $tenants = $this->usuarioModel->getTenantsByUsuario((int)$userId);
        $currentTenantId = $request->getAttribute('tenantId');

        $response->getBody()->write(json_encode([
            'tenants' => $tenants,
            'requires_tenant_selection' => count($tenants) > 1,
            'current_tenant_id' => $currentTenantId
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Selecionar tenant/academia após login (quando usuário tem múltiplos contratos)
     */
    #[OA\Post(
        path: "/auth/select-tenant",
        summary: "Selecionar tenant/academia",
        description: "Após login com múltiplos tenants, seleciona qual academia acessar e retorna novo token.",
        tags: ["Autenticação"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["tenant_id"],
                properties: [
                    new OA\Property(property: "tenant_id", type: "integer", example: 1)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Tenant selecionado com sucesso",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Academia selecionada com sucesso"),
                        new OA\Property(property: "token", type: "string", example: "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."),
                        new OA\Property(property: "tenant", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 403, description: "Sem acesso ao tenant ou contrato inativo"),
            new OA\Response(response: 422, description: "tenant_id não informado")
        ]
    )]
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
        
        // Se for Tenant Admin (papel_id = 3), verificar contrato ativo
        if (($usuario['papel_id'] ?? null) == 3) {
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

        // Buscar aluno_id se o usuário for aluno (papel_id = 1)
        $alunoId = null;
        if (($usuario['papel_id'] ?? null) == 1) {
            $db = require __DIR__ . '/../../config/database.php';
            $stmtAluno = $db->prepare("SELECT id FROM alunos WHERE usuario_id = ?");
            $stmtAluno->execute([$usuario['id']]);
            $aluno = $stmtAluno->fetch(\PDO::FETCH_ASSOC);
            if ($aluno) {
                $alunoId = $aluno['id'];
            }
        }

        // Gerar novo token com o tenant selecionado
        $token = $this->jwtService->encode([
            'user_id' => $usuario['id'],
            'email' => $usuario['email'],
            'tenant_id' => $tenantId,
            'aluno_id' => $alunoId
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
                'papel_id' => $usuario['papel_id'] ?? null
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

        // Buscar aluno_id se o usuário for aluno (papel_id = 1)
        $alunoId = null;
        if (($usuario['papel_id'] ?? null) == 1) {
            $db = require __DIR__ . '/../../config/database.php';
            $stmtAluno = $db->prepare("SELECT id FROM alunos WHERE usuario_id = ?");
            $stmtAluno->execute([$usuario['id']]);
            $aluno = $stmtAluno->fetch(\PDO::FETCH_ASSOC);
            if ($aluno) {
                $alunoId = $aluno['id'];
            }
        }

        // Gerar token com o tenant selecionado
        $token = $this->jwtService->encode([
            'user_id' => $usuario['id'],
            'email' => $usuario['email'],
            'tenant_id' => $tenantId,
            'aluno_id' => $alunoId
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
                'papel_id' => $usuario['papel_id'] ?? null
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

        // Salvar token no banco - usar DATE_ADD do MySQL para garantir timezone correto
        $db = require __DIR__ . '/../../config/database.php';
        $stmt = $db->prepare("
            UPDATE usuarios
            SET password_reset_token = :token,
                password_reset_expires_at = DATE_ADD(NOW(), INTERVAL 60 MINUTE)
            WHERE id = :usuario_id
        ");
        $stmt->execute([
            ':token' => $token,
            ':usuario_id' => $usuario['id']
        ]);

        // Enviar email
        try {
            $mailService = new \App\Services\MailService($db);
            $mailService->sendPasswordRecoveryEmail(
                $usuario['email'],
                $usuario['nome'],
                $token,
                60,
                null, // tenantId
                $usuario['id'] // usuarioId para log
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
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
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
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
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
