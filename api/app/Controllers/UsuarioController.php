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
        $usuarioLogado = $request->getAttribute('usuario');
        $tenantId = $request->getAttribute('tenantId');
        $queryParams = $request->getQueryParams();
        $apenasAtivos = isset($queryParams['ativos']) && $queryParams['ativos'] === 'true';
        
        // SuperAdmin (role_id = 3) pode listar usuários de todos os tenants
        $isSuperAdmin = isset($usuarioLogado['role_id']) && $usuarioLogado['role_id'] == 3;

        if ($isSuperAdmin) {
            // SuperAdmin: listar todos os usuários
            $usuarios = $this->usuarioModel->listarTodos(true, null, $apenasAtivos);
        } else {
            // Admin/Aluno: listar apenas do próprio tenant
            $usuarios = $this->usuarioModel->listarPorTenant($tenantId, $apenasAtivos);
        }

        // Filtrar apenas alunos (role_id = 1) - Admins não aparecem na tela de usuários (exceto para SuperAdmin)
        if (!$isSuperAdmin) {
            $usuarios = array_filter($usuarios, function($usuario) {
                return $usuario['role_id'] == 1;
            });
        }

        // Reindexar array
        $usuarios = array_values($usuarios);

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
        $usuarioLogado = $request->getAttribute('usuario');
        $tenantId = $request->getAttribute('tenantId');
        
        // SuperAdmin (role_id = 3) pode acessar usuários de qualquer tenant
        $isSuperAdmin = isset($usuarioLogado['role_id']) && $usuarioLogado['role_id'] == 3;
        
        // Se for SuperAdmin, não filtra por tenant
        $usuario = $this->usuarioModel->findById($id, $isSuperAdmin ? null : $tenantId);

        if (!$usuario) {
            $response->getBody()->write(json_encode([
                'error' => 'Usuário não encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Bloquear visualização de usuários Admin (role_id >= 2) pela tela de usuários (exceto SuperAdmin)
        if (!$isSuperAdmin && $usuario['role_id'] >= 2) {
            $response->getBody()->write(json_encode([
                'error' => 'Usuários administradores só podem ser visualizados pela tela de Academia'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
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
        $usuarioLogado = $request->getAttribute('usuario');
        $tenantId = $request->getAttribute('tenantId');
        $data = $request->getParsedBody();
        
        // SuperAdmin (role_id = 3) pode editar usuários de qualquer tenant
        $isSuperAdmin = isset($usuarioLogado['role_id']) && $usuarioLogado['role_id'] == 3;

        // Verificar se usuário pertence ao tenant (ou se é SuperAdmin)
        $usuarioExistente = $this->usuarioModel->findById($id, $isSuperAdmin ? null : $tenantId);
        if (!$usuarioExistente) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Usuário não encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Bloquear edição de usuários Admin (role_id >= 2) pela tela de usuários (exceto SuperAdmin)
        if (!$isSuperAdmin && $usuarioExistente['role_id'] >= 2) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Usuários administradores só podem ser editados pela tela de Academia'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
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

        // Se for SuperAdmin, pode alterar status de usuários de qualquer tenant
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

            // Não permitir alterar status de outros SuperAdmins
            if ($usuario['role_id'] == 3) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Não é permitido alterar status de usuários SuperAdmin'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }

            // Não permitir alterar status de Admins proprietários de tenants (role_id = 2)
            if ($usuario['role_id'] == 2) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Não é permitido alterar status de administradores de academias/tenants'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }

            // Alternar status usando o tenant_id do usuário alvo
            $toggled = $this->usuarioModel->toggleStatusUsuarioTenant($id, $usuario['tenant_id']);
        } else {
            // Se não for SuperAdmin, só pode alterar status do próprio tenant
            $usuario = $this->usuarioModel->findById($id, $tenantId);
            
            if (!$usuario) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Usuário não encontrado'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            // Alternar status do vínculo com o tenant
            $toggled = $this->usuarioModel->toggleStatusUsuarioTenant($id, $tenantId);
        }

        if (!$toggled) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao alterar status do usuário'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        // Buscar usuário atualizado para retornar status correto
        $usuarioAtualizado = $this->usuarioModel->findById($id, $tenantId);
        $acao = $usuarioAtualizado['status'] === 'ativo' ? 'ativado' : 'desativado';

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => "Usuário {$acao} com sucesso"
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

    /**
     * Buscar um usuário específico (SuperAdmin - sem restrição de tenant)
     * 
     * @param Request $request Requisição HTTP
     * @param Response $response Resposta HTTP
     * @param array $args Argumentos da rota (id)
     * @return Response JSON com dados do usuário
     * 
     * @api GET /superadmin/usuarios/{id}
     * @apiGroup SuperAdmin
     * @apiPermission superadmin
     * 
     * @apiParam {Number} id ID do usuário
     * 
     * @apiSuccess {Object} usuario Dados completos do usuário
     * @apiError (404) UsuarioNaoEncontrado Usuário não existe
     */
    public function buscarSuperAdmin(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];

        // SuperAdmin pode buscar usuário de qualquer tenant (passa null no tenantId)
        $usuario = $this->usuarioModel->findById($id, null);

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
     * Atualizar usuário (SuperAdmin - sem restrição de tenant)
     * 
     * @param Request $request Requisição HTTP com dados a atualizar
     * @param Response $response Resposta HTTP
     * @param array $args Argumentos da rota (id)
     * @return Response JSON com usuário atualizado
     * 
     * @api PUT /superadmin/usuarios/{id}
     * @apiGroup SuperAdmin
     * @apiPermission superadmin
     * 
     * @apiParam {Number} id ID do usuário
     * @apiParam {String} [nome] Nome completo
     * @apiParam {String} [email] Email
     * @apiParam {String} [senha] Nova senha
     * @apiParam {String} [telefone] Telefone
     * @apiParam {String} [cpf] CPF
     * @apiParam {String} [cep] CEP
     * @apiParam {String} [logradouro] Logradouro
     * @apiParam {String} [numero] Número
     * @apiParam {String} [complemento] Complemento
     * @apiParam {String} [bairro] Bairro
     * @apiParam {String} [cidade] Cidade
     * @apiParam {String} [estado] Estado (UF)
     * @apiParam {Number} [plano_id] ID do plano
     * @apiParam {Number} [role_id] ID da role
     * 
     * @apiSuccess {String} message Mensagem de sucesso
     * @apiSuccess {Object} usuario Dados do usuário atualizado
     * @apiError (404) UsuarioNaoEncontrado Usuário não existe
     * @apiError (422) ValidacaoFalhou Erros de validação
     */
    public function atualizarSuperAdmin(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $data = $request->getParsedBody();

        // Buscar usuário sem restrição de tenant
        $usuario = $this->usuarioModel->findById($id, null);

        if (!$usuario) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Usuário não encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Validar usando o tenant_id do usuário que está sendo editado
        $errors = $this->validarDadosUsuario($data, $usuario['tenant_id'], $id);

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

        $usuario = $this->usuarioModel->findById($id, null);

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'Usuário atualizado com sucesso',
            'usuario' => $usuario
        ]));

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

        // Validar CPF se fornecido
        if (!empty($data['cpf'])) {
            $cpfLimpo = preg_replace('/[^0-9]/', '', $data['cpf']);
            if (strlen($cpfLimpo) !== 11) {
                $errors[] = 'CPF deve conter 11 dígitos';
            } elseif (!$this->validarCPF($cpfLimpo)) {
                $errors[] = 'CPF inválido';
            }
        }

        // Validar CEP se fornecido
        if (!empty($data['cep'])) {
            $cepLimpo = preg_replace('/[^0-9]/', '', $data['cep']);
            if (strlen($cepLimpo) !== 8) {
                $errors[] = 'CEP deve conter 8 dígitos';
            }
        }

        // Validar Estado se fornecido
        if (!empty($data['estado']) && strlen($data['estado']) !== 2) {
            $errors[] = 'Estado deve ter 2 caracteres (sigla UF)';
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

    /**
     * Validar CPF
     * 
     * @param string $cpf CPF sem formatação (apenas números)
     * @return bool True se válido
     */
    private function validarCPF(string $cpf): bool
    {
        // Eliminar CPFs conhecidos como inválidos
        if (strlen($cpf) != 11 || preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        // Validar primeiro dígito verificador
        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }
        return true;
    }

    // ========================================
    // Rotas para Busca e Associação de Usuário
    // ========================================

    /**
     * Buscar usuário por CPF (global, sem filtro de tenant)
     * 
     * @api GET /tenant/usuarios/buscar-cpf/{cpf}
     * @apiGroup Usuario
     * @apiDescription Busca usuário por CPF em toda a base (todos os tenants)
     * 
     * @param Request $request
     * @param Response $response
     * @param array $args ['cpf' => string]
     * @return Response
     */
    public function buscarPorCpf(Request $request, Response $response, array $args): Response
    {
        $cpf = $args['cpf'] ?? '';
        $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf);
        
        if (strlen($cpfLimpo) !== 11) {
            $response->getBody()->write(json_encode([
                'error' => 'CPF deve conter 11 dígitos'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        if (!$this->validarCPF($cpfLimpo)) {
            $response->getBody()->write(json_encode([
                'error' => 'CPF inválido'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $usuario = $this->usuarioModel->findByCpf($cpfLimpo);
        
        if (!$usuario) {
            $response->getBody()->write(json_encode([
                'found' => false,
                'message' => 'Usuário não encontrado. Você pode cadastrar um novo usuário.'
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }
        
        // Buscar tenants aos quais o usuário está associado
        $tenants = $this->usuarioModel->getTenantsByUsuario($usuario['id']);
        
        // Verificar se já está associado ao tenant atual
        $tenantId = $request->getAttribute('tenantId');
        $jaAssociado = $this->usuarioModel->isAssociatedWithTenant($usuario['id'], $tenantId);
        
        $response->getBody()->write(json_encode([
            'found' => true,
            'usuario' => [
                'id' => $usuario['id'],
                'nome' => $usuario['nome'],
                'email' => $usuario['email'],
                'telefone' => $usuario['telefone'],
                'cpf' => $usuario['cpf']
            ],
            'tenants' => $tenants,
            'ja_associado' => $jaAssociado,
            'pode_associar' => !$jaAssociado
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Associar usuário existente ao tenant atual
     * 
     * @api POST /tenant/usuarios/associar
     * @apiGroup Usuario
     * @apiDescription Associa um usuário existente ao tenant atual
     * 
     * @apiParam {Number} usuario_id ID do usuário a ser associado
     * @apiParam {String} [status=ativo] Status da associação
     * 
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function associarUsuario(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $tenantId = $request->getAttribute('tenantId');
        
        if (empty($data['usuario_id'])) {
            $response->getBody()->write(json_encode([
                'error' => 'ID do usuário é obrigatório'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $usuarioId = (int) $data['usuario_id'];
        $status = $data['status'] ?? 'ativo';
        
        // Verificar se usuário existe
        $usuario = $this->usuarioModel->findById($usuarioId, null); // null = busca global
        if (!$usuario) {
            $response->getBody()->write(json_encode([
                'error' => 'Usuário não encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        // Verificar se já está associado
        if ($this->usuarioModel->isAssociatedWithTenant($usuarioId, $tenantId)) {
            $response->getBody()->write(json_encode([
                'error' => 'Usuário já está associado a esta academia'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
        }
        
        // Associar usuário ao tenant
        $success = $this->usuarioModel->associateToTenant($usuarioId, $tenantId, $status);
        
        if (!$success) {
            $response->getBody()->write(json_encode([
                'error' => 'Erro ao associar usuário'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        
        // Buscar dados atualizados do usuário
        $usuarioAtualizado = $this->usuarioModel->findById($usuarioId, $tenantId);
        
        $response->getBody()->write(json_encode([
            'message' => 'Usuário associado com sucesso',
            'usuario' => $usuarioAtualizado
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }
}
