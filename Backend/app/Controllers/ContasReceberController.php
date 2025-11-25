<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ContasReceberController
{
    /**
     * Listar contas a receber
     */
    public function index(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId', 1);
        $db = require __DIR__ . '/../../config/database.php';
        
        $params = $request->getQueryParams();
        $status = $params['status'] ?? null;
        $usuarioId = $params['usuario_id'] ?? null;
        $mesReferencia = $params['mes_referencia'] ?? null;
        
        $sql = "
            SELECT 
                cr.*,
                u.nome as aluno_nome,
                u.email as aluno_email,
                p.nome as plano_nome,
                p.duracao_dias,
                admin_criou.nome as criado_por_nome,
                admin_baixa.nome as baixa_por_nome
            FROM contas_receber cr
            INNER JOIN usuarios u ON cr.usuario_id = u.id
            INNER JOIN planos p ON cr.plano_id = p.id
            LEFT JOIN usuarios admin_criou ON cr.criado_por = admin_criou.id
            LEFT JOIN usuarios admin_baixa ON cr.baixa_por = admin_baixa.id
            WHERE cr.tenant_id = ?
        ";
        
        $executeParams = [$tenantId];
        
        if ($status) {
            $sql .= " AND cr.status = ?";
            $executeParams[] = $status;
        }
        
        if ($usuarioId) {
            $sql .= " AND cr.usuario_id = ?";
            $executeParams[] = $usuarioId;
        }
        
        if ($mesReferencia) {
            $sql .= " AND cr.referencia_mes = ?";
            $executeParams[] = $mesReferencia;
        }
        
        $sql .= " ORDER BY cr.data_vencimento DESC, cr.created_at DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($executeParams);
        $contas = $stmt->fetchAll();
        
        $response->getBody()->write(json_encode([
            'contas' => $contas,
            'total' => count($contas)
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Dar baixa em uma conta
     */
    public function darBaixa(Request $request, Response $response, array $args): Response
    {
        $contaId = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId', 1);
        $adminId = $request->getAttribute('userId', null);
        $data = $request->getParsedBody();
        
        $db = require __DIR__ . '/../../config/database.php';
        
        // Buscar conta
        $stmt = $db->prepare("
            SELECT cr.*, p.duracao_dias, p.valor as plano_valor
            FROM contas_receber cr
            INNER JOIN planos p ON cr.plano_id = p.id
            WHERE cr.id = ? AND cr.tenant_id = ?
        ");
        $stmt->execute([$contaId, $tenantId]);
        $conta = $stmt->fetch();
        
        if (!$conta) {
            $response->getBody()->write(json_encode(['error' => 'Conta não encontrada']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        if ($conta['status'] === 'pago') {
            $response->getBody()->write(json_encode(['error' => 'Conta já está paga']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        // Atualizar conta atual
        $dataPagamento = $data['data_pagamento'] ?? date('Y-m-d');
        $formaPagamentoId = $data['forma_pagamento_id'] ?? null;
        $observacoes = $data['observacoes'] ?? null;
        
        // Calcular desconto se houver forma de pagamento
        $valorLiquido = $conta['valor'];
        $valorDesconto = 0;
        
        if ($formaPagamentoId) {
            $stmtForma = $db->prepare("SELECT percentual_desconto FROM formas_pagamento WHERE id = ? AND ativo = 1");
            $stmtForma->execute([$formaPagamentoId]);
            $formaPagamento = $stmtForma->fetch();
            
            if ($formaPagamento && $formaPagamento['percentual_desconto'] > 0) {
                $valorDesconto = ($conta['valor'] * $formaPagamento['percentual_desconto']) / 100;
                $valorLiquido = $conta['valor'] - $valorDesconto;
            }
        }
        
        $stmtUpdate = $db->prepare("
            UPDATE contas_receber 
            SET status = 'pago',
                data_pagamento = ?,
                forma_pagamento_id = ?,
                valor_liquido = ?,
                valor_desconto = ?,
                observacoes = ?,
                baixa_por = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmtUpdate->execute([
            $dataPagamento,
            $formaPagamentoId,
            $valorLiquido,
            $valorDesconto,
            $observacoes,
            $adminId,
            $contaId
        ]);
        
        // Se for recorrente, criar próxima conta
        $proximaContaId = null;
        if ($conta['recorrente'] && $conta['intervalo_dias']) {
            $proximoVencimento = date('Y-m-d', strtotime($conta['data_vencimento'] . " +{$conta['intervalo_dias']} days"));
            $proximaReferencia = date('Y-m', strtotime($proximoVencimento));
            
            $stmtProxima = $db->prepare("
                INSERT INTO contas_receber 
                (tenant_id, usuario_id, plano_id, historico_plano_id, valor, data_vencimento, 
                 status, referencia_mes, recorrente, intervalo_dias, conta_origem_id, criado_por)
                VALUES (?, ?, ?, ?, ?, ?, 'pendente', ?, true, ?, ?, ?)
            ");
            
            $stmtProxima->execute([
                $tenantId,
                $conta['usuario_id'],
                $conta['plano_id'],
                $conta['historico_plano_id'],
                $conta['plano_valor'],
                $proximoVencimento,
                $proximaReferencia,
                $conta['intervalo_dias'],
                $contaId,
                $adminId
            ]);
            
            $proximaContaId = (int) $db->lastInsertId();
            
            // Vincular próxima conta à atual
            $stmtVincular = $db->prepare("UPDATE contas_receber SET proxima_conta_id = ? WHERE id = ?");
            $stmtVincular->execute([$proximaContaId, $contaId]);
        }
        
        // Buscar conta atualizada
        $stmt->execute([$contaId, $tenantId]);
        $contaAtualizada = $stmt->fetch();
        
        $response->getBody()->write(json_encode([
            'message' => 'Baixa realizada com sucesso',
            'conta' => $contaAtualizada,
            'proxima_conta_id' => $proximaContaId,
            'proxima_vencimento' => $proximaContaId ? $proximoVencimento : null
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Cancelar uma conta
     */
    public function cancelar(Request $request, Response $response, array $args): Response
    {
        $contaId = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId', 1);
        $adminId = $request->getAttribute('userId', null);
        $data = $request->getParsedBody();
        
        $db = require __DIR__ . '/../../config/database.php';
        
        $stmt = $db->prepare("
            SELECT * FROM contas_receber 
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$contaId, $tenantId]);
        $conta = $stmt->fetch();
        
        if (!$conta) {
            $response->getBody()->write(json_encode(['error' => 'Conta não encontrada']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        if ($conta['status'] === 'pago') {
            $response->getBody()->write(json_encode(['error' => 'Não é possível cancelar conta já paga']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $observacoes = $data['observacoes'] ?? 'Cancelado pelo admin';
        
        $stmtUpdate = $db->prepare("
            UPDATE contas_receber 
            SET status = 'cancelado',
                observacoes = ?,
                baixa_por = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmtUpdate->execute([$observacoes, $adminId, $contaId]);
        
        $response->getBody()->write(json_encode([
            'message' => 'Conta cancelada com sucesso'
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Estatísticas de contas a receber
     */
    public function estatisticas(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId', 1);
        $db = require __DIR__ . '/../../config/database.php';
        
        $params = $request->getQueryParams();
        $mesReferencia = $params['mes_referencia'] ?? date('Y-m');
        
        // Total por status
        $stmtStatus = $db->prepare("
            SELECT 
                status,
                COUNT(*) as quantidade,
                SUM(valor) as total
            FROM contas_receber
            WHERE tenant_id = ? AND referencia_mes = ?
            GROUP BY status
        ");
        $stmtStatus->execute([$tenantId, $mesReferencia]);
        $porStatus = $stmtStatus->fetchAll();
        
        // Vencidas
        $stmtVencidas = $db->prepare("
            SELECT 
                COUNT(*) as quantidade,
                SUM(valor) as total
            FROM contas_receber
            WHERE tenant_id = ? 
            AND status IN ('pendente', 'vencido')
            AND data_vencimento < CURDATE()
        ");
        $stmtVencidas->execute([$tenantId]);
        $vencidas = $stmtVencidas->fetch();
        
        // A vencer (próximos 7 dias)
        $stmtAVencer = $db->prepare("
            SELECT 
                COUNT(*) as quantidade,
                SUM(valor) as total
            FROM contas_receber
            WHERE tenant_id = ? 
            AND status = 'pendente'
            AND data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ");
        $stmtAVencer->execute([$tenantId]);
        $aVencer = $stmtVencidas->fetch();
        
        $response->getBody()->write(json_encode([
            'por_status' => $porStatus,
            'vencidas' => $vencidas,
            'a_vencer_7_dias' => $aVencer,
            'mes_referencia' => $mesReferencia
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}

