<?php
/**
 * Novo webhook controller usando SDK oficial do Mercado Pago
 * 
 * Substitui a lógica atual por uma baseada no SDK oficial
 */

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\MercadoPagoService;
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
            if (in_array($type, ['payment', 'authorized_payment', 'subscription_authorized_payment'], true)) {
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
            
            $response->getBody()->write(json_encode(['success' => true]));
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
                
        } catch (\Exception $e) {
            $this->log("❌ ERRO: " . $e->getMessage());
            $this->log("Stack: " . $e->getTraceAsString());

            $type = $body['type'] ?? 'unknown';
            $dataId = isset($body['data']['id']) ? (string)$body['data']['id'] : null;
            if (is_array($body)) {
                $this->salvarWebhook($body, $type, $dataId, 'erro');
            }
            
            $response->getBody()->write(json_encode(['success' => false, 'error' => $e->getMessage()]));
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
        }
    }
    
    /**
     * Processar pagamento usando SDK
     */
    private function processarPayment(string $paymentId): void
    {
        $this->log("📋 Processando PAYMENT: {$paymentId}");
        
        try {
            $payment = null;

            try {
                $client = new PaymentClient();
                $paymentRaw = $client->get($paymentId);
                $payment = $this->normalizarRecursoMercadoPago($paymentRaw);
            } catch (\Exception $sdkException) {
                $this->log("⚠️ SDK PaymentClient falhou para {$paymentId}: " . $sdkException->getMessage());
                $this->log("🔁 Tentando fallback via MercadoPagoService...");

                $legacyService = new MercadoPagoService();
                $payment = $legacyService->buscarPagamento($paymentId);
            }
            
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
            $preapprovalRaw = $client->get($preapprovalId);
            $preapproval = $this->normalizarRecursoMercadoPago($preapprovalRaw);
            
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
            if ($type === 'PAC' && $id) {
                $paymentStatus = strtolower((string)($payment['status'] ?? ''));
                if (in_array($paymentStatus, ['approved', 'authorized'], true)) {
                    $this->ativarPacoteContrato((int)$id);
                } else {
                    $this->log("ℹ️  Webhook PAC recebido com status {$paymentStatus}, sem ativação");
                }
                return;
            }

            $this->log("⚠️  Tipo desconhecido ou ID inválido: {$type}");
            return;
        }
        
        // Buscar matrícula
        $sql = "
            SELECT m.id, m.tenant_id, m.aluno_id, m.plano_id, m.valor, m.proxima_data_vencimento
            FROM matriculas m
            WHERE m.id = ?
            LIMIT 1
        ";
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

        // Persistir rastreabilidade em pagamentos_mercadopago
        $this->sincronizarPagamentoMercadoPago($payment, $matricula);

        $paymentStatus = strtolower((string)($payment['status'] ?? ''));
        $isAprovado = in_array($paymentStatus, ['approved', 'authorized'], true);
        $statusPagamentoId = $isAprovado ? 2 : 1;
        $dataPagamento = $isAprovado
            ? date('Y-m-d H:i:s', strtotime((string)($payment['date_approved'] ?? 'now')))
            : null;
        $observacao = "Webhook MP - Payment ID: " . ($payment['id'] ?? 'N/A') . " - Status: " . ($paymentStatus ?: 'desconhecido');

        // Primeiro tenta baixar um pagamento pendente existente da matrícula
        if ($isAprovado) {
            $sql_update = "
                UPDATE pagamentos_plano
                SET status_pagamento_id = 2,
                    data_pagamento = COALESCE(data_pagamento, ?),
                    observacoes = ?,
                    updated_at = NOW()
                WHERE tenant_id = ?
                  AND matricula_id = ?
                                    AND status_pagamento_id = 1
                                    AND data_pagamento IS NULL
                                ORDER BY data_vencimento ASC, id ASC
                LIMIT 1
            ";

            $stmt_update = $this->db->prepare($sql_update);
            $stmt_update->bind_param(
                "ssii",
                $dataPagamento,
                $observacao,
                $matricula['tenant_id'],
                $matricula['id']
            );

            if ($stmt_update->execute() && $stmt_update->affected_rows > 0) {
                $this->log("✅ Pagamento pendente baixado como PAGO via webhook.");
                $this->ativarMatricula((int)$matricula['id']);
                $this->atualizarAssinaturaAvulsaPorPagamento((int)$matricula['id'], $payment);
                return;
            }
        }

        // Se não havia pendente para atualizar, cria um registro com o status do webhook
        $this->log("💾 Criando pagamento_plano a partir do webhook...");

        $valorPagamento = isset($payment['transaction_amount'])
            ? (float)$payment['transaction_amount']
            : (float)($matricula['valor'] ?? 0);
        $dataVencimento = $matricula['proxima_data_vencimento'] ?? date('Y-m-d');

        $sql_insert = "
            INSERT INTO pagamentos_plano (
                tenant_id, aluno_id, matricula_id, plano_id, valor, data_vencimento,
                data_pagamento, status_pagamento_id, observacoes, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ";

        $stmt_insert = $this->db->prepare($sql_insert);
        $stmt_insert->bind_param(
            "iiiidssis",
            $matricula['tenant_id'],
            $matricula['aluno_id'],
            $matricula['id'],
            $matricula['plano_id'],
            $valorPagamento,
            $dataVencimento,
            $dataPagamento,
            $statusPagamentoId,
            $observacao
        );

        if ($stmt_insert->execute()) {
            $this->log("✅ Pagamento registrado via webhook com status_id={$statusPagamentoId}");
            if ($isAprovado) {
                $this->ativarMatricula((int)$matricula['id']);
                $this->atualizarAssinaturaAvulsaPorPagamento((int)$matricula['id'], $payment);
            } elseif (in_array($paymentStatus, ['cancelled', 'refunded', 'charged_back'], true)) {
                $this->cancelarMatricula((int)$matricula['id']);
                $this->cancelarAssinaturaAvulsaPorPagamento((int)$matricula['id'], $paymentStatus);
            }
        } else {
            $this->log("❌ Erro ao registrar pagamento via webhook: " . $stmt_insert->error);
        }
    }

    private function sincronizarPagamentoMercadoPago(array $payment, array $matricula): void
    {
        try {
            $paymentId = (string)($payment['id'] ?? '');
            if ($paymentId === '') {
                return;
            }

            $metadata = is_array($payment['metadata'] ?? null) ? $payment['metadata'] : [];

            $sqlBusca = "SELECT id FROM pagamentos_mercadopago WHERE payment_id = ? LIMIT 1";
            $stmtBusca = $this->db->prepare($sqlBusca);
            $stmtBusca->bind_param("s", $paymentId);
            $stmtBusca->execute();
            $resBusca = $stmtBusca->get_result();
            $existe = $resBusca ? $resBusca->fetch_assoc() : null;

            $status = (string)($payment['status'] ?? '');
            $statusDetail = (string)($payment['status_detail'] ?? '');
            $transactionAmount = (float)($payment['transaction_amount'] ?? 0);
            $paymentMethod = (string)($payment['payment_method_id'] ?? '');
            $paymentType = (string)($payment['payment_type_id'] ?? '');
            $installments = (int)($payment['installments'] ?? 0);
            $dateApproved = !empty($payment['date_approved']) ? date('Y-m-d H:i:s', strtotime((string)$payment['date_approved'])) : null;
            $dateCreated = !empty($payment['date_created']) ? date('Y-m-d H:i:s', strtotime((string)$payment['date_created'])) : date('Y-m-d H:i:s');
            $externalReference = (string)($payment['external_reference'] ?? '');

            if ($existe) {
                $sqlUpdate = "
                    UPDATE pagamentos_mercadopago
                    SET status = ?,
                        status_detail = ?,
                        transaction_amount = ?,
                        payment_method_id = ?,
                        installments = ?,
                        date_approved = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ";
                $stmtUpdate = $this->db->prepare($sqlUpdate);
                $stmtUpdate->bind_param(
                    "ssdsisi",
                    $status,
                    $statusDetail,
                    $transactionAmount,
                    $paymentMethod,
                    $installments,
                    $dateApproved,
                    $existe['id']
                );
                $stmtUpdate->execute();
                return;
            }

            $tenantId = (int)($matricula['tenant_id'] ?? 0);
            $matriculaId = (int)($matricula['id'] ?? 0);
            $alunoId = isset($metadata['aluno_id']) ? (int)$metadata['aluno_id'] : (int)($matricula['aluno_id'] ?? 0);
            $usuarioId = isset($metadata['usuario_id']) ? (int)$metadata['usuario_id'] : null;

            $sqlInsert = "
                INSERT INTO pagamentos_mercadopago (
                    tenant_id, matricula_id, aluno_id, usuario_id,
                    payment_id, external_reference, status, status_detail,
                    transaction_amount, payment_method_id, payment_type_id,
                    installments, date_approved, date_created, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ";
            $stmtInsert = $this->db->prepare($sqlInsert);
            $stmtInsert->bind_param(
                "iiiissssdsisss",
                $tenantId,
                $matriculaId,
                $alunoId,
                $usuarioId,
                $paymentId,
                $externalReference,
                $status,
                $statusDetail,
                $transactionAmount,
                $paymentMethod,
                $paymentType,
                $installments,
                $dateApproved,
                $dateCreated
            );
            $stmtInsert->execute();
        } catch (\Exception $e) {
            $this->log("⚠️ Erro ao sincronizar pagamentos_mercadopago: " . $e->getMessage());
        }
    }

    private function ativarMatricula(int $matriculaId): void
    {
        try {
            $sql = "SELECT id, status_id, plano_id, plano_ciclo_id FROM matriculas WHERE id = ? LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("i", $matriculaId);
            $stmt->execute();
            $res = $stmt->get_result();
            $mat = $res ? $res->fetch_assoc() : null;
            if (!$mat) {
                return;
            }

            $stmtStatusAtiva = $this->db->prepare("SELECT id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1");
            $stmtStatusAtiva->execute();
            $resStatusAtiva = $stmtStatusAtiva->get_result();
            $rowStatusAtiva = $resStatusAtiva ? $resStatusAtiva->fetch_assoc() : null;
            $statusAtivaId = (int)($rowStatusAtiva['id'] ?? 1);

            if ((int)$mat['status_id'] === $statusAtivaId) {
                return;
            }

            $sqlDuracao = "
                SELECT p.duracao_dias, pc.meses
                FROM matriculas m
                INNER JOIN planos p ON p.id = m.plano_id
                LEFT JOIN plano_ciclos pc ON pc.id = m.plano_ciclo_id
                WHERE m.id = ?
                LIMIT 1
            ";
            $stmtDuracao = $this->db->prepare($sqlDuracao);
            $stmtDuracao->bind_param("i", $matriculaId);
            $stmtDuracao->execute();
            $resDuracao = $stmtDuracao->get_result();
            $duracao = $resDuracao ? $resDuracao->fetch_assoc() : null;

            $hoje = new \DateTimeImmutable(date('Y-m-d'));
            $meses = (int)($duracao['meses'] ?? 0);
            if ($meses > 0) {
                $vencimento = $hoje->modify("+{$meses} months")->format('Y-m-d');
            } else {
                $dias = max(1, (int)($duracao['duracao_dias'] ?? 30));
                $vencimento = $hoje->modify("+{$dias} days")->format('Y-m-d');
            }

            $dataInicio = $hoje->format('Y-m-d');
            $sqlUpdate = "
                UPDATE matriculas
                SET status_id = ?,
                    data_inicio = ?,
                    data_vencimento = ?,
                    proxima_data_vencimento = ?,
                    updated_at = NOW()
                WHERE id = ?
            ";
            $stmtUpdate = $this->db->prepare($sqlUpdate);
            $stmtUpdate->bind_param("isssi", $statusAtivaId, $dataInicio, $vencimento, $vencimento, $matriculaId);
            $stmtUpdate->execute();
        } catch (\Exception $e) {
            $this->log("⚠️ Erro ao ativar matrícula: " . $e->getMessage());
        }
    }

    private function cancelarMatricula(int $matriculaId): void
    {
        try {
            $stmtStatus = $this->db->prepare("SELECT id FROM status_matricula WHERE codigo = 'cancelada' LIMIT 1");
            $stmtStatus->execute();
            $resStatus = $stmtStatus->get_result();
            $rowStatus = $resStatus ? $resStatus->fetch_assoc() : null;
            $statusCanceladaId = (int)($rowStatus['id'] ?? 0);
            if ($statusCanceladaId <= 0) {
                return;
            }

            $sql = "
                UPDATE matriculas
                SET status_id = ?, updated_at = NOW()
                WHERE id = ?
                  AND status_id IN (
                    SELECT id FROM status_matricula WHERE codigo IN ('ativa', 'pendente', 'vencida')
                  )
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("ii", $statusCanceladaId, $matriculaId);
            $stmt->execute();
        } catch (\Exception $e) {
            $this->log("⚠️ Erro ao cancelar matrícula: " . $e->getMessage());
        }
    }

    private function atualizarAssinaturaAvulsaPorPagamento(int $matriculaId, array $payment): void
    {
        try {
            $sqlBusca = "
                SELECT a.id, a.tipo_cobranca
                FROM assinaturas a
                WHERE a.matricula_id = ?
                ORDER BY a.id DESC
                LIMIT 1
            ";
            $stmtBusca = $this->db->prepare($sqlBusca);
            $stmtBusca->bind_param("i", $matriculaId);
            $stmtBusca->execute();
            $resBusca = $stmtBusca->get_result();
            $assinatura = $resBusca ? $resBusca->fetch_assoc() : null;
            if (!$assinatura) {
                return;
            }

            $statusCodigo = ($assinatura['tipo_cobranca'] ?? '') === 'avulso' ? 'paga' : 'ativa';
            $stmtStatus = $this->db->prepare("SELECT id FROM assinatura_status WHERE codigo = ? LIMIT 1");
            $stmtStatus->bind_param("s", $statusCodigo);
            $stmtStatus->execute();
            $resStatus = $stmtStatus->get_result();
            $rowStatus = $resStatus ? $resStatus->fetch_assoc() : null;
            $statusId = (int)($rowStatus['id'] ?? 0);
            if ($statusId <= 0) {
                $fallback = 'ativa';
                $stmtStatus2 = $this->db->prepare("SELECT id FROM assinatura_status WHERE codigo = ? LIMIT 1");
                $stmtStatus2->bind_param("s", $fallback);
                $stmtStatus2->execute();
                $resStatus2 = $stmtStatus2->get_result();
                $rowStatus2 = $resStatus2 ? $resStatus2->fetch_assoc() : null;
                $statusId = (int)($rowStatus2['id'] ?? 1);
            }

            $dateApproved = !empty($payment['date_approved']) ? date('Y-m-d H:i:s', strtotime((string)$payment['date_approved'])) : date('Y-m-d H:i:s');
            $sqlUpdate = "
                UPDATE assinaturas
                SET status_id = ?,
                    status_gateway = 'approved',
                    ultima_cobranca = ?,
                    atualizado_em = NOW()
                WHERE id = ?
            ";
            $stmtUpdate = $this->db->prepare($sqlUpdate);
            $stmtUpdate->bind_param("isi", $statusId, $dateApproved, $assinatura['id']);
            $stmtUpdate->execute();
        } catch (\Exception $e) {
            $this->log("⚠️ Erro ao atualizar assinatura por pagamento: " . $e->getMessage());
        }
    }

    private function cancelarAssinaturaAvulsaPorPagamento(int $matriculaId, string $gatewayStatus): void
    {
        try {
            $sqlBusca = "
                SELECT a.id, a.tipo_cobranca
                FROM assinaturas a
                WHERE a.matricula_id = ?
                ORDER BY a.id DESC
                LIMIT 1
            ";
            $stmtBusca = $this->db->prepare($sqlBusca);
            $stmtBusca->bind_param("i", $matriculaId);
            $stmtBusca->execute();
            $resBusca = $stmtBusca->get_result();
            $assinatura = $resBusca ? $resBusca->fetch_assoc() : null;
            if (!$assinatura || (($assinatura['tipo_cobranca'] ?? '') !== 'avulso')) {
                return;
            }

            $cancelada = 'cancelada';
            $stmtStatus = $this->db->prepare("SELECT id FROM assinatura_status WHERE codigo = ? LIMIT 1");
            $stmtStatus->bind_param("s", $cancelada);
            $stmtStatus->execute();
            $resStatus = $stmtStatus->get_result();
            $rowStatus = $resStatus ? $resStatus->fetch_assoc() : null;
            $statusId = (int)($rowStatus['id'] ?? 0);
            if ($statusId <= 0) {
                return;
            }

            $sqlUpdate = "
                UPDATE assinaturas
                SET status_id = ?,
                    status_gateway = ?,
                    atualizado_em = NOW()
                WHERE id = ?
            ";
            $stmtUpdate = $this->db->prepare($sqlUpdate);
            $stmtUpdate->bind_param("isi", $statusId, $gatewayStatus, $assinatura['id']);
            $stmtUpdate->execute();
        } catch (\Exception $e) {
            $this->log("⚠️ Erro ao cancelar assinatura por pagamento: " . $e->getMessage());
        }
    }

    private function ativarPacoteContrato(int $contratoId): void
    {
        try {
            $sql = "UPDATE pacote_contratos SET status = 'ativo' WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("i", $contratoId);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $this->log("✅ Contrato de pacote #{$contratoId} ativado via V2");
            } else {
                $this->log("ℹ️ Contrato de pacote #{$contratoId} não alterado (já ativo ou não encontrado)");
            }
        } catch (\Exception $e) {
            $this->log("⚠️ Erro ao ativar pacote #{$contratoId}: " . $e->getMessage());
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
        
        // Atualizar status_gateway e status_id na assinatura (fonte: webhook)
        $status = strtolower((string)($preapproval['status'] ?? 'unknown'));

        $statusCodigo = match ($status) {
            'approved', 'authorized' => 'ativa',
            'pending' => 'pendente',
            'paused' => 'pausada',
            'cancelled', 'cancelled_by_user', 'refunded', 'charged_back' => 'cancelada',
            default => null,
        };

        $statusId = null;
        if ($statusCodigo !== null) {
            $sqlStatus = "SELECT id FROM assinatura_status WHERE codigo = ? LIMIT 1";
            $stmtStatus = $this->db->prepare($sqlStatus);
            $stmtStatus->bind_param("s", $statusCodigo);
            if ($stmtStatus->execute()) {
                $resStatus = $stmtStatus->get_result();
                $statusRow = $resStatus ? $resStatus->fetch_assoc() : null;
                $statusId = $statusRow ? (int)$statusRow['id'] : null;
            }
        }

        if ($statusId !== null) {
            $sql = "UPDATE assinaturas SET status_gateway = ?, status_id = ?, atualizado_em = NOW() WHERE matricula_id = ? LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("sii", $status, $statusId, $id);
        } else {
            $sql = "UPDATE assinaturas SET status_gateway = ?, atualizado_em = NOW() WHERE matricula_id = ? LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("si", $status, $id);
        }

        if ($stmt->execute()) {
            $this->log("✅ Assinatura atualizada: status_gateway = {$status}" . ($statusCodigo ? ", status = {$statusCodigo}" : ""));
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
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'external_reference é obrigatório'
                ]));
                return $response
                    ->withStatus(400)
                    ->withHeader('Content-Type', 'application/json');
            }
            
            $this->log("=== VALIDAÇÃO FORÇADA DE ASSINATURA ===");
            $this->log("External Ref: {$externalRef}");
            
            // Extrair tipo e ID
            $parts = explode('-', $externalRef);
            if (count($parts) < 2) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Formato inválido: MAT-{id}-{timestamp}'
                ]));
                return $response
                    ->withStatus(400)
                    ->withHeader('Content-Type', 'application/json');
            }
            
            $type = $parts[0];
            $id = (int)$parts[1];
            
            if ($type !== 'MAT') {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Apenas MAT (matrícula) é suportado'
                ]));
                return $response
                    ->withStatus(400)
                    ->withHeader('Content-Type', 'application/json');
            }
            
            // Buscar matrícula
            $sql = "SELECT id, tenant_id FROM matriculas WHERE id = ? LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if (!$result || $result->num_rows === 0) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => "Matrícula {$id} não encontrada"
                ]));
                return $response
                    ->withStatus(404)
                    ->withHeader('Content-Type', 'application/json');
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
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Nenhuma assinatura encontrada para esta matrícula'
                ]));
                return $response
                    ->withStatus(404)
                    ->withHeader('Content-Type', 'application/json');
            }
            
            $assinatura = $result_ass->fetch_assoc();
            $this->log("✅ Assinatura encontrada: {$assinatura['id']}, Status: {$assinatura['status_gateway']}");
            
            // Se já está approved, não faz nada
            if ($assinatura['status_gateway'] === 'approved') {
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'message' => 'Assinatura já está em status approved',
                    'status' => 'approved'
                ]));
                return $response
                    ->withStatus(200)
                    ->withHeader('Content-Type', 'application/json');
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
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Erro ao atualizar assinatura no banco'
                ]));
                return $response
                    ->withStatus(500)
                    ->withHeader('Content-Type', 'application/json');
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
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Assinatura validada e recuperada com sucesso',
                'matricula_id' => (int)$id,
                'assinatura_id' => (int)$assinatura['id'],
                'status' => 'approved'
            ]));
            return $response
                ->withStatus(200)
                ->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $this->log("❌ ERRO: " . $e->getMessage());
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
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

    /**
     * Normaliza recursos do SDK do Mercado Pago para array associativo
     */
    private function normalizarRecursoMercadoPago($resource): array
    {
        if (is_array($resource)) {
            return $resource;
        }

        if (is_object($resource)) {
            $json = json_encode($resource);
            if ($json !== false) {
                $decoded = json_decode($json, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        return [];
    }
}
