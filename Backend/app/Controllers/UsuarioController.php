<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Usuario;

/**
 * UsuarioController
 * 
 * Controller responsável pela gestão de usuários no sistema.
 * Gerencia perfis, autenticação, CRUD de usuários por tenant e operações de SuperAdmin.
 * 
 * Rotas disponíveis:
 * - GET  /me                     - Dados do usuário autenticado
 * - PUT  /me                     - Atualizar perfil do usuário autenticado
 * - GET  /usuarios/{id}/estatisticas - Estatísticas de um usuário
 * 
 * Rotas Tenant (AdminMiddleware):
 * - GET    /tenant/usuarios      - Listar usuários do tenant
 * - GET    /tenant/usuarios/{id} - Buscar usuário específico
 * - POST   /tenant/usuarios      - Criar novo usuário
 * - PUT    /tenant/usuarios/{id} - Atualizar usuário
 * - DELETE /tenant/usuarios/{id} - Desativar usuário
 * 
 * Rotas SuperAdmin:
 * - GET /superadmin/usuarios     - Listar todos os usuários de todos os tenants
 * 
 * @package App\Controllers
 * @author App Checkin Team
 * @version 1.0.0
 */
class UsuarioController
{
    private Usuario $usuarioModel;

    public function __construct()
    {
        $db = require __DIR__ . '/../../config/database.php';
        $this->usuarioModel = new Usuario($db);
    }

    // ========================================
    // Rotas de Perfil do Usuário Autenticado
    // ========================================

    /**
     * Retorna os dados do usuário autenticado
     * 
     * @param Request $request Requisição HTTP
     * @param Response $response Resposta HTTP
     * @return Response JSON com dados do usuário
     * 
     * @api GET /me
     * @apiGroup Usuario
     * @apiDescription Retorna os dados completos do usuário autenticado incluindo role e plano
     * 
     * @apiSuccess {Number} id ID do usuário
     * @apiSuccess {String} nome Nome completo
     * @apiSuccess {String} email Email do usuário
     * @apiSuccess {Object} role Informações da role (id, nome, descricao)
     * @apiSuccess {Number} plano_id ID do plano ativo
     * @apiSuccess {String} foto_base64 Foto em base64
     * 
     * @apiError (404) UsuarioNaoEncontrado Usuário não existe ou não pertence ao tenant
     */
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

    /**
     * Atualiza os dados do usuário autenticado
     * 
     * @param Request $request Requisição HTTP com dados a atualizar
     * @param Response $response Resposta HTTP
     * @return Response JSON com usuário atualizado
     * 
     * @api PUT /me
     * @apiGroup Usuario
     * @apiDescription Permite ao usuário atualizar seu próprio perfil
     * 
     * @apiParam {String} [nome] Nome completo
     * @apiParam {String} [email] Email (validado e único por tenant)
     * @apiParam {String} [senha] Nova senha (mínimo 6 caracteres)
     * @apiParam {String} [foto_base64] Foto em base64 (máximo 5MB)
     * 
     * @apiSuccess {String} message Mensagem de sucesso
     * @apiSuccess {Object} user Dados atualizados do usuário
     * 
     * @apiError (422) ValidacaoFalhou Erros de validação
     * @apiError (400) NenhumDadoAtualizado Nenhum campo foi modificado
     */
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

