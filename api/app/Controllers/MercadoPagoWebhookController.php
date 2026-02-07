<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\MercadoPagoService;

/**
 * Controller para processar webhooks do Mercado Pago
 */
class MercadoPagoWebhookController
{
    private $db;
    private ?MercadoPagoService $mercadoPagoService = null;
    
    public function __construct()
    {
        $this->db = require __DIR__ . '/../../config/database.php';
        // MercadoPagoService será instanciado com tenant_id quando processar a notificação
    }
    
    /**
     * Obter instância do MercadoPagoService com tenant específico
     */
    private function getMercadoPagoService(?int $tenantId = null): MercadoPagoService
    {
        return new MercadoPagoService($tenantId);
    }
    
    /**
     * Processar notificação de pagamento
     * 
     * POST /api/webhooks/mercadopago
     */
    public function processarWebhook(Request $request, Response $response): Response
    {
        try {
            $body = $request->getParsedBody();
            
            // Log da notificação
            error_log("=== WEBHOOK MERCADO PAGO ===");
            error_log("Body recebido: " . json_encode($body));
            
            // Validar se é notificação válida
            if (!isset($body['type']) || !isset($body['data']['id'])) {
                error_log("[Webhook MP] ❌ Notificação inválida - falta type ou data.id");
                $response->getBody()->write(json_encode([
                    'error' => 'Notificação inválida'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            error_log("[Webhook MP] ✅ Tipo: {$body['type']}, Payment ID: {$body['data']['id']}");
            
            // Buscar tenant_id pela matrícula/pagamento (se disponível nos metadados)
            // Por enquanto, usar credenciais globais (ENV) para processar webhook
            $mercadoPagoService = $this->getMercadoPagoService();
            
            // Processar notificação
            error_log("[Webhook MP] Processando notificação...");
            $pagamento = $mercadoPagoService->processarNotificacao($body);
            error_log("[Webhook MP] Pagamento processado: status=" . ($pagamento['status'] ?? 'N/A'));
            
            // Atualizar status no banco de dados
            error_log("[Webhook MP] Atualizando banco de dados...");
            $this->atualizarPagamento($pagamento);
            error_log("[Webhook MP] ✅ Banco atualizado com sucesso");
            
            // Retornar 200 OK para o Mercado Pago
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Notificação processada',
                'payment_status' => $pagamento['status'] ?? null,
                'matricula_id' => $pagamento['metadata']['matricula_id'] ?? null
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            
        } catch (\Exception $e) {
            error_log("[Webhook MP] ❌ ERRO: " . $e->getMessage());
            error_log("[Webhook MP] Stack: " . $e->getTraceAsString());
            
            // Retornar 200 mesmo com erro para evitar reenvios
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        }
    }
    
    /**
     * Debug: Forçar processamento de um pagamento
     * 
     * POST /api/webhooks/mercadopago/debug
     */
    public function debugProcessarPagamento(Request $request, Response $response): Response
    {
        try {
            $body = $request->getParsedBody();
            $paymentId = $body['payment_id'] ?? null;
            $matriculaId = $body['matricula_id'] ?? null;
            
            if (!$paymentId && !$matriculaId) {
                $response->getBody()->write(json_encode([
                    'error' => 'Informe payment_id ou matricula_id'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            $result = [];
            
            // Se tem payment_id, buscar no MP
            if ($paymentId) {
                $mercadoPagoService = $this->getMercadoPagoService();
                $pagamento = $mercadoPagoService->buscarPagamento($paymentId);
                $result['pagamento_mp'] = $pagamento;
                
                // Atualizar no banco
                $this->atualizarPagamento($pagamento);
                $result['banco_atualizado'] = true;
            }
            
            // Se tem matricula_id, criar pagamento manualmente
            if ($matriculaId && !$paymentId) {
                $this->criarPagamentoManual($matriculaId);
                $result['pagamento_manual_criado'] = true;
            }
            
            // Buscar dados atuais
            $stmt = $this->db->prepare("SELECT * FROM pagamentos_plano WHERE matricula_id = ?");
            $stmt->execute([$matriculaId ?? $pagamento['metadata']['matricula_id'] ?? 0]);
            $result['pagamentos_plano'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $result
            ], JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Criar pagamento manual para uma matrícula
     */
    private function criarPagamentoManual(int $matriculaId): void
    {
        // Buscar dados da matrícula
        $stmt = $this->db->prepare("
            SELECT m.*, p.valor as valor_plano 
            FROM matriculas m 
            INNER JOIN planos p ON p.id = m.plano_id 
            WHERE m.id = ?
        ");
        $stmt->execute([$matriculaId]);
        $matricula = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$matricula) {
            throw new \Exception("Matrícula não encontrada");
        }
        
        // Inserir pagamento
        $stmtInsert = $this->db->prepare("
            INSERT INTO pagamentos_plano (
                tenant_id, aluno_id, matricula_id, plano_id,
                valor, data_vencimento, data_pagamento,
                status_pagamento_id, observacoes, tipo_baixa_id, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?,
                ?, CURDATE(), NOW(),
                2, 'Criado manualmente via debug', 2, NOW(), NOW()
            )
        ");
        
        $stmtInsert->execute([
            $matricula['tenant_id'],
            $matricula['aluno_id'],
            $matriculaId,
            $matricula['plano_id'],
            $matricula['valor_plano']
        ]);
    }
    
    /**
     * Atualizar status do pagamento no banco
     */
    private function atualizarPagamento(array $pagamento): void
    {
        $externalReference = $pagamento['external_reference'];
        $metadata = $pagamento['metadata'];
        
        // Extrair IDs da external_reference (formato: MAT-123-timestamp)
        if (preg_match('/MAT-(\d+)/', $externalReference, $matches)) {
            $matriculaId = $matches[1];
        } else {
            $matriculaId = $metadata['matricula_id'] ?? null;
        }
        
        if (!$matriculaId) {
            throw new \Exception('Matrícula não identificada no pagamento');
        }
        
        // Buscar ou criar registro de pagamento
        $stmtBuscar = $this->db->prepare("
            SELECT id FROM pagamentos_mercadopago 
            WHERE payment_id = ? OR matricula_id = ?
            LIMIT 1
        ");
        $stmtBuscar->execute([$pagamento['id'], $matriculaId]);
        $pagamentoExiste = $stmtBuscar->fetch(\PDO::FETCH_ASSOC);
        
        if ($pagamentoExiste) {
            // Atualizar pagamento existente
            $stmtUpdate = $this->db->prepare("
                UPDATE pagamentos_mercadopago
                SET status = ?,
                    status_detail = ?,
                    transaction_amount = ?,
                    payment_method_id = ?,
                    installments = ?,
                    date_approved = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmtUpdate->execute([
                $pagamento['status'],
                $pagamento['status_detail'],
                $pagamento['transaction_amount'],
                $pagamento['payment_method_id'],
                $pagamento['installments'],
                $pagamento['date_approved'],
                $pagamentoExiste['id']
            ]);
        } else {
            // Criar novo registro
            $stmtInsert = $this->db->prepare("
                INSERT INTO pagamentos_mercadopago (
                    tenant_id, matricula_id, aluno_id, usuario_id,
                    payment_id, external_reference, status, status_detail,
                    transaction_amount, payment_method_id, payment_type_id,
                    installments, date_approved, date_created, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmtInsert->execute([
                $metadata['tenant_id'] ?? null,
                $matriculaId,
                $metadata['aluno_id'] ?? null,
                $metadata['usuario_id'] ?? null,
                $pagamento['id'],
                $externalReference,
                $pagamento['status'],
                $pagamento['status_detail'],
                $pagamento['transaction_amount'],
                $pagamento['payment_method_id'],
                $pagamento['payment_type_id'],
                $pagamento['installments'],
                $pagamento['date_approved'],
                $pagamento['date_created']
            ]);
        }
        
        // Se pagamento foi aprovado, ativar matrícula e baixar pagamento_plano
        if ($pagamento['status'] === 'approved') {
            $this->ativarMatricula($matriculaId);
            $this->baixarPagamentoPlano($matriculaId, $pagamento);
        }
    }
    
    /**
     * Ativar matrícula após pagamento aprovado
     */
    private function ativarMatricula(int $matriculaId): void
    {
        $stmtUpdate = $this->db->prepare("
            UPDATE matriculas
            SET status_id = 1,
                updated_at = NOW()
            WHERE id = ?
            AND status_id IN (5, 2)
        ");
        
        $stmtUpdate->execute([$matriculaId]);
        
        if ($stmtUpdate->rowCount() > 0) {
            error_log("Matrícula #{$matriculaId} ativada após pagamento aprovado");
        }
    }
    
    /**
     * Baixar pagamento na tabela pagamentos_plano
     */
    private function baixarPagamentoPlano(int $matriculaId, array $pagamento): void
    {
        try {
            error_log("[Webhook MP] Iniciando baixa de pagamento para matrícula #{$matriculaId}");
            
            // Buscar dados da matrícula para obter tenant_id, aluno_id, plano_id
            $stmtMatricula = $this->db->prepare("
                SELECT m.id, m.tenant_id, m.aluno_id, m.plano_id, p.valor as valor_plano
                FROM matriculas m
                INNER JOIN planos p ON p.id = m.plano_id
                WHERE m.id = ?
            ");
            $stmtMatricula->execute([$matriculaId]);
            $matricula = $stmtMatricula->fetch(\PDO::FETCH_ASSOC);
            
            if (!$matricula) {
                error_log("[Webhook MP] ❌ Matrícula #{$matriculaId} não encontrada");
                return;
            }
            
            // Buscar o pagamento pendente mais antigo da matrícula (status 1 = Aguardando)
            $stmtBuscar = $this->db->prepare("
                SELECT pp.id, pp.valor
                FROM pagamentos_plano pp
                WHERE pp.matricula_id = ?
                AND pp.status_pagamento_id = 1
                ORDER BY pp.data_vencimento ASC
                LIMIT 1
            ");
            $stmtBuscar->execute([$matriculaId]);
            $pagamentoPendente = $stmtBuscar->fetch(\PDO::FETCH_ASSOC);
            
            // Buscar forma de pagamento (PIX, cartão, etc)
            $formaPagamentoId = $this->obterFormaPagamentoId($pagamento['payment_method_id'] ?? 'pix');
            
            if ($pagamentoPendente) {
                // Atualizar o pagamento existente para "pago"
                error_log("[Webhook MP] Atualizando pagamento existente #{$pagamentoPendente['id']}");
                
                $stmtUpdate = $this->db->prepare("
                    UPDATE pagamentos_plano
                    SET status_pagamento_id = 2,
                        data_pagamento = NOW(),
                        forma_pagamento_id = ?,
                        tipo_baixa_id = 2,
                        observacoes = CONCAT(IFNULL(observacoes, ''), ' | Pago via Mercado Pago - ID: " . $pagamento['id'] . "'),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmtUpdate->execute([
                    $formaPagamentoId,
                    $pagamentoPendente['id']
                ]);
                
                if ($stmtUpdate->rowCount() > 0) {
                    error_log("[Webhook MP] ✅ Pagamento #{$pagamentoPendente['id']} atualizado para PAGO");
                }
            } else {
                // Criar novo registro de pagamento já como PAGO
                error_log("[Webhook MP] Nenhum pagamento pendente, criando novo registro...");
                
                $stmtInsert = $this->db->prepare("
                    INSERT INTO pagamentos_plano (
                        tenant_id, aluno_id, matricula_id, plano_id,
                        valor, data_vencimento, data_pagamento,
                        status_pagamento_id, forma_pagamento_id,
                        observacoes, tipo_baixa_id, created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?,
                        ?, CURDATE(), NOW(),
                        2, ?,
                        ?, 2, NOW(), NOW()
                    )
                ");
                
                $stmtInsert->execute([
                    $matricula['tenant_id'],
                    $matricula['aluno_id'],
                    $matriculaId,
                    $matricula['plano_id'],
                    $pagamento['transaction_amount'],
                    $formaPagamentoId,
                    'Pago via Mercado Pago - ID: ' . $pagamento['id']
                ]);
                
                $novoPagamentoId = $this->db->lastInsertId();
                error_log("[Webhook MP] ✅ Novo pagamento #{$novoPagamentoId} criado como PAGO");
            }
            
        } catch (\Exception $e) {
            error_log("[Webhook MP] ❌ Erro ao baixar pagamento_plano: " . $e->getMessage());
            error_log("[Webhook MP] Stack: " . $e->getTraceAsString());
        }
    }
    
    /**
     * Obter ID da forma de pagamento baseado no método do MP
     */
    private function obterFormaPagamentoId(string $paymentMethodId): ?int
    {
        // Mapear métodos do MP para IDs de formas de pagamento do sistema
        // IDs baseados na tabela formas_pagamento:
        // 1=Dinheiro, 2=Pix, 3=Débito, 4=Crédito à vista, 8=Boleto, 9=Cartão
        $mapeamento = [
            'pix' => 2,
            'account_money' => 2, // Saldo MP = considerar como PIX
            'credit_card' => 9,
            'debit_card' => 3,
            'visa' => 9,
            'master' => 9,
            'elo' => 9,
            'amex' => 9,
            'hipercard' => 9,
            'bolbradesco' => 8,
            'pec' => 8, // Pagamento em lotérica
        ];
        
        return $mapeamento[$paymentMethodId] ?? 2; // Default: PIX
    }
}
