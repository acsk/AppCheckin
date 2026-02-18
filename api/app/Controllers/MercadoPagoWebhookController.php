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
    private string $logFile;
    
    public function __construct()
    {
        $this->db = require __DIR__ . '/../../config/database.php';
        // MercadoPagoService será instanciado com tenant_id quando processar a notificação
        
        // Definir arquivo de log dedicado para webhook
        $this->logFile = __DIR__ . '/../../storage/logs/webhook_mercadopago.log';
        @mkdir(dirname($this->logFile), 0777, true);
    }
    
    /**
     * Registrar log em arquivo e em error_log
     */
    private function logWebhook(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $fullMessage = "[{$timestamp}] {$message}";
        
        // Gravar em arquivo (para fácil acompanhamento na VPS)
        file_put_contents($this->logFile, $fullMessage . "\n", FILE_APPEND | LOCK_EX);
        
        // Manter também em error_log (compatibilidade)
        error_log($message);
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
            $this->logWebhook("=== WEBHOOK MERCADO PAGO ===");
            $this->logWebhook("Body recebido: " . json_encode($body));
            
            // Validar se é notificação válida
            if (!isset($body['type']) || !isset($body['data']['id'])) {
                error_log("[Webhook MP] ❌ Notificação inválida - falta type ou data.id");
                $response->getBody()->write(json_encode([
                    'error' => 'Notificação inválida'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            $type = $body['type'];
            $dataId = $body['data']['id'];
            
            error_log("[Webhook MP] ✅ Tipo: {$type}, ID: {$dataId}");
            
            // Buscar tenant_id pela matrícula/pagamento (se disponível nos metadados)
            // Por enquanto, usar credenciais globais (ENV) para processar webhook
            $mercadoPagoService = $this->getMercadoPagoService();
            
            // Processar notificação baseado no tipo
            error_log("[Webhook MP] Processando notificação tipo: {$type}...");
            
            $resultadoProcessamento = null;
            
            if ($type === 'subscription_preapproval' || $type === 'subscription' || $type === 'preapproval') {
                // Notificação de assinatura (preapproval)
                $assinatura = $mercadoPagoService->buscarAssinatura($dataId);
                error_log("[Webhook MP] Assinatura processada: status=" . ($assinatura['status'] ?? 'N/A'));
                
                // Atualizar status da assinatura
                $this->atualizarAssinatura($assinatura);
                
                $resultadoProcessamento = [
                    'success' => true,
                    'message' => 'Assinatura processada',
                    'subscription_status' => $assinatura['status'] ?? null,
                    'preapproval_id' => $assinatura['preapproval_id'] ?? $dataId
                ];
                
                $response->getBody()->write(json_encode($resultadoProcessamento));
            } else {
                // Notificação de pagamento normal
                $pagamento = $mercadoPagoService->processarNotificacao($body);
                error_log("[Webhook MP] Pagamento processado: status=" . ($pagamento['status'] ?? 'N/A'));
                
                // Atualizar status no banco de dados
                $this->atualizarPagamento($pagamento);
                
                $resultadoProcessamento = [
                    'success' => true,
                    'message' => 'Notificação processada',
                    'payment_status' => $pagamento['status'] ?? null,
                    'matricula_id' => $pagamento['metadata']['matricula_id'] ?? null
                ];
                
                $response->getBody()->write(json_encode($resultadoProcessamento));
            }
            
            // Salvar payload no banco para auditoria
            $this->salvarWebhookPayload($body, $type, $dataId, 'sucesso', null, $resultadoProcessamento);
            
            error_log("[Webhook MP] ✅ Processamento concluído com sucesso");
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            
        } catch (\Exception $e) {
            error_log("[Webhook MP] ❌ ERRO: " . $e->getMessage());
            error_log("[Webhook MP] Stack: " . $e->getTraceAsString());
            
            // Salvar payload com erro
            $type = $body['type'] ?? 'unknown';
            $dataId = $body['data']['id'] ?? null;
            $this->salvarWebhookPayload($body, $type, $dataId, 'erro', $e->getMessage(), null);
            
            // Retornar 200 mesmo com erro para evitar reenvios
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        }
    }
    
    /**
     * Salvar payload do webhook no banco para auditoria
     */
    private function salvarWebhookPayload(
        array $body, 
        string $tipo, 
        ?int $dataId, 
        string $statusProcessamento, 
        ?string $erroProcessamento = null,
        ?array $resultadoProcessamento = null
    ): void
    {
        try {
            // Extrair informações do body
            $externalReference = null;
            $paymentId = null;
            $preapprovalId = null;
            $tenantId = null;
            
            // Se é notificação de pagamento
            if ($tipo === 'payment') {
                $paymentId = $dataId;
                // Tentar extrair external_reference do body (pode estar em metadata)
                // Será preenchido via query que buscará os dados completos do MP
            } 
            // Se é notificação de assinatura
            elseif (in_array($tipo, ['subscription_preapproval', 'subscription', 'preapproval'])) {
                $preapprovalId = $dataId;
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO webhook_payloads_mercadopago (
                    tenant_id, 
                    tipo, 
                    data_id, 
                    external_reference, 
                    payment_id, 
                    preapproval_id, 
                    status, 
                    erro_processamento, 
                    payload, 
                    resultado_processamento, 
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $tenantId,
                $tipo,
                $dataId,
                $externalReference,
                $paymentId,
                $preapprovalId,
                $statusProcessamento,
                $erroProcessamento,
                json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $resultadoProcessamento ? json_encode($resultadoProcessamento, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null
            ]);
            
            error_log("[Webhook MP] 💾 Payload salvo no banco para auditoria - ID: " . $this->db->lastInsertId());
            
        } catch (\Exception $e) {
            error_log("[Webhook MP] ⚠️ Erro ao salvar payload para auditoria: " . $e->getMessage());
            // Não lança exceção para não interromper o fluxo do webhook
        }
    }
    
    /**
     * Debug: Listar webhooks salvos
     * 
     * GET /api/webhooks/mercadopago/list
     */
    public function listarWebhooks(Request $request, Response $response): Response
    {
        try {
            $filtro = $request->getQueryParams()['filtro'] ?? null;
            $limite = (int) ($request->getQueryParams()['limite'] ?? 50);
            
            $sql = "SELECT id, created_at, tipo, data_id, status, external_reference, payment_id, preapproval_id, erro_processamento FROM webhook_payloads_mercadopago";
            
            if ($filtro === 'erro') {
                $sql .= " WHERE status = 'erro'";
            } elseif ($filtro === 'sucesso') {
                $sql .= " WHERE status = 'sucesso'";
            }
            
            $sql .= " ORDER BY id DESC LIMIT {$limite}";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $webhooks = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'total' => count($webhooks),
                'webhooks' => $webhooks
            ], JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Debug: Ver detalhes de um webhook específico
     * 
     * GET /api/webhooks/mercadopago/show/{id}
     */
    public function mostrarWebhook(Request $request, Response $response, int $id): Response
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM webhook_payloads_mercadopago WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $webhook = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$webhook) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Webhook não encontrado'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            // Decodificar JSONs
            $webhook['payload'] = json_decode($webhook['payload'], true);
            $webhook['resultado_processamento'] = $webhook['resultado_processamento'] ? json_decode($webhook['resultado_processamento'], true) : null;
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'webhook' => $webhook
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Debug: Reprocessar um webhook
     * 
     * POST /api/webhooks/mercadopago/reprocess/{id}
     */
    public function reprocessarWebhook(Request $request, Response $response, int $id): Response
    {
        try {
            $stmt = $this->db->prepare("SELECT payload FROM webhook_payloads_mercadopago WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $resultado = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$resultado) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Webhook não encontrado'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            $payload = json_decode($resultado['payload'], true);
            
            // Reprocessar
            $mercadoPagoService = $this->getMercadoPagoService();
            if ($payload['type'] === 'payment') {
                $pagamento = $mercadoPagoService->buscarPagamento($payload['data']['id']);
                $this->atualizarPagamento($pagamento);
            } elseif (in_array($payload['type'], ['subscription_preapproval', 'subscription', 'preapproval'])) {
                $assinatura = $mercadoPagoService->buscarAssinatura($payload['data']['id']);
                $this->atualizarAssinatura($assinatura);
            }
            
            // Atualizar status
            $stmtUpdate = $this->db->prepare("UPDATE webhook_payloads_mercadopago SET status = 'reprocessado', updated_at = NOW() WHERE id = ?");
            $stmtUpdate->execute([$id]);
            
            error_log("[Webhook MP] 🔄 Webhook #{$id} reprocessado com sucesso");
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Webhook reprocessado com sucesso'
            ]));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log("[Webhook MP] ❌ Erro ao reprocessar webhook: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Debug: Buscar pagamento direto da API do MP
     * 
     * GET /api/webhooks/mercadopago/payment/{paymentId}
     */
    public function buscarPagamentoDebug(Request $request, Response $response, string $paymentId): Response
    {
        try {
            $mercadoPagoService = $this->getMercadoPagoService();
            $pagamento = $mercadoPagoService->buscarPagamento($paymentId);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'pagamento' => $pagamento
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Debug: Reprocessar pagamento específico
     * 
     * POST /api/webhooks/mercadopago/payment/{paymentId}/reprocess
     */
    public function reprocessarPagamento(Request $request, Response $response, string $paymentId): Response
    {
        try {
            error_log("[Webhook MP] 🔄 Reprocessando pagamento #{$paymentId}...");
            
            $mercadoPagoService = $this->getMercadoPagoService();
            $pagamento = $mercadoPagoService->buscarPagamento($paymentId);
            
            error_log("[Webhook MP] 💾 Dados do pagamento: " . json_encode($pagamento));
            
            // Reprocessar
            $this->atualizarPagamento($pagamento);
            
            error_log("[Webhook MP] ✅ Pagamento #{$paymentId} reprocessado com sucesso");
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Pagamento reprocessado com sucesso',
                'payment_id' => $paymentId
            ]));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log("[Webhook MP] ❌ Erro ao reprocessar pagamento: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
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
            $assinaturaId = $body['assinatura_id'] ?? null;
            
            if (!$paymentId && !$matriculaId && !$assinaturaId) {
                $response->getBody()->write(json_encode([
                    'error' => 'Informe payment_id, matricula_id ou assinatura_id'
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
                $result['matricula_id_extraido'] = $pagamento['metadata']['matricula_id'] ?? null;
            }
            
            // Se tem assinatura_id, forçar atualização direta
            if ($assinaturaId) {
                $this->forcarAtualizacaoAssinatura((int)$assinaturaId);
                $result['assinatura_forcada'] = true;
            }
            
            // Se tem matricula_id, criar pagamento manualmente ou forçar atualização da assinatura
            if ($matriculaId && !$paymentId) {
                $this->criarPagamentoManual((int)$matriculaId);
                $result['pagamento_manual_criado'] = true;
                
                // Também forçar atualização da assinatura
                $this->forcarAtualizacaoAssinaturaPorMatricula((int)$matriculaId);
                $result['assinatura_atualizada_por_matricula'] = true;
            }
            
            // Buscar dados atuais
            $matId = $matriculaId ?? $pagamento['metadata']['matricula_id'] ?? 0;
            
            $stmt = $this->db->prepare("SELECT * FROM pagamentos_plano WHERE matricula_id = ?");
            $stmt->execute([$matId]);
            $result['pagamentos_plano'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $stmtAss = $this->db->prepare("
                SELECT a.*, s.codigo as status_codigo, s.nome as status_nome 
                FROM assinaturas a 
                LEFT JOIN assinatura_status s ON s.id = a.status_id
                WHERE a.matricula_id = ? OR a.id = ?
            ");
            $stmtAss->execute([$matId, $assinaturaId ?? 0]);
            $result['assinaturas'] = $stmtAss->fetchAll(\PDO::FETCH_ASSOC);
            
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
     * Forçar atualização de assinatura pelo ID
     */
    private function forcarAtualizacaoAssinatura(int $assinaturaId): void
    {
        error_log("[Webhook MP DEBUG] Forçando atualização da assinatura #{$assinaturaId}");
        
        // Buscar assinatura
        $stmtBuscar = $this->db->prepare("
            SELECT a.id, a.tipo_cobranca 
            FROM assinaturas a
            WHERE a.id = ?
        ");
        $stmtBuscar->execute([$assinaturaId]);
        $assinatura = $stmtBuscar->fetch(\PDO::FETCH_ASSOC);
        
        if (!$assinatura) {
            error_log("[Webhook MP DEBUG] Assinatura #{$assinaturaId} não encontrada");
            return;
        }
        
        // Buscar status correto
        $statusCodigo = $assinatura['tipo_cobranca'] === 'avulso' ? 'paga' : 'ativa';
        $stmtStatus = $this->db->prepare("SELECT id FROM assinatura_status WHERE codigo = ?");
        $stmtStatus->execute([$statusCodigo]);
        $statusId = $stmtStatus->fetchColumn();
        
        if (!$statusId) {
            $stmtStatus->execute(['ativa']);
            $statusId = $stmtStatus->fetchColumn() ?: 2;
        }
        
        // Atualizar
        $stmtUpdate = $this->db->prepare("
            UPDATE assinaturas 
            SET status_id = ?, status_gateway = 'approved', ultima_cobranca = CURDATE(), atualizado_em = NOW()
            WHERE id = ?
        ");
        $stmtUpdate->execute([$statusId, $assinaturaId]);
        
        error_log("[Webhook MP DEBUG] Assinatura #{$assinaturaId} atualizada para status_id={$statusId}");
    }
    
    /**
     * Forçar atualização de assinatura pela matrícula
     */
    private function forcarAtualizacaoAssinaturaPorMatricula(int $matriculaId): void
    {
        error_log("[Webhook MP DEBUG] Forçando atualização da assinatura pela matrícula #{$matriculaId}");
        
        // Buscar assinatura
        $stmtBuscar = $this->db->prepare("SELECT id FROM assinaturas WHERE matricula_id = ? LIMIT 1");
        $stmtBuscar->execute([$matriculaId]);
        $assinaturaId = $stmtBuscar->fetchColumn();
        
        if ($assinaturaId) {
            $this->forcarAtualizacaoAssinatura((int)$assinaturaId);
        } else {
            error_log("[Webhook MP DEBUG] Nenhuma assinatura encontrada para matrícula #{$matriculaId}");
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
     * Atualizar status da assinatura no banco
     * Processa notificações de preapproval (assinaturas recorrentes)
     */
    private function atualizarAssinatura(array $assinatura): void
    {
        $preapprovalId = $assinatura['preapproval_id'] ?? $assinatura['id'] ?? null;
        $externalReference = $assinatura['external_reference'] ?? '';
        $status = $assinatura['status'] ?? 'pending';
        $statusDetail = $assinatura['status_detail'] ?? $status;
        
        error_log("[Webhook MP] 📋 Atualizando assinatura: preapproval_id={$preapprovalId}, external_reference={$externalReference}, status={$status}");
        
        // ⭐ NOVO: Detectar se é PACOTE pelo external_reference (PAC-xxx)
        if (strpos($externalReference, 'PAC-') === 0) {
            error_log("[Webhook MP] 🎁 DETECÇÃO: Assinatura recorrente de PACOTE encontrada!");
            error_log("[Webhook MP] 📦 External Reference: {$externalReference}");
            
            // Extrair contratoId do formato PAC-{contratoId}-{timestamp}
            if (preg_match('/PAC-(\d+)-/', $externalReference, $matches)) {
                $contratoId = (int) $matches[1];
                error_log("[Webhook MP] 🎯 Contrato ID extraído: {$contratoId}");
                
                if ($status === 'approved' || $status === 'authorized') {
                    error_log("[Webhook MP] ✅ Assinatura de PACOTE APROVADA - Chamando ativarPacoteContrato");
                    // Montar objeto pagamento similiar para ativarPacoteContrato
                    $pagamento = [
                        'id' => $preapprovalId,
                        'status' => $status,
                        'metadata' => [
                            'tipo' => 'pacote',
                            'pacote_contrato_id' => $contratoId
                        ],
                        'init_point' => $assinatura['init_point'] ?? null
                    ];
                    $this->ativarPacoteContrato($contratoId, $pagamento);
                } else {
                    error_log("[Webhook MP] ⚠️ Assinatura de pacote com status: {$status} (não processando)");
                }
            } else {
                error_log("[Webhook MP] ❌ Não consegui extrair contratoId do external_reference: {$externalReference}");
            }
            return; // IMPORTANTE: sair daqui, não continuar processamento normal
        }
        
        // ========== PROCESSAMENTO NORMAL (para assinatura de matrícula regular) ==========
        
        // Extrair matrícula do external_reference (formato: MAT-123-timestamp)
        $matriculaId = null;
        if (preg_match('/MAT-(\d+)/', $externalReference, $matches)) {
            $matriculaId = (int)$matches[1];
        }
        
        error_log("[Webhook MP] 📋 Processando assinatura regular de matrícula (não-pacote)");
        
        // Buscar assinatura na tabela assinaturas pelo gateway_assinatura_id
        $stmtBuscar = $this->db->prepare("
            SELECT a.id, a.matricula_id, s.codigo as status_atual 
            FROM assinaturas a
            INNER JOIN assinatura_status s ON s.id = a.status_id
            WHERE a.gateway_assinatura_id = ?
            LIMIT 1
        ");
        $stmtBuscar->execute([$preapprovalId]);
        $assinaturaDb = $stmtBuscar->fetch(\PDO::FETCH_ASSOC);
        
        if ($assinaturaDb) {
            $matriculaId = $matriculaId ?? $assinaturaDb['matricula_id'];
            
            // Mapear status do MP para status interno
            $statusMap = [
                'approved' => 'ativa',
                'authorized' => 'ativa',
                'pending' => 'pendente',
                'paused' => 'pausada',
                'cancelled' => 'cancelada'
            ];
            $statusInterno = $statusMap[$status] ?? 'pendente';
            
            // Buscar ID do status
            $stmtStatusId = $this->db->prepare("SELECT id FROM assinatura_status WHERE codigo = ?");
            $stmtStatusId->execute([$statusInterno]);
            $statusId = $stmtStatusId->fetchColumn() ?: 1;
            
            // Atualizar status da assinatura
            $stmtUpdate = $this->db->prepare("
                UPDATE assinaturas
                SET status_id = ?,
                    status_gateway = ?,
                    atualizado_em = NOW()
                WHERE id = ?
            ");
            $stmtUpdate->execute([$statusId, $status, $assinaturaDb['id']]);
            
            error_log("[Webhook MP] ✅ Assinatura #{$assinaturaDb['id']} atualizada: {$assinaturaDb['status_atual']} -> {$statusInterno}");
        } else {
            error_log("[Webhook MP] ⚠️ Assinatura não encontrada no banco: preapproval_id={$preapprovalId}");
        }
        
        // Se assinatura foi autorizada, ativar matrícula e registrar pagamento
        if ($status === 'approved' || $status === 'authorized') {
            if ($matriculaId) {
                $this->ativarMatricula($matriculaId);
                $this->baixarPagamentoPlanoAssinatura($matriculaId, $assinatura);
            }
        }
    }
    
    /**
     * Baixar pagamento na tabela pagamentos_plano para assinatura
     */
    private function baixarPagamentoPlanoAssinatura(int $matriculaId, array $assinatura): void
    {
        try {
            error_log("[Webhook MP] Iniciando baixa de pagamento de ASSINATURA para matrícula #{$matriculaId}");
            
            // Buscar dados da matrícula
            $stmtMatricula = $this->db->prepare("
                SELECT m.id, m.tenant_id, m.aluno_id, m.plano_id, m.plano_ciclo_id, p.valor as valor_plano,
                       pc.valor as valor_ciclo
                FROM matriculas m
                INNER JOIN planos p ON p.id = m.plano_id
                LEFT JOIN plano_ciclos pc ON pc.id = m.plano_ciclo_id
                WHERE m.id = ?
            ");
            $stmtMatricula->execute([$matriculaId]);
            $matricula = $stmtMatricula->fetch(\PDO::FETCH_ASSOC);
            
            if (!$matricula) {
                error_log("[Webhook MP] ❌ Matrícula #{$matriculaId} não encontrada");
                return;
            }
            
            // Usar valor da assinatura ou valor do ciclo ou valor do plano
            $valor = $assinatura['transaction_amount'] ?? $matricula['valor_ciclo'] ?? $matricula['valor_plano'];
            
            // Buscar o pagamento pendente mais antigo da matrícula (status 1 = Aguardando)
            $stmtBuscar = $this->db->prepare("
                SELECT pp.id, pp.valor
                FROM pagamentos_plano pp
                INNER JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
                WHERE pp.matricula_id = ?
                AND sp.codigo IN ('pendente', 'aguardando')
                ORDER BY pp.data_vencimento ASC
                LIMIT 1
            ");
            $stmtBuscar->execute([$matriculaId]);
            $pagamentoPendente = $stmtBuscar->fetch(\PDO::FETCH_ASSOC);
            
            // Verificar se já existe pagamento pago hoje para evitar duplicatas (webhook duplicado)
            $stmtDuplicata = $this->db->prepare("
                SELECT pp.id FROM pagamentos_plano pp
                INNER JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
                WHERE pp.matricula_id = ? AND sp.codigo = 'pago' AND DATE(pp.data_pagamento) = CURDATE()
                LIMIT 1
            ");
            $stmtDuplicata->execute([$matriculaId]);
            if ($stmtDuplicata->fetch()) {
                error_log("[Webhook MP] ⚠️ Pagamento assinatura já processado hoje para matrícula #{$matriculaId}, ignorando duplicata");
                return;
            }
            
            // Para assinaturas, forma de pagamento é sempre cartão de crédito (ID 9)
            $formaPagamentoId = 9;
            
            if ($pagamentoPendente) {
                // Atualizar o pagamento existente para "pago"
                error_log("[Webhook MP] Atualizando pagamento existente #{$pagamentoPendente['id']}");
                
                $stmtUpdate = $this->db->prepare("
                    UPDATE pagamentos_plano
                    SET status_pagamento_id = 2,
                        data_pagamento = NOW(),
                        forma_pagamento_id = ?,
                        tipo_baixa_id = 2,
                        observacoes = CONCAT(IFNULL(observacoes, ''), ' | Pago via Assinatura MP - ID: " . ($assinatura['preapproval_id'] ?? 'N/A') . "'),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmtUpdate->execute([$formaPagamentoId, $pagamentoPendente['id']]);
                
                if ($stmtUpdate->rowCount() > 0) {
                    error_log("[Webhook MP] ✅ Pagamento #{$pagamentoPendente['id']} atualizado para PAGO (Assinatura)");
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
                    $valor,
                    $formaPagamentoId,
                    'Pago via Assinatura MP - ID: ' . ($assinatura['preapproval_id'] ?? 'N/A')
                ]);
                
                $novoPagamentoId = $this->db->lastInsertId();
                error_log("[Webhook MP] ✅ Novo pagamento #{$novoPagamentoId} criado como PAGO (Assinatura)");
            }
            
        } catch (\Exception $e) {
            error_log("[Webhook MP] ❌ Erro ao baixar pagamento_plano (assinatura): " . $e->getMessage());
            error_log("[Webhook MP] Stack: " . $e->getTraceAsString());
        }
    }
    
    /**
     * Atualizar status do pagamento no banco
     */
    private function atualizarPagamento(array $pagamento): void
    {
        error_log("[Webhook MP] 📊 ATUALIZANDO PAGAMENTO");
        error_log("[Webhook MP] 📋 Status: " . ($pagamento['status'] ?? 'N/A'));
        error_log("[Webhook MP] 💳 ID Pagamento: " . ($pagamento['id'] ?? 'N/A'));
        error_log("[Webhook MP] 📝 External reference: " . ($pagamento['external_reference'] ?? 'N/A'));
        error_log("[Webhook MP] 🏷️ Metadados completos: " . json_encode($pagamento['metadata'] ?? []));
        
        $externalReference = $pagamento['external_reference'];
        $metadata = $pagamento['metadata'];
        $tipo = $metadata['tipo'] ?? null;
        
        // FALLBACK: Se tipo não veio no metadata, tentar extrair do external_reference
        if (!$tipo && $externalReference) {
            if (strpos($externalReference, 'PAC-') === 0) {
                $tipo = 'pacote';
                error_log("[Webhook MP] 🎁 Tipo detectado como PACOTE pelo external_reference: {$externalReference}");
            } elseif (strpos($externalReference, 'MAT-') === 0) {
                $tipo = 'matricula';
                error_log("[Webhook MP] 📚 Tipo detectado como MATRÍCULA pelo external_reference: {$externalReference}");
            }
        }
        
        // Para pagamentos de pacote, não há matrícula ainda (será criada pelo webhook)
        // Saímos do fluxo normal de pagamento e processamos direto com ativarPacoteContrato
        if ($tipo === 'pacote') {
            error_log("[Webhook MP] 🎁 PACOTE DETECTED - Processando como pagamento de pacote");
            
            if ($pagamento['status'] === 'approved') {
                error_log("[Webhook MP] ✅ Pagamento de pacote APROVADO - Chamando ativarPacoteContrato");
                
                $pacoteContratoId = $metadata['pacote_contrato_id'] ?? null;
                $tenantIdFromMetadata = $metadata['tenant_id'] ?? null;
                
                // FALLBACK: Extrair do external_reference se não estiver no metadata
                if (!$pacoteContratoId && $externalReference && preg_match('/PAC-(\d+)-/', $externalReference, $matches)) {
                    $pacoteContratoId = (int) $matches[1];
                    error_log("[Webhook MP] 🎯 pacote_contrato_id extraído do external_reference: {$pacoteContratoId}");
                }
                
                // FALLBACK: Se tenant_id não está no metadata, buscar da tabela pacote_contratos
                if (!$tenantIdFromMetadata && $pacoteContratoId) {
                    error_log("[Webhook MP] 🔍 tenant_id não encontrado em metadata, buscando em pacote_contratos...");
                    $stmtTenantFallback = $this->db->prepare("
                        SELECT tenant_id FROM pacote_contratos 
                        WHERE id = ? 
                        LIMIT 1
                    ");
                    $stmtTenantFallback->execute([$pacoteContratoId]);
                    $tenantFallback = $stmtTenantFallback->fetch(\PDO::FETCH_ASSOC);
                    
                    if ($tenantFallback) {
                        $tenantIdFromMetadata = (int) $tenantFallback['tenant_id'];
                        error_log("[Webhook MP] ✅ tenant_id encontrado em pacote_contratos: {$tenantIdFromMetadata}");
                        
                        // Atualizar metadata para ter tenant_id
                        $metadata['tenant_id'] = $tenantIdFromMetadata;
                    } else {
                        error_log("[Webhook MP] ❌ Não conseguiu encontrar tenant_id para contrato {$pacoteContratoId}");
                    }
                }
                
                if ($pacoteContratoId) {
                    // Repassar pagamento com metadata atualizada
                    $pagamento['metadata'] = $metadata;
                    $this->ativarPacoteContrato($pacoteContratoId, $pagamento);
                } else {
                    error_log("[Webhook MP] ❌ pacote_contrato_id não encontrado no metadata nem no external_reference");
                }
            } else {
                error_log("[Webhook MP] ⚠️ Pagamento de pacote com status: {$pagamento['status']} (não processando)");
            }
            return; // Sair da função aqui, não continua com o fluxo de matrícula avulsa
        }
        
        // PAGAMENTOS AVULSOS (de matrícula)
        // Tentar extrair IDs da external_reference (formato: MAT-123-timestamp)
        $matriculaId = null;
        
        if (preg_match('/MAT-(\d+)/', $externalReference, $matches)) {
            $matriculaId = (int) $matches[1];
            error_log("[Webhook MP] 📌 Matrícula ID extraído do external_reference: {$matriculaId}");
        } else {
            $matriculaId = $metadata['matricula_id'] ?? null;
            
            if ($matriculaId) {
                error_log("[Webhook MP] 📌 Matrícula ID extraído dos metadados: {$matriculaId}");
            } else {
                error_log("[Webhook MP] ⚠️ external_reference não contém MAT-, tentando fallback...");
                
                // FALLBACK 1: Procurar por payment_id na tabela pagamentos_mercadopago
                $stmtFallback1 = $this->db->prepare("
                    SELECT matricula_id FROM pagamentos_mercadopago 
                    WHERE payment_id = ? 
                    LIMIT 1
                ");
                $stmtFallback1->execute([$pagamento['id']]);
                $fallback1 = $stmtFallback1->fetch(\PDO::FETCH_ASSOC);
                
                if ($fallback1) {
                    $matriculaId = (int) $fallback1['matricula_id'];
                    error_log("[Webhook MP] ✅ Fallback 1: Matrícula encontrada no pagamentos_mercadopago: {$matriculaId}");
                }
                
                // FALLBACK 2: Se ainda não encontrou, procurar por aluno_id + data recente
                if (!$matriculaId && isset($metadata['aluno_id'])) {
                    $stmtFallback2 = $this->db->prepare("
                        SELECT m.id FROM matriculas m
                        WHERE m.aluno_id = ? 
                        AND m.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                        ORDER BY m.created_at DESC
                        LIMIT 1
                    ");
                    $stmtFallback2->execute([$metadata['aluno_id']]);
                    $fallback2 = $stmtFallback2->fetch(\PDO::FETCH_ASSOC);
                    
                    if ($fallback2) {
                        $matriculaId = (int) $fallback2['id'];
                        error_log("[Webhook MP] ✅ Fallback 2: Matrícula encontrada por aluno_id recente: {$matriculaId}");
                    }
                }
            }
        }
        
        if (!$matriculaId) {
            error_log("[Webhook MP] ❌ Falha: Matrícula não identificada por nenhum método (external_reference, metadata, payment_id, aluno_id)");
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
        
        // Se pagamento foi aprovado, ativar matrícula, baixar pagamento_plano e atualizar assinatura
        // (Note: Pacotes são processados no início desta função e retornam cedo)
        if ($pagamento['status'] === 'approved') {
            $matriculaIdInt = (int) $matriculaId;
            error_log("[Webhook MP] ✅ Pagamento APROVADO - matriculaId: {$matriculaIdInt}");
            
            $this->ativarMatricula($matriculaIdInt);
            $this->baixarPagamentoPlano($matriculaIdInt, $pagamento);
            $this->atualizarAssinaturaAvulsa($matriculaIdInt, $pagamento);
        } elseif (in_array($pagamento['status'], ['refunded', 'cancelled', 'charged_back'], true)) {
            // Para pagamentos avulsos estornados/cancelados, cancelar assinatura e matrícula
            $matriculaIdInt = (int) $matriculaId;
            error_log("[Webhook MP] ⚠️ Pagamento {$pagamento['status']} - matriculaId: {$matriculaIdInt}");
            $this->cancelarMatricula($matriculaIdInt);
            $this->atualizarAssinaturaAvulsaCancelada($matriculaIdInt, $pagamento);
        }
    }

    /**
     * Ativar contrato de pacote e gerar matrículas rateadas
     */
    private function ativarPacoteContrato(int $contratoId, array $pagamento): void
    {
        try {
            error_log("[Webhook MP] 🎯 INICIANDO ativarPacoteContrato - contratoId: {$contratoId}");
            error_log("[Webhook MP] 📦 Metadados do pagamento: " . json_encode($pagamento['metadata'] ?? []));
            
            $this->db->beginTransaction();

            $tenantId = $pagamento['metadata']['tenant_id'] ?? null;
            error_log("[Webhook MP] 🏢 tenant_id: {$tenantId}");

            $stmtContrato = $this->db->prepare("
                SELECT pc.*, p.plano_id, p.plano_ciclo_id, p.valor_total,
                       COALESCE(pc2.permite_recorrencia, 0) as permite_recorrencia
                FROM pacote_contratos pc
                INNER JOIN pacotes p ON p.id = pc.pacote_id
                LEFT JOIN plano_ciclos pc2 ON pc2.id = p.plano_ciclo_id
                WHERE pc.id = ? AND pc.tenant_id = ?
                LIMIT 1
            ");
            $stmtContrato->execute([$contratoId, $tenantId]);
            $contrato = $stmtContrato->fetch(\PDO::FETCH_ASSOC);

            if (!$contrato) {
                error_log("[Webhook MP] ❌ Contrato não encontrado: contratoId={$contratoId}, tenantId={$tenantId}");
                error_log("[Webhook MP] SQL: SELECT pc.*, p.plano_id, p.plano_ciclo_id, p.valor_total, COALESCE(pc2.permite_recorrencia, 0) as permite_recorrencia FROM pacote_contratos pc INNER JOIN pacotes p ON p.id = pc.pacote_id LEFT JOIN plano_ciclos pc2 ON pc2.id = p.plano_ciclo_id WHERE pc.id = {$contratoId} AND pc.tenant_id = {$tenantId}");
                $this->db->rollBack();
                return;
            }

            error_log("[Webhook MP] ✅ Contrato encontrado:");
            error_log("[Webhook MP]    ID: {$contrato['id']}");
            error_log("[Webhook MP]    Status: {$contrato['status']}");
            error_log("[Webhook MP]    Pacote ID: {$contrato['pacote_id']}");
            error_log("[Webhook MP]    Plano ID: {$contrato['plano_id']}");
            error_log("[Webhook MP]    Valor Total: {$contrato['valor_total']}");
            error_log("[Webhook MP]    Permite Recorrência: {$contrato['permite_recorrencia']}");
            
            if (($contrato['status'] ?? '') === 'ativo') {
                error_log("[Webhook MP] ⚠️ Contrato já está ativo, ignorando");
                $this->db->rollBack();
                return;
            }

            $stmtBenef = $this->db->prepare("
                SELECT pb.id, pb.aluno_id
                FROM pacote_beneficiarios pb
                WHERE pb.pacote_contrato_id = ? AND pb.tenant_id = ?
            ");
            $stmtBenef->execute([$contratoId, $contrato['tenant_id']]);
            $beneficiarios = $stmtBenef->fetchAll(\PDO::FETCH_ASSOC);

            error_log("[Webhook MP] 👥 Total de beneficiários: " . count($beneficiarios));
            
            if (!empty($beneficiarios)) {
                error_log("[Webhook MP] 📋 Beneficiários encontrados:");
                foreach ($beneficiarios as $b) {
                    error_log("[Webhook MP]    - ID: {$b['id']}, Aluno ID: {$b['aluno_id']}");
                }
            }
            
            if (empty($beneficiarios)) {
                error_log("[Webhook MP] ❌ Nenhum beneficiário encontrado para contrato {$contratoId}");
                error_log("[Webhook MP] SQL: SELECT pb.id, pb.aluno_id FROM pacote_beneficiarios pb WHERE pb.pacote_contrato_id = {$contratoId} AND pb.tenant_id = {$contrato['tenant_id']}");
                $this->db->rollBack();
                return;
            }

            $valorTotal = (float) $contrato['valor_total'];
            $valorRateado = $valorTotal / max(1, count($beneficiarios));

            error_log("[Webhook MP] 💰 Valor total: {$valorTotal}, Valor rateado por beneficiário: {$valorRateado}");

            $dataInicio = date('Y-m-d');
            $dataFim = null;
            if (!empty($contrato['plano_ciclo_id'])) {
                $stmtCiclo = $this->db->prepare("SELECT meses FROM plano_ciclos WHERE id = ? AND tenant_id = ? LIMIT 1");
                $stmtCiclo->execute([(int)$contrato['plano_ciclo_id'], $contrato['tenant_id']]);
                $meses = (int) ($stmtCiclo->fetchColumn() ?: 0);
                if ($meses > 0) {
                    $dataFim = date('Y-m-d', strtotime("+{$meses} months"));
                }
            }
            if (!$dataFim) {
                $stmtPlano = $this->db->prepare("SELECT duracao_dias FROM planos WHERE id = ? AND tenant_id = ? LIMIT 1");
                $stmtPlano->execute([(int)$contrato['plano_id'], $contrato['tenant_id']]);
                $duracaoDias = (int) ($stmtPlano->fetchColumn() ?: 30);
                $dataFim = date('Y-m-d', strtotime("+{$duracaoDias} days"));
            }

            error_log("[Webhook MP] 📅 Data início: {$dataInicio}, Data fim: {$dataFim}");

            $stmtUpdContrato = $this->db->prepare("
                UPDATE pacote_contratos
                SET status = 'ativo',
                    pagamento_id = ?,
                    data_inicio = ?,
                    data_fim = ?,
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmtUpdContrato->execute([
                $pagamento['id'] ?? null,
                $dataInicio,
                $dataFim,
                $contratoId,
                $contrato['tenant_id']
            ]);
            
            error_log("[Webhook MP] ✅ Contrato atualizado para status 'ativo'");

            $stmtStatusAtiva = $this->db->prepare("SELECT id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1");
            $stmtStatusAtiva->execute();
            $statusAtivaId = (int) ($stmtStatusAtiva->fetchColumn() ?: 1);


            $stmtMotivo = $this->db->prepare("SELECT id FROM motivo_matricula WHERE codigo = 'nova' LIMIT 1");
            $stmtMotivo->execute();
            $motivoId = (int) ($stmtMotivo->fetchColumn() ?: 1);

            // Buscar informações de frequência, status de assinatura e gateway para assinatura recorrente
            $frequenciaId = 4; // mensal padrão
            $statusAssinaturaId = null;
            $gatewayId = null;
            
            if ((bool) ($contrato['permite_recorrencia'] ?? false) && !empty($contrato['plano_ciclo_id'])) {
                $stmtFreq = $this->db->prepare("
                    SELECT af.id
                    FROM plano_ciclos pc
                    INNER JOIN assinatura_frequencias af ON af.id = pc.assinatura_frequencia_id
                    WHERE pc.id = ? AND pc.tenant_id = ?
                    LIMIT 1
                ");
                $stmtFreq->execute([(int) $contrato['plano_ciclo_id'], $contrato['tenant_id']]);
                $frequenciaId = $stmtFreq->fetchColumn() ?: 4;

                // Status 'ativa' para assubscriptions
                $stmtStatusAssinatura = $this->db->prepare("SELECT id FROM assinatura_status WHERE codigo = 'ativa' LIMIT 1");
                $stmtStatusAssinatura->execute();
                $statusAssinaturaId = $stmtStatusAssinatura->fetchColumn() ?: 1;

                // Gateway Mercado Pago (usar ID 1 como padrão)
                $gatewayId = 1;
            }

            foreach ($beneficiarios as $ben) {
                $tipoCobranca = (bool) ($contrato['permite_recorrencia'] ?? false) ? 'recorrente' : 'avulso';
                
                error_log("[Webhook MP] 🎓 Processando beneficiário aluno_id={$ben['aluno_id']}, tipo_cobranca={$tipoCobranca}");
                
                // Verificar se matrícula já existe para este aluno + pacote
                $stmtVerificar = $this->db->prepare("
                    SELECT id, status_id, data_vencimento, valor, valor_rateado
                    FROM matriculas
                    WHERE aluno_id = ? AND pacote_contrato_id = ? AND tenant_id = ?
                    ORDER BY id DESC LIMIT 1
                ");
                $stmtVerificar->execute([(int) $ben['aluno_id'], $contratoId, $contrato['tenant_id']]);
                $matriculaExistente = $stmtVerificar->fetch(\PDO::FETCH_ASSOC);
                
                if ($matriculaExistente) {
                    // ATUALIZAR matrícula existente (renovação recorrente)
                    $matriculaId = (int) $matriculaExistente['id'];
                    $dadosAntigos = [
                        'status_id' => $matriculaExistente['status_id'],
                        'data_vencimento' => $matriculaExistente['data_vencimento'],
                        'valor' => (float) $matriculaExistente['valor'],
                        'valor_rateado' => (float) $matriculaExistente['valor_rateado']
                    ];
                    
                    $stmtUpdate = $this->db->prepare("
                        UPDATE matriculas
                        SET status_id = ?, data_vencimento = ?, proxima_data_vencimento = ?, 
                            valor = ?, valor_rateado = ?, updated_at = NOW()
                        WHERE id = ? AND tenant_id = ?
                    ");
                    $stmtUpdate->execute([
                        $statusAtivaId,
                        $dataFim,
                        $dataFim,
                        $valorRateado,
                        $valorRateado,
                        $matriculaId,
                        $contrato['tenant_id']
                    ]);
                    
                    $rowsAffected = $stmtUpdate->rowCount();
                    error_log("[Webhook MP] 🔄 Matrícula ATUALIZADA: matricula_id={$matriculaId}, linhas_afetadas={$rowsAffected}, novo_vencimento={$dataFim}");
                    $tipoOperacaoHistorico = 'UPDATE';
                } else {
                    // CRIAR nova matrícula
                    $stmtMat = $this->db->prepare("
                        INSERT INTO matriculas
                        (tenant_id, aluno_id, plano_id, plano_ciclo_id, tipo_cobranca,
                         data_matricula, data_inicio, data_vencimento, valor, valor_rateado,
                         status_id, motivo_id, proxima_data_vencimento, pacote_contrato_id, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $stmtMat->execute([
                        $contrato['tenant_id'],
                        (int) $ben['aluno_id'],
                        (int) $contrato['plano_id'],
                        !empty($contrato['plano_ciclo_id']) ? (int) $contrato['plano_ciclo_id'] : null,
                        $tipoCobranca,
                        $dataInicio,
                        $dataInicio,
                        $dataFim,
                        $valorRateado,
                        $valorRateado,
                        $statusAtivaId,
                        $motivoId,
                        $dataFim,
                        $contratoId
                    ]);
                    $matriculaId = (int) $this->db->lastInsertId();
                    error_log("[Webhook MP] ✨ Matrícula CRIADA: matricula_id={$matriculaId}, aluno_id={$ben['aluno_id']}, vencimento={$dataFim}");
                    error_log("[Webhook MP]    tenant_id={$contrato['tenant_id']}, plano_id={$contrato['plano_id']}, status_id={$statusAtivaId}");
                    $dadosAntigos = null;
                    $tipoOperacaoHistorico = 'INSERT';
                }

                // Se é recorrente, criar ou atualizar assinatura também
                if ($tipoCobranca === 'recorrente' && $statusAssinaturaId && $gatewayId) {
                    error_log("[Webhook MP] 🔐 Processando assinatura recorrente para matrícula {$matriculaId}");
                    // Verificar se assinatura já existe
                    $stmtAssinComprovacao = $this->db->prepare("
                        SELECT id FROM assinaturas
                        WHERE matricula_id = ? AND tenant_id = ?
                        LIMIT 1
                    ");
                    $stmtAssinComprovacao->execute([$matriculaId, $contrato['tenant_id']]);
                    $assinaturaExistente = $stmtAssinComprovacao->fetchColumn();
                    
                    if (!$assinaturaExistente) {
                        error_log("[Webhook MP] 🆕 Criando NOVA assinatura");
                        // Criar nova assinatura
                        $stmtAssinatura = $this->db->prepare("
                            INSERT INTO assinaturas
                            (tenant_id, matricula_id, aluno_id, plano_id,
                             gateway_id, gateway_preference_id, external_reference, payment_url,
                             status_id, status_gateway, valor, frequencia_id, dia_cobranca,
                             data_inicio, data_fim, proxima_cobranca, tipo_cobranca, criado_em)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, ?, ?, ?, ?, ?, 'recorrente', NOW())
                        ");
                        $stmtAssinatura->execute([
                            $contrato['tenant_id'],
                            $matriculaId,
                            (int) $ben['aluno_id'],
                            (int) $contrato['plano_id'],
                            $gatewayId,
                            $pagamento['id'] ?? null,
                            'pacote_' . $contratoId . '_' . $matriculaId,
                            $pagamento['init_point'] ?? null,
                            $statusAssinaturaId,
                            (float) $valorRateado,
                            $frequenciaId,
                            (int) date('d'),
                            $dataInicio,
                            $dataFim,
                            $dataFim,
                        ]);
                        error_log("[Webhook MP] ✨ Assinatura CRIADA para matrícula {$matriculaId}");
                    } else {
                        // Atualizar assinatura existente
                        error_log("[Webhook MP] 🔄 Atualizando assinatura existente para matrícula {$matriculaId}");
                        $stmtAssinUpdate = $this->db->prepare("
                            UPDATE assinaturas
                            SET status_id = ?, data_fim = ?, proxima_cobranca = ?, valor = ?, 
                                status_gateway = 'approved', atualizado_em = NOW()
                            WHERE matricula_id = ? AND tenant_id = ?
                        ");
                        $stmtAssinUpdate->execute([
                            $statusAssinaturaId,
                            $dataFim,
                            $dataFim,
                            (float) $valorRateado,
                            $matriculaId,
                            $contrato['tenant_id']
                        ]);
                        error_log("[Webhook MP] ✅ Assinatura ATUALIZADA para matrícula {$matriculaId}");
                    }
                }
                
                // Registrar a operação no histórico
                $dadosNovos = [
                    'aluno_id' => (int) $ben['aluno_id'],
                    'plano_id' => (int) $contrato['plano_id'],
                    'plano_ciclo_id' => !empty($contrato['plano_ciclo_id']) ? (int) $contrato['plano_ciclo_id'] : null,
                    'data_vencimento' => $dataFim,
                    'valor' => (float) $valorRateado,
                    'status_id' => $statusAtivaId,
                    'tipo_cobranca' => $tipoCobranca
                ];
                
                $this->registrarHistoricoMatricula(
                    $contrato['tenant_id'],
                    $matriculaId,
                    (int) $ben['aluno_id'],
                    $tipoOperacaoHistorico,
                    $dadosAntigos,
                    $dadosNovos,
                    $tipoOperacaoHistorico === 'INSERT' ? 'Primeira compra do pacote' : 'Renovação recorrente do pacote'
                );

                $stmtUpdBen = $this->db->prepare("
                    UPDATE pacote_beneficiarios
                    SET matricula_id = ?, valor_rateado = ?, status = 'ativo', updated_at = NOW()
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmtUpdBen->execute([
                    $matriculaId,
                    $valorRateado,
                    (int) $ben['id'],
                    $contrato['tenant_id']
                ]);

                $stmtPag = $this->db->prepare("
                    INSERT INTO pagamentos_plano
                    (tenant_id, aluno_id, matricula_id, plano_id, valor, data_vencimento,
                     status_pagamento_id, pacote_contrato_id, observacoes, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, (SELECT id FROM status_pagamento WHERE codigo = 'aprovado' LIMIT 1), ?, 'Pacote rateado', NOW(), NOW())
                ");
                $stmtPag->execute([
                    $contrato['tenant_id'],
                    (int) $ben['aluno_id'],
                    $matriculaId,
                    (int) $contrato['plano_id'],
                    $valorRateado,
                    $dataInicio,
                    $contratoId
                ]);
                
                error_log("[Webhook MP] 💾 Pagamento de pacote rateado registrado para matrícula {$matriculaId}");
                error_log("[Webhook MP] ✨ Beneficiário FINALIZADO: aluno_id={$ben['aluno_id']}, matricula_id={$matriculaId}");
            }

            error_log("[Webhook MP] 🎉 Todos os beneficiários processados. Fazendo COMMIT...");
            $this->db->commit();
            error_log("[Webhook MP] ✅✅✅ PACOTE CONTRATO ATIVADO COM SUCESSO - contratoId: {$contratoId}");
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("[Webhook MP] ❌❌❌ ERRO CRÍTICO ao ativar pacote: " . $e->getMessage());
            error_log("[Webhook MP] Stack trace: " . $e->getTraceAsString());
        }
    }
    
    /**
     * Atualizar assinatura avulsa após pagamento aprovado
     */
    private function atualizarAssinaturaAvulsa(int $matriculaId, array $pagamento): void
    {
        try {
            error_log("[Webhook MP] 🔍 Buscando assinatura para matrícula #{$matriculaId}...");
            
            // Extrair preference_id do pagamento (para pagamentos avulsos)
            $preferenceId = $pagamento['preference_id'] ?? null;
            error_log("[Webhook MP] 📋 preference_id do pagamento: " . ($preferenceId ?? 'NULL'));
            
            // Buscar assinatura pela matrícula OU pelo preference_id
            $stmtBuscar = $this->db->prepare("
                SELECT a.id, a.tipo_cobranca, a.gateway_preference_id, a.status_id,
                       a.matricula_id,
                       s.codigo as status_atual
                FROM assinaturas a
                LEFT JOIN assinatura_status s ON s.id = a.status_id
                WHERE a.matricula_id = ? 
                   OR (a.gateway_preference_id = ? AND ? IS NOT NULL)
                LIMIT 1
            ");
            $stmtBuscar->execute([$matriculaId, $preferenceId, $preferenceId]);
            $assinatura = $stmtBuscar->fetch(\PDO::FETCH_ASSOC);
            
            if (!$assinatura) {
                error_log("[Webhook MP] ⚠️ Nenhuma assinatura encontrada para matrícula #{$matriculaId} ou preference_id={$preferenceId}");
                
                // Debug: listar todas as assinaturas para ver o que existe
                $stmtDebug = $this->db->prepare("SELECT id, matricula_id, gateway_preference_id, tipo_cobranca FROM assinaturas ORDER BY id DESC LIMIT 5");
                $stmtDebug->execute();
                $assinaturasDebug = $stmtDebug->fetchAll(\PDO::FETCH_ASSOC);
                error_log("[Webhook MP] 📋 Últimas assinaturas no banco: " . json_encode($assinaturasDebug));
                return;
            }
            
            error_log("[Webhook MP] 📋 Assinatura encontrada: ID={$assinatura['id']}, matricula_id={$assinatura['matricula_id']}, tipo={$assinatura['tipo_cobranca']}, status_atual={$assinatura['status_atual']}");
            
            // Se já está ativa/paga, não atualizar
            if (in_array($assinatura['status_atual'], ['ativa', 'paga'])) {
                error_log("[Webhook MP] ℹ️ Assinatura #{$assinatura['id']} já está {$assinatura['status_atual']}, ignorando");
                return;
            }
            
            // Buscar ID do status 'ativa' ou 'paga'
            // Para avulso, usar 'paga'. Para recorrente, usar 'ativa'
            $statusCodigo = $assinatura['tipo_cobranca'] === 'avulso' ? 'paga' : 'ativa';
            $stmtStatus = $this->db->prepare("SELECT id FROM assinatura_status WHERE codigo = ?");
            $stmtStatus->execute([$statusCodigo]);
            $statusId = $stmtStatus->fetchColumn();
            
            // Se não existe status 'paga', usar 'ativa'
            if (!$statusId) {
                $stmtStatus->execute(['ativa']);
                $statusId = $stmtStatus->fetchColumn() ?: 2; // fallback para ID 2
            }
            
            // Buscar método de pagamento
            $metodoPagamento = $pagamento['payment_method_id'] ?? 'unknown';
            $stmtMetodo = $this->db->prepare("SELECT id FROM metodos_pagamento WHERE codigo = ?");
            $stmtMetodo->execute([$metodoPagamento]);
            $metodoPagamentoId = $stmtMetodo->fetchColumn();
            
            // Atualizar assinatura
            $stmtUpdate = $this->db->prepare("
                UPDATE assinaturas
                SET status_id = ?,
                    status_gateway = 'approved',
                    metodo_pagamento_id = COALESCE(?, metodo_pagamento_id),
                    ultima_cobranca = ?,
                    atualizado_em = NOW()
                WHERE id = ?
            ");
            
            $stmtUpdate->execute([
                $statusId,
                $metodoPagamentoId,
                $pagamento['date_approved'] ?? date('Y-m-d'),
                $assinatura['id']
            ]);
            
            if ($stmtUpdate->rowCount() > 0) {
                error_log("[Webhook MP] ✅ Assinatura #{$assinatura['id']} atualizada para status '{$statusCodigo}'");
            }
            
        } catch (\Exception $e) {
            error_log("[Webhook MP] ⚠️ Erro ao atualizar assinatura avulsa: " . $e->getMessage());
            // Não lança exceção para não interromper o fluxo do webhook
        }
    }

    /**
     * Atualizar assinatura avulsa para cancelada após estorno/cancelamento
     */
    private function atualizarAssinaturaAvulsaCancelada(int $matriculaId, array $pagamento): void
    {
        try {
            error_log("[Webhook MP] 🔍 Buscando assinatura (cancelamento) para matrícula #{$matriculaId}...");
            
            $preferenceId = $pagamento['preference_id'] ?? null;
            $stmtBuscar = $this->db->prepare("
                SELECT a.id, a.tipo_cobranca, a.gateway_preference_id, a.status_id,
                       a.matricula_id,
                       s.codigo as status_atual
                FROM assinaturas a
                LEFT JOIN assinatura_status s ON s.id = a.status_id
                WHERE a.matricula_id = ? 
                   OR (a.gateway_preference_id = ? AND ? IS NOT NULL)
                LIMIT 1
            ");
            $stmtBuscar->execute([$matriculaId, $preferenceId, $preferenceId]);
            $assinatura = $stmtBuscar->fetch(\PDO::FETCH_ASSOC);
            
            if (!$assinatura) {
                error_log("[Webhook MP] ⚠️ Nenhuma assinatura encontrada para cancelamento (matrícula #{$matriculaId})");
                return;
            }
            
            // Só aplicar para avulso
            if (($assinatura['tipo_cobranca'] ?? '') !== 'avulso') {
                error_log("[Webhook MP] ℹ️ Assinatura #{$assinatura['id']} não é avulsa, ignorando cancelamento");
                return;
            }
            
            // Buscar ID do status 'cancelada'
            $stmtStatus = $this->db->prepare("SELECT id FROM assinatura_status WHERE codigo = 'cancelada'");
            $stmtStatus->execute();
            $statusId = $stmtStatus->fetchColumn() ?: null;
            
            if (!$statusId) {
                error_log("[Webhook MP] ⚠️ Status 'cancelada' não encontrado, abortando");
                return;
            }
            
            $stmtUpdate = $this->db->prepare("
                UPDATE assinaturas
                SET status_id = ?,
                    status_gateway = ?,
                    atualizado_em = NOW()
                WHERE id = ?
            ");
            $stmtUpdate->execute([
                $statusId,
                $pagamento['status'] ?? 'cancelled',
                $assinatura['id']
            ]);
            
            if ($stmtUpdate->rowCount() > 0) {
                error_log("[Webhook MP] ✅ Assinatura #{$assinatura['id']} cancelada após estorno/cancelamento");
            }
        } catch (\Exception $e) {
            error_log("[Webhook MP] ⚠️ Erro ao cancelar assinatura avulsa: " . $e->getMessage());
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
     * Cancelar matrícula após estorno/cancelamento
     */
    private function cancelarMatricula(int $matriculaId): void
    {
        $stmtStatus = $this->db->prepare("SELECT id FROM status_matricula WHERE codigo = 'cancelada' LIMIT 1");
        $stmtStatus->execute();
        $statusCanceladaId = $stmtStatus->fetchColumn();
        
        if (!$statusCanceladaId) {
            error_log("[Webhook MP] ⚠️ Status 'cancelada' não encontrado para matrícula");
            return;
        }
        
        $stmtUpdate = $this->db->prepare("
            UPDATE matriculas
            SET status_id = ?,
                updated_at = NOW()
            WHERE id = ?
            AND status_id IN (
                SELECT id FROM status_matricula WHERE codigo IN ('ativa', 'pendente', 'vencida')
            )
        ");
        $stmtUpdate->execute([$statusCanceladaId, $matriculaId]);
        
        if ($stmtUpdate->rowCount() > 0) {
            error_log("[Webhook MP] ✅ Matrícula #{$matriculaId} cancelada após estorno/cancelamento");
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
            
            // Buscar o pagamento pendente mais antigo da matrícula
            $stmtBuscar = $this->db->prepare("
                SELECT pp.id, pp.valor
                FROM pagamentos_plano pp
                INNER JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
                WHERE pp.matricula_id = ?
                AND sp.codigo IN ('pendente', 'aguardando')
                ORDER BY pp.data_vencimento ASC
                LIMIT 1
            ");
            $stmtBuscar->execute([$matriculaId]);
            $pagamentoPendente = $stmtBuscar->fetch(\PDO::FETCH_ASSOC);
            
            // Verificar se já existe pagamento pago hoje para evitar duplicatas (webhook duplicado)
            $stmtDuplicata = $this->db->prepare("
                SELECT pp.id FROM pagamentos_plano pp
                INNER JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
                WHERE pp.matricula_id = ? AND sp.codigo = 'pago' AND DATE(pp.data_pagamento) = CURDATE()
                LIMIT 1
            ");
            $stmtDuplicata->execute([$matriculaId]);
            if ($stmtDuplicata->fetch()) {
                error_log("[Webhook MP] ⚠️ Pagamento já processado hoje para matrícula #{$matriculaId}, ignorando duplicata");
                return;
            }
            
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

    /**
     * Registrar mudança em matrícula no histórico
     *
     * @param int $tenantId
     * @param int $matriculaId
     * @param int $alunoId
     * @param string $tipoOperacao INSERT ou UPDATE
     * @param ?array $dadosAntigos Dados anteriores (null para INSERT)
     * @param array $dadosNovos Dados novos após a operação
     * @param string $motivo Motivo da mudança
     */
    private function registrarHistoricoMatricula(
        int $tenantId,
        int $matriculaId,
        int $alunoId,
        string $tipoOperacao,
        ?array $dadosAntigos,
        array $dadosNovos,
        string $motivo
    ): void {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO matriculas_historico
                (tenant_id, matricula_id, aluno_id, tipo_operacao, dados_anteriores, dados_novos, motivo, criado_em)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $tenantId,
                $matriculaId,
                $alunoId,
                $tipoOperacao,
                $dadosAntigos ? json_encode($dadosAntigos, JSON_UNESCAPED_UNICODE) : null,
                json_encode($dadosNovos, JSON_UNESCAPED_UNICODE),
                $motivo
            ]);
            
            error_log("[Webhook MP] 📝 Histórico registrado para matrícula #{$matriculaId}: {$tipoOperacao} ({$motivo})");
        } catch (\Exception $e) {
            error_log("[Webhook MP] ⚠️ Erro ao registrar histórico da matrícula #{$matriculaId}: " . $e->getMessage());
        }
    }
}