        // Validar foto em base64
        if (isset($data['foto_base64'])) {
            // Se vier vazio, permitir (para remover foto)
            if ($data['foto_base64'] !== null && $data['foto_base64'] !== '') {
                // Verificar se é base64 válido
                if (!preg_match('/^data:image\/(jpeg|jpg|png|gif|webp);base64,/', $data['foto_base64'])) {
                    $errors[] = 'Formato de imagem inválido. Use data:image/[tipo];base64,[dados]';
                } else {
                    // Extrair apenas os dados base64
                    $base64Data = preg_replace('/^data:image\/\w+;base64,/', '', $data['foto_base64']);
                    
                    // Verificar se é base64 válido
                    if (!base64_decode($base64Data, true)) {
                        $errors[] = 'Dados base64 da imagem são inválidos';
                    } else {
                        // Verificar tamanho (máximo 5MB)
                        $imageSizeInBytes = strlen(base64_decode($base64Data));
                        $maxSizeInBytes = 5 * 1024 * 1024; // 5MB
                        
                        if ($imageSizeInBytes > $maxSizeInBytes) {
                            $errors[] = 'Imagem muito grande. Tamanho máximo: 5MB';
                        }
                    }
                }
            }
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => implode(', ', $errors)
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        // Atualizar
        $updated = $this->usuarioModel->update($userId, $data);

        if (!$updated) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Nenhum dado foi atualizado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $usuario = $this->usuarioModel->findById($userId, $tenantId);

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'Usuário atualizado com sucesso',
            'user' => $usuario
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Retorna estatísticas de um usuário específico
     * 
     * @param Request $request Requisição HTTP
     * @param Response $response Resposta HTTP
     * @param array $args Argumentos da rota (id do usuário)
     * @return Response JSON com estatísticas
     * 
     * @api GET /usuarios/{id}/estatisticas
     * @apiGroup Usuario
     * @apiDescription Retorna estatísticas de checkins e PRs do usuário
     * 
     * @apiParam {Number} id ID do usuário
     * 
     * @apiSuccess {Number} id ID do usuário
     * @apiSuccess {String} nome Nome do usuário
     * @apiSuccess {Number} total_checkins Total de check-ins realizados
     * @apiSuccess {Number} total_prs Total de PRs (se implementado)
     * 
     * @apiError (404) UsuarioNaoEncontrado Usuário não existe no tenant
     */
    public function estatisticas(Request $request, Response $response, array $args): Response
    {
        $usuarioId = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId', 1);
        
        $estatisticas = $this->usuarioModel->getEstatisticas($usuarioId, $tenantId);

        if (!$estatisticas) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Usuário não encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode($estatisticas));
        return $response->withHeader('Content-Type', 'application/json');
    }

    // ========================================
    // CRUD de Usuários para Tenant (AdminMiddleware)
    // ========================================

