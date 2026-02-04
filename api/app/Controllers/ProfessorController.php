<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Professor;
use App\Models\Tenant;
use App\Models\Usuario;
use PDO;
use OpenApi\Attributes as OA;

/**
 * ProfessorController
 * 
 * CRUD completo para gestão de professores.
 * 
 * ARQUITETURA SIMPLIFICADA (2026-02-03):
 * - professores: Cadastro global (nome, cpf, email, foto, usuario_id)
 * - tenant_usuario_papel: Vínculo com tenant (papel_id=2 para professor)
 * - usuarios: Dados de autenticação
 * 
 * FLUXO DE ASSOCIAÇÃO:
 * professores.usuario_id → tenant_usuario_papel.usuario_id (papel_id=2)
 * 
 * Rotas:
 * - GET    /admin/professores              - Listar professores do tenant
 * - GET    /admin/professores/{id}         - Buscar professor por ID
 * - GET    /admin/professores/cpf/{cpf}    - Buscar professor por CPF
 * - POST   /admin/professores              - Criar e associar professor
 * - PUT    /admin/professores/{id}         - Atualizar professor
 * - DELETE /admin/professores/{id}         - Desativar professor
 */
class ProfessorController
{
    private PDO $db;
    private Professor $professorModel;
    private Tenant $tenantModel;
    private Usuario $usuarioModel;

    public function __construct()
    {
        $this->db = require __DIR__ . '/../../config/database.php';
        $this->professorModel = new Professor($this->db);
        $this->tenantModel = new Tenant($this->db);
        $this->usuarioModel = new Usuario($this->db);
    }

