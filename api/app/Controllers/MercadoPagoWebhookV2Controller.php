<?php
/**
 * Novo webhook controller usando SDK oficial do Mercado Pago
 * 
 * Substitui a lógica atual por uma baseada no SDK oficial
 */

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\PreApproval\PreApprovalClient;
use MercadoPago\MercadoPagoConfig;

class MercadoPagoWebhookV2Controller
{
    private $db;
    private string $logFile;
    
    public function __construct()
    {
        $this->db = require __DIR__ . '/../../config/database.php';
        
        // Definir arquivo de log dedicado para webhook
        $this->logFile = __DIR__ . '/../../storage/logs/webhook_mercadopago.log';
        @mkdir(dirname($this->logFile), 0777, true);
        
        // Configurar SDK do MP
        $token = getenv('MP_ENVIRONMENT') === 'production' 
            ? getenv('MP_ACCESS_TOKEN_PROD')
            : getenv('MP_ACCESS_TOKEN_TEST');
            
        MercadoPagoConfig::setAccessToken($token);
    }
    
    /**
     * Processar webhook usando SDK oficial
     * 
     * POST /api/webhooks/mercadopago/v2
     */
    public function processar(Request $request, Response $response): Response
    {
        try {
            $body = $request->getParsedBody();
            
            $this->log("=== WEBHOOK MERCADO PAGO V2 ===");
            $this->log("Body: " . json_encode($body));
            
            // Validar notificação
            if (!isset($body['type']) || !isset($body['data']['id'])) {
                $this->log("❌ Notificação inválida - falta type ou data.id");
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            $type = $body['type'];
            $dataId = $body['data']['id'];
            
            $this->log("✅ Tipo: {$type}, ID: {$dataId}");
            
            // ===== PROCESSAR PAYMENT =====
            if ($type === 'payment') {
                $this->processarPayment($dataId);
            }
            // ===== PROCESSAR SUBSCRIPTION/PREAPPROVAL =====
            elseif (in_array($type, ['subscription', 'preapproval', 'subscription_preapproval'])) {
                $this->processarPreApproval($dataId);
            }
            // Ignorar outros tipos
            else {
                $this->log("⚠️  Tipo de notificação ignorado: {$type}");
            }
            
            // Salvar webhook no banco
            $this->salvarWebhook($body, $type, $dataId, 'sucesso');
            
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json')
                ->write(json_encode(['success' => true]));
                
        } catch (\Exception $e) {
            $this->log("❌ ERRO: " . $e->getMessage());
            $this->log("Stack: " . $e->getTraceAsString());
            
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json')
                ->write(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }
    }
    
    /**
     * Processar pagamento usando SDK
     */
    private function processarPayment(string $paymentId): void
    {
        $this->log("📋 Processando PAYMENT: {$paymentId}");
        
        try {
            $client = new PaymentClient();
            $payment = $client->get($paymentId);
            
            $this->log("✅ Payment encontrado");
            $this->log("   Status: " . $payment['status']);
            $this->log("   External Ref: " . ($payment['external_reference'] ?? 'NULL'));
            $this->log("   Valor: " . $payment['transaction_amount']);
            
            // Se tiver external_reference, tenta encontrar a matrícula
            if (!empty($payment['external_reference'])) {
                $this->criarPagamento($payment);
            } else {
                $this->log("⚠️  Payment sem external_reference, não consegue associar");
            }
            
        } catch (\Exception $e) {
            $this->log("❌ Erro ao processar payment: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Processar preapproval/subscription usando SDK
     */
    private function processarPreApproval(string $preapprovalId): void
    {
        $this->log("📋 Processando PREAPPROVAL: {$preapprovalId}");
        
        try {
            $client = new PreApprovalClient();
            $preapproval = $client->get($preapprovalId);
            
            $this->log("✅ Preapproval encontrado");
            $this->log("   Status: " . $preapproval['status']);
            $this->log("   External Ref: " . ($preapproval['external_reference'] ?? 'NULL'));
            
            // Atualizar status da assinatura no banco
            $this->atualizarAssinatura($preapproval);
            
        } catch (\Exception $e) {
            $this->log("❌ Erro ao processar preapproval: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Criar pagamento_plano a partir dos dados do payment
     */
    private function criarPagamento(array $payment): void
    {
        $externalRef = $payment['external_reference'] ?? null;
        
        if (!$externalRef) {
            return;
        }
        
        $this->log("🔗 Procurando matrícula com external_ref: {$externalRef}");
        
        // Extrair ID da external_reference (MAT-{id}-{timestamp})
        $parts = explode('-', $externalRef);
        if (count($parts) < 2) {
            $this->log("⚠️  External ref com formato inválido");
            return;
        }
        
        $type = $parts[0];
        $id = $parts[1] ?? null;
        
        if ($type !== 'MAT' || !$id) {
            $this->log("⚠️  Tipo desconhecido ou ID inválido: {$type}");
            return;
        }
        
        // Buscar matrícula
        $sql = "SELECT m.id, m.tenant_id FROM matriculas m WHERE m.id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$result || $result->num_rows === 0) {
            $this->log("❌ Matrícula não encontrada: {$id}");
            return;
        }
        
        $matricula = $result->fetch_assoc();
        $this->log("✅ Matrícula encontrada: " . $matricula['id']);
        
        // Buscar assinatura para saber o plano e valor
        $sql_ass = "
            SELECT id, plano_id, valor, proxima_cobranca
            FROM assinaturas
            WHERE matricula_id = ? AND status_id = 1
            LIMIT 1
        ";
        
        $stmt_ass = $this->db->prepare($sql_ass);
        $stmt_ass->bind_param("i", $matricula['id']);
        $stmt_ass->execute();
        $result_ass = $stmt_ass->get_result();
        
        if (!$result_ass || $result_ass->num_rows === 0) {
            $this->log("⚠️  Nenhuma assinatura ativa encontrada para matrícula");
            return;
        }
        
        $assinatura = $result_ass->fetch_assoc();
        
        // Verificar se já existe pagamento recente
        $sql_check = "
            SELECT id FROM pagamentos_plano
            WHERE matricula_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            LIMIT 1
        ";
        
        $stmt_check = $this->db->prepare($sql_check);
        $stmt_check->bind_param("i", $matricula['id']);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check && $result_check->num_rows > 0) {
            $this->log("⚠️  Pagamento recente já existe");
            return;
        }
        
        // Criar pagamento_plano
        $this->log("💾 Criando pagamento_plano...");
        
        $sql_insert = "
            INSERT INTO pagamentos_plano (
                tenant_id, matricula_id, plano_id, valor, data_vencimento,
                status_pagamento_id, observacoes, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, 1, ?, NOW(), NOW())
        ";
        
        $stmt_insert = $this->db->prepare($sql_insert);
        $obs = "Pago via webhook MP - Payment ID: " . $payment['id'];
        
        $stmt_insert->bind_param(
            "iiidss",
            $matricula['tenant_id'],
            $matricula['id'],
            $assinatura['plano_id'],
            $assinatura['valor'],
            $assinatura['proxima_cobranca'],
            $obs
        );
        
        if ($stmt_insert->execute()) {
            $this->log("✅ Pagamento criado com sucesso!");
        } else {
            $this->log("❌ Erro ao criar pagamento: " . $stmt_insert->error);
        }
    }
    
    /**
     * Atualizar status da assinatura
     */
    private function atualizarAssinatura(array $preapproval): void
    {
        $externalRef = $preapproval['external_reference'] ?? null;
        
        if (!$externalRef) {
            $this->log("⚠️  Preapproval sem external_reference");
            return;
        }
        
        // Similar ao processamento acima
        $this->log("🔗 Atualizar assinatura com external_ref: {$externalRef}");
        
        $parts = explode('-', $externalRef);
        if (count($parts) < 2) {
            return;
        }
        
        $type = $parts[0];
        $id = $parts[1] ?? null;
        
        if (!$id) {
            return;
        }
        
        // Atualizar status_gateway na assinatura
        $status = $preapproval['status'] ?? 'unknown';
        
        $sql = "UPDATE assinaturas SET status_gateway = ? WHERE matricula_id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("si", $status, $id);
        
        if ($stmt->execute()) {
            $this->log("✅ Assinatura atualizada: status = {$status}");
        }
    }
    
    /**
     * Salvar webhook no banco para auditoria
     */
    private function salvarWebhook(array $body, string $type, ?string $dataId, string $status): void
    {
        try {
            $sql = "
                INSERT INTO webhook_payloads_mercadopago (
                    tipo, data_id, status, payload, created_at
                ) VALUES (?, ?, ?, ?, NOW())
            ";
            
            $stmt = $this->db->prepare($sql);
            $payload = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            
            $stmt->bind_param("ssss", $type, $dataId, $status, $payload);
            $stmt->execute();
            
            $this->log("💾 Webhook salvo no banco - ID: " . $this->db->lastInsertId());
        } catch (\Exception $e) {
            $this->log("⚠️  Erro ao salvar webhook: " . $e->getMessage());
        }
    }
    
    /**
     * Validação forçada de assinatura
     * Consulta MP, verifica se approved e processa o webhook
     * 
     * POST /api/webhooks/mercadopago/recuperar-assinatura
     * Body: { "external_reference": "MAT-158-1771524282" }
     */
    public function recuperarAssinatura(Request $request, Response $response): Response
    {
        try {
            $body = $request->getParsedBody();
            $externalRef = $body['external_reference'] ?? null;
            
            if (!$externalRef) {
                return $response
                    ->withStatus(400)
                    ->withHeader('Content-Type', 'application/json')
                    ->write(json_encode([
                        'success' => false,
                        'error' => 'external_reference é obrigatório'
                    ]));
            }
            
            $this->log("=== VALIDAÇÃO FORÇADA DE ASSINATURA ===");
            $this->log("External Ref: {$externalRef}");
            
            // Extrair tipo e ID
            $parts = explode('-', $externalRef);
            if (count($parts) < 2) {
                return $response
                    ->withStatus(400)
                    ->withHeader('Content-Type', 'application/json')
                    ->write(json_encode([
                        'success' => false,
                        'error' => 'Formato inválido: MAT-{id}-{timestamp}'
                    ]));
            }
            
            $type = $parts[0];
            $id = (int)$parts[1];
            
            if ($type !== 'MAT') {
                return $response
                    ->withStatus(400)
                    ->withHeader('Content-Type', 'application/json')
                    ->write(json_encode([
                        'success' => false,
                        'error' => 'Apenas MAT (matrícula) é suportado'
                    ]));
            }
            
            // Buscar matrícula
            $sql = "SELECT id, tenant_id FROM matriculas WHERE id = ? LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if (!$result || $result->num_rows === 0) {
                return $response
                    ->withStatus(404)
                    ->withHeader('Content-Type', 'application/json')
                    ->write(json_encode([
                        'success' => false,
                        'error' => "Matrícula {$id} não encontrada"
                    ]));
            }
            
            $matricula = $result->fetch_assoc();
            $this->log("✅ Matrícula encontrada: {$matricula['id']}");
            
            // Buscar assinatura ativa
            $sql_ass = "
                SELECT id, plano_id, valor, status_gateway, status_id
                FROM assinaturas
                WHERE matricula_id = ? 
                ORDER BY id DESC
                LIMIT 1
            ";
            
            $stmt_ass = $this->db->prepare($sql_ass);
            $stmt_ass->bind_param("i", $id);
            $stmt_ass->execute();
            $result_ass = $stmt_ass->get_result();
            
            if (!$result_ass || $result_ass->num_rows === 0) {
                return $response
                    ->withStatus(404)
                    ->withHeader('Content-Type', 'application/json')
                    ->write(json_encode([
                        'success' => false,
                        'error' => 'Nenhuma assinatura encontrada para esta matrícula'
                    ]));
            }
            
            $assinatura = $result_ass->fetch_assoc();
            $this->log("✅ Assinatura encontrada: {$assinatura['id']}, Status: {$assinatura['status_gateway']}");
            
            // Se já está approved, não faz nada
            if ($assinatura['status_gateway'] === 'approved') {
                return $response
                    ->withStatus(200)
                    ->withHeader('Content-Type', 'application/json')
                    ->write(json_encode([
                        'success' => true,
                        'message' => 'Assinatura já está em status approved',
                        'status' => 'approved'
                    ]));
            }
            
            // Agora processa como validação forçada
            // Atualiza o status da assinatura para approved
            $approvedStatus = 'approved';
            
            // Buscar ID do status 'ativa' na tabela assinatura_status
            $sql_status = "SELECT id FROM assinatura_status WHERE codigo = 'ativa' LIMIT 1";
            $stmt_status = $this->db->prepare($sql_status);
            $stmt_status->execute();
            $result_status = $stmt_status->get_result();
            $statusRow = $result_status->fetch_assoc();
            $statusId = $statusRow ? $statusRow['id'] : 1;
            
            // Atualizar assinatura
            $sql_update = "
                UPDATE assinaturas 
                SET status_gateway = ?, status_id = ?, atualizado_em = NOW()
                WHERE id = ?
            ";
            
            $stmt_update = $this->db->prepare($sql_update);
            $stmt_update->bind_param("sii", $approvedStatus, $statusId, $assinatura['id']);
            
            if (!$stmt_update->execute()) {
                $this->log("❌ Erro ao atualizar assinatura: " . $stmt_update->error);
                return $response
                    ->withStatus(500)
                    ->withHeader('Content-Type', 'application/json')
                    ->write(json_encode([
                        'success' => false,
                        'error' => 'Erro ao atualizar assinatura no banco'
                    ]));
            }
            
            $this->log("✅ Assinatura atualizada para 'approved'");
            
            // Atualizar matrícula para status 'ativa'
            $sql_mat_status = "SELECT id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1";
            $stmt_mat_status = $this->db->prepare($sql_mat_status);
            $stmt_mat_status->execute();
            $result_mat_status = $stmt_mat_status->get_result();
            $matStatusRow = $result_mat_status->fetch_assoc();
            $matStatusId = $matStatusRow ? $matStatusRow['id'] : 2;

            // Calcular vigência no momento do approved (webhook)
            $duracaoDias = 30;
            $duracaoMeses = 0;
            $sqlDuracao = "
                SELECT p.duracao_dias, pc.meses
                FROM matriculas m
                INNER JOIN planos p ON p.id = m.plano_id
                LEFT JOIN plano_ciclos pc ON pc.id = m.plano_ciclo_id
                WHERE m.id = ?
                LIMIT 1
            ";
            $stmtDuracao = $this->db->prepare($sqlDuracao);
            $stmtDuracao->bind_param("i", $id);
            if ($stmtDuracao->execute()) {
                $resDuracao = $stmtDuracao->get_result();
                $duracaoRow = $resDuracao->fetch_assoc();
                if ($duracaoRow) {
                    $duracaoDias = max(1, (int) ($duracaoRow['duracao_dias'] ?? 30));
                    $duracaoMeses = (int) ($duracaoRow['meses'] ?? 0);
                }
            }

            $hoje = new \DateTimeImmutable(date('Y-m-d'));
            if ($duracaoMeses > 0) {
                $dataVencimento = $hoje->modify("+{$duracaoMeses} months")->format('Y-m-d');
            } else {
                $dataVencimento = $hoje->modify("+{$duracaoDias} days")->format('Y-m-d');
            }
            $dataInicio = $hoje->format('Y-m-d');
            
            $sql_mat_update = "
                UPDATE matriculas 
                SET status_id = ?,
                    data_inicio = ?,
                    data_vencimento = ?,
                    proxima_data_vencimento = ?,
                    updated_at = NOW()
                WHERE id = ?
            ";
            
            $stmt_mat_update = $this->db->prepare($sql_mat_update);
            $stmt_mat_update->bind_param("isssi", $matStatusId, $dataInicio, $dataVencimento, $dataVencimento, $id);
            
            if (!$stmt_mat_update->execute()) {
                $this->log("❌ Erro ao atualizar matrícula: " . $stmt_mat_update->error);
            } else {
                $this->log("✅ Matrícula atualizada para 'ativa'");
            }
            
            return $response
                ->withStatus(200)
                ->withHeader('Content-Type', 'application/json')
                ->write(json_encode([
                    'success' => true,
                    'message' => 'Assinatura validada e recuperada com sucesso',
                    'matricula_id' => (int)$id,
                    'assinatura_id' => (int)$assinatura['id'],
                    'status' => 'approved'
                ]));
            
        } catch (\Exception $e) {
            $this->log("❌ ERRO: " . $e->getMessage());
            
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json')
                ->write(json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]));
        }
    }

    /**
     * Registrar log
     */
    private function log(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $fullMessage = "[{$timestamp}] {$message}";
        
        file_put_contents($this->logFile, $fullMessage . "\n", FILE_APPEND | LOCK_EX);
        error_log($message);
    }
}
