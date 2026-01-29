<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Professor;
use App\Models\Tenant;
use App\Models\Usuario;
use PDO;

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
     * Criar novo professor
     * POST /admin/professores
     * 
     * Ao criar um professor:
     * 1. Verifica se já existe usuário com mesmo email (global)
     * 2. Se não existir, cria o usuário com senha temporária
     * 3. Vincula o usuário ao tenant com papel_id = 2 (professor)
     * 4. Cria o registro na tabela professores
     */
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
        
        // Email é obrigatório para criar conta de usuário
        if (empty($data['email'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Email é obrigatório para criar professor'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
        }
        
        // Verificar se já existe professor com esse usuario_id vinculado a este tenant
        $professorExistente = $this->professorModel->findByEmail($data['email'], $tenantId);
        if ($professorExistente) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Já existe um professor vinculado a este tenant com este email'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
        }
        
        try {
            $this->db->beginTransaction();
            
            // 1. Verificar se já existe usuário global com esse email
            $usuarioExistente = $this->usuarioModel->findByEmailGlobal($data['email']);
            $usuarioId = null;
            $senhaTemporaria = null;
            $usuarioCriado = false;
            $professorExisteGlobal = false;
            
            if ($usuarioExistente) {
                $usuarioId = (int) $usuarioExistente['id'];
                
                // Verificar se já existe registro em professores para este usuário
                $professorGlobal = $this->professorModel->findByUsuarioId($usuarioId);
                if ($professorGlobal) {
                    $professorExisteGlobal = true;
                }
                
                // Garantir vínculo com o tenant
                $this->criarVinculoTenant($usuarioId, $tenantId);
                
                // Adicionar papel de professor se não tiver
                if (!$this->temPapel($usuarioId, $tenantId, 2)) {
                    $this->adicionarPapel($usuarioId, $tenantId, 2);
                }
            } else {
                // 2. Criar novo usuário global
                $senhaTemporaria = $this->gerarSenhaTemporaria();
                
                $usuarioData = [
                    'nome' => $data['nome'],
                    'email' => $data['email'],
                    'email_global' => $data['email'],
                    'senha' => $senhaTemporaria,
                    // papel é definido via tenant_usuario_papel pelo método create()
                    'telefone' => $data['telefone'] ?? null,
                    'cpf' => $data['cpf'] ?? null,
                    'ativo' => 1
                ];
                
                $usuarioId = $this->usuarioModel->create($usuarioData, $tenantId);
                $usuarioCriado = true;
                
                // 3. Adicionar papel de professor na tabela tenant_usuario_papel
                $this->adicionarPapel($usuarioId, $tenantId, 2); // papel_id = 2 (professor)
            }
            
            // 4. Criar registro na tabela professores com usuario_id (se não existir)
            $data['usuario_id'] = $usuarioId;
            
            if ($professorExisteGlobal) {
                // Professor já existe, apenas retornar ele
                $professor = $this->professorModel->findByUsuarioId($usuarioId);
            } else {
                // Criar novo professor
                $professorId = $this->professorModel->create($data);
                $professor = $this->professorModel->findById($professorId);
            }
            
            $this->db->commit();
            
            $responseData = [
                'type' => 'success',
                'message' => $professorExisteGlobal 
                    ? 'Professor existente vinculado ao tenant com sucesso' 
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
        // Verificar se já existe vínculo
        $stmt = $this->db->prepare(
            "SELECT id FROM usuario_tenant WHERE usuario_id = :usuario_id AND tenant_id = :tenant_id"
        );
        $stmt->execute(['usuario_id' => $usuarioId, 'tenant_id' => $tenantId]);
        
        if (!$stmt->fetch()) {
            $stmt = $this->db->prepare(
                "INSERT INTO usuario_tenant (usuario_id, tenant_id, status, data_inicio) 
                 VALUES (:usuario_id, :tenant_id, 'ativo', CURDATE())"
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