    /**
     * Listar todos os usuários do tenant
     * 
     * @param Request $request Requisição HTTP
     * @param Response $response Resposta HTTP
     * @return Response JSON com lista de usuários
     * 
     * @api GET /tenant/usuarios
     * @apiGroup Tenant
     * @apiDescription Lista todos os usuários vinculados ao tenant autenticado
     * @apiPermission admin
     * 
     * @apiQuery {Boolean} [ativos=false] Filtrar apenas usuários ativos
     * 
     * @apiSuccess {Array} usuarios Lista de usuários com role, plano e status
     * 
     * @apiExample {curl} Exemplo de uso:
     *     curl -X GET http://api/tenant/usuarios?ativos=true \
     *          -H "Authorization: Bearer {token}"
     */
    public function listar(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $queryParams = $request->getQueryParams();
        $apenasAtivos = isset($queryParams['ativos']) && $queryParams['ativos'] === 'true';

        $usuarios = $this->usuarioModel->listarPorTenant($tenantId, $apenasAtivos);

        $response->getBody()->write(json_encode($usuarios));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Buscar um usuário específico do tenant
     * 
     * @param Request $request Requisição HTTP
     * @param Response $response Resposta HTTP
     * @param array $args Argumentos da rota (id)
     * @return Response JSON com dados do usuário
     * 
     * @api GET /tenant/usuarios/{id}
     * @apiGroup Tenant
     * @apiPermission admin
     * 
     * @apiParam {Number} id ID do usuário
     * 
     * @apiSuccess {Object} usuario Dados completos do usuário
     * @apiError (404) UsuarioNaoEncontrado Usuário não existe no tenant
     */
    public function buscar(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId');

        $usuario = $this->usuarioModel->findById($id, $tenantId);

        if (!$usuario) {
            $response->getBody()->write(json_encode([
                'error' => 'Usuário não encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode($usuario));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Criar novo usuário no tenant
     * 
     * @param Request $request Requisição HTTP com dados do usuário
     * @param Response $response Resposta HTTP
     * @return Response JSON com usuário criado
     * 
     * @api POST /tenant/usuarios
     * @apiGroup Tenant
     * @apiPermission admin
     * @apiDescription Cria um novo usuário vinculado ao tenant
     * 
     * @apiParam {String} nome Nome completo (obrigatório)
     * @apiParam {String} email Email válido e único (obrigatório)
     * @apiParam {String} senha Senha com mínimo 6 caracteres (obrigatório)
     * @apiParam {String} [telefone] Telefone com DDD
     * @apiParam {Number} [role_id=1] Role: 1=Aluno, 2=Admin, 3=SuperAdmin
     * @apiParam {Number} [plano_id] ID do plano
     * @apiParam {String} [data_vencimento_plano] Data de vencimento do plano
     * 
     * @apiSuccess (201) {String} message Mensagem de sucesso
     * @apiSuccess (201) {Object} usuario Dados do usuário criado
     * 
     * @apiError (422) ValidacaoFalhou Lista de erros de validação
     * @apiError (500) ErroCriacao Erro ao criar usuário no banco
     */
    public function criar(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $data = $request->getParsedBody();

        $errors = $this->validarDadosUsuario($data, $tenantId);

        if (!empty($errors)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => implode(', ', $errors)
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        // Criar usuário
        $usuarioId = $this->usuarioModel->criarUsuarioCompleto($data, $tenantId);

        if (!$usuarioId) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao criar usuário'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $usuario = $this->usuarioModel->findById($usuarioId, $tenantId);

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'Usuário criado com sucesso',
            'usuario' => $usuario
        ]));

        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    /**
     * Atualizar usuário do tenant
     * 
     * @param Request $request Requisição HTTP com dados a atualizar
     * @param Response $response Resposta HTTP
     * @param array $args Argumentos da rota (id)
     * @return Response JSON com usuário atualizado
     * 
     * @api PUT /tenant/usuarios/{id}
     * @apiGroup Tenant
     * @apiPermission admin
     * @apiDescription Atualiza dados de um usuário do tenant
     * 
     * @apiParam {Number} id ID do usuário
     * @apiParam {String} [nome] Nome completo
     * @apiParam {String} [email] Email válido e único
     * @apiParam {String} [senha] Nova senha (mínimo 6 caracteres, opcional)
     * @apiParam {String} [telefone] Telefone
     * @apiParam {Number} [role_id] Nova role
     * @apiParam {Number} [plano_id] Novo plano
     * 
     * @apiSuccess {String} message Mensagem de sucesso
     * @apiSuccess {Object} usuario Dados atualizados
     * 
     * @apiError (404) UsuarioNaoEncontrado Usuário não existe no tenant
     * @apiError (422) ValidacaoFalhou Erros de validação
     */
    public function atualizar(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId');
        $data = $request->getParsedBody();

        // Verificar se usuário pertence ao tenant
        $usuarioExistente = $this->usuarioModel->findById($id, $tenantId);
        if (!$usuarioExistente) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Usuário não encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $errors = $this->validarDadosUsuario($data, $tenantId, $id);

        if (!empty($errors)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => implode(', ', $errors)
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        // Atualizar
        $updated = $this->usuarioModel->update($id, $data);

        if (!$updated) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Nenhum dado foi atualizado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $usuario = $this->usuarioModel->findById($id, $tenantId);

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'Usuário atualizado com sucesso',
            'usuario' => $usuario
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Desativar/Excluir usuário do tenant
     * 
     * @param Request $request Requisição HTTP
     * @param Response $response Resposta HTTP
     * @param array $args Argumentos da rota (id)
     * @return Response JSON com confirmação
     * 
     * @api DELETE /tenant/usuarios/{id}
     * @apiGroup Tenant
     * @apiPermission admin
     * @apiDescription Desativa um usuário do tenant (soft delete)
     * 
     * @apiParam {Number} id ID do usuário
     * 
     * @apiSuccess {String} message Mensagem de confirmação
     * @apiError (404) UsuarioNaoEncontrado Usuário não existe no tenant
     * @apiError (500) ErroDesativacao Erro ao desativar usuário
     */
    public function excluir(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $userId = $request->getAttribute('userId');
        $tenantId = $request->getAttribute('tenantId');

        // Obter o usuário autenticado para verificar seu role
        $userAuth = $this->usuarioModel->findById($userId, $tenantId);
        $isSuperAdmin = $userAuth && isset($userAuth['role_id']) && $userAuth['role_id'] == 3;

        // Se for SuperAdmin, pode deletar usuários de qualquer tenant
        if ($isSuperAdmin) {
            // Buscar usuário sem restrição de tenant
            $usuario = $this->usuarioModel->findById($id, null);
            
            if (!$usuario) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Usuário não encontrado'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            // Não permitir deletar outros SuperAdmins
            if ($usuario['role_id'] == 3) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Não é permitido deletar usuários SuperAdmin'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }

            // Não permitir deletar Admins proprietários de tenants (role_id = 2)
            if ($usuario['role_id'] == 2) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Não é permitido deletar administradores de academias/tenants'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }

            // Desativar usando o tenant_id do usuário alvo
            $deleted = $this->usuarioModel->desativarUsuarioTenant($id, $usuario['tenant_id']);
        } else {
            // Se não for SuperAdmin, só pode deletar do próprio tenant
            $usuario = $this->usuarioModel->findById($id, $tenantId);
            
            if (!$usuario) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Usuário não encontrado'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            // Soft delete: atualizar status do vínculo com o tenant
            $deleted = $this->usuarioModel->desativarUsuarioTenant($id, $tenantId);
        }

        if (!$deleted) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao desativar usuário'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'Usuário desativado com sucesso'
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    // ========================================
    // Operações SuperAdmin
    // ========================================

    /**
     * Listar todos os usuários de todos os tenants (SuperAdmin)
     * 
     * @param Request $request Requisição HTTP
     * @param Response $response Resposta HTTP
     * @return Response JSON com lista de usuários
     * 
     * @api GET /superadmin/usuarios
     * @apiGroup SuperAdmin
     * @apiPermission superadmin
     * @apiDescription Lista todos os usuários do sistema, de todos os tenants
     * 
     * @apiQuery {Boolean} [ativos=false] Filtrar apenas usuários ativos
     * 
     * @apiSuccess {Array} usuarios Lista com todos os usuários incluindo dados do tenant
     * @apiSuccess {Number} usuarios.id ID do usuário
     * @apiSuccess {String} usuarios.nome Nome do usuário
     * @apiSuccess {String} usuarios.email Email do usuário
     * @apiSuccess {Object} usuarios.tenant Informações do tenant (id, nome, slug)
     * @apiSuccess {String} usuarios.role_nome Nome da role
     * @apiSuccess {String} usuarios.plano_nome Nome do plano
     * @apiSuccess {String} usuarios.status Status do vínculo (ativo/inativo)
     * 
     * @apiExample {curl} Exemplo de uso:
     *     curl -X GET http://api/superadmin/usuarios?ativos=true \
     *          -H "Authorization: Bearer {superadmin_token}"
     */
    public function listarTodos(Request $request, Response $response): Response
    {
        error_log("DEBUG UsuarioController::listarTodos() - INICIANDO (SuperAdmin)");
        
        $queryParams = $request->getQueryParams();
        $apenasAtivos = isset($queryParams['ativos']) && $queryParams['ativos'] === 'true';

        // SuperAdmin: passa isSuperAdmin=true para listar TODOS sem filtro
        $usuarios = $this->usuarioModel->listarTodos(true, null, $apenasAtivos);
        
        error_log("DEBUG UsuarioController::listarTodos() - Total usuarios: " . count($usuarios));

        $resultado = [
            'total' => count($usuarios),
            'usuarios' => $usuarios
        ];
        
        $response->getBody()->write(json_encode($resultado));
        return $response->withHeader('Content-Type', 'application/json');
    }

    // ========================================
    // Métodos Privados de Validação
    // ========================================

    /**
     * Validar dados do usuário
     * 
     * @param array $data Dados a validar
     * @param int $tenantId ID do tenant
     * @param int|null $excludeId ID do usuário a excluir da validação de email
     * @return array Lista de erros (vazio se válido)
     */
    private function validarDadosUsuario(array $data, int $tenantId, ?int $excludeId = null): array
    {
        $errors = [];

        // Nome obrigatório
        if (empty($data['nome'])) {
            $errors[] = 'Nome é obrigatório';
        }

        // Email obrigatório e válido
        if (empty($data['email'])) {
            $errors[] = 'Email é obrigatório';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email inválido';
        } elseif ($this->usuarioModel->emailExists($data['email'], $excludeId, $tenantId)) {
            $errors[] = 'Email já cadastrado';
        }

        // Senha obrigatória apenas na criação
        if (!$excludeId && empty($data['senha'])) {
            $errors[] = 'Senha é obrigatória';
        }

        // Validar senha se fornecida
        if (!empty($data['senha']) && strlen($data['senha']) < 6) {
            $errors[] = 'Senha deve ter no mínimo 6 caracteres';
        }

        // Validar plano_id se fornecido
        if (isset($data['plano_id']) && !empty($data['plano_id'])) {
            // Verificar se plano existe (implementar se necessário)
            // Por enquanto, apenas aceita números
            if (!is_numeric($data['plano_id'])) {
                $errors[] = 'Plano inválido';
            }
        }

        // Validar role_id se fornecido
        if (isset($data['role_id']) && !empty($data['role_id'])) {
            // Role 1 = Aluno, Role 2 = Admin, Role 3 = SuperAdmin
            if (!in_array($data['role_id'], [1, 2, 3])) {
                $errors[] = 'Role inválido';
            }
        }

        return $errors;
    }
}
