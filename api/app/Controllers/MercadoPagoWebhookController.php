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
    private MercadoPagoService $mercadoPagoService;
    
    public function __construct()
    {
        $this->db = require __DIR__ . '/../../config/database.php';
        $this->mercadoPagoService = new MercadoPagoService();
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
            error_log(json_encode($body));
            
            // Validar se é notificação válida
            if (!isset($body['type']) || !isset($body['data']['id'])) {
                $response->getBody()->write(json_encode([
                    'error' => 'Notificação inválida'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            // Processar notificação
            $pagamento = $this->mercadoPagoService->processarNotificacao($body);
            
            // Atualizar status no banco de dados
            $this->atualizarPagamento($pagamento);
            
            // Retornar 200 OK para o Mercado Pago
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Notificação processada'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            
        } catch (\Exception $e) {
            error_log("Erro webhook MP: " . $e->getMessage());
            
            // Retornar 200 mesmo com erro para evitar reenvios
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        }
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
        
        // Se pagamento foi aprovado, ativar matrícula
        if ($pagamento['status'] === 'approved') {
            $this->ativarMatricula($matriculaId);
        }
    }
    
    /**
     * Ativar matrícula após pagamento aprovado
     */
    private function ativarMatricula(int $matriculaId): void
    {
        $stmtUpdate = $this->db->prepare("
            UPDATE matriculas
            SET status_id = (SELECT id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1),
                updated_at = NOW()
            WHERE id = ?
            AND status_id = (SELECT id FROM status_matricula WHERE codigo = 'pendente' LIMIT 1)
        ");
        
        $stmtUpdate->execute([$matriculaId]);
        
        if ($stmtUpdate->rowCount() > 0) {
            error_log("Matrícula #{$matriculaId} ativada após pagamento aprovado");
        }
    }
}
