<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Tenant;
use App\Models\Usuario;
use App\Models\TenantPlano;
use App\Models\PlanoSistema;

/**
 * Controller para operações exclusivas do Super Admin
 * - Criar academias/tenants
 * - Criar admins para academias
 * - Visualizar todas as academias
 * - Gerenciar planos do sistema
 */
class SuperAdminController
{
    private Tenant $tenantModel;
    private Usuario $usuarioModel;
    private TenantPlano $tenantPlanoModel;
    private PlanoSistema $planoSistemaModel;

    public function __construct()
    {
        $db = require __DIR__ . '/../../config/database.php';
        $this->tenantModel = new Tenant($db);
        $this->usuarioModel = new Usuario($db);
        $this->tenantPlanoModel = new TenantPlano($db);
        $this->planoSistemaModel = new PlanoSistema($db);
    }

    /**
     * Listar todas as academias/tenants
     * GET /superadmin/academias
     */
    public function listarAcademias(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('userId');
        $user = $this->usuarioModel->findById($userId);

        // Verificar se é super admin
        if (($user['papel_id'] ?? null) != 4) {
            $response->getBody()->write(json_encode([
                'error' => 'Acesso negado. Apenas Super Admin pode acessar'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $queryParams = $request->getQueryParams();
        $semContratoAtivo = isset($queryParams['sem_contrato_ativo']) && $queryParams['sem_contrato_ativo'] === 'true';
        
        // Preparar filtros
        $filtros = [];
        if (!empty($queryParams['busca'])) {
            $filtros['busca'] = $queryParams['busca'];
        }
        if (isset($queryParams['ativo'])) {
            $filtros['ativo'] = $queryParams['ativo'] === 'true' || $queryParams['ativo'] === '1';
        }

        $academias = $this->tenantModel->getAll($filtros);

        // Se solicitado, filtrar apenas academias sem contrato ativo
        if ($semContratoAtivo) {
            $academias = array_filter($academias, function($academia) {
                $contratoAtivo = $this->tenantPlanoModel->buscarContratoAtivo($academia['id']);
                return !$contratoAtivo;
            });
            $academias = array_values($academias); // Reindexar array
        }

        $response->getBody()->write(json_encode([
            'academias' => $academias
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Criar nova academia/tenant
     * POST /superadmin/academias
     */
    public function criarAcademia(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $userId = $request->getAttribute('userId');
        $user = $this->usuarioModel->findById($userId);

        // Verificar se é super admin
        if (($user['papel_id'] ?? null) != 4) {
            $response->getBody()->write(json_encode([
                'error' => 'Acesso negado. Apenas Super Admin pode criar academias'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Validações
        $errors = [];

        if (empty($data['nome'])) {
            $errors[] = 'Nome da academia é obrigatório';
        }

        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email válido é obrigatório';
        }

        // Validar CNPJ se fornecido
        if (!empty($data['cnpj'])) {
            $cnpj = preg_replace('/[^0-9]/', '', $data['cnpj']);
            if (strlen($cnpj) != 14) {
                $errors[] = 'CNPJ inválido. Deve conter 14 dígitos';
            }
        }

        // Validar senha do admin
        if (empty($data['senha_admin'])) {
            $errors[] = 'Senha do administrador é obrigatória';
        } elseif (strlen($data['senha_admin']) < 6) {
            $errors[] = 'Senha do administrador deve ter no mínimo 6 caracteres';
        }

        // Gerar slug a partir do nome
        $slug = $this->generateSlug($data['nome'] ?? '');
        if ($this->tenantModel->findBySlug($slug)) {
            $errors[] = 'Já existe uma academia com este nome';
        }

        // Verificar se email já está em uso
        if ($this->usuarioModel->emailExists($data['email'], null, null)) {
            $errors[] = 'Email já está sendo utilizado por outro usuário';
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode(['errors' => $errors]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        // Criar academia (sem plano_id direto)
        $academiaData = [
            'nome' => $data['nome'],
            'slug' => $slug,
            'email' => $data['email'],
            'cnpj' => isset($data['cnpj']) ? preg_replace('/[^0-9]/', '', $data['cnpj']) : null,
            'telefone' => isset($data['telefone']) ? preg_replace('/[^0-9]/', '', $data['telefone']) : null,
            'responsavel_nome' => $data['responsavel_nome'] ?? null,
            'responsavel_cpf' => isset($data['responsavel_cpf']) ? preg_replace('/[^0-9]/', '', $data['responsavel_cpf']) : null,
            'responsavel_telefone' => isset($data['responsavel_telefone']) ? preg_replace('/[^0-9]/', '', $data['responsavel_telefone']) : null,
            'responsavel_email' => $data['responsavel_email'] ?? null,
            'cep' => $data['cep'] ?? null,
            'logradouro' => $data['logradouro'] ?? null,
            'numero' => $data['numero'] ?? null,
            'complemento' => $data['complemento'] ?? null,
            'bairro' => $data['bairro'] ?? null,
            'cidade' => $data['cidade'] ?? null,
            'estado' => $data['estado'] ?? null,
            'endereco' => $data['endereco'] ?? null
        ];

        try {
            $tenantId = $this->tenantModel->create($academiaData);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() == 409 ? 409 : 500;
            $response->getBody()->write(json_encode([
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
        }

        if (!$tenantId) {
            $response->getBody()->write(json_encode([
                'error' => 'Erro ao criar academia'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        // Criar contrato de plano se fornecido
        if (!empty($data['plano_sistema_id'])) {
            $contratoData = [
                'tenant_id' => $tenantId,
                'plano_sistema_id' => $data['plano_sistema_id'],
                'data_inicio' => date('Y-m-d'),
                'data_vencimento' => date('Y-m-d', strtotime('+1 month')),
                'forma_pagamento' => $data['forma_pagamento'] ?? 'pix',
                'observacoes' => 'Contrato criado junto com a academia'
            ];
            
            try {
                $this->tenantPlanoModel->criar($contratoData);
            } catch (\Exception $e) {
                // Log do erro, mas não falha a criação da academia
                error_log("Erro ao criar contrato inicial: " . $e->getMessage());
            }
        }

        // Criar usuário administrador da academia usando dados do responsável
        $adminData = [
            'nome' => $data['responsavel_nome'] ?? $data['nome'], // Usa nome do responsável ou nome da academia
            'email' => $data['responsavel_email'] ?? $data['email'], // Email do responsável ou email da academia
            'senha' => $data['senha_admin'],
            'telefone' => isset($data['responsavel_telefone']) ? preg_replace('/[^0-9]/', '', $data['responsavel_telefone']) : null,
            'cpf' => isset($data['responsavel_cpf']) ? preg_replace('/[^0-9]/', '', $data['responsavel_cpf']) : null,
            'cep' => $data['cep'] ?? null,
            'logradouro' => $data['logradouro'] ?? null,
            'numero' => $data['numero'] ?? null,
            'complemento' => $data['complemento'] ?? null,
            'bairro' => $data['bairro'] ?? null,
            'cidade' => $data['cidade'] ?? null,
            'estado' => $data['estado'] ?? null,
            'papel_id' => 3 // Admin
        ];

        $adminId = $this->usuarioModel->criarUsuarioCompleto($adminData, $tenantId);

        if (!$adminId) {
            // Rollback: deletar a academia se não conseguir criar o admin
            $this->tenantModel->delete($tenantId);
            $response->getBody()->write(json_encode([
                'error' => 'Erro ao criar usuário administrador da academia'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $response->getBody()->write(json_encode([
            'message' => 'Academia e administrador criados com sucesso',
            'academia' => $this->tenantModel->findById($tenantId),
            'admin' => [
                'id' => $adminId,
                'nome' => $adminData['nome'],
                'email' => $adminData['email'],
                'papel_id' => 3
            ]
        ]));

        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    /**
     * Buscar academia por ID
     * GET /superadmin/academias/{id}
     */
    public function buscarAcademia(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('userId');
        $user = $this->usuarioModel->findById($userId);
        $tenantId = (int) $args['id'];

        // Verificar se é super admin
        if (($user['papel_id'] ?? null) != 4) {
            $response->getBody()->write(json_encode([
                'error' => 'Acesso negado. Apenas Super Admin pode acessar'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $academia = $this->tenantModel->findById($tenantId);

        if (!$academia) {
            $response->getBody()->write(json_encode([
                'error' => 'Academia não encontrada'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode([
            'academia' => $academia
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Atualizar academia
     * PUT /superadmin/academias/{id}
     */
    public function atualizarAcademia(Request $request, Response $response, array $args): Response
    {
        $data = $request->getParsedBody();
        $userId = $request->getAttribute('userId');
        $user = $this->usuarioModel->findById($userId);
        $tenantId = (int) $args['id'];

        // Verificar se é super admin
        if (($user['papel_id'] ?? null) != 4) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Acesso negado. Apenas Super Admin pode atualizar academias'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Verificar se academia existe
        $academia = $this->tenantModel->findById($tenantId);
        if (!$academia) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Academia não encontrada'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Validações
        $errors = [];

        if (empty($data['nome'])) {
            $errors[] = 'Nome da academia é obrigatório';
        }

        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email válido é obrigatório';
        }

        // Gerar slug a partir do nome
        $slug = $this->generateSlug($data['nome'] ?? '');
        
        // Verificar se slug já existe em outra academia
        $existingAcademia = $this->tenantModel->findBySlug($slug);
        if ($existingAcademia && $existingAcademia['id'] != $tenantId) {
            $errors[] = 'Já existe outra academia com este nome';
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => implode(', ', $errors)
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        // Validar CNPJ se fornecido
        if (!empty($data['cnpj'])) {
            $cnpj = preg_replace('/[^0-9]/', '', $data['cnpj']);
            if (strlen($cnpj) != 14) {
                $errors[] = 'CNPJ inválido. Deve conter 14 dígitos';
            }
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => implode(', ', $errors)
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        // Atualizar academia (sem plano_id)
        $academiaData = [
            'nome' => $data['nome'],
            'slug' => $slug,
            'email' => $data['email'],
            'cnpj' => isset($data['cnpj']) ? preg_replace('/[^0-9]/', '', $data['cnpj']) : $academia['cnpj'],
            'telefone' => isset($data['telefone']) ? preg_replace('/[^0-9]/', '', $data['telefone']) : $academia['telefone'],
            'responsavel_nome' => $data['responsavel_nome'] ?? $academia['responsavel_nome'],
            'responsavel_cpf' => isset($data['responsavel_cpf']) ? preg_replace('/[^0-9]/', '', $data['responsavel_cpf']) : $academia['responsavel_cpf'],
            'responsavel_telefone' => isset($data['responsavel_telefone']) ? preg_replace('/[^0-9]/', '', $data['responsavel_telefone']) : $academia['responsavel_telefone'],
            'responsavel_email' => $data['responsavel_email'] ?? $academia['responsavel_email'],
            'cep' => $data['cep'] ?? $academia['cep'],
            'logradouro' => $data['logradouro'] ?? $academia['logradouro'],
            'numero' => $data['numero'] ?? $academia['numero'],
            'complemento' => $data['complemento'] ?? $academia['complemento'],
            'bairro' => $data['bairro'] ?? $academia['bairro'],
            'cidade' => $data['cidade'] ?? $academia['cidade'],
            'estado' => $data['estado'] ?? $academia['estado'],
            'endereco' => $data['endereco'] ?? null,
            'ativo' => isset($data['ativo']) ? (filter_var($data['ativo'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0) : 1
        ];

        $success = $this->tenantModel->update($tenantId, $academiaData);

        if (!$success) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao atualizar academia'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'Academia atualizada com sucesso',
            'academia' => $this->tenantModel->findById($tenantId)
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Excluir academia (soft delete)
     * DELETE /superadmin/academias/{id}
     */
    public function excluirAcademia(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('userId');
        $user = $this->usuarioModel->findById($userId);
        $tenantId = (int) $args['id'];

        // Verificar se é super admin
        if (($user['papel_id'] ?? null) != 4) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Acesso negado. Apenas Super Admin pode excluir academias'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Verificar se academia existe
        $academia = $this->tenantModel->findById($tenantId);
        if (!$academia) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Academia não encontrada'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Verificar se é a academia do sistema (id = 1)
        if ($tenantId == 1) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Não é possível excluir a academia do sistema'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Soft delete
        $success = $this->tenantModel->delete($tenantId);

        if (!$success) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao excluir academia'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'Academia desativada com sucesso'
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Criar usuário Admin para uma academia
     * POST /superadmin/academias/{tenantId}/admin
     */
    public function criarAdminAcademia(Request $request, Response $response, array $args): Response
    {
        $data = $request->getParsedBody();
        $userId = $request->getAttribute('userId');
        $user = $this->usuarioModel->findById($userId);
        $tenantId = (int) $args['tenantId'];

        // Verificar se é super admin (4) ou admin (3)
        $papelId = $user['papel_id'] ?? null;
        if (!in_array($papelId, [3, 4])) {
            $response->getBody()->write(json_encode([
                'error' => 'Acesso negado. Apenas Admin ou Super Admin podem criar admins'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Se for Admin (3), verificar se pertence a esta academia
        if ($papelId == 3) {
            $db = require __DIR__ . '/../../config/database.php';
            $stmt = $db->prepare("
                SELECT COUNT(*) as count
                FROM tenant_usuario_papel
                WHERE tenant_id = :tenant_id
                  AND usuario_id = :usuario_id
                  AND papel_id = 3
                  AND ativo = 1
            ");
            $stmt->execute(['tenant_id' => $tenantId, 'usuario_id' => $userId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($result['count'] == 0) {
                $response->getBody()->write(json_encode([
                    'error' => 'Você não tem permissão para criar admins nesta academia'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }
        }

        // Verificar se tenant existe
        $tenant = $this->tenantModel->findById($tenantId);
        if (!$tenant) {
            $response->getBody()->write(json_encode([
                'error' => 'Academia não encontrada'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Validações
        $errors = [];

        if (empty($data['nome'])) {
            $errors[] = 'Nome é obrigatório';
        }

        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email válido é obrigatório';
        }

        // Validar papéis (deve ter pelo menos Admin)
        $papeis = isset($data['papeis']) && is_array($data['papeis']) ? $data['papeis'] : [3];
        if (!in_array(3, $papeis)) {
            $errors[] = 'Usuário deve ter pelo menos o papel de Admin';
        }
        // Validar que os papéis sejam válidos (1=aluno, 2=professor, 3=admin)
        foreach ($papeis as $papel) {
            if (!in_array($papel, [1, 2, 3])) {
                $errors[] = 'Papel inválido: ' . $papel . '. Valores válidos: 1 (aluno), 2 (professor), 3 (admin)';
            }
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode(['errors' => $errors]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        // Verificar se email já existe
        $usuarioExistente = null;
        if (!empty($data['email'])) {
            $usuarioExistente = $this->usuarioModel->findByEmailGlobal($data['email']);
        }

        $db = require __DIR__ . '/../../config/database.php';
        $adminId = null;

        if ($usuarioExistente) {
            // Usuário já existe - verificar se já tem vínculo com este tenant
            $adminId = $usuarioExistente['id'];

            // Atualizar dados do usuário existente (se fornecidos)
            $updateData = [];
            if (!empty($data['nome'])) $updateData['nome'] = $data['nome'];
            if (!empty($data['email'])) $updateData['email'] = $data['email'];
            if (isset($data['telefone'])) $updateData['telefone'] = $data['telefone'];
            if (isset($data['cpf'])) $updateData['cpf'] = $data['cpf'];

            if (!empty($data['senha'])) {
                if (strlen($data['senha']) < 6) {
                    $response->getBody()->write(json_encode([
                        'errors' => ['Senha deve ter no mínimo 6 caracteres']
                    ]));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
                }
                $updateData['senha'] = $data['senha'];
            }

            if (!empty($updateData)) {
                $this->usuarioModel->update($adminId, $updateData);
            }
            
            $stmtVerifica = $db->prepare("
                SELECT COUNT(*) as count
                FROM tenant_usuario_papel
                WHERE tenant_id = :tenant_id
                  AND usuario_id = :usuario_id
                  AND papel_id IN (1, 2, 3)
            ");
            $stmtVerifica->execute(['tenant_id' => $tenantId, 'usuario_id' => $adminId]);
            $vinculoExiste = $stmtVerifica->fetch(\PDO::FETCH_ASSOC);

            if ($vinculoExiste['count'] > 0) {
                $response->getBody()->write(json_encode([
                    'error' => 'Usuário já está vinculado a esta academia. Use o endpoint de atualização para modificar os papéis.'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
            }

            // Usuário existe mas não tem vínculo com este tenant - apenas criar associação
        } else {
            // Usuário não existe - criar novo
            if (empty($data['senha']) || strlen($data['senha']) < 6) {
                $response->getBody()->write(json_encode([
                    'errors' => ['Senha deve ter no mínimo 6 caracteres']
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
            }

            $adminData = [
                'nome' => $data['nome'],
                'email' => $data['email'],
                'senha' => $data['senha'], // Model já faz o hash automaticamente
                'telefone' => isset($data['telefone']) ? preg_replace('/[^0-9]/', '', $data['telefone']) : null,
                'cpf' => isset($data['cpf']) ? preg_replace('/[^0-9]/', '', $data['cpf']) : null,
                'papel_id' => 3 // Admin - evita criar registro de aluno automaticamente
            ];

            $adminId = $this->usuarioModel->create($adminData, $tenantId);

            if (!$adminId) {
                // Log do erro do modelo
                $erro = $this->usuarioModel->lastError ?? 'Erro desconhecido';
                error_log("[criarAdminAcademia] Falha ao criar usuário. Erro: $erro");
                
                $response->getBody()->write(json_encode([
                    'error' => 'Erro ao criar admin',
                    'details' => $erro
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
        }

        // Criar múltiplos papéis em tenant_usuario_papel
        foreach ($papeis as $papelId) {
            $stmtPapel = $db->prepare("
                INSERT INTO tenant_usuario_papel (tenant_id, usuario_id, papel_id, ativo)
                VALUES (:tenant_id, :usuario_id, :papel_id, 1)
                ON DUPLICATE KEY UPDATE ativo = 1
            ");
            $stmtPapel->execute([
                'tenant_id' => $tenantId,
                'usuario_id' => $adminId,
                'papel_id' => $papelId
            ]);
        }

        // Papéis gerenciados apenas via tenant_usuario_papel - sem duplicar em professores/alunos

        $admin = $this->usuarioModel->findById($adminId);

        $response->getBody()->write(json_encode([
            'message' => 'Admin criado com sucesso',
            'admin' => [
                'id' => $admin['id'],
                'nome' => $admin['nome'],
                'email' => $admin['email'],
                'papeis' => $papeis,
                'tenant' => $tenant
            ]
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    /**
     * Listar papéis disponíveis
     * GET /papeis
     */
    public function listarPapeis(Request $request, Response $response): Response
    {
        $papeis = [
            ['id' => 1, 'nome' => 'Aluno', 'descricao' => 'Pode acessar o app mobile e fazer check-in'],
            ['id' => 2, 'nome' => 'Professor', 'descricao' => 'Pode marcar presença e gerenciar turmas'],
            ['id' => 3, 'nome' => 'Admin', 'descricao' => 'Pode acessar o painel administrativo']
        ];

        $response->getBody()->write(json_encode(['papeis' => $papeis], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Listar admins de uma academia
     * GET /superadmin/academias/{tenantId}/admins
     */
    public function listarAdminsAcademia(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('userId');
        $user = $this->usuarioModel->findById($userId);
        $tenantId = (int) $args['tenantId'];

        // Verificar se é super admin (4) ou admin (3)
        $papelId = $user['papel_id'] ?? null;
        if (!in_array($papelId, [3, 4])) {
            $response->getBody()->write(json_encode([
                'error' => 'Acesso negado. Apenas Admin ou Super Admin podem listar admins'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Se for Admin (3), verificar se pertence a esta academia
        if ($papelId == 3) {
            $db = require __DIR__ . '/../../config/database.php';
            $stmt = $db->prepare("
                SELECT COUNT(*) as count
                FROM tenant_usuario_papel
                WHERE tenant_id = :tenant_id
                  AND usuario_id = :usuario_id
                  AND papel_id = 3
                  AND ativo = 1
            ");
            $stmt->execute(['tenant_id' => $tenantId, 'usuario_id' => $userId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($result['count'] == 0) {
                $response->getBody()->write(json_encode([
                    'error' => 'Você não tem permissão para acessar esta academia'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }
        }

        // Verificar se tenant existe
        $tenant = $this->tenantModel->findById($tenantId);
        if (!$tenant) {
            $response->getBody()->write(json_encode([
                'error' => 'Academia não encontrada'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Buscar todos os admins (papel_id = 3) da academia
        $db = require __DIR__ . '/../../config/database.php';
        $stmt = $db->prepare("
            SELECT DISTINCT u.id, u.nome, u.email, u.telefone, u.cpf,
                   MAX(tup.ativo) as ativo, MIN(tup.created_at) as vinculado_em
            FROM usuarios u
            INNER JOIN tenant_usuario_papel tup ON u.id = tup.usuario_id
            WHERE tup.tenant_id = :tenant_id
              AND tup.papel_id = 3
            GROUP BY u.id, u.nome, u.email, u.telefone, u.cpf
            ORDER BY u.nome ASC
        ");
        $stmt->execute(['tenant_id' => $tenantId]);
        $admins = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Buscar mapeamento de papéis do banco
        $papeisMapa = $this->getPapeisMapa();

        // Buscar papéis de cada admin
        foreach ($admins as &$admin) {
            $stmtPapeis = $db->prepare("
                SELECT papel_id
                FROM tenant_usuario_papel
                WHERE tenant_id = :tenant_id
                  AND usuario_id = :usuario_id
                  AND ativo = 1
                ORDER BY papel_id DESC
            ");
            $stmtPapeis->execute(['tenant_id' => $tenantId, 'usuario_id' => $admin['id']]);
            $papelIds = array_column($stmtPapeis->fetchAll(\PDO::FETCH_ASSOC), 'papel_id');
            
            // Montar array com id e nome dos papéis
            $admin['papeis'] = array_map(function($papelId) use ($papeisMapa) {
                return [
                    'id' => $papelId,
                    'nome' => $papeisMapa[$papelId] ?? 'Desconhecido'
                ];
            }, $papelIds);
        }

        $response->getBody()->write(json_encode([
            'academia' => $tenant,
            'admins' => $admins,
            'total' => count($admins)
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Atualizar admin de uma academia
     * PUT /superadmin/academias/{tenantId}/admins/{adminId}
     */
    public function atualizarAdminAcademia(Request $request, Response $response, array $args): Response
    {
        $data = $request->getParsedBody();
        $userId = $request->getAttribute('userId');
        $user = $this->usuarioModel->findById($userId);
        $tenantId = (int) $args['tenantId'];
        $adminId = (int) $args['adminId'];

        // Verificar se é super admin (4) ou admin (3)
        $papelId = $user['papel_id'] ?? null;
        if (!in_array($papelId, [3, 4])) {
            $response->getBody()->write(json_encode([
                'error' => 'Acesso negado. Apenas Admin ou Super Admin podem atualizar admins'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Se for Admin (3), verificar se pertence a esta academia
        if ($papelId == 3) {
            $db = require __DIR__ . '/../../config/database.php';
            $stmt = $db->prepare("
                SELECT COUNT(*) as count
                FROM tenant_usuario_papel
                WHERE tenant_id = :tenant_id
                  AND usuario_id = :usuario_id
                  AND papel_id = 3
                  AND ativo = 1
            ");
            $stmt->execute(['tenant_id' => $tenantId, 'usuario_id' => $userId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($result['count'] == 0) {
                $response->getBody()->write(json_encode([
                    'error' => 'Você não tem permissão para atualizar admins nesta academia'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }
        }

        // Verificar se tenant existe
        $tenant = $this->tenantModel->findById($tenantId);
        if (!$tenant) {
            $response->getBody()->write(json_encode([
                'error' => 'Academia não encontrada'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Verificar se admin existe
        $admin = $this->usuarioModel->findById($adminId);
        if (!$admin) {
            $response->getBody()->write(json_encode([
                'error' => 'Admin não encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Verificar se o usuário é admin da academia
        $db = require __DIR__ . '/../../config/database.php';
        $stmt = $db->prepare("
            SELECT * FROM tenant_usuario_papel
            WHERE tenant_id = :tenant_id
              AND usuario_id = :usuario_id
              AND papel_id = 3
        ");
        $stmt->execute(['tenant_id' => $tenantId, 'usuario_id' => $adminId]);
        $vinculo = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$vinculo) {
            $response->getBody()->write(json_encode([
                'error' => 'Usuário não é admin desta academia'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Validações
        $errors = [];

        if (isset($data['nome']) && empty($data['nome'])) {
            $errors[] = 'Nome não pode ser vazio';
        }

        if (isset($data['email'])) {
            if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email válido é obrigatório';
            }
            // Verificar se email já existe em outro usuário
            $existingUser = $this->usuarioModel->findByEmailGlobal($data['email']);
            if ($existingUser && $existingUser['id'] != $adminId) {
                $errors[] = 'Email já cadastrado por outro usuário';
            }
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode(['errors' => $errors]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        // Atualizar dados do usuário
        $updateData = [];
        if (isset($data['nome'])) $updateData['nome'] = $data['nome'];
        if (isset($data['email'])) $updateData['email'] = $data['email'];
        if (isset($data['telefone'])) $updateData['telefone'] = preg_replace('/[^0-9]/', '', $data['telefone']);
        if (isset($data['cpf'])) $updateData['cpf'] = preg_replace('/[^0-9]/', '', $data['cpf']);

        // Se forneceu senha nova, atualizar
        if (!empty($data['senha'])) {
            if (strlen($data['senha']) < 6) {
                $response->getBody()->write(json_encode([
                    'errors' => ['Senha deve ter no mínimo 6 caracteres']
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
            }
            $updateData['senha'] = password_hash($data['senha'], PASSWORD_DEFAULT);
        }

        if (!empty($updateData)) {
            $this->usuarioModel->update($adminId, $updateData);
        }

        // Atualizar papéis se fornecido
        if (isset($data['papeis']) && is_array($data['papeis'])) {
            $papeis = $data['papeis'];
            
            // Validar que Admin (3) está presente
            if (!in_array(3, $papeis)) {
                $response->getBody()->write(json_encode([
                    'errors' => ['Usuário deve manter pelo menos o papel de Admin']
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
            }

            // Remover papéis antigos
            $db = require __DIR__ . '/../../config/database.php';
            $stmtDelete = $db->prepare("
                DELETE FROM tenant_usuario_papel
                WHERE tenant_id = :tenant_id
                  AND usuario_id = :usuario_id
            ");
            $stmtDelete->execute(['tenant_id' => $tenantId, 'usuario_id' => $adminId]);

            // Adicionar novos papéis
            foreach ($papeis as $papelId) {
                if (!in_array($papelId, [1, 2, 3])) continue;
                
                $stmtPapel = $db->prepare("
                    INSERT INTO tenant_usuario_papel (tenant_id, usuario_id, papel_id, ativo)
                    VALUES (:tenant_id, :usuario_id, :papel_id, 1)
                ");
                $stmtPapel->execute([
                    'tenant_id' => $tenantId,
                    'usuario_id' => $adminId,
                    'papel_id' => $papelId
                ]);
            }

            // Papéis gerenciados apenas via tenant_usuario_papel - sem duplicar em professores/alunos
        }

        $adminAtualizado = $this->usuarioModel->findById($adminId);
        
        // Buscar papéis atualizados
        $db = require __DIR__ . '/../../config/database.php';
        $stmtPapeis = $db->prepare("
            SELECT papel_id
            FROM tenant_usuario_papel
            WHERE tenant_id = :tenant_id
              AND usuario_id = :usuario_id
              AND ativo = 1
            ORDER BY papel_id DESC
        ");
        $stmtPapeis->execute(['tenant_id' => $tenantId, 'usuario_id' => $adminId]);
        $adminAtualizado['papeis'] = array_column($stmtPapeis->fetchAll(\PDO::FETCH_ASSOC), 'papel_id');

        $response->getBody()->write(json_encode([
            'message' => 'Admin atualizado com sucesso',
            'admin' => $adminAtualizado
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Desativar admin de uma academia
     * DELETE /superadmin/academias/{tenantId}/admins/{adminId}
     */
    public function desativarAdminAcademia(Request $request, Response $response, array $args): Response
    {
        $data = $request->getParsedBody();
        $userId = $request->getAttribute('userId');
        $user = $this->usuarioModel->findById($userId);
        $tenantId = (int) $args['tenantId'];
        $adminId = (int) $args['adminId'];

        // Verificar se é super admin (4) ou admin (3)
        $papelId = $user['papel_id'] ?? null;
        if (!in_array($papelId, [3, 4])) {
            $response->getBody()->write(json_encode([
                'error' => 'Acesso negado. Apenas Admin ou Super Admin podem desativar admins'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Se for Admin (3), verificar se pertence a esta academia
        if ($papelId == 3) {
            $db = require __DIR__ . '/../../config/database.php';
            $stmt = $db->prepare("
                SELECT COUNT(*) as count
                FROM tenant_usuario_papel
                WHERE tenant_id = :tenant_id
                  AND usuario_id = :usuario_id
                  AND papel_id = 3
                  AND ativo = 1
            ");
            $stmt->execute(['tenant_id' => $tenantId, 'usuario_id' => $userId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($result['count'] == 0) {
                $response->getBody()->write(json_encode([
                    'error' => 'Você não tem permissão para desativar admins nesta academia'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }
        }

        // Verificar se tenant existe
        $tenant = $this->tenantModel->findById($tenantId);
        if (!$tenant) {
            $response->getBody()->write(json_encode([
                'error' => 'Academia não encontrada'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Obter papéis a serem desativados
        $papeisDesativar = isset($data['papeis']) && is_array($data['papeis']) ? $data['papeis'] : [3];
        
        // Validar papéis
        $errors = [];
        foreach ($papeisDesativar as $papel) {
            if (!in_array($papel, [1, 2, 3])) {
                $errors[] = 'Papel inválido: ' . $papel;
            }
        }
        
        if (!empty($errors)) {
            $response->getBody()->write(json_encode(['errors' => $errors]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        $db = require __DIR__ . '/../../config/database.php';

        // Se está tentando desativar o papel Admin (3), verificar se não é o último
        if (in_array(3, $papeisDesativar)) {
            $stmt = $db->prepare("
                SELECT COUNT(*) as total
                FROM tenant_usuario_papel
                WHERE tenant_id = :tenant_id
                  AND papel_id = 3
                  AND ativo = 1
            ");
            $stmt->execute(['tenant_id' => $tenantId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result['total'] <= 1) {
                $response->getBody()->write(json_encode([
                    'error' => 'Não é possível desativar o único admin da academia. Crie outro admin primeiro.'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
        }

        // Desativar os papéis especificados
        $desativados = [];
        foreach ($papeisDesativar as $papel) {
            $stmt = $db->prepare("
                UPDATE tenant_usuario_papel
                SET ativo = 0
                WHERE tenant_id = :tenant_id
                  AND usuario_id = :usuario_id
                  AND papel_id = :papel_id
            ");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'usuario_id' => $adminId,
                'papel_id' => $papel
            ]);
            
            if ($stmt->rowCount() > 0) {
                $desativados[] = $papel;
            }
        }

        if (empty($desativados)) {
            $response->getBody()->write(json_encode([
                'error' => 'Nenhum papel foi desativado. Verifique se o usuário possui os papéis especificados.'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Mapear nomes dos papéis desativados do banco
        $papeisMapa = $this->getPapeisMapa();
        $nomesDesativados = array_map(fn($p) => $papeisMapa[$p] ?? $p, $desativados);

        $response->getBody()->write(json_encode([
            'message' => 'Papéis desativados com sucesso',
            'papeis_desativados' => $desativados,
            'nomes' => $nomesDesativados
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Reativar admin de uma academia
     * POST /superadmin/academias/{tenantId}/admins/{adminId}/reativar
     */
    public function reativarAdminAcademia(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('userId');
        $user = $this->usuarioModel->findById($userId);
        $tenantId = (int) $args['tenantId'];
        $adminId = (int) $args['adminId'];

        // Verificar se é super admin (4) ou admin (3)
        $papelId = $user['papel_id'] ?? null;
        if (!in_array($papelId, [3, 4])) {
            $response->getBody()->write(json_encode([
                'error' => 'Acesso negado. Apenas Admin ou Super Admin podem reativar admins'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Se for Admin (3), verificar se pertence a esta academia
        if ($papelId == 3) {
            $db = require __DIR__ . '/../../config/database.php';
            $stmt = $db->prepare("
                SELECT COUNT(*) as count
                FROM tenant_usuario_papel
                WHERE tenant_id = :tenant_id
                  AND usuario_id = :usuario_id
                  AND papel_id = 3
                  AND ativo = 1
            ");
            $stmt->execute(['tenant_id' => $tenantId, 'usuario_id' => $userId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($result['count'] == 0) {
                $response->getBody()->write(json_encode([
                    'error' => 'Você não tem permissão para reativar admins nesta academia'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }
        }

        // Verificar se tenant existe
        $tenant = $this->tenantModel->findById($tenantId);
        if (!$tenant) {
            $response->getBody()->write(json_encode([
                'error' => 'Academia não encontrada'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Reativar vínculo do admin com a academia
        $db = require __DIR__ . '/../../config/database.php';
        $stmt = $db->prepare("
            UPDATE tenant_usuario_papel
            SET ativo = 1
            WHERE tenant_id = :tenant_id
              AND usuario_id = :usuario_id
              AND papel_id = 3
        ");
        $success = $stmt->execute(['tenant_id' => $tenantId, 'usuario_id' => $adminId]);

        if (!$success || $stmt->rowCount() === 0) {
            $response->getBody()->write(json_encode([
                'error' => 'Erro ao reativar admin ou admin não encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $response->getBody()->write(json_encode([
            'message' => 'Admin reativado com sucesso'
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Buscar mapeamento de papéis do banco de dados
     * @return array Array associativo [id => nome]
     */
    private function getPapeisMapa(): array
    {
        $db = require __DIR__ . '/../../config/database.php';
        $stmt = $db->prepare("SELECT id, nome FROM papeis WHERE ativo = 1 ORDER BY id");
        $stmt->execute();
        $papeis = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $mapa = [];
        foreach ($papeis as $papel) {
            $mapa[$papel['id']] = ucfirst($papel['nome']);
        }
        
        return $mapa;
    }

    /**
     * Gerar slug a partir de um texto
     */
    private function generateSlug(string $text): string
    {
        // Converter para minúsculas
        $text = strtolower($text);
        
        // Remover acentos
        $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        
        // Remover caracteres especiais
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
        
        // Remover hífens duplicados
        $text = preg_replace('/-+/', '-', $text);
        
        // Remover hífens do início e fim
        $text = trim($text, '-');
        
        return $text;
    }

    /**
     * Criar contrato de plano para uma academia
     * POST /superadmin/academias/{id}/contrato
     */
    public function criarContrato(Request $request, Response $response, array $args): Response
    {
        $data = $request->getParsedBody();
        $userId = $request->getAttribute('userId');
        $user = $this->usuarioModel->findById($userId);
        $tenantId = (int) $args['id'];

        // Verificar se é super admin
        if (($user['papel_id'] ?? null) != 4) {
            $response->getBody()->write(json_encode([
                'error' => 'Acesso negado. Apenas Super Admin pode criar contratos'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Verificar se academia existe
        $academia = $this->tenantModel->findById($tenantId);
        if (!$academia) {
            $response->getBody()->write(json_encode([
                'error' => 'Academia não encontrada'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Validações
        $errors = [];
        if (empty($data['plano_sistema_sistema_id'])) {
            $errors[] = 'Plano é obrigatório';
        }
        if (empty($data['forma_pagamento']) || !in_array($data['forma_pagamento'], ['cartao', 'pix', 'operadora'])) {
            $errors[] = 'Forma de pagamento inválida (cartao, pix ou operadora)';
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode(['errors' => $errors]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        try {
            // Desativar contrato atual se existir
            $this->tenantPlanoModel->desativarContratoAtivo($tenantId);

            // Criar novo contrato
            $contratoData = [
                'tenant_id' => $tenantId,
                'plano_sistema_id' => $data['plano_sistema_id'],
                'data_inicio' => $data['data_inicio'] ?? date('Y-m-d'),
                'data_vencimento' => $data['data_vencimento'] ?? date('Y-m-d', strtotime('+1 month')),
                'forma_pagamento' => $data['forma_pagamento'],
                'observacoes' => $data['observacoes'] ?? null
            ];

            $contratoId = $this->tenantPlanoModel->criar($contratoData);

            $response->getBody()->write(json_encode([
                'message' => 'Contrato criado com sucesso',
                'contrato_id' => $contratoId
            ]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'error' => 'Erro ao criar contrato: ' . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Trocar plano de uma academia
     * POST /superadmin/academias/{id}/trocar-plano
     */
    public function trocarPlano(Request $request, Response $response, array $args): Response
    {
        $data = $request->getParsedBody();
        $userId = $request->getAttribute('userId');
        $user = $this->usuarioModel->findById($userId);
        $tenantId = (int) $args['id'];

        // Verificar se é super admin
        if (($user['papel_id'] ?? null) != 4) {
            $response->getBody()->write(json_encode([
                'error' => 'Acesso negado. Apenas Super Admin pode trocar planos'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Verificar se academia existe
        $academia = $this->tenantModel->findById($tenantId);
        if (!$academia) {
            $response->getBody()->write(json_encode([
                'error' => 'Academia não encontrada'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Validações
        $errors = [];
        if (empty($data['plano_id'])) {
            $errors[] = 'Novo plano é obrigatório';
        }
        if (empty($data['forma_pagamento']) || !in_array($data['forma_pagamento'], ['cartao', 'pix', 'operadora'])) {
            $errors[] = 'Forma de pagamento inválida (cartao, pix ou operadora)';
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode(['errors' => $errors]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        try {
            $resultado = $this->tenantPlanoModel->trocarPlano(
                $tenantId,
                $data['plano_sistema_id'],
                $data['forma_pagamento'],
                $data['observacoes'] ?? 'Troca de plano realizada pelo Super Admin'
            );

            $response->getBody()->write(json_encode([
                'message' => 'Plano trocado com sucesso',
                'contrato' => $resultado
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'error' => 'Erro ao trocar plano: ' . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Buscar histórico de contratos de uma academia
     * GET /superadmin/academias/{id}/contratos
     */
    public function listarContratos(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('userId');
        $user = $this->usuarioModel->findById($userId);
        $tenantId = (int) $args['id'];

        // Verificar se é super admin
        if (($user['papel_id'] ?? null) != 4) {
            $response->getBody()->write(json_encode([
                'error' => 'Acesso negado. Apenas Super Admin pode visualizar contratos'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Verificar se academia existe
        $academia = $this->tenantModel->findById($tenantId);
        if (!$academia) {
            $response->getBody()->write(json_encode([
                'error' => 'Academia não encontrada'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $contratos = $this->tenantPlanoModel->buscarHistorico($tenantId);
        $contratoAtivo = $this->tenantPlanoModel->buscarContratoAtivo($tenantId);

        $response->getBody()->write(json_encode([
            'contrato_ativo' => $contratoAtivo,
            'historico' => $contratos
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Renovar contrato de uma academia
     * POST /superadmin/contratos/{id}/renovar
     */
    public function renovarContrato(Request $request, Response $response, array $args): Response
    {
        $data = $request->getParsedBody();
        $userId = $request->getAttribute('userId');
        $user = $this->usuarioModel->findById($userId);
        $contratoId = (int) $args['id'];

        // Verificar se é super admin
        if (($user['papel_id'] ?? null) != 4) {
            $response->getBody()->write(json_encode([
                'error' => 'Acesso negado. Apenas Super Admin pode renovar contratos'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        try {
            $resultado = $this->tenantPlanoModel->renovarContrato(
                $contratoId,
                $data['observacoes'] ?? null
            );

            $response->getBody()->write(json_encode([
                'message' => 'Contrato renovado com sucesso',
                'novo_contrato' => $resultado
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'error' => 'Erro ao renovar contrato: ' . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Listar contratos próximos do vencimento
     * GET /superadmin/contratos/proximos-vencimento
     */
    public function contratosProximosVencimento(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('userId');
        $user = $this->usuarioModel->findById($userId);

        // Verificar se é super admin
        if (($user['papel_id'] ?? null) != 4) {
            $response->getBody()->write(json_encode([
                'error' => 'Acesso negado. Apenas Super Admin pode visualizar relatórios'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $params = $request->getQueryParams();
        $dias = $params['dias'] ?? 7;

        $contratos = $this->tenantPlanoModel->buscarContratosProximosVencimento($dias);

        $response->getBody()->write(json_encode([
            'total' => count($contratos),
            'dias_alerta' => $dias,
            'contratos' => $contratos
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Listar contratos vencidos
     * GET /superadmin/contratos/vencidos
     */
    public function contratosVencidos(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('userId');
        $user = $this->usuarioModel->findById($userId);

        // Verificar se é super admin
        if (($user['papel_id'] ?? null) != 4) {
            $response->getBody()->write(json_encode([
                'error' => 'Acesso negado. Apenas Super Admin pode visualizar relatórios'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $contratos = $this->tenantPlanoModel->buscarContratosVencidos();

        $response->getBody()->write(json_encode([
            'total' => count($contratos),
            'contratos' => $contratos
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Listar TODOS os contratos de todas as academias
     * GET /superadmin/contratos
     */
    public function listarTodosContratos(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('userId');
        $user = $this->usuarioModel->findById($userId);

        // Verificar se é super admin
        if (($user['papel_id'] ?? null) != 4) {
            $response->getBody()->write(json_encode([
                'error' => 'Acesso negado. Apenas Super Admin pode visualizar contratos'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $contratos = $this->tenantPlanoModel->listarTodos();

        $response->getBody()->write(json_encode([
            'total' => count($contratos),
            'contratos' => $contratos
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Cancelar/deletar contrato
     * DELETE /superadmin/contratos/{id}
     */
    public function cancelarContrato(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('userId');
        $user = $this->usuarioModel->findById($userId);
        $contratoId = (int) $args['id'];

        // Verificar se é super admin
        if (($user['papel_id'] ?? null) != 4) {
            $response->getBody()->write(json_encode([
                'error' => 'Acesso negado. Apenas Super Admin pode cancelar contratos'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Buscar contrato
        $contrato = $this->tenantPlanoModel->buscarPorId($contratoId);
        if (!$contrato) {
            $response->getBody()->write(json_encode([
                'error' => 'Contrato não encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Cancelar contrato (atualizar status para 'cancelado')
        $this->tenantPlanoModel->cancelar($contratoId);

        $response->getBody()->write(json_encode([
            'message' => 'Contrato cancelado com sucesso'
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    // ==================== PLANOS DO SISTEMA ====================

    /**
     * Listar todos os planos do sistema
     * GET /superadmin/planos-sistema
     */
    public function listarPlanosSistema(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('userId');
        $user = $this->usuarioModel->findById($userId);

        // Verificar se é super admin
        if (($user['papel_id'] ?? null) != 4) {
            $response->getBody()->write(json_encode([
                'error' => 'Acesso negado. Apenas Super Admin'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $apenasAtivos = $request->getQueryParams()['ativos'] ?? false;
        $planos = $this->planoSistemaModel->listarTodos((bool) $apenasAtivos);

        $response->getBody()->write(json_encode([
            'planos' => $planos,
            'total' => count($planos)
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Listar planos disponíveis para novos contratos
     * GET /superadmin/planos-sistema/disponiveis
     */
    public function listarPlanosDisponiveis(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('userId');
        $user = $this->usuarioModel->findById($userId);

        // Verificar se é super admin
        if (($user['papel_id'] ?? null) != 4) {
            $response->getBody()->write(json_encode([
                'error' => 'Acesso negado. Apenas Super Admin'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $planos = $this->planoSistemaModel->listarDisponiveis();

        $response->getBody()->write(json_encode([
            'planos' => $planos,
            'total' => count($planos)
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Criar novo plano do sistema
     * POST /superadmin/planos-sistema
     */
    public function criarPlanoSistema(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('userId');
        $user = $this->usuarioModel->findById($userId);
        $data = $request->getParsedBody();

        // Verificar se é super admin
        if (($user['papel_id'] ?? null) != 4) {
            $response->getBody()->write(json_encode([
                'error' => 'Acesso negado. Apenas Super Admin'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

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
     * PUT /superadmin/planos-sistema/{id}
     */
    public function atualizarPlanoSistema(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('userId');
        $user = $this->usuarioModel->findById($userId);
        $planoId = (int) $args['id'];
        $data = $request->getParsedBody();

        // Verificar se é super admin
        if (($user['papel_id'] ?? null) != 4) {
            $response->getBody()->write(json_encode([
                'error' => 'Acesso negado. Apenas Super Admin'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $plano = $this->planoSistemaModel->buscarPorId($planoId);
        if (!$plano) {
            $response->getBody()->write(json_encode([
                'error' => 'Plano não encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        try {
            $this->planoSistemaModel->atualizar($planoId, $data);
            $planoAtualizado = $this->planoSistemaModel->buscarPorId($planoId);

            $response->getBody()->write(json_encode([
                'message' => 'Plano atualizado com sucesso',
                'plano' => $planoAtualizado
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'error' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    }

    /**
     * Marcar plano como histórico
     * POST /superadmin/planos-sistema/{id}/marcar-historico
     */
    public function marcarPlanoComoHistorico(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('userId');
        $user = $this->usuarioModel->findById($userId);
        $planoId = (int) $args['id'];

        // Verificar se é super admin
        if (($user['papel_id'] ?? null) != 4) {
            $response->getBody()->write(json_encode([
                'error' => 'Acesso negado. Apenas Super Admin'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

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
     * DELETE /superadmin/planos-sistema/{id}
     */
    public function desativarPlanoSistema(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('userId');
        $user = $this->usuarioModel->findById($userId);
        $planoId = (int) $args['id'];

        // Verificar se é super admin
        if (($user['papel_id'] ?? null) != 4) {
            $response->getBody()->write(json_encode([
                'error' => 'Acesso negado. Apenas Super Admin'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

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

    /**
     * Listar planos de alunos de todos os tenants ou filtrado por tenant
     * GET /superadmin/planos?tenant_id=X&ativos=true
     */
    public function listarPlanosAlunos(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('userId');
        $user = $this->usuarioModel->findById($userId);

        // Verificar se é super admin
        if (($user['papel_id'] ?? null) != 4) {
            $response->getBody()->write(json_encode([
                'error' => 'Acesso negado. Apenas Super Admin'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $queryParams = $request->getQueryParams();
        $tenantId = isset($queryParams['tenant_id']) ? (int) $queryParams['tenant_id'] : null;
        $apenasAtivos = isset($queryParams['ativos']) && $queryParams['ativos'] === 'true';

        // Se não tem tenant_id, retorna apenas a lista de tenants
        if (!$tenantId) {
            $tenants = $this->tenantModel->getAll(['ativo' => true]);
            $response->getBody()->write(json_encode([
                'planos' => [],
                'total' => 0,
                'tenants' => $tenants,
                'message' => 'Selecione uma academia para ver os planos'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $db = require __DIR__ . '/../../config/database.php';
        
        $sql = "SELECT p.*, t.nome as academia_nome,
                       m.nome as modalidade_nome, m.icone as modalidade_icone, m.cor as modalidade_cor
                FROM planos p
                INNER JOIN tenants t ON p.tenant_id = t.id
                LEFT JOIN modalidades m ON p.modalidade_id = m.id
                WHERE p.tenant_id = :tenant_id";
        $params = ['tenant_id' => $tenantId];

        if ($apenasAtivos) {
            $sql .= " AND p.ativo = 1";
        }

        $sql .= " ORDER BY p.nome ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $planos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Buscar lista de tenants para o filtro
        $tenants = $this->tenantModel->getAll(['ativo' => true]);

        $response->getBody()->write(json_encode([
            'planos' => $planos,
            'total' => count($planos),
            'tenants' => $tenants
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Obter variáveis de ambiente (.env)
     * GET /superadmin/env
     * Apenas para SuperAdmin - NÃO exponha senhas em produção!
     */
    public function getEnvironmentVariables(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('userId');
        $user = $this->usuarioModel->findById($userId);

        // Verificar se é super admin
        if (($user['papel_id'] ?? null) != 4) {
            $response->getBody()->write(json_encode([
                'error' => 'Acesso negado. Apenas Super Admin pode acessar'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Variáveis seguras para expor
        $safeVars = [
            'APP_ENV' => $_ENV['APP_ENV'] ?? 'unknown',
            'APP_URL' => $_ENV['APP_URL'] ?? 'unknown',
            'APP_DEBUG' => $_ENV['APP_DEBUG'] ?? false,
            'APP_TIMEZONE' => $_ENV['APP_TIMEZONE'] ?? 'unknown',
            'DB_HOST' => $_ENV['DB_HOST'] ?? 'unknown',
            'DB_PORT' => $_ENV['DB_PORT'] ?? 3306,
            'DB_NAME' => $_ENV['DB_NAME'] ?? 'unknown',
            'DB_USER' => $_ENV['DB_USER'] ?? 'unknown',
            // DB_PASS intencialmente não incluída por segurança
            'JWT_EXPIRATION' => $_ENV['JWT_EXPIRATION'] ?? 86400,
            'LOG_LEVEL' => $_ENV['LOG_LEVEL'] ?? 'error',
            'LOG_PATH' => $_ENV['LOG_PATH'] ?? '/var/log/appcheckin',
            'RATE_LIMIT_ENABLED' => $_ENV['RATE_LIMIT_ENABLED'] ?? true,
            'RATE_LIMIT_MAX_REQUESTS' => $_ENV['RATE_LIMIT_MAX_REQUESTS'] ?? 100,
            'RATE_LIMIT_WINDOW_SECONDS' => $_ENV['RATE_LIMIT_WINDOW_SECONDS'] ?? 60,
        ];

        // Adicionar aviso de segurança
        $response->getBody()->write(json_encode([
            'warning' => 'Dados de ambiente do servidor - Proteja este acesso',
            'environment' => $safeVars,
            'php_version' => phpversion(),
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
