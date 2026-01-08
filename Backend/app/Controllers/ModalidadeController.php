<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Modalidade;
use PDO;

class ModalidadeController
{
    private Modalidade $modalidadeModel;

    public function __construct()
    {
        $db = require __DIR__ . '/../../config/database.php';
        $this->modalidadeModel = new Modalidade($db);
    }

    /**
     * Listar modalidades do tenant
     * GET /admin/modalidades
     */
    public function index(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $queryParams = $request->getQueryParams();
        $apenasAtivas = isset($queryParams['apenas_ativas']) && $queryParams['apenas_ativas'] === 'true';
        
        $modalidades = $this->modalidadeModel->listarPorTenant($tenantId, $apenasAtivas);
        
        $response->getBody()->write(json_encode([
            'modalidades' => $modalidades
        ], JSON_UNESCAPED_UNICODE));
        
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Buscar modalidade por ID
     * GET /admin/modalidades/{id}
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId');
        
        $modalidade = $this->modalidadeModel->buscarPorId($id);
        
        if (!$modalidade) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Modalidade não encontrada'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }
        
        // Verificar se a modalidade pertence ao tenant
        if ($modalidade['tenant_id'] !== $tenantId) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Acesso negado'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(403);
        }
        
        $response->getBody()->write(json_encode([
            'modalidade' => $modalidade
        ], JSON_UNESCAPED_UNICODE));
        
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Criar nova modalidade
     * POST /admin/modalidades
     */
    public function create(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $data = $request->getParsedBody();
        
        // Validações
        $errors = [];
        if (empty($data['nome'])) {
            $errors[] = 'Nome é obrigatório';
        }
        
        if (!empty($errors)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => implode(', ', $errors)
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(422);
        }
        
        // Verificar se nome já existe
        if ($this->modalidadeModel->nomeExiste($tenantId, $data['nome'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Já existe uma modalidade com este nome'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(422);
        }
        
        try {
            $data['tenant_id'] = $tenantId;
            
            // Iniciar transação para criar modalidade + planos
            $db = require __DIR__ . '/../../config/database.php';
            $db->beginTransaction();
            
            try {
                // 1. Criar modalidade
                $modalidadeId = $this->modalidadeModel->criar($data);
                
                // 2. Criar planos se foram enviados
                if (!empty($data['planos']) && is_array($data['planos'])) {
                    $stmt = $db->prepare("
                        INSERT INTO planos 
                        (tenant_id, modalidade_id, nome, valor, checkins_semanais, duracao_dias, ativo, atual) 
                        VALUES (?, ?, ?, ?, ?, ?, 1, 1)
                    ");
                    
                    foreach ($data['planos'] as $plano) {
                        $stmt->execute([
                            $tenantId,
                            $modalidadeId,
                            $plano['nome'],
                            $plano['valor'],
                            $plano['checkins_semanais'],
                            $plano['duracao_dias'] ?? 30
                        ]);
                    }
                }
                
                $db->commit();
                
                $modalidade = $this->modalidadeModel->buscarPorId($modalidadeId);
                
                $response->getBody()->write(json_encode([
                    'type' => 'success',
                    'message' => 'Modalidade criada com sucesso',
                    'modalidade' => $modalidade
                ], JSON_UNESCAPED_UNICODE));
                
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(201);
                
            } catch (\Exception $e) {
                $db->rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao criar modalidade: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Atualizar modalidade
     * PUT /admin/modalidades/{id}
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId');
        $data = $request->getParsedBody();
        
        $modalidade = $this->modalidadeModel->buscarPorId($id);
        
        if (!$modalidade) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Modalidade não encontrada'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }
        
        // Verificar se a modalidade pertence ao tenant
        if ($modalidade['tenant_id'] !== $tenantId) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Acesso negado'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(403);
        }
        
        // Validações
        $errors = [];
        if (empty($data['nome'])) {
            $errors[] = 'Nome é obrigatório';
        }
        
        if (!empty($errors)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => implode(', ', $errors)
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(422);
        }
        
        // Verificar se nome já existe (exceto para própria modalidade)
        if ($this->modalidadeModel->nomeExiste($tenantId, $data['nome'], $id)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Já existe uma modalidade com este nome'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(422);
        }
        
        try {
            // Iniciar transação para atualizar modalidade + planos
            $db = require __DIR__ . '/../../config/database.php';
            $db->beginTransaction();
            
            try {
                // 1. Atualizar modalidade
                $this->modalidadeModel->atualizar($id, $data);
                
                // 2. Gerenciar planos se foram enviados
                if (!empty($data['planos']) && is_array($data['planos'])) {
                    // Buscar IDs dos planos atuais
                    $stmtExistentes = $db->prepare("SELECT id FROM planos WHERE modalidade_id = ?");
                    $stmtExistentes->execute([$id]);
                    $idsExistentes = array_column($stmtExistentes->fetchAll(PDO::FETCH_ASSOC), 'id');
                    
                    $idsEnviados = [];
                    
                    foreach ($data['planos'] as $plano) {
                        // Validar se já existe um plano com as mesmas características
                        $stmtCheck = $db->prepare("
                            SELECT id FROM planos 
                            WHERE modalidade_id = ? 
                            AND nome = ? 
                            AND valor = ? 
                            AND checkins_semanais = ? 
                            AND duracao_dias = ?
                            AND id != ?
                        ");
                        $stmtCheck->execute([
                            $id,
                            $plano['nome'],
                            $plano['valor'],
                            $plano['checkins_semanais'],
                            $plano['duracao_dias'] ?? 30,
                            $plano['id'] ?? 0
                        ]);
                        
                        if ($stmtCheck->fetch()) {
                            throw new \Exception("Já existe um plano com essas características: {$plano['nome']}");
                        }
                        
                        if (!empty($plano['id'])) {
                            // Atualizar plano existente
                            $stmt = $db->prepare("
                                UPDATE planos SET 
                                    nome = ?, 
                                    valor = ?, 
                                    checkins_semanais = ?, 
                                    duracao_dias = ?,
                                    ativo = ?,
                                    atual = ?
                                WHERE id = ? AND modalidade_id = ?
                            ");
                            $stmt->execute([
                                $plano['nome'],
                                $plano['valor'],
                                $plano['checkins_semanais'],
                                $plano['duracao_dias'] ?? 30,
                                $plano['ativo'] ?? 1,
                                $plano['atual'] ?? 1,
                                $plano['id'],
                                $id
                            ]);
                            $idsEnviados[] = $plano['id'];
                        } else {
                            // Criar novo plano
                            $stmt = $db->prepare("
                                INSERT INTO planos 
                                (tenant_id, modalidade_id, nome, valor, checkins_semanais, duracao_dias, ativo, atual) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $tenantId,
                                $id,
                                $plano['nome'],
                                $plano['valor'],
                                $plano['checkins_semanais'],
                                $plano['duracao_dias'] ?? 30,
                                $plano['ativo'] ?? 1,
                                $plano['atual'] ?? 1
                            ]);
                        }
                    }
                    
                    // Remover planos que não foram enviados (foram excluídos)
                    $idsParaRemover = array_diff($idsExistentes, $idsEnviados);
                    if (!empty($idsParaRemover)) {
                        $placeholders = str_repeat('?,', count($idsParaRemover) - 1) . '?';
                        $stmt = $db->prepare("DELETE FROM planos WHERE id IN ($placeholders)");
                        $stmt->execute($idsParaRemover);
                    }
                }
                
                $db->commit();
                
                $modalidade = $this->modalidadeModel->buscarPorId($id);
                
                $response->getBody()->write(json_encode([
                    'type' => 'success',
                    'message' => 'Modalidade e planos atualizados com sucesso',
                    'modalidade' => $modalidade
                ], JSON_UNESCAPED_UNICODE));
                
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
                
            } catch (\Exception $e) {
                $db->rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao atualizar modalidade: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Excluir modalidade (desativar/ativar)
     * DELETE /admin/modalidades/{id}
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId');
        
        $modalidade = $this->modalidadeModel->buscarPorId($id);
        
        if (!$modalidade) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Modalidade não encontrada'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }
        
        // Verificar se a modalidade pertence ao tenant
        if ($modalidade['tenant_id'] !== $tenantId) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Acesso negado'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(403);
        }
        
        // Se está tentando desativar, verificar se há contratos ativos
        if ($modalidade['ativo']) {
            $db = require __DIR__ . '/../../config/database.php';
            $stmt = $db->prepare("
                SELECT COUNT(*) as total 
                FROM matriculas m
                INNER JOIN planos p ON m.plano_id = p.id
                WHERE p.modalidade_id = ?
                AND m.status = 'ativa'
            ");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['total'] > 0) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Não é possível desativar modalidade com contratos ativos'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(422);
            }
        }
        
        try {
            $this->modalidadeModel->excluir($id);
            
            $acao = $modalidade['ativo'] ? 'desativada' : 'ativada';
            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => "Modalidade {$acao} com sucesso"
            ], JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao alterar modalidade: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }
}
