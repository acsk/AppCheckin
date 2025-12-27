<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Tenant;
use App\Models\Usuario;

/**
 * Controller para operações exclusivas do Super Admin
 * - Criar academias/tenants
 * - Criar admins para academias
 * - Visualizar todas as academias
 */
class SuperAdminController
{
    private Tenant $tenantModel;
    private Usuario $usuarioModel;

    public function __construct()
    {
        $db = require __DIR__ . '/../../config/database.php';
        $this->tenantModel = new Tenant($db);
        $this->usuarioModel = new Usuario($db);
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
        if ($user['role_id'] != 3) {
            $response->getBody()->write(json_encode([
                'error' => 'Acesso negado. Apenas Super Admin pode acessar'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $academias = $this->tenantModel->getAll();

        $response->getBody()->write(json_encode([
            'academias' => $academias
        ]));

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
        if ($user['role_id'] != 3) {
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

        // Gerar slug a partir do nome
        $slug = $this->generateSlug($data['nome'] ?? '');
        if ($this->tenantModel->findBySlug($slug)) {
            $errors[] = 'Já existe uma academia com este nome';
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode(['errors' => $errors]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        // Criar academia
        $academiaData = [
            'nome' => $data['nome'],
            'slug' => $slug,
            'email' => $data['email'],
            'telefone' => $data['telefone'] ?? null,
            'endereco' => $data['endereco'] ?? null,
            'plano_id' => $data['plano_id'] ?? null
        ];

        $tenantId = $this->tenantModel->create($academiaData);

        if (!$tenantId) {
            $response->getBody()->write(json_encode([
                'error' => 'Erro ao criar academia'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $response->getBody()->write(json_encode([
            'message' => 'Academia criada com sucesso',
            'academia' => $this->tenantModel->findById($tenantId)
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
        if ($user['role_id'] != 3) {
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
        if ($user['role_id'] != 3) {
            $response->getBody()->write(json_encode([
                'error' => 'Acesso negado. Apenas Super Admin pode atualizar academias'
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
            $response->getBody()->write(json_encode(['errors' => $errors]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        // Atualizar academia
        $academiaData = [
            'nome' => $data['nome'],
            'slug' => $slug,
            'email' => $data['email'],
            'telefone' => $data['telefone'] ?? null,
            'endereco' => $data['endereco'] ?? null,
            'plano_id' => $data['plano_id'] ?? null,
            'ativo' => $data['ativo'] ?? true
        ];

        $success = $this->tenantModel->update($tenantId, $academiaData);

        if (!$success) {
            $response->getBody()->write(json_encode([
                'error' => 'Erro ao atualizar academia'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $response->getBody()->write(json_encode([
            'message' => 'Academia atualizada com sucesso',
            'academia' => $this->tenantModel->findById($tenantId)
        ]));

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
        if ($user['role_id'] != 3) {
            $response->getBody()->write(json_encode([
                'error' => 'Acesso negado. Apenas Super Admin pode excluir academias'
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

        // Verificar se é a academia do sistema (id = 1)
        if ($tenantId == 1) {
            $response->getBody()->write(json_encode([
                'error' => 'Não é possível excluir a academia do sistema'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Soft delete
        $success = $this->tenantModel->delete($tenantId);

        if (!$success) {
            $response->getBody()->write(json_encode([
                'error' => 'Erro ao excluir academia'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $response->getBody()->write(json_encode([
            'message' => 'Academia desativada com sucesso'
        ]));

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

        // Verificar se é super admin
        if ($user['role_id'] != 3) {
            $response->getBody()->write(json_encode([
                'error' => 'Acesso negado. Apenas Super Admin pode criar admins'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
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

        if (empty($data['senha']) || strlen($data['senha']) < 6) {
            $errors[] = 'Senha deve ter no mínimo 6 caracteres';
        }

        // Verificar se email já existe
        if (!empty($data['email']) && $this->usuarioModel->findByEmailGlobal($data['email'])) {
            $errors[] = 'Email já cadastrado';
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode(['errors' => $errors]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        // Criar usuário admin
        $adminData = [
            'nome' => $data['nome'],
            'email' => $data['email'],
            'senha' => $data['senha']
        ];

        $adminId = $this->usuarioModel->create($adminData, $tenantId);

        if (!$adminId) {
            $response->getBody()->write(json_encode([
                'error' => 'Erro ao criar admin'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        // Atualizar role para admin
        $this->usuarioModel->update($adminId, ['role_id' => 2]);

        // Vincular admin ao tenant
        $this->usuarioModel->vincularTenant($adminId, $tenantId);

        $admin = $this->usuarioModel->findById($adminId);

        $response->getBody()->write(json_encode([
            'message' => 'Admin criado com sucesso',
            'admin' => [
                'id' => $admin['id'],
                'nome' => $admin['nome'],
                'email' => $admin['email'],
                'role_id' => $admin['role_id'],
                'tenant' => $tenant
            ]
        ]));

        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
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
}
