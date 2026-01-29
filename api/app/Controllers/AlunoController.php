<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Aluno;
use App\Models\Usuario;
use PDO;

/**
 * AlunoController
 * 
 * CRUD completo para gestão de alunos.
 * Separa dados de perfil (alunos) de autenticação (usuarios).
 * 
 * Rotas:
 * - GET    /admin/alunos           - Listar alunos do tenant
 * - GET    /admin/alunos/basico    - Listar alunos (dados básicos)
 * - GET    /admin/alunos/{id}      - Buscar aluno por ID
 * - POST   /admin/alunos           - Criar novo aluno
 * - PUT    /admin/alunos/{id}      - Atualizar aluno
 * - DELETE /admin/alunos/{id}      - Desativar aluno
 */
class AlunoController
{
    private PDO $db;
    private Aluno $alunoModel;
    private Usuario $usuarioModel;

    public function __construct()
    {
        $this->db = require __DIR__ . '/../../config/database.php';
        $this->alunoModel = new Aluno($this->db);
        $this->usuarioModel = new Usuario($this->db);
    }

    /**
     * Listar todos os alunos do tenant
     * GET /admin/alunos
     */
    public function index(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $queryParams = $request->getQueryParams();
        
        $apenasAtivos = isset($queryParams['apenas_ativos']) && $queryParams['apenas_ativos'] === 'true';
        $busca = $queryParams['busca'] ?? null;
        $pagina = (int) ($queryParams['pagina'] ?? 1);
        $porPagina = (int) ($queryParams['por_pagina'] ?? 50);
        
        // Se tiver busca ou paginação específica, usar método paginado
        if ($busca || isset($queryParams['pagina'])) {
            $alunos = $this->alunoModel->listarPaginado($tenantId, $pagina, $porPagina, $busca);
            $total = $this->alunoModel->contarPorTenant($tenantId, true);
            
            $response->getBody()->write(json_encode([
                'alunos' => $this->enriquecerAlunos($alunos, $tenantId),
                'total' => $total,
                'pagina' => $pagina,
                'por_pagina' => $porPagina,
                'total_paginas' => ceil($total / $porPagina)
            ], JSON_UNESCAPED_UNICODE));
        } else {
            $alunos = $this->alunoModel->listarPorTenant($tenantId, $apenasAtivos);
            
            $response->getBody()->write(json_encode([
                'alunos' => $this->enriquecerAlunos($alunos, $tenantId),
                'total' => count($alunos)
            ], JSON_UNESCAPED_UNICODE));
        }
        
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Listar alunos (dados básicos) - para selects
     * GET /admin/alunos/basico
     */
    public function listarBasico(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        
        $stmt = $this->db->prepare("
            SELECT a.id, a.nome, u.email, a.usuario_id
            FROM alunos a
            INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = a.usuario_id 
                AND tup.tenant_id = :tenant_id 
                AND tup.papel_id = 1 
                AND tup.ativo = 1
            LEFT JOIN usuarios u ON u.id = a.usuario_id
            WHERE a.ativo = 1
            ORDER BY a.nome ASC
        ");
        $stmt->execute(['tenant_id' => $tenantId]);
        $alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $response->getBody()->write(json_encode([
            'alunos' => $alunos,
            'total' => count($alunos)
        ], JSON_UNESCAPED_UNICODE));
        
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Buscar aluno por ID
     * GET /admin/alunos/{id}
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId');
        
        $aluno = $this->alunoModel->findById($id, $tenantId);
        
        if (!$aluno) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Aluno não encontrado'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }
        
        // Enriquecer com dados adicionais
        $aluno = $this->enriquecerAluno($aluno, $tenantId);
        
        $response->getBody()->write(json_encode([
            'aluno' => $aluno
        ], JSON_UNESCAPED_UNICODE));
        
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Criar novo aluno
     * POST /admin/alunos
     * 
     * Fluxo:
     * 1. Valida dados
     * 2. Cria usuário (autenticação)
     * 3. Cria aluno (perfil) - feito automaticamente pelo Usuario::create()
     * 4. Vincula ao tenant com papel de aluno
     */
    public function create(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $data = $request->getParsedBody();
        
        // Validações
        $errors = $this->validarDados($data, null, $tenantId);
        
        if (!empty($errors)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro de validação',
                'errors' => $errors
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(422);
        }
        
        try {
            $this->db->beginTransaction();
            
            // Verificar se já existe usuário com esse email
            $usuarioExistente = $this->usuarioModel->findByEmailGlobal($data['email']);
            
            if ($usuarioExistente) {
                // Usuário já existe - verificar se já é aluno neste tenant
                $alunoExistente = $this->alunoModel->findByUsuarioIdAndTenant($usuarioExistente['id'], $tenantId);
                
                if ($alunoExistente) {
                    $this->db->rollBack();
                    $response->getBody()->write(json_encode([
                        'type' => 'error',
                        'message' => 'Este email já está cadastrado como aluno neste tenant'
                    ], JSON_UNESCAPED_UNICODE));
                    return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
                }
                
                // Vincular usuário existente como aluno neste tenant
                $usuarioId = $usuarioExistente['id'];
                
                // Verificar se já tem registro em alunos
                $alunoGlobal = $this->alunoModel->findByUsuarioId($usuarioId);
                
                if (!$alunoGlobal) {
                    // Criar registro em alunos
                    $data['usuario_id'] = $usuarioId;
                    $this->alunoModel->create($data);
                }
                
                // Adicionar vínculo com tenant
                $this->criarVinculoTenant($usuarioId, $tenantId);
                
                // Adicionar papel de aluno
                $this->adicionarPapel($usuarioId, $tenantId, 1);
                
                $aluno = $this->alunoModel->findByUsuarioId($usuarioId);
                
            } else {
                // Criar novo usuário (isso automaticamente cria o aluno e vínculos)
                $data['role_id'] = 1; // aluno
                $usuarioId = $this->usuarioModel->create($data, $tenantId);
                
                if (!$usuarioId) {
                    throw new \Exception('Erro ao criar usuário');
                }
                
                $aluno = $this->alunoModel->findByUsuarioId($usuarioId);
            }
            
            $this->db->commit();
            
            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Aluno criado com sucesso',
                'aluno' => $this->enriquecerAluno($aluno, $tenantId)
            ], JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(201);
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao criar aluno: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Atualizar aluno
     * PUT /admin/alunos/{id}
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId');
        $data = $request->getParsedBody();
        
        // Verificar se aluno existe
        $aluno = $this->alunoModel->findById($id, $tenantId);
        
        if (!$aluno) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Aluno não encontrado'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }
        
        // Validações (excluindo o próprio aluno na verificação de email)
        $errors = $this->validarDados($data, $aluno['usuario_id'], $tenantId, true);
        
        if (!empty($errors)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro de validação',
                'errors' => $errors
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(422);
        }
        
        try {
            $this->db->beginTransaction();
            
            // Atualizar dados do aluno (perfil)
            $this->alunoModel->update($id, $data);
            
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
                    $this->usuarioModel->update($aluno['usuario_id'], $usuarioData);
                }
            }
            
            $this->db->commit();
            
            $alunoAtualizado = $this->alunoModel->findById($id, $tenantId);
            
            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Aluno atualizado com sucesso',
                'aluno' => $this->enriquecerAluno($alunoAtualizado, $tenantId)
            ], JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao atualizar aluno: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Desativar aluno (soft delete)
     * DELETE /admin/alunos/{id}
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId');
        
        $aluno = $this->alunoModel->findById($id, $tenantId);
        
        if (!$aluno) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Aluno não encontrado'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }
        
        try {
            $this->db->beginTransaction();
            
            // Desativar aluno
            $this->alunoModel->delete($id);
            
            // Desativar papel no tenant
            $stmt = $this->db->prepare(
                "UPDATE tenant_usuario_papel SET ativo = 0, updated_at = CURRENT_TIMESTAMP 
                 WHERE usuario_id = :usuario_id AND tenant_id = :tenant_id AND papel_id = 1"
            );
            $stmt->execute([
                'usuario_id' => $aluno['usuario_id'],
                'tenant_id' => $tenantId
            ]);
            
            // Desativar vínculo com tenant
            $stmt = $this->db->prepare(
                "UPDATE usuario_tenant SET status = 'inativo' 
                 WHERE usuario_id = :usuario_id AND tenant_id = :tenant_id"
            );
            $stmt->execute([
                'usuario_id' => $aluno['usuario_id'],
                'tenant_id' => $tenantId
            ]);
            
            $this->db->commit();
            
            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Aluno desativado com sucesso'
            ], JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao desativar aluno: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Buscar histórico de planos do aluno
     * GET /admin/alunos/{id}/historico-planos
     */
    public function historicoPlanos(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId');
        
        $aluno = $this->alunoModel->findById($id, $tenantId);
        
        if (!$aluno) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Aluno não encontrado'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }
        
        $stmt = $this->db->prepare("
            SELECT m.*, p.nome as plano_nome, p.valor as plano_valor
            FROM matriculas m
            INNER JOIN planos p ON p.id = m.plano_id
            INNER JOIN alunos a ON a.id = m.aluno_id
            WHERE a.usuario_id = :usuario_id AND m.tenant_id = :tenant_id
            ORDER BY m.created_at DESC
        ");
        $stmt->execute([
            'usuario_id' => $aluno['usuario_id'],
            'tenant_id' => $tenantId
        ]);
        $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $response->getBody()->write(json_encode([
            'historico' => $historico
        ], JSON_UNESCAPED_UNICODE));
        
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    // ========================================
    // Métodos Auxiliares
    // ========================================

    /**
     * Validar dados do aluno
     */
    private function validarDados(array $data, ?int $usuarioIdExcluir, int $tenantId, bool $isUpdate = false): array
    {
        $errors = [];
        
        // Nome obrigatório apenas na criação
        if (!$isUpdate && empty($data['nome'])) {
            $errors[] = 'Nome é obrigatório';
        }
        
        // Email obrigatório apenas na criação
        if (!$isUpdate) {
            if (empty($data['email'])) {
                $errors[] = 'Email é obrigatório';
            } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email inválido';
            } elseif ($this->usuarioModel->emailExists($data['email'], $usuarioIdExcluir, $tenantId)) {
                $errors[] = 'Email já cadastrado';
            }
        } elseif (isset($data['email'])) {
            // Na atualização, validar apenas se foi informado
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email inválido';
            } elseif ($this->usuarioModel->emailExists($data['email'], $usuarioIdExcluir, $tenantId)) {
                $errors[] = 'Email já cadastrado';
            }
        }
        
        // Senha obrigatória apenas na criação
        if (!$isUpdate) {
            if (empty($data['senha'])) {
                $errors[] = 'Senha é obrigatória';
            } elseif (strlen($data['senha']) < 6) {
                $errors[] = 'Senha deve ter no mínimo 6 caracteres';
            }
        } elseif (isset($data['senha']) && !empty($data['senha']) && strlen($data['senha']) < 6) {
            $errors[] = 'Senha deve ter no mínimo 6 caracteres';
        }
        
        // Validar CPF se informado
        if (!empty($data['cpf'])) {
            $cpfLimpo = preg_replace('/[^0-9]/', '', $data['cpf']);
            if (strlen($cpfLimpo) !== 11) {
                $errors[] = 'CPF deve ter 11 dígitos';
            }
        }
        
        // Validar telefone se informado
        if (!empty($data['telefone'])) {
            $telefoneLimpo = preg_replace('/[^0-9]/', '', $data['telefone']);
            if (strlen($telefoneLimpo) < 10 || strlen($telefoneLimpo) > 11) {
                $errors[] = 'Telefone deve ter 10 ou 11 dígitos';
            }
        }
        
        return $errors;
    }

    /**
     * Cria vínculo do usuário com tenant
     */
    private function criarVinculoTenant(int $usuarioId, int $tenantId): void
    {
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
        } else {
            // Reativar se existir
            $stmt = $this->db->prepare(
                "UPDATE usuario_tenant SET status = 'ativo' 
                 WHERE usuario_id = :usuario_id AND tenant_id = :tenant_id"
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
            "INSERT INTO tenant_usuario_papel (tenant_id, usuario_id, papel_id, ativo) 
             VALUES (:tenant_id, :usuario_id, :papel_id, 1)
             ON DUPLICATE KEY UPDATE ativo = 1"
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'usuario_id' => $usuarioId,
            'papel_id' => $papelId
        ]);
    }

    /**
     * Enriquece lista de alunos com dados adicionais
     */
    private function enriquecerAlunos(array $alunos, int $tenantId): array
    {
        foreach ($alunos as &$aluno) {
            $aluno = $this->enriquecerAluno($aluno, $tenantId);
        }
        return $alunos;
    }

    /**
     * Enriquece um aluno com dados adicionais (plano, checkins, etc)
     */
    private function enriquecerAluno(array $aluno, int $tenantId): array
    {
        $usuarioId = $aluno['usuario_id'];
        $alunoId = $aluno['id'];
        
        // Buscar matrícula ativa
        $stmt = $this->db->prepare("
            SELECT m.*, p.nome as plano_nome, p.valor as plano_valor
            FROM matriculas m
            INNER JOIN planos p ON p.id = m.plano_id
            INNER JOIN status_matricula sm ON sm.id = m.status_id
            WHERE m.aluno_id = :aluno_id AND m.tenant_id = :tenant_id AND sm.codigo = 'ativa'
            ORDER BY m.created_at DESC
            LIMIT 1
        ");
        $stmt->execute(['aluno_id' => $alunoId, 'tenant_id' => $tenantId]);
        $matricula = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($matricula) {
            $aluno['plano'] = [
                'id' => $matricula['plano_id'],
                'nome' => $matricula['plano_nome'],
                'valor' => $matricula['plano_valor']
            ];
            $aluno['matricula_id'] = $matricula['id'];
        } else {
            $aluno['plano'] = null;
            $aluno['matricula_id'] = null;
        }
        
        // Contar checkins (usando aluno_id)
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total, MAX(created_at) as ultimo
            FROM checkins 
            WHERE aluno_id = :aluno_id AND tenant_id = :tenant_id
        ");
        $stmt->execute(['aluno_id' => $alunoId, 'tenant_id' => $tenantId]);
        $checkins = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $aluno['total_checkins'] = (int) ($checkins['total'] ?? 0);
        $aluno['ultimo_checkin'] = $checkins['ultimo'];
        
        // Verificar status de pagamento
        // Se não tem matrícula/plano, pagamento é null (não aplicável)
        // Se tem matrícula, verifica se há pagamento ativo
        if (!$matricula) {
            // Sem plano = sem obrigação de pagamento
            $aluno['pagamento_ativo'] = null; // null indica "não aplicável"
        } else {
            // Com plano, verificar se há pagamento pago com cobertura válida
            $stmt = $this->db->prepare("
                SELECT COUNT(*) > 0 as ativo
                FROM pagamentos_plano pp
                WHERE pp.aluno_id = :aluno_id
                AND pp.tenant_id = :tenant_id
                AND pp.status_pagamento_id = 2
                AND DATE_ADD(pp.data_vencimento, INTERVAL 30 DAY) >= CURDATE()
            ");
            $stmt->execute(['aluno_id' => $alunoId, 'tenant_id' => $tenantId]);
            $pagamento = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $aluno['pagamento_ativo'] = (bool) ($pagamento['ativo'] ?? false);
        }
        
        return $aluno;
    }

    /**
     * Buscar aluno por CPF (global, sem filtro de tenant)
     * 
     * GET /admin/alunos/buscar-cpf/{cpf}
     * 
     * Busca se já existe um aluno com esse CPF em qualquer tenant.
     * Útil para reutilizar cadastro de aluno em outro tenant.
     */
    public function buscarPorCpf(Request $request, Response $response, array $args): Response
    {
        $cpf = $args['cpf'] ?? '';
        $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf);
        $tenantId = $request->getAttribute('tenantId');
        
        if (strlen($cpfLimpo) !== 11) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'CPF deve conter 11 dígitos'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        if (!$this->validarCPF($cpfLimpo)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'CPF inválido'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        // Buscar aluno por CPF (global)
        $aluno = $this->alunoModel->findByCpf($cpfLimpo);
        
        if (!$aluno) {
            $response->getBody()->write(json_encode([
                'success' => true,
                'found' => false,
                'message' => 'Aluno não encontrado. Você pode cadastrar um novo aluno.'
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }
        
        // Verificar se o aluno já está associado ao tenant atual via tenant_usuario_papel
        $jaAssociado = false;
        if ($aluno['usuario_id']) {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) > 0 as associado 
                 FROM tenant_usuario_papel 
                 WHERE usuario_id = :usuario_id AND tenant_id = :tenant_id AND papel_id = 1"
            );
            $stmt->execute(['usuario_id' => $aluno['usuario_id'], 'tenant_id' => $tenantId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $jaAssociado = (bool) ($result['associado'] ?? false);
        }
        
        // Buscar tenants aos quais o aluno está associado
        $tenants = [];
        if ($aluno['usuario_id']) {
            $stmt = $this->db->prepare(
                "SELECT t.id, t.nome, t.slug 
                 FROM tenant_usuario_papel tup
                 INNER JOIN tenants t ON t.id = tup.tenant_id
                 WHERE tup.usuario_id = :usuario_id AND tup.papel_id = 1"
            );
            $stmt->execute(['usuario_id' => $aluno['usuario_id']]);
            $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'found' => true,
            'aluno' => [
                'id' => (int) $aluno['id'],
                'usuario_id' => (int) $aluno['usuario_id'],
                'nome' => $aluno['nome'],
                'email' => $aluno['email'] ?? null,
                'telefone' => $aluno['telefone'],
                'cpf' => $aluno['cpf']
            ],
            'tenants' => $tenants,
            'ja_associado' => $jaAssociado,
            'pode_associar' => !$jaAssociado
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Associar aluno existente ao tenant atual
     * 
     * POST /admin/alunos/associar
     * 
     * Associa um aluno que já existe em outro tenant ao tenant atual.
     * Cria o vínculo em tenant_usuario_papel com papel_id=1 (Aluno).
     */
    public function associarAluno(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $tenantId = $request->getAttribute('tenantId');
        
        if (empty($data['aluno_id'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'ID do aluno é obrigatório'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $alunoId = (int) $data['aluno_id'];
        
        // Buscar aluno (global, sem filtro de tenant)
        $stmt = $this->db->prepare("SELECT * FROM alunos WHERE id = :id");
        $stmt->execute(['id' => $alunoId]);
        $aluno = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$aluno) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Aluno não encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        $usuarioId = $aluno['usuario_id'];
        
        // Verificar se já está associado ao tenant como aluno
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) > 0 as associado 
             FROM tenant_usuario_papel 
             WHERE usuario_id = :usuario_id AND tenant_id = :tenant_id AND papel_id = 1"
        );
        $stmt->execute(['usuario_id' => $usuarioId, 'tenant_id' => $tenantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['associado']) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Aluno já está associado a esta academia'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
        }
        
        // Verificar se o vínculo usuario_tenant existe, senão criar
        $stmt = $this->db->prepare(
            "SELECT id FROM usuario_tenant WHERE usuario_id = :usuario_id AND tenant_id = :tenant_id"
        );
        $stmt->execute(['usuario_id' => $usuarioId, 'tenant_id' => $tenantId]);
        $vinculo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$vinculo) {
            // Criar vínculo usuario_tenant
            $stmt = $this->db->prepare(
                "INSERT INTO usuario_tenant (usuario_id, tenant_id, status, data_inicio) 
                 VALUES (:usuario_id, :tenant_id, 'ativo', CURDATE())"
            );
            $stmt->execute(['usuario_id' => $usuarioId, 'tenant_id' => $tenantId]);
        }
        
        // Associar como aluno (papel_id=1) no tenant
        $stmt = $this->db->prepare(
            "INSERT INTO tenant_usuario_papel (tenant_id, usuario_id, papel_id, ativo, created_at, updated_at) 
             VALUES (:tenant_id, :usuario_id, 1, 1, NOW(), NOW())"
        );
        $success = $stmt->execute(['tenant_id' => $tenantId, 'usuario_id' => $usuarioId]);
        
        if (!$success) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Erro ao associar aluno'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        
        // Buscar dados atualizados do aluno
        $alunoAtualizado = $this->getAlunoDetalhado($alunoId, $tenantId);
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Aluno associado com sucesso',
            'aluno' => $alunoAtualizado
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Validar CPF
     */
    private function validarCPF(string $cpf): bool
    {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        
        if (strlen($cpf) !== 11) {
            return false;
        }
        
        // CPFs inválidos conhecidos
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }
        
        // Validar dígitos verificadores
        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }
        
        return true;
    }
}
