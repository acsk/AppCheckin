<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Controller para gerenciar ciclos de planos
 */
class PlanoCicloController
{
    private $db;
    
    public function __construct()
    {
        $this->db = require __DIR__ . '/../../config/database.php';
    }
    
    /**
     * Listar ciclos de um plano
     * GET /admin/planos/{plano_id}/ciclos
     */
    public function listar(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = $request->getAttribute('tenantId');
            $planoId = (int) $args['plano_id'];
            
            // Verificar se plano pertence ao tenant
            $stmtPlano = $this->db->prepare("SELECT id, nome FROM planos WHERE id = ? AND tenant_id = ?");
            $stmtPlano->execute([$planoId, $tenantId]);
            $plano = $stmtPlano->fetch(\PDO::FETCH_ASSOC);
            
            if (!$plano) {
                $response->getBody()->write(json_encode(['error' => 'Plano não encontrado']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            $stmt = $this->db->prepare("
                SELECT 
                    id, nome, codigo, meses, valor, valor_mensal_equivalente,
                    desconto_percentual, permite_recorrencia, ativo, ordem
                FROM plano_ciclos
                WHERE plano_id = ? AND tenant_id = ?
                ORDER BY ordem ASC
            ");
            $stmt->execute([$planoId, $tenantId]);
            $ciclos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Formatar valores
            foreach ($ciclos as &$ciclo) {
                $ciclo['id'] = (int) $ciclo['id'];
                $ciclo['meses'] = (int) $ciclo['meses'];
                $ciclo['valor'] = (float) $ciclo['valor'];
                $ciclo['valor_mensal_equivalente'] = (float) $ciclo['valor_mensal_equivalente'];
                $ciclo['desconto_percentual'] = (float) $ciclo['desconto_percentual'];
                $ciclo['permite_recorrencia'] = (bool) $ciclo['permite_recorrencia'];
                $ciclo['ativo'] = (bool) $ciclo['ativo'];
                $ciclo['valor_formatado'] = 'R$ ' . number_format($ciclo['valor'], 2, ',', '.');
                $ciclo['valor_mensal_formatado'] = 'R$ ' . number_format($ciclo['valor_mensal_equivalente'], 2, ',', '.');
            }
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'plano' => $plano,
                'ciclos' => $ciclos,
                'total' => count($ciclos)
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log("Erro ao listar ciclos: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Erro interno']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Criar ciclo para um plano
     * POST /admin/planos/{plano_id}/ciclos
     */
    public function criar(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = $request->getAttribute('tenantId');
            $planoId = (int) $args['plano_id'];
            $data = $request->getParsedBody();
            
            // Validações
            $errors = [];
            if (empty($data['nome'])) $errors[] = 'Nome é obrigatório';
            if (empty($data['codigo'])) $errors[] = 'Código é obrigatório';
            if (empty($data['meses']) || $data['meses'] < 1) $errors[] = 'Meses deve ser maior que 0';
            if (!isset($data['valor']) || $data['valor'] < 0) $errors[] = 'Valor é obrigatório';
            
            if (!empty($errors)) {
                $response->getBody()->write(json_encode(['errors' => $errors]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
            }
            
            // Verificar se plano pertence ao tenant
            $stmtPlano = $this->db->prepare("SELECT id, valor FROM planos WHERE id = ? AND tenant_id = ?");
            $stmtPlano->execute([$planoId, $tenantId]);
            $plano = $stmtPlano->fetch(\PDO::FETCH_ASSOC);
            
            if (!$plano) {
                $response->getBody()->write(json_encode(['error' => 'Plano não encontrado']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            // Verificar se código já existe para este plano
            $stmtCheck = $this->db->prepare("SELECT id FROM plano_ciclos WHERE plano_id = ? AND codigo = ?");
            $stmtCheck->execute([$planoId, $data['codigo']]);
            if ($stmtCheck->fetch()) {
                $response->getBody()->write(json_encode(['error' => 'Já existe um ciclo com este código para este plano']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            // Calcular desconto em relação ao valor mensal do plano
            $valorMensalBase = $plano['valor'];
            $valorTotalSemDesconto = $valorMensalBase * $data['meses'];
            $descontoPercentual = $valorTotalSemDesconto > 0 
                ? round((($valorTotalSemDesconto - $data['valor']) / $valorTotalSemDesconto) * 100, 2)
                : 0;
            
            // Inserir
            $stmt = $this->db->prepare("
                INSERT INTO plano_ciclos 
                (tenant_id, plano_id, nome, codigo, meses, valor, desconto_percentual, permite_recorrencia, ativo, ordem)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $tenantId,
                $planoId,
                $data['nome'],
                $data['codigo'],
                (int) $data['meses'],
                (float) $data['valor'],
                $descontoPercentual,
                isset($data['permite_recorrencia']) ? (int) $data['permite_recorrencia'] : 1,
                isset($data['ativo']) ? (int) $data['ativo'] : 1,
                isset($data['ordem']) ? (int) $data['ordem'] : 99
            ]);
            
            $cicloId = (int) $this->db->lastInsertId();
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Ciclo criado com sucesso',
                'id' => $cicloId
            ]));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
            
        } catch (\Exception $e) {
            error_log("Erro ao criar ciclo: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Erro interno: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Atualizar ciclo
     * PUT /admin/planos/{plano_id}/ciclos/{id}
     */
    public function atualizar(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = $request->getAttribute('tenantId');
            $planoId = (int) $args['plano_id'];
            $cicloId = (int) $args['id'];
            $data = $request->getParsedBody();
            
            // Verificar se ciclo existe e pertence ao tenant
            $stmt = $this->db->prepare("
                SELECT pc.*, p.valor as plano_valor
                FROM plano_ciclos pc
                INNER JOIN planos p ON p.id = pc.plano_id
                WHERE pc.id = ? AND pc.plano_id = ? AND pc.tenant_id = ?
            ");
            $stmt->execute([$cicloId, $planoId, $tenantId]);
            $ciclo = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$ciclo) {
                $response->getBody()->write(json_encode(['error' => 'Ciclo não encontrado']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            // Calcular desconto se valor foi alterado
            $valor = isset($data['valor']) ? (float) $data['valor'] : (float) $ciclo['valor'];
            $meses = isset($data['meses']) ? (int) $data['meses'] : (int) $ciclo['meses'];
            $valorMensalBase = $ciclo['plano_valor'];
            $valorTotalSemDesconto = $valorMensalBase * $meses;
            $descontoPercentual = $valorTotalSemDesconto > 0 
                ? round((($valorTotalSemDesconto - $valor) / $valorTotalSemDesconto) * 100, 2)
                : 0;
            
            // Atualizar
            $stmt = $this->db->prepare("
                UPDATE plano_ciclos
                SET nome = COALESCE(?, nome),
                    meses = COALESCE(?, meses),
                    valor = COALESCE(?, valor),
                    desconto_percentual = ?,
                    permite_recorrencia = COALESCE(?, permite_recorrencia),
                    ativo = COALESCE(?, ativo),
                    ordem = COALESCE(?, ordem),
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['nome'] ?? null,
                isset($data['meses']) ? (int) $data['meses'] : null,
                isset($data['valor']) ? (float) $data['valor'] : null,
                $descontoPercentual,
                isset($data['permite_recorrencia']) ? (int) $data['permite_recorrencia'] : null,
                isset($data['ativo']) ? (int) $data['ativo'] : null,
                isset($data['ordem']) ? (int) $data['ordem'] : null,
                $cicloId
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Ciclo atualizado com sucesso'
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log("Erro ao atualizar ciclo: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Erro interno']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Excluir ciclo
     * DELETE /admin/planos/{plano_id}/ciclos/{id}
     */
    public function excluir(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = $request->getAttribute('tenantId');
            $planoId = (int) $args['plano_id'];
            $cicloId = (int) $args['id'];
            
            // Verificar se ciclo existe e pertence ao tenant
            $stmt = $this->db->prepare("
                SELECT id FROM plano_ciclos WHERE id = ? AND plano_id = ? AND tenant_id = ?
            ");
            $stmt->execute([$cicloId, $planoId, $tenantId]);
            
            if (!$stmt->fetch()) {
                $response->getBody()->write(json_encode(['error' => 'Ciclo não encontrado']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            // Verificar se há matrículas vinculadas
            $stmtCheck = $this->db->prepare("SELECT COUNT(*) as total FROM matriculas WHERE plano_ciclo_id = ?");
            $stmtCheck->execute([$cicloId]);
            $result = $stmtCheck->fetch(\PDO::FETCH_ASSOC);
            
            if ($result['total'] > 0) {
                $response->getBody()->write(json_encode([
                    'error' => "Não é possível excluir. Existem {$result['total']} matrícula(s) vinculada(s) a este ciclo."
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            // Excluir
            $stmt = $this->db->prepare("DELETE FROM plano_ciclos WHERE id = ?");
            $stmt->execute([$cicloId]);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Ciclo excluído com sucesso'
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log("Erro ao excluir ciclo: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Erro interno']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Gerar ciclos automáticos para um plano
     * POST /admin/planos/{plano_id}/ciclos/gerar
     */
    public function gerarCiclosAutomaticos(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = $request->getAttribute('tenantId');
            $planoId = (int) $args['plano_id'];
            $data = $request->getParsedBody();
            
            // Verificar se plano existe
            $stmtPlano = $this->db->prepare("SELECT id, nome, valor FROM planos WHERE id = ? AND tenant_id = ?");
            $stmtPlano->execute([$planoId, $tenantId]);
            $plano = $stmtPlano->fetch(\PDO::FETCH_ASSOC);
            
            if (!$plano) {
                $response->getBody()->write(json_encode(['error' => 'Plano não encontrado']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            // Ciclos padrão
            $ciclosPadrao = [
                ['codigo' => 'mensal', 'nome' => 'Mensal', 'meses' => 1, 'desconto' => $data['desconto_mensal'] ?? 0, 'ordem' => 1],
                ['codigo' => 'trimestral', 'nome' => 'Trimestral', 'meses' => 3, 'desconto' => $data['desconto_trimestral'] ?? 10, 'ordem' => 2],
                ['codigo' => 'semestral', 'nome' => 'Semestral', 'meses' => 6, 'desconto' => $data['desconto_semestral'] ?? 15, 'ordem' => 3],
                ['codigo' => 'anual', 'nome' => 'Anual', 'meses' => 12, 'desconto' => $data['desconto_anual'] ?? 20, 'ordem' => 4],
            ];
            
            $ciclosCriados = [];
            
            $stmtInsert = $this->db->prepare("
                INSERT INTO plano_ciclos 
                (tenant_id, plano_id, nome, codigo, meses, valor, desconto_percentual, permite_recorrencia, ativo, ordem)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, ?)
                ON DUPLICATE KEY UPDATE
                valor = VALUES(valor),
                desconto_percentual = VALUES(desconto_percentual),
                updated_at = NOW()
            ");
            
            foreach ($ciclosPadrao as $ciclo) {
                $valorBase = $plano['valor'] * $ciclo['meses'];
                $valorComDesconto = round($valorBase * (1 - ($ciclo['desconto'] / 100)), 2);
                
                $stmtInsert->execute([
                    $tenantId,
                    $planoId,
                    $ciclo['nome'],
                    $ciclo['codigo'],
                    $ciclo['meses'],
                    $valorComDesconto,
                    $ciclo['desconto'],
                    $ciclo['ordem']
                ]);
                
                $ciclosCriados[] = [
                    'nome' => $ciclo['nome'],
                    'meses' => $ciclo['meses'],
                    'valor' => $valorComDesconto,
                    'desconto' => $ciclo['desconto'],
                    'economia' => $valorBase - $valorComDesconto
                ];
            }
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Ciclos gerados com sucesso',
                'ciclos' => $ciclosCriados
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log("Erro ao gerar ciclos: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Erro interno: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}
