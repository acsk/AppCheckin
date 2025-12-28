<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Tenant;
use App\Models\Usuario;
use App\Models\TenantPlano;

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
    private TenantPlano $tenantPlanoModel;

    public function __construct()
    {
        $db = require __DIR__ . '/../../config/database.php';
        $this->tenantModel = new Tenant($db);
        $this->usuarioModel = new Usuario($db);
        $this->tenantPlanoModel = new TenantPlano($db);
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
            'telefone' => $data['telefone'] ?? null,
            'cep' => $data['cep'] ?? null,
            'logradouro' => $data['logradouro'] ?? null,
            'numero' => $data['numero'] ?? null,
            'complemento' => $data['complemento'] ?? null,
            'bairro' => $data['bairro'] ?? null,
            'cidade' => $data['cidade'] ?? null,
            'estado' => $data['estado'] ?? null,
            'endereco' => $data['endereco'] ?? null
        ];

        $tenantId = $this->tenantModel->create($academiaData);

        if (!$tenantId) {
            $response->getBody()->write(json_encode([
                'error' => 'Erro ao criar academia'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        // Criar contrato de plano se fornecido
        if (!empty($data['plano_id'])) {
            $contratoData = [
                'tenant_id' => $tenantId,
                'plano_id' => $data['plano_id'],
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

        // Criar usuário administrador da academia automaticamente
        $adminData = [
            'nome' => $data['nome'], // Usa o nome da academia
            'email' => $data['email'],
            'senha' => $data['senha_admin'],
            'role_id' => 2, // Admin
            'plano_id' => null
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
                'role_id' => 2
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

        // Validar CNPJ se fornecido
        if (!empty($data['cnpj'])) {
            $cnpj = preg_replace('/[^0-9]/', '', $data['cnpj']);
            if (strlen($cnpj) != 14) {
                $errors[] = 'CNPJ inválido. Deve conter 14 dígitos';
            }
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode(['errors' => $errors]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        // Atualizar academia (sem plano_id)
        $academiaData = [
            'nome' => $data['nome'],
            'slug' => $slug,
            'email' => $data['email'],
            'cnpj' => isset($data['cnpj']) ? preg_replace('/[^0-9]/', '', $data['cnpj']) : $academia['cnpj'],
            'telefone' => $data['telefone'] ?? null,
            'cep' => $data['cep'] ?? $academia['cep'],
            'logradouro' => $data['logradouro'] ?? $academia['logradouro'],
            'numero' => $data['numero'] ?? $academia['numero'],
            'complemento' => $data['complemento'] ?? $academia['complemento'],
            'bairro' => $data['bairro'] ?? $academia['bairro'],
            'cidade' => $data['cidade'] ?? $academia['cidade'],
            'estado' => $data['estado'] ?? $academia['estado'],
            'endereco' => $data['endereco'] ?? null,
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
        if ($user['role_id'] != 3) {
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
        if (empty($data['plano_id'])) {
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
                'plano_id' => $data['plano_id'],
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
        if ($user['role_id'] != 3) {
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
                $data['plano_id'],
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
        if ($user['role_id'] != 3) {
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
        if ($user['role_id'] != 3) {
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
        if ($user['role_id'] != 3) {
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
        if ($user['role_id'] != 3) {
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
        if ($user['role_id'] != 3) {
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
        if ($user['role_id'] != 3) {
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
}