    /**
     * Listar professores do tenant
     * GET /admin/professores
     */
    #[OA\Get(
        path: "/admin/professores",
        summary: "Listar professores do tenant",
        description: "Retorna lista de professores vinculados ao tenant via tenant_usuario_papel (papel_id=2)",
        tags: ["Professores"],
        parameters: [
            new OA\Parameter(
                name: "apenas_ativos",
                description: "Filtrar apenas professores ativos (true/false)",
                in: "query",
                schema: new OA\Schema(type: "string", enum: ["true", "false"], default: "false")
            )
        ],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Lista de professores",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "professores",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer", example: 1),
                                    new OA\Property(property: "nome", type: "string", example: "Carlos Mendes"),
                                    new OA\Property(property: "cpf", type: "string", example: "12345678901"),
                                    new OA\Property(property: "email", type: "string", example: "carlos@teste.com"),
                                    new OA\Property(property: "foto_url", type: "string", nullable: true),
                                    new OA\Property(property: "ativo", type: "integer", example: 1),
                                    new OA\Property(property: "usuario_id", type: "integer", example: 5),
                                    new OA\Property(property: "telefone", type: "string", example: "11999999999"),
                                    new OA\Property(property: "vinculo_ativo", type: "integer", example: 1, description: "Status do vínculo em tenant_usuario_papel"),
                                    new OA\Property(property: "turmas_count", type: "integer", example: 3)
                                ],
                                type: "object"
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Não autorizado")
        ]
    )]
    public function index(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $queryParams = $request->getQueryParams();
        $apenasAtivos = isset($queryParams['apenas_ativos']) && $queryParams['apenas_ativos'] === 'true';
        
        $professores = $this->professorModel->listarPorTenant($tenantId, $apenasAtivos);
        
        $response->getBody()->write(json_encode([
            'professores' => $professores
        ], JSON_UNESCAPED_UNICODE));
        
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Buscar professor por ID
     * GET /admin/professores/{id}
     */
    #[OA\Get(
        path: "/admin/professores/{id}",
        summary: "Buscar professor por ID",
        description: "Retorna dados completos do professor se ele pertencer ao tenant",
        tags: ["Professores"],
        parameters: [
            new OA\Parameter(
                name: "id",
                description: "ID do professor",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Professor encontrado",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "professor",
                            properties: [
                                new OA\Property(property: "id", type: "integer", example: 1),
                                new OA\Property(property: "nome", type: "string", example: "Carlos Mendes"),
                                new OA\Property(property: "cpf", type: "string", example: "12345678901"),
                                new OA\Property(property: "email", type: "string", example: "carlos@teste.com"),
                                new OA\Property(property: "foto_url", type: "string", nullable: true),
                                new OA\Property(property: "ativo", type: "integer", example: 1),
                                new OA\Property(property: "usuario_id", type: "integer", example: 5),
                                new OA\Property(property: "telefone", type: "string", example: "11999999999"),
                                new OA\Property(property: "vinculo_ativo", type: "integer", example: 1),
                                new OA\Property(property: "created_at", type: "string", format: "datetime"),
                                new OA\Property(property: "updated_at", type: "string", format: "datetime")
                            ],
                            type: "object"
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: "Professor não encontrado",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "type", type: "string", example: "error"),
                        new OA\Property(property: "message", type: "string", example: "Professor não encontrado")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Não autorizado")
        ]
    )]
    public function show(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId');
        
        $professor = $this->professorModel->findById($id, $tenantId);
        
        if (!$professor) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Professor não encontrado'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }
        
        $response->getBody()->write(json_encode([
            'professor' => $professor
        ], JSON_UNESCAPED_UNICODE));
        
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Buscar professor por CPF
     * GET /admin/professores/cpf/{cpf}
     */
    #[OA\Get(
        path: "/admin/professores/cpf/{cpf}",
        summary: "Buscar professor por CPF",
        description: "Busca professor pelo CPF dentro do tenant. Aceita CPF com ou sem formatação.",
        tags: ["Professores"],
        parameters: [
            new OA\Parameter(
                name: "cpf",
                description: "CPF do professor (11 dígitos, com ou sem formatação)",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", example: "12345678901")
            )
        ],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Professor encontrado",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "professor",
                            properties: [
                                new OA\Property(property: "id", type: "integer"),
                                new OA\Property(property: "nome", type: "string"),
                                new OA\Property(property: "cpf", type: "string"),
                                new OA\Property(property: "email", type: "string"),
                                new OA\Property(property: "vinculo_ativo", type: "integer"),
                                new OA\Property(property: "turmas_count", type: "integer")
                            ],
                            type: "object"
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "CPF inválido",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "type", type: "string", example: "error"),
                        new OA\Property(property: "message", type: "string", example: "CPF inválido. Deve conter 11 dígitos.")
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: "Professor não encontrado",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "type", type: "string", example: "error"),
                        new OA\Property(property: "message", type: "string", example: "Professor não encontrado com este CPF")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Não autorizado")
        ]
    )]
    public function getByCpf(Request $request, Response $response, array $args): Response
    {
        $cpf = $args['cpf'] ?? '';
        $tenantId = $request->getAttribute('tenantId');
        
        // Remover caracteres especiais do CPF (aceita com ou sem formatação)
        $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf);
        
        if (strlen($cpfLimpo) !== 11) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'CPF inválido. Deve conter 11 dígitos.'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
        }
        
        $professor = $this->professorModel->findByCpf($cpfLimpo, $tenantId);
        
        if (!$professor) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Professor não encontrado com este CPF'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }
        
        $response->getBody()->write(json_encode([
            'professor' => $professor
        ], JSON_UNESCAPED_UNICODE));
        
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Buscar professor por CPF globalmente (sem filtro de tenant)
     * GET /admin/professores/global/cpf/{cpf}
     * 
     * Este endpoint é útil para verificar se um professor existe no sistema
     * antes de associá-lo a um tenant.
     */
    #[OA\Get(
        path: "/admin/professores/global/cpf/{cpf}",
        summary: "Buscar professor por CPF (busca global)",
        description: "Busca professor pelo CPF em todo o sistema, independente do tenant. Útil para verificar se um professor existe antes de associá-lo ao tenant.",
        tags: ["Professores"],
        parameters: [
            new OA\Parameter(
                name: "cpf",
                description: "CPF do professor (11 dígitos, com ou sem formatação)",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", example: "12345678901")
            )
        ],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Professor encontrado",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "professor",
                            properties: [
                                new OA\Property(property: "id", type: "integer", example: 101),
                                new OA\Property(property: "nome", type: "string", example: "Maria Oliveira"),
                                new OA\Property(property: "cpf", type: "string", example: "11122233344"),
                                new OA\Property(property: "email", type: "string", example: "prof.maria@exemplo.com"),
                                new OA\Property(property: "foto_url", type: "string", nullable: true),
                                new OA\Property(property: "ativo", type: "integer", example: 1),
                                new OA\Property(property: "usuario_id", type: "integer", example: 101),
                                new OA\Property(property: "telefone", type: "string", example: "11987654321"),
                                new OA\Property(property: "vinculado_ao_tenant_atual", type: "boolean", example: false, description: "Indica se já está vinculado ao tenant do usuário autenticado"),
                                new OA\Property(property: "created_at", type: "string", format: "datetime"),
                                new OA\Property(property: "updated_at", type: "string", format: "datetime")
                            ],
                            type: "object"
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "CPF inválido",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "type", type: "string", example: "error"),
                        new OA\Property(property: "message", type: "string", example: "CPF inválido. Deve conter 11 dígitos.")
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: "Professor não encontrado",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "type", type: "string", example: "error"),
                        new OA\Property(property: "message", type: "string", example: "Professor não encontrado no sistema")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Não autorizado")
        ]
    )]
    public function getByCpfGlobal(Request $request, Response $response, array $args): Response
    {
        $cpf = $args['cpf'] ?? '';
        $tenantId = $request->getAttribute('tenantId');
        
        // Remover caracteres especiais do CPF (aceita com ou sem formatação)
        $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf);
        
        if (strlen($cpfLimpo) !== 11) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'CPF inválido. Deve conter 11 dígitos.'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
        }
        
        // Busca global (sem filtro de tenant)
        $professor = $this->professorModel->findByCpfGlobal($cpfLimpo);
        
        if (!$professor) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Professor não encontrado no sistema'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }
        
        // Verificar se está vinculado ao tenant atual
        $vinculadoAoTenant = $this->professorModel->pertenceAoTenant($professor['id'], $tenantId);
        $professor['vinculado_ao_tenant_atual'] = $vinculadoAoTenant;
        
        $response->getBody()->write(json_encode([
            'professor' => $professor
        ], JSON_UNESCAPED_UNICODE));
        
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Criar novo professor
     * POST /admin/professores
     * 
     * FLUXO ATUALIZADO (2026-02-03):
     * 1. Valida EMAIL e CPF obrigatórios
     * 2. Consulta tabela usuarios para verificar se EMAIL ou CPF já existem
     * 3. Se existir, busca professor global e associa ao tenant
     * 4. Se não existir, cria usuário, professor e associa ao tenant
     */
    #[OA\Post(
        path: "/admin/professores",
        summary: "Criar e associar professor ao tenant",
        description: "Cria novo professor (ou associa existente) e vincula ao tenant via tenant_usuario_papel (papel_id=2). Se o professor já existe globalmente, apenas associa ao tenant. Se não existe, cria usuário + professor + associação.",
        tags: ["Professores"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["nome", "email", "cpf"],
                properties: [
                    new OA\Property(property: "nome", type: "string", example: "João Silva", description: "Nome completo do professor (obrigatório)"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "joao.silva@exemplo.com", description: "Email do professor (obrigatório)"),
                    new OA\Property(property: "cpf", type: "string", example: "12345678901", description: "CPF com 11 dígitos (obrigatório)"),
                    new OA\Property(property: "telefone", type: "string", example: "11999998888", description: "Telefone (opcional)"),
                    new OA\Property(property: "foto_url", type: "string", format: "url", example: "https://exemplo.com/foto.jpg", description: "URL da foto (opcional)")
                ]
            )
        ),
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 201,
                description: "Professor criado/associado com sucesso",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "type", type: "string", example: "success"),
                        new OA\Property(property: "message", type: "string", example: "Professor criado com sucesso"),
                        new OA\Property(
                            property: "professor",
                            properties: [
                                new OA\Property(property: "id", type: "integer", example: 101),
                                new OA\Property(property: "nome", type: "string", example: "João Silva"),
                                new OA\Property(property: "cpf", type: "string", example: "12345678901"),
                                new OA\Property(property: "email", type: "string", example: "joao.silva@exemplo.com"),
                                new OA\Property(property: "vinculo_ativo", type: "integer", example: 1, description: "1 = vinculado ao tenant")
                            ],
                            type: "object"
                        ),
                        new OA\Property(
                            property: "usuario",
                            properties: [
                                new OA\Property(property: "id", type: "integer", example: 150),
                                new OA\Property(property: "criado", type: "boolean", example: true, description: "true se criou novo usuário, false se já existia"),
                                new OA\Property(property: "vinculado_ao_tenant", type: "boolean", example: true),
                                new OA\Property(property: "papel", type: "string", example: "professor", description: "papel_id=2")
                            ],
                            type: "object"
                        ),
                        new OA\Property(property: "professor_existia", type: "boolean", example: false, description: "true se professor já existia globalmente"),
                        new OA\Property(
                            property: "credenciais",
                            properties: [
                                new OA\Property(property: "email", type: "string", example: "joao.silva@exemplo.com"),
                                new OA\Property(property: "senha_temporaria", type: "string", example: "Xy89Kp2m"),
                                new OA\Property(property: "mensagem", type: "string", example: "Informe estas credenciais ao professor. Recomende trocar a senha no primeiro acesso.")
                            ],
                            type: "object",
                            description: "Apenas quando cria novo usuário"
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "Dados inválidos",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "type", type: "string", example: "error"),
                        new OA\Property(property: "message", type: "string", example: "Email é obrigatório para criar professor")
                    ]
                )
            ),
            new OA\Response(
                response: 409,
                description: "Conflito - Professor já cadastrado",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "type", type: "string", example: "error"),
                        new OA\Property(property: "message", type: "string", example: "Professor já está vinculado a este tenant")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(
                response: 500,
                description: "Erro interno",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "type", type: "string", example: "error"),
                        new OA\Property(property: "message", type: "string", example: "Erro ao criar professor: ...")
                    ]
                )
            )
        ]
    )]
    public function create(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $data = $request->getParsedBody();
        
        // Validações básicas
        if (empty($data['nome'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Nome do professor é obrigatório'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
        }
        
        // Email é obrigatório
        if (empty($data['email'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Email é obrigatório para criar professor'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
        }
        
        // CPF é obrigatório
        if (empty($data['cpf'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'CPF é obrigatório para criar professor'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
        }
        
        // Limpar CPF
        $cpfLimpo = preg_replace('/[^0-9]/', '', $data['cpf']);
        if (strlen($cpfLimpo) !== 11) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'CPF inválido. Deve conter 11 dígitos'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
        }
        
        try {
            $this->db->beginTransaction();
            
            // PASSO 1: Consultar tabela usuarios para verificar se EMAIL ou CPF já existem
            $usuarioExistente = null;
            
            // Verificar por EMAIL primeiro
            $usuarioPorEmail = $this->usuarioModel->findByEmailGlobal($data['email']);
            if ($usuarioPorEmail) {
                $usuarioExistente = $usuarioPorEmail;
            }
            
            // Verificar por CPF (se não encontrou por email)
            if (!$usuarioExistente) {
                $usuarioPorCpf = $this->usuarioModel->findByCpfGlobal($cpfLimpo);
                if ($usuarioPorCpf) {
                    // CPF já existe mas com email diferente
                    $this->db->rollBack();
                    $response->getBody()->write(json_encode([
                        'type' => 'error',
                        'message' => 'CPF já cadastrado para outro usuário no sistema'
                    ], JSON_UNESCAPED_UNICODE));
                    return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(409);
                }
            }
            
            $usuarioId = null;
            $senhaTemporaria = null;
            $usuarioCriado = false;
            $professorExisteGlobal = false;
            $professorId = null;
            
            if ($usuarioExistente) {
                // PASSO 2: Usuário já existe - buscar ou criar professor
                $usuarioId = (int) $usuarioExistente['id'];
                
                // Verificar se já existe professor global com esse usuario_id
                $professorGlobal = $this->professorModel->findByUsuarioId($usuarioId);
                
                if ($professorGlobal) {
                    // Professor já existe globalmente
                    $professorExisteGlobal = true;
                    $professorId = $professorGlobal['id'];
                    
                    // Verificar se já está vinculado a este tenant
                    if ($this->professorModel->pertenceAoTenant($professorId, $tenantId)) {
                        $this->db->rollBack();
                        $response->getBody()->write(json_encode([
                            'type' => 'error',
                            'message' => 'Professor já está vinculado a este tenant'
                        ], JSON_UNESCAPED_UNICODE));
                        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(409);
                    }
                    
                    // Associar ao tenant
                    $this->professorModel->associarAoTenant($professorId, $tenantId, 'ativo');
                } else {
                    // Usuário existe mas não é professor - criar registro de professor
                    $professorData = [
                        'usuario_id' => $usuarioId,
                        'nome' => $data['nome'],
                        'cpf' => $cpfLimpo,
                        'email' => $data['email'],
                        'foto_url' => $data['foto_url'] ?? null,
                        'ativo' => 1
                    ];
                    $professorId = $this->professorModel->create($professorData);
                    
                    // Associar ao tenant
                    $this->professorModel->associarAoTenant($professorId, $tenantId, 'ativo');
                }
                
            } else {
                // PASSO 3: Usuário não existe - criar tudo do zero
                $senhaTemporaria = $this->gerarSenhaTemporaria();
                
                // Criar usuário
                $usuarioData = [
                    'nome' => $data['nome'],
                    'email' => $data['email'],
                    'email_global' => $data['email'],
                    'senha' => $senhaTemporaria,
                    'telefone' => $data['telefone'] ?? null,
                    'cpf' => $cpfLimpo,
                    'ativo' => 1
                ];
                
                $usuarioId = $this->usuarioModel->create($usuarioData, $tenantId);
                $usuarioCriado = true;
                
                // Criar professor
                $professorData = [
                    'usuario_id' => $usuarioId,
                    'nome' => $data['nome'],
                    'cpf' => $cpfLimpo,
                    'email' => $data['email'],
                    'foto_url' => $data['foto_url'] ?? null,
                    'ativo' => 1
                ];
                $professorId = $this->professorModel->create($professorData);
                
                // Associar ao tenant
                $this->professorModel->associarAoTenant($professorId, $tenantId, 'ativo');
            }
            
            // Buscar dados completos do professor
            $professor = $this->professorModel->findById($professorId, $tenantId);
            
            $this->db->commit();
            
            $responseData = [
                'type' => 'success',
                'message' => $professorExisteGlobal 
                    ? 'Professor existente associado ao tenant com sucesso' 
                    : 'Professor criado com sucesso',
                'professor' => $professor,
                'usuario' => [
                    'id' => $usuarioId,
                    'criado' => $usuarioCriado,
                    'vinculado_ao_tenant' => true,
                    'papel' => 'professor'
                ],
                'professor_existia' => $professorExisteGlobal
            ];
            
            // Se criou novo usuário, incluir senha temporária na resposta
            if ($usuarioCriado && $senhaTemporaria) {
                $responseData['credenciais'] = [
                    'email' => $data['email'],
                    'senha_temporaria' => $senhaTemporaria,
                    'mensagem' => 'Informe estas credenciais ao professor. Recomende trocar a senha no primeiro acesso.'
                ];
            }
            
            $response->getBody()->write(json_encode($responseData, JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(201);
        } catch (\Exception $e) {
            $this->db->rollBack();
            
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao criar professor: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }
    
    /**
     * Gera senha temporária aleatória
     */
    private function gerarSenhaTemporaria(int $tamanho = 8): string
    {
        $caracteres = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $senha = '';
        for ($i = 0; $i < $tamanho; $i++) {
            $senha .= $caracteres[random_int(0, strlen($caracteres) - 1)];
        }
        return $senha;
    }
    
    /**
     * Cria vínculo do usuário com tenant (se não existir)
     */
    private function criarVinculoTenant(int $usuarioId, int $tenantId): void
    {
        // Verificar se já existe vínculo como professor (papel_id = 2)
        $stmt = $this->db->prepare(
            "SELECT id FROM tenant_usuario_papel 
             WHERE usuario_id = :usuario_id AND tenant_id = :tenant_id AND papel_id = 2"
        );
        $stmt->execute(['usuario_id' => $usuarioId, 'tenant_id' => $tenantId]);
        
        if (!$stmt->fetch()) {
            $stmt = $this->db->prepare(
                "INSERT INTO tenant_usuario_papel (usuario_id, tenant_id, papel_id, ativo, created_at, updated_at) 
                 VALUES (:usuario_id, :tenant_id, 2, 1, NOW(), NOW())"
            );
            $stmt->execute([
                'usuario_id' => $usuarioId,
                'tenant_id' => $tenantId
            ]);
        }
    }
    
    /**
     * Adiciona um papel ao usuário no tenant
     */
    private function adicionarPapel(int $usuarioId, int $tenantId, int $papelId): void
    {
        $stmt = $this->db->prepare(
            "INSERT IGNORE INTO tenant_usuario_papel (tenant_id, usuario_id, papel_id, ativo) 
             VALUES (:tenant_id, :usuario_id, :papel_id, 1)"
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'usuario_id' => $usuarioId,
            'papel_id' => $papelId
        ]);
    }
    
    /**
     * Verifica se usuário tem um papel específico no tenant
     */
    private function temPapel(int $usuarioId, int $tenantId, int $papelId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM tenant_usuario_papel 
             WHERE usuario_id = :usuario_id AND tenant_id = :tenant_id AND papel_id = :papel_id AND ativo = 1"
        );
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'tenant_id' => $tenantId,
            'papel_id' => $papelId
        ]);
        return $stmt->fetch() !== false;
    }

    /**
     * Atualizar professor
     * PUT /admin/professores/{id}
     * Atualiza tanto a tabela 'professores' quanto 'usuarios' (email, senha)
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId');
        $data = $request->getParsedBody();
        
        // Verificar se professor existe e pertence ao tenant
        $professor = $this->professorModel->findById($id, $tenantId);
        if (!$professor) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Professor não encontrado'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }
        
        // Validar email único (se estiver sendo alterado)
        if (isset($data['email']) && !empty($data['email'])) {
            $stmt = $this->db->prepare(
                "SELECT id FROM usuarios WHERE email = :email AND id != :usuario_id"
            );
            $stmt->execute([
                'email' => $data['email'],
                'usuario_id' => $professor['usuario_id']
            ]);
            if ($stmt->fetch()) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Email já está em uso por outro usuário'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(422);
            }
        }
        
        try {
            $this->db->beginTransaction();
            
            // Atualizar dados do professor (perfil)
            $this->professorModel->update($id, $data);
            
            // Se tiver email ou senha, atualizar em usuarios também
            if (isset($data['email']) || isset($data['senha'])) {
                $usuarioData = [];
                if (isset($data['email'])) {
                    $usuarioData['email'] = $data['email'];
                }
                if (isset($data['senha']) && !empty($data['senha'])) {
                    $usuarioData['senha'] = $data['senha'];
                }
                if (!empty($usuarioData)) {
                    $this->usuarioModel->update($professor['usuario_id'], $usuarioData);
                }
            }
            
            $this->db->commit();
            
            $professorAtualizado = $this->professorModel->findById($id, $tenantId);
            
            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Professor atualizado com sucesso',
                'professor' => $professorAtualizado
            ], JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        } catch (\Exception $e) {
            $this->db->rollBack();
            
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao atualizar professor: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Deletar professor
     * DELETE /admin/professores/{id}
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId');
        
        // Verificar se professor existe e pertence ao tenant
        $professor = $this->professorModel->findById($id, $tenantId);
        if (!$professor) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Professor não encontrado'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }
        
        try {
            $this->professorModel->delete($id);
            
            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Professor deletado com sucesso'
            ], JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao deletar professor: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }
}
