<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\MercadoPagoService;
use OpenApi\Attributes as OA;

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
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            $raw = (string) $request->getBody();
            $decoded = json_decode($raw, true);
            $body = is_array($decoded) ? $decoded : [];
        }

        $queryParams = $request->getQueryParams();

        $type = $body['type']
            ?? $body['topic']
            ?? $queryParams['type']
            ?? $queryParams['topic']
            ?? null;

        $dataIdRaw = $body['data']['id']
            ?? $body['id']
            ?? $queryParams['id']
            ?? null;

        $dataId = null;
        if ($dataIdRaw !== null && $dataIdRaw !== '') {
            $dataIdString = (string) $dataIdRaw;
            if (preg_match('/\/(\d+)(?:\?.*)?$/', $dataIdString, $matches)) {
                $dataId = (int) $matches[1];
            } elseif (ctype_digit($dataIdString)) {
                $dataId = (int) $dataIdString;
            }
        }

        if ($type === null || $dataId === null) {
            $this->logWebhook("[Webhook MP V1] Notificação inválida: faltando type/topic ou id/data.id");
            $this->salvarWebhookPayload($body, (string)($type ?? 'unknown'), null, 'erro', 'Notificação inválida');

            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Notificação inválida'
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }

        $normalizedBody = $body;
        $normalizedBody['type'] = $type;
        $normalizedBody['data']['id'] = $dataId;

        try {
            $this->logWebhook("[Webhook MP V1] Processando type={$type}, id={$dataId}");

            // Primeiro, buscar pagamento com credenciais padrão (ENV) para obter external_reference
            $mercadoPagoService = $this->getMercadoPagoService();
            
            // Variáveis para enriquecer o webhook payload
            $webhookTenantId = null;
            $webhookExternalRef = null;

            if (in_array($type, ['payment', 'authorized_payment', 'subscription_authorized_payment'], true)) {
                $pagamento = $mercadoPagoService->buscarPagamento((string)$dataId);
                
                // Extrair tenant_id da matrícula via external_reference
                $extRef = $pagamento['external_reference'] ?? '';
                $webhookExternalRef = $extRef;
                $matIdForTenant = null;
                
                if (preg_match('/MAT-(\d+)/', $extRef, $mTenant)) {
                    $matIdForTenant = (int) $mTenant[1];
                } elseif (preg_match('/PAC-(\d+)/', $extRef, $mTenant)) {
                    // Para pacotes, buscar tenant do contrato
                    $stmtPacTenant = $this->db->prepare("SELECT tenant_id FROM pacote_contratos WHERE id = ? LIMIT 1");
                    $stmtPacTenant->execute([(int) $mTenant[1]]);
                    $webhookTenantId = $stmtPacTenant->fetchColumn() ?: null;
                }
                
                if ($matIdForTenant && !$webhookTenantId) {
                    $stmtTenant = $this->db->prepare("SELECT tenant_id FROM matriculas WHERE id = ? LIMIT 1");
                    $stmtTenant->execute([$matIdForTenant]);
                    $webhookTenantId = $stmtTenant->fetchColumn() ?: null;
                }
                
                // Re-instanciar com tenant correto para garantir credenciais certas
                if ($webhookTenantId) {
                    $mercadoPagoService = $this->getMercadoPagoService((int)$webhookTenantId);
                    // Re-buscar pagamento com credenciais do tenant (pode ser necessário)
                    try {
                        $pagamento = $mercadoPagoService->buscarPagamento((string)$dataId);
                    } catch (\Throwable $e2) {
                        $this->logWebhook("[Webhook MP V1] Aviso: falha ao re-buscar com tenant {$webhookTenantId}, usando dados anteriores: " . $e2->getMessage());
                    }
                }
                
                $this->atualizarPagamento($pagamento);
            } elseif (in_array($type, ['subscription_preapproval', 'subscription', 'preapproval'], true)) {
                $assinatura = $mercadoPagoService->buscarAssinatura((string)$dataId);
                $webhookExternalRef = $assinatura['external_reference'] ?? null;
                $this->atualizarAssinatura($assinatura);
            } else {
                $this->logWebhook("[Webhook MP V1] Tipo ignorado: {$type}");
            }

            $this->salvarWebhookPayload($normalizedBody, $type, $dataId, 'sucesso', null, null, $webhookTenantId, $webhookExternalRef);

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Webhook processado com sucesso (V1)'
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);
        } catch (\Throwable $e) {
            $this->logWebhook("[Webhook MP V1] Erro ao processar webhook: " . $e->getMessage());
            $this->salvarWebhookPayload($normalizedBody, $type, $dataId, 'erro', $e->getMessage(), null, $webhookTenantId ?? null, $webhookExternalRef ?? null);

            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);
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
        ?array $resultadoProcessamento = null,
        ?int $resolvedTenantId = null,
        ?string $resolvedExternalReference = null
    ): void
    {
        try {
            // Usar dados já resolvidos pelo processarWebhook (se disponíveis)
            $externalReference = $resolvedExternalReference;
            $paymentId = null;
            $preapprovalId = null;
            $tenantId = $resolvedTenantId;
            
            // Se é notificação de pagamento
            if ($tipo === 'payment') {
                $paymentId = $dataId;
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
    public function mostrarWebhook(Request $request, Response $response, array $args): Response
    {
        try {
            $id = (int)($args['id'] ?? 0);
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
    public function reprocessarWebhook(Request $request, Response $response, array $args): Response
    {
        try {
            $id = (int)($args['id'] ?? 0);
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
            
            // Determinar tenant_id a partir do webhook salvo ou do request
            $reprocessTenantId = $request->getAttribute('tenantId');
            if (!$reprocessTenantId) {
                $stmtReprTenant = $this->db->prepare("SELECT tenant_id FROM webhook_payloads_mercadopago WHERE id = ? LIMIT 1");
                $stmtReprTenant->execute([$id]);
                $reprocessTenantId = $stmtReprTenant->fetchColumn() ?: null;
            }
            
            // Reprocessar
            $mercadoPagoService = $this->getMercadoPagoService($reprocessTenantId ? (int)$reprocessTenantId : null);
            if (in_array($payload['type'], ['payment', 'authorized_payment', 'subscription_authorized_payment'], true)) {
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
     * Consultar cobranças no Mercado Pago por external_reference
     * 
     * GET /api/webhooks/mercadopago/cobrancas?external_reference=MAT-158-1771524282
     */
    #[OA\Get(
        path: "/api/webhooks/mercadopago/cobrancas",
        summary: "Consultar cobranças por external_reference",
        description: "Busca pagamentos na API do Mercado Pago e em 3 tabelas locais: pagamentos_plano (mensalidades), pagamentos_mercadopago (espelho do MP) e webhook_payloads_mercadopago (auditoria de webhooks recebidos). Requer autenticação Admin.",
        tags: ["Webhook - Cobranças"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "external_reference",
                in: "query",
                description: "Referência externa do pagamento. Formato: MAT-{matricula_id}-{timestamp} ou PAC-{contrato_id}-{timestamp}",
                required: true,
                schema: new OA\Schema(type: "string", example: "MAT-158-1771524282")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Cobranças encontradas",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "external_reference", type: "string", example: "MAT-158-1771524282"),
                        new OA\Property(
                            property: "mercadopago",
                            type: "object",
                            description: "Dados vindos diretamente da API do Mercado Pago",
                            properties: [
                                new OA\Property(property: "total", type: "integer", example: 1),
                                new OA\Property(
                                    property: "pagamentos",
                                    type: "array",
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: "id", type: "integer", example: 146225815559),
                                            new OA\Property(property: "status", type: "string", example: "rejected", description: "approved, rejected, pending, refunded, cancelled, charged_back"),
                                            new OA\Property(property: "status_detail", type: "string", example: "cc_rejected_high_risk"),
                                            new OA\Property(property: "external_reference", type: "string", example: "MAT-158-1771524282"),
                                            new OA\Property(property: "transaction_amount", type: "number", format: "float", example: 70),
                                            new OA\Property(property: "currency_id", type: "string", example: "BRL"),
                                            new OA\Property(property: "payment_method_id", type: "string", example: "visa"),
                                            new OA\Property(property: "payment_type_id", type: "string", example: "credit_card"),
                                            new OA\Property(property: "installments", type: "integer", example: 1),
                                            new OA\Property(property: "date_created", type: "string", format: "date-time"),
                                            new OA\Property(property: "date_approved", type: "string", format: "date-time", nullable: true),
                                            new OA\Property(property: "date_last_updated", type: "string", format: "date-time", nullable: true),
                                            new OA\Property(
                                                property: "payer",
                                                type: "object",
                                                properties: [
                                                    new OA\Property(property: "email", type: "string", example: "aluno@email.com", nullable: true),
                                                    new OA\Property(property: "id", type: "string", example: "1870951865", nullable: true)
                                                ]
                                            ),
                                            new OA\Property(property: "metadata", type: "object")
                                        ]
                                    )
                                )
                            ]
                        ),
                        new OA\Property(
                            property: "local",
                            type: "object",
                            description: "Dados encontrados nas tabelas locais do banco de dados",
                            properties: [
                                new OA\Property(
                                    property: "pagamentos_plano",
                                    type: "array",
                                    description: "Mensalidades/parcelas (buscadas por matricula_id extraído do external_reference)",
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: "id", type: "integer"),
                                            new OA\Property(property: "matricula_id", type: "integer"),
                                            new OA\Property(property: "aluno_id", type: "integer"),
                                            new OA\Property(property: "plano_id", type: "integer"),
                                            new OA\Property(property: "valor", type: "number", format: "float"),
                                            new OA\Property(property: "status_pagamento_id", type: "integer", description: "1=Aguardando, 2=Pago, 3=Atrasado"),
                                            new OA\Property(property: "data_pagamento", type: "string", format: "date-time", nullable: true),
                                            new OA\Property(property: "data_vencimento", type: "string", format: "date"),
                                            new OA\Property(property: "forma_pagamento_id", type: "integer", nullable: true),
                                            new OA\Property(property: "tipo_baixa_id", type: "integer", nullable: true),
                                            new OA\Property(property: "observacoes", type: "string", nullable: true),
                                            new OA\Property(property: "created_at", type: "string", format: "date-time")
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: "pagamentos_mercadopago",
                                    type: "array",
                                    description: "Espelho dos pagamentos do MP (gravada para qualquer status: approved, rejected, pending, etc.)",
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: "id", type: "integer"),
                                            new OA\Property(property: "payment_id", type: "string"),
                                            new OA\Property(property: "matricula_id", type: "integer"),
                                            new OA\Property(property: "status", type: "string", example: "rejected"),
                                            new OA\Property(property: "status_detail", type: "string", example: "cc_rejected_high_risk"),
                                            new OA\Property(property: "transaction_amount", type: "number", format: "float"),
                                            new OA\Property(property: "payment_method_id", type: "string"),
                                            new OA\Property(property: "payer_email", type: "string", nullable: true),
                                            new OA\Property(property: "date_approved", type: "string", format: "date-time", nullable: true),
                                            new OA\Property(property: "created_at", type: "string", format: "date-time")
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: "webhook_payloads",
                                    type: "array",
                                    description: "Log de webhooks recebidos do MP (auditoria)",
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: "id", type: "integer"),
                                            new OA\Property(property: "tipo", type: "string", example: "payment"),
                                            new OA\Property(property: "data_id", type: "integer"),
                                            new OA\Property(property: "payment_id", type: "integer", nullable: true),
                                            new OA\Property(property: "status", type: "string", example: "sucesso"),
                                            new OA\Property(property: "erro_processamento", type: "string", nullable: true),
                                            new OA\Property(property: "created_at", type: "string", format: "date-time")
                                        ]
                                    )
                                )
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "Parâmetro external_reference ausente",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: false),
                        new OA\Property(property: "error", type: "string", example: "O parâmetro external_reference é obrigatório")
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: "Erro ao consultar cobranças",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: false),
                        new OA\Property(property: "error", type: "string")
                    ]
                )
            )
        ]
    )]
    public function consultarCobrancas(Request $request, Response $response): Response
    {
        try {
            $tenantId = $request->getAttribute('tenantId');
            $params = $request->getQueryParams();
            $externalReference = trim($params['external_reference'] ?? '');

            if (empty($externalReference)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'O parâmetro external_reference é obrigatório'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $mercadoPagoService = $this->getMercadoPagoService($tenantId);
            $resultado = $mercadoPagoService->buscarPagamentosPorExternalReference($externalReference);

            // Extrair matricula_id do external_reference (MAT-{id}-timestamp)
            $matriculaIdRef = null;
            if (preg_match('/MAT-(\d+)/', $externalReference, $mRef)) {
                $matriculaIdRef = (int) $mRef[1];
            }

            // Buscar dados locais: pagamentos_plano (mensalidades)
            // Nota: pagamentos_plano não tem external_reference, busca por matricula_id
            // Busca por tenant_id OU tenant_id NULL (para não perder registros com tenant_id faltando)
            $dadosPagamentosPlano = [];
            if ($matriculaIdRef) {
                try {
                    $stmt = $this->db->prepare("
                        SELECT pp.id, pp.tenant_id, pp.matricula_id, pp.aluno_id, pp.plano_id,
                               pp.valor, pp.status_pagamento_id,
                               pp.data_pagamento, pp.data_vencimento,
                               pp.forma_pagamento_id, pp.tipo_baixa_id,
                               pp.observacoes, pp.created_at, pp.updated_at
                        FROM pagamentos_plano pp
                        WHERE pp.matricula_id = ? AND (pp.tenant_id = ? OR pp.tenant_id IS NULL)
                        ORDER BY pp.created_at DESC
                    ");
                    $stmt->execute([$matriculaIdRef, $tenantId]);
                    $dadosPagamentosPlano = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                } catch (\Exception $e) {
                    error_log("[consultarCobrancas] Erro ao buscar pagamentos_plano: " . $e->getMessage());
                }
            }

            // Buscar dados locais: pagamentos_mercadopago (registro espelho do MP)
            $dadosPagamentosMp = [];
            try {
                $stmt2 = $this->db->prepare("
                    SELECT pm.id, pm.tenant_id, pm.matricula_id, pm.aluno_id, pm.usuario_id,
                           pm.payment_id, pm.external_reference, pm.preference_id,
                           pm.status, pm.status_detail, pm.transaction_amount,
                           pm.payment_method_id, pm.payment_type_id, pm.installments,
                           pm.date_approved, pm.date_created,
                           pm.payer_email, pm.payer_identification_type, pm.payer_identification_number,
                           pm.created_at, pm.updated_at
                    FROM pagamentos_mercadopago pm
                    WHERE pm.external_reference = ? AND pm.tenant_id = ?
                    ORDER BY pm.created_at DESC
                ");
                $stmt2->execute([$externalReference, $tenantId]);
                $dadosPagamentosMp = $stmt2->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                error_log("[consultarCobrancas] Erro ao buscar pagamentos_mercadopago: " . $e->getMessage());
            }

            // Buscar dados locais: webhook_payloads_mercadopago (auditoria de webhooks recebidos)
            $dadosWebhooks = [];
            try {
                $stmt3 = $this->db->prepare("
                    SELECT wp.id, wp.tipo, wp.data_id, wp.payment_id, wp.preapproval_id,
                           wp.status, wp.erro_processamento, wp.created_at
                    FROM webhook_payloads_mercadopago wp
                    WHERE wp.external_reference = ?
                       OR wp.payment_id IN (
                           SELECT pm2.payment_id FROM pagamentos_mercadopago pm2 WHERE pm2.external_reference = ? AND pm2.tenant_id = ?
                       )
                    ORDER BY wp.created_at DESC
                    LIMIT 20
                ");
                $stmt3->execute([$externalReference, $externalReference, $tenantId]);
                $dadosWebhooks = $stmt3->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                error_log("[consultarCobrancas] Erro ao buscar webhook_payloads: " . $e->getMessage());
            }

            $response->getBody()->write(json_encode([
                'success' => true,
                'external_reference' => $externalReference,
                'mercadopago' => $resultado,
                'local' => [
                    'pagamentos_plano' => $dadosPagamentosPlano,
                    'pagamentos_mercadopago' => $dadosPagamentosMp,
                    'webhook_payloads' => $dadosWebhooks
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            error_log("[consultarCobrancas] Erro: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Debug: Buscar pagamento direto da API do MP
     * 
     * GET /api/webhooks/mercadopago/payment/{paymentId}
     */
    public function buscarPagamentoDebug(Request $request, Response $response, array $args): Response
    {
        try {
            $paymentId = (string)($args['paymentId'] ?? '');
            $tenantId = $request->getAttribute('tenantId');
            $mercadoPagoService = $this->getMercadoPagoService($tenantId ? (int)$tenantId : null);
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
    public function reprocessarPagamento(Request $request, Response $response, array $args): Response
    {
        try {
            $paymentId = (string)($args['paymentId'] ?? '');
            error_log("[Webhook MP] 🔄 Reprocessando pagamento #{$paymentId}...");
            
            $tenantId = $request->getAttribute('tenantId');
            $mercadoPagoService = $this->getMercadoPagoService($tenantId ? (int)$tenantId : null);
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
                $debugTenantId = $request->getAttribute('tenantId');
                $mercadoPagoService = $this->getMercadoPagoService($debugTenantId ? (int)$debugTenantId : null);
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
        
        // Extrair pacote_contrato_id se for PAC- (será armazenado associado à assinatura)
        $pacoteContratoId = null;
        if (strpos($externalReference, 'PAC-') === 0) {
            if (preg_match('/PAC-(\d+)-/', $externalReference, $matches)) {
                $pacoteContratoId = (int) $matches[1];
                error_log("[Webhook MP] 🎁 Pacote detectado: pacote_contrato_id={$pacoteContratoId}");
            }
        }
        
        // Extrair matrícula do external_reference (formato: MAT-123-timestamp)
        $matriculaId = null;
        if (preg_match('/MAT-(\d+)/', $externalReference, $matches)) {
            $matriculaId = (int)$matches[1];
        }
        
        error_log("[Webhook MP] 📋 Processando assinatura");
        
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
            
            // Atualizar status da assinatura (+ pacote_contrato_id se houver)
            $sqlUpdate = "UPDATE assinaturas SET status_id = ?, status_gateway = ?, atualizado_em = NOW()";
            $paramsUpdate = [$statusId, $status];
            
            if ($pacoteContratoId) {
                $sqlUpdate .= ", pacote_contrato_id = ?";
                $paramsUpdate[] = $pacoteContratoId;
            }
            
            $sqlUpdate .= " WHERE id = ?";
            $paramsUpdate[] = $assinaturaDb['id'];
            
            $stmtUpdate = $this->db->prepare($sqlUpdate);
            $stmtUpdate->execute($paramsUpdate);
            
            error_log("[Webhook MP] ✅ Assinatura #{$assinaturaDb['id']} atualizada: {$assinaturaDb['status_atual']} -> {$statusInterno}");
            if ($pacoteContratoId) {
                error_log("[Webhook MP] ✅ Associado a pacote_contrato_id={$pacoteContratoId}");
            }
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
            $dataPagamento = !empty($assinatura['date_approved'])
                ? date('Y-m-d H:i:s', strtotime((string)$assinatura['date_approved']))
                : date('Y-m-d H:i:s');
            $assinaturaRef = (string)($assinatura['preapproval_id'] ?? 'N/A');
            
            // Buscar o pagamento pendente mais antigo da matrícula (status 1 = Aguardando)
            $stmtBuscar = $this->db->prepare("
                SELECT pp.id, pp.valor
                FROM pagamentos_plano pp
                WHERE pp.matricula_id = ?
                AND pp.status_pagamento_id IN (1, 3)
                AND pp.data_pagamento IS NULL
                ORDER BY pp.data_vencimento ASC
                LIMIT 1
            ");
            $stmtBuscar->execute([$matriculaId]);
            $pagamentoPendente = $stmtBuscar->fetch(\PDO::FETCH_ASSOC);

            if (!$pagamentoPendente) {
                $stmtJaProcessado = $this->db->prepare("
                    SELECT pp.id
                    FROM pagamentos_plano pp
                    WHERE pp.matricula_id = ?
                      AND pp.status_pagamento_id = 2
                      AND pp.observacoes LIKE ?
                    LIMIT 1
                ");
                $patternAssinatura = '%ID: ' . $assinaturaRef . '%';
                $stmtJaProcessado->execute([$matriculaId, $patternAssinatura]);
                if ($stmtJaProcessado->fetch()) {
                    error_log("[Webhook MP] ℹ️ Assinatura {$assinaturaRef} já processada para matrícula #{$matriculaId}, ignorando reprocessamento");
                    return;
                }
            }
            
            // Para assinaturas, forma de pagamento é sempre cartão de crédito (ID 9)
            $formaPagamentoId = 9;
            
            if ($pagamentoPendente) {
                // Atualizar o pagamento existente para "pago"
                error_log("[Webhook MP] Atualizando pagamento existente #{$pagamentoPendente['id']}");
                
                $stmtUpdate = $this->db->prepare("
                    UPDATE pagamentos_plano
                    SET status_pagamento_id = 2,
                        data_pagamento = ?,
                        forma_pagamento_id = ?,
                        tipo_baixa_id = 4,
                        observacoes = CONCAT(IFNULL(observacoes, ''), ' | Pago via Assinatura MP - ID: " . ($assinatura['preapproval_id'] ?? 'N/A') . "'),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmtUpdate->execute([$dataPagamento, $formaPagamentoId, $pagamentoPendente['id']]);
                
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
                        ?, CURDATE(), ?,
                        2, ?,
                        ?, 4, NOW(), NOW()
                    )
                ");
                
                $stmtInsert->execute([
                    $matricula['tenant_id'],
                    $matricula['aluno_id'],
                    $matriculaId,
                    $matricula['plano_id'],
                    $valor,
                    $dataPagamento,
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
            error_log("[Webhook MP] 🎁 PACOTE DETECTED - Processando pagamento de pacote");
            
            if ($pagamento['status'] === 'approved') {
                error_log("[Webhook MP] ✅ Pagamento de pacote APROVADO");
                
                $pacoteContratoId = $metadata['pacote_contrato_id'] ?? null;
                
                // FALLBACK: Extrair do external_reference se não estiver no metadata
                if (!$pacoteContratoId && $externalReference && preg_match('/PAC-(\d+)-/', $externalReference, $matches)) {
                    $pacoteContratoId = (int) $matches[1];
                    error_log("[Webhook MP] 🎯 pacote_contrato_id extraído do external_reference: {$pacoteContratoId}");
                }
                
                if ($pacoteContratoId) {
                    // Ativar contrato e criar matrículas para todos (pagante + beneficiários)
                    error_log("[Webhook MP] 📨 Ativando contrato e criando matrículas...");
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
            WHERE payment_id = ?
            LIMIT 1
        ");
        $stmtBuscar->execute([$pagamento['id']]);
        $pagamentoExiste = $stmtBuscar->fetch(\PDO::FETCH_ASSOC);
        
        if ($pagamentoExiste) {
            // Atualizar pagamento existente
            $stmtUpdate = $this->db->prepare("
                UPDATE pagamentos_mercadopago
                SET status = ?,
                    status_detail = ?,
                    transaction_amount = ?,
                    payment_method_id = ?,
                    payment_type_id = ?,
                    installments = ?,
                    date_approved = ?,
                    payer_email = COALESCE(?, payer_email),
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmtUpdate->execute([
                $pagamento['status'],
                $pagamento['status_detail'],
                $pagamento['transaction_amount'],
                $pagamento['payment_method_id'],
                $pagamento['payment_type_id'] ?? null,
                $pagamento['installments'],
                $pagamento['date_approved'],
                $pagamento['payer']['email'] ?? null,
                $pagamentoExiste['id']
            ]);
        } else {
            // Criar novo registro
            $stmtInsert = $this->db->prepare("
                INSERT INTO pagamentos_mercadopago (
                    tenant_id, matricula_id, aluno_id, usuario_id,
                    payment_id, external_reference, preference_id, status, status_detail,
                    transaction_amount, payment_method_id, payment_type_id,
                    installments, date_approved, date_created,
                    payer_email, payer_identification_type, payer_identification_number,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            // Buscar tenant_id e aluno_id da matrícula (metadata pode estar vazio em pagamentos avulsos)
            $tenantIdForInsert = $metadata['tenant_id'] ?? null;
            $alunoIdForInsert = $metadata['aluno_id'] ?? null;
            $usuarioIdForInsert = $metadata['usuario_id'] ?? null;
            
            if (!$tenantIdForInsert || !$alunoIdForInsert) {
                $stmtMatInfo = $this->db->prepare("SELECT tenant_id, aluno_id FROM matriculas WHERE id = ? LIMIT 1");
                $stmtMatInfo->execute([$matriculaId]);
                $matInfo = $stmtMatInfo->fetch(\PDO::FETCH_ASSOC);
                if ($matInfo) {
                    $tenantIdForInsert = $tenantIdForInsert ?: ($matInfo['tenant_id'] ?? null);
                    $alunoIdForInsert = $alunoIdForInsert ?: ($matInfo['aluno_id'] ?? null);
                }
            }
            
            $stmtInsert->execute([
                $tenantIdForInsert,
                $matriculaId,
                $alunoIdForInsert,
                $usuarioIdForInsert,
                $pagamento['id'],
                $externalReference,
                $pagamento['preference_id'] ?? null,
                $pagamento['status'],
                $pagamento['status_detail'],
                $pagamento['transaction_amount'],
                $pagamento['payment_method_id'],
                $pagamento['payment_type_id'],
                $pagamento['installments'],
                $pagamento['date_approved'],
                $pagamento['date_created'],
                $pagamento['payer']['email'] ?? null,
                $pagamento['payer']['identification']['type'] ?? null,
                $pagamento['payer']['identification']['number'] ?? null
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
        } elseif ($pagamento['status'] === 'rejected') {
            // Pagamento rejeitado: atualizar assinatura para status rejeitado
            $matriculaIdInt = (int) $matriculaId;
            error_log("[Webhook MP] ❌ Pagamento REJEITADO - matriculaId: {$matriculaIdInt}, motivo: " . ($pagamento['status_detail'] ?? 'N/A'));
            $this->atualizarAssinaturaRejeitada($matriculaIdInt, $pagamento);
        }
    }

    /**
     * Ativar contrato de pacote e gerar matrículas rateadas
     */
    /**
     * Ativar contrato de pacote - SIMPLES
     * Apenas atualiza status para 'ativo'
     */
    private function ativarPacoteContrato(int $contratoId, array $pagamento): void
    {
        try {
            error_log("[Webhook MP] 🎯 Atualizando status do contrato #{$contratoId} para 'ativo'");
            
            // SIMPLES: apenas UPDATE
            $stmt = $this->db->prepare("
                UPDATE pacote_contratos
                SET status = 'ativo'
                WHERE id = ?
            ");
            
            $stmt->execute([$contratoId]);
            $rowsAffected = $stmt->rowCount();
            
            if ($rowsAffected > 0) {
                error_log("[Webhook MP] ✅ Contrato #{$contratoId} atualizado para status 'ativo'");
            } else {
                error_log("[Webhook MP] ⚠️ Contrato #{$contratoId} não encontrado ou já estava ativo");
            }
            
        } catch (\Exception $e) {
            error_log("[Webhook MP] ❌ Erro ao atualizar contrato: " . $e->getMessage());
            error_log("[Webhook MP] Stack: " . $e->getTraceAsString());
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
     * Atualizar assinatura para rejeitada após pagamento rejected
     */
    private function atualizarAssinaturaRejeitada(int $matriculaId, array $pagamento): void
    {
        try {
            $preferenceId = $pagamento['preference_id'] ?? null;
            
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
                error_log("[Webhook MP] ⚠️ Nenhuma assinatura encontrada para rejeição: matrícula #{$matriculaId}, preference_id={$preferenceId}");
                return;
            }
            
            // Se já está ativa/paga, não rebaixar para rejeitada (pagamento posterior pode ter sido aprovado)
            if (in_array($assinatura['status_atual'], ['ativa', 'paga'])) {
                error_log("[Webhook MP] ℹ️ Assinatura #{$assinatura['id']} já está {$assinatura['status_atual']}, não rebaixando para rejeitada");
                return;
            }
            
            // Buscar ID do status 'rejeitada' (ou criar fallback para 'cancelada')
            $stmtStatus = $this->db->prepare("SELECT id FROM assinatura_status WHERE codigo = 'rejeitada'");
            $stmtStatus->execute();
            $statusId = $stmtStatus->fetchColumn();
            
            // Se não existe 'rejeitada', usar 'cancelada'
            if (!$statusId) {
                $stmtStatus2 = $this->db->prepare("SELECT id FROM assinatura_status WHERE codigo = 'cancelada'");
                $stmtStatus2->execute();
                $statusId = $stmtStatus2->fetchColumn();
                error_log("[Webhook MP] ⚠️ Status 'rejeitada' não encontrado, usando 'cancelada' (id={$statusId})");
            }
            
            if (!$statusId) {
                error_log("[Webhook MP] ❌ Nenhum status de rejeição encontrado, abortando");
                return;
            }
            
            $statusDetail = $pagamento['status_detail'] ?? 'rejected';
            
            $stmtUpdate = $this->db->prepare("
                UPDATE assinaturas
                SET status_id = ?,
                    status_gateway = ?,
                    atualizado_em = NOW()
                WHERE id = ?
            ");
            $stmtUpdate->execute([
                $statusId,
                "rejected:{$statusDetail}",
                $assinatura['id']
            ]);
            
            if ($stmtUpdate->rowCount() > 0) {
                error_log("[Webhook MP] ✅ Assinatura #{$assinatura['id']} marcada como rejeitada (motivo: {$statusDetail})");
            }
            
            // Também atualizar pagamentos_plano para refletir rejeição
            try {
                $stmtPagPlano = $this->db->prepare("
                    SELECT id, status_pagamento_id FROM pagamentos_plano
                    WHERE matricula_id = ? AND data_pagamento IS NULL
                    ORDER BY data_vencimento ASC
                    LIMIT 1
                ");
                $stmtPagPlano->execute([$matriculaId]);
                $pagPlano = $stmtPagPlano->fetch(\PDO::FETCH_ASSOC);
                
                if ($pagPlano) {
                    // Buscar status "rejeitado" ou "cancelado" na tabela status_pagamento
                    $stmtStatusPag = $this->db->prepare("SELECT id FROM status_pagamento WHERE nome LIKE '%rejeitad%' OR nome LIKE '%cancelad%' LIMIT 1");
                    $stmtStatusPag->execute();
                    $statusPagId = $stmtStatusPag->fetchColumn();
                    
                    if ($statusPagId) {
                        $stmtUpdatePag = $this->db->prepare("
                            UPDATE pagamentos_plano 
                            SET status_pagamento_id = ?, 
                                observacoes = CONCAT(COALESCE(observacoes, ''), ' | Rejeitado MP: ', ?),
                                updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmtUpdatePag->execute([$statusPagId, $statusDetail, $pagPlano['id']]);
                        error_log("[Webhook MP] ✅ pagamentos_plano #{$pagPlano['id']} atualizado para rejeitado");
                    }
                }
            } catch (\Exception $e) {
                error_log("[Webhook MP] ⚠️ Erro ao atualizar pagamentos_plano: " . $e->getMessage());
            }
            
        } catch (\Exception $e) {
            error_log("[Webhook MP] ⚠️ Erro ao rejeitar assinatura: " . $e->getMessage());
        }
    }
    
    /**
     * Ativar matrícula após pagamento aprovado
     */
    private function ativarMatricula(int $matriculaId): void
    {
        // Buscar dados da matrícula/plano para calcular vigência somente no approved
        $stmtMatricula = $this->db->prepare("
            SELECT m.id, m.status_id, m.plano_id, m.plano_ciclo_id, p.duracao_dias, pc.meses
            FROM matriculas m
            INNER JOIN planos p ON p.id = m.plano_id
            LEFT JOIN plano_ciclos pc ON pc.id = m.plano_ciclo_id
            WHERE m.id = ?
            LIMIT 1
        ");
        $stmtMatricula->execute([$matriculaId]);
        $matricula = $stmtMatricula->fetch(\PDO::FETCH_ASSOC);

        if (!$matricula) {
            error_log("[Webhook MP] ⚠️ Matrícula #{$matriculaId} não encontrada para ativação");
            return;
        }

        // Id do status ativa
        $stmtStatusAtiva = $this->db->prepare("SELECT id FROM status_matricula WHERE codigo IN ('ativa', 'ativo') LIMIT 1");
        $stmtStatusAtiva->execute();
        $statusAtivaId = $stmtStatusAtiva->fetchColumn();

        if (!$statusAtivaId) {
            error_log("[Webhook MP] ❌ Status de matrícula 'ativa/ativo' não encontrado. Matrícula #{$matriculaId} não foi ativada");
            return;
        }
        $statusAtivaId = (int) $statusAtivaId;

        // Evitar reprocessamento de webhooks duplicados
        if ((int) $matricula['status_id'] === $statusAtivaId) {
            error_log("[Webhook MP] ℹ️ Matrícula #{$matriculaId} já ativa, ignorando atualização de vigência");
            return;
        }

        $hoje = new \DateTimeImmutable(date('Y-m-d'));
        $duracaoMeses = (int) ($matricula['meses'] ?? 0);

        if ($duracaoMeses > 0) {
            $dataVencimento = $hoje->modify("+{$duracaoMeses} months")->format('Y-m-d');
        } else {
            $duracaoDias = max(1, (int) ($matricula['duracao_dias'] ?? 30));
            $dataVencimento = $hoje->modify("+{$duracaoDias} days")->format('Y-m-d');
        }

        $stmtUpdate = $this->db->prepare("
            UPDATE matriculas
            SET status_id = ?,
                data_inicio = ?,
                data_vencimento = ?,
                proxima_data_vencimento = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmtUpdate->execute([
            $statusAtivaId,
            $hoje->format('Y-m-d'),
            $dataVencimento,
            $dataVencimento,
            $matriculaId
        ]);

        if ($stmtUpdate->rowCount() > 0) {
            error_log("Matrícula #{$matriculaId} ativada após pagamento aprovado com vigência até {$dataVencimento}");
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
            $paymentId = (string)($pagamento['id'] ?? '');
            $dateApproved = !empty($pagamento['date_approved'])
                ? date('Y-m-d H:i:s', strtotime((string)$pagamento['date_approved']))
                : date('Y-m-d H:i:s');
            
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

            // Idempotência por payment_id (evita reprocessar o mesmo pagamento)
            if ($paymentId !== '') {
                $stmtIdempotencia = $this->db->prepare("
                    SELECT pp.id
                    FROM pagamentos_plano pp
                    WHERE pp.matricula_id = ?
                      AND pp.status_pagamento_id = 2
                      AND pp.observacoes LIKE ?
                    LIMIT 1
                ");
                $patternPaymentId = '%ID: ' . $paymentId . '%';
                $stmtIdempotencia->execute([$matriculaId, $patternPaymentId]);

                if ($stmtIdempotencia->fetch()) {
                    error_log("[Webhook MP] ℹ️ Pagamento MP #{$paymentId} já baixado anteriormente, ignorando reprocessamento");
                    return;
                }
            }
            
            // Buscar o pagamento pendente mais antigo da matrícula
            $stmtBuscar = $this->db->prepare("
                SELECT pp.id, pp.valor
                FROM pagamentos_plano pp
                WHERE pp.matricula_id = ?
                AND pp.status_pagamento_id IN (1, 3)
                AND pp.data_pagamento IS NULL
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
                        data_pagamento = ?,
                        forma_pagamento_id = ?,
                        tipo_baixa_id = 4,
                        observacoes = CONCAT(IFNULL(observacoes, ''), ' | Pago via Mercado Pago - ID: " . $pagamento['id'] . "'),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmtUpdate->execute([
                    $dateApproved,
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
                        ?, CURDATE(), ?,
                        2, ?,
                        ?, 4, NOW(), NOW()
                    )
                ");
                
                $stmtInsert->execute([
                    $matricula['tenant_id'],
                    $matricula['aluno_id'],
                    $matriculaId,
                    $matricula['plano_id'],
                    $pagamento['transaction_amount'],
                    $dateApproved,
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
     * NOVO FLUXO: Criar matrícula do PAGANTE quando webhook de assinatura PAC- chega
     * 
     * Este método é chamado quando webhook de assinatura (subscription_preapproval) chega
     * com external_reference = "PAC-{contratoId}-..."
     * 
     * Cria:
     * 1. Matrícula do pagante
     * 2. Assinatura recorrente com pacote_contrato_id armazenado
     * 
     * Os beneficiários serão criados quando o webhook de PAGAMENTO chegar
     */


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

    /**
     * Simular webhook de pagamento aprovado para testes
     * GET /api/webhooks/mercadopago/test
     * 
     * Query params:
     * - external_reference: MAT-{matricula_id}-{timestamp} ou PAC-{contrato_id}-{timestamp}
     * - status: approved (padrão), pending, rejected
     * - payment_type: credit_card (padrão), pix, boleto
     * 
     * Exemplo:
     * GET /api/webhooks/mercadopago/test?external_reference=MAT-123-1708380000&status=approved&payment_type=credit_card
     */
    #[OA\Get(
        path: "/api/webhooks/mercadopago/test",
        summary: "Simular webhook de pagamento",
        description: "Simula um webhook de pagamento do Mercado Pago para testes. Cria um payment_id fake, processa o pagamento e atualiza assinatura/matrícula. Útil para simular fluxos de pagamento sem integração real com MP.",
        tags: ["Webhook - Testes"],
        parameters: [
            new OA\Parameter(
                name: "external_reference",
                in: "query",
                description: "Referência externa do pagamento. Formato: MAT-{matricula_id}-{timestamp} ou PAC-{contrato_id}-{timestamp}",
                required: false,
                schema: new OA\Schema(type: "string", example: "MAT-123-1708380000")
            ),
            new OA\Parameter(
                name: "status",
                in: "query",
                description: "Status do pagamento simulado",
                required: false,
                schema: new OA\Schema(
                    type: "string",
                    enum: ["pending", "approved", "rejected", "authorized"],
                    default: "approved"
                )
            ),
            new OA\Parameter(
                name: "payment_type",
                in: "query",
                description: "Tipo de método de pagamento simulado",
                required: false,
                schema: new OA\Schema(
                    type: "string",
                    enum: ["credit_card", "pix", "boleto"],
                    default: "credit_card"
                )
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Webhook simulado com sucesso",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Webhook de teste simulado com sucesso"),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "payment_id", type: "string", example: "1234567890"),
                                new OA\Property(property: "external_reference", type: "string", example: "MAT-123-1708380000"),
                                new OA\Property(property: "status", type: "string", example: "approved"),
                                new OA\Property(property: "payment_type", type: "string", example: "credit_card"),
                                new OA\Property(property: "tenant_id", type: "integer", example: 1),
                                new OA\Property(property: "__test__", type: "boolean", example: true)
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "Status inválido ou parâmetro mal formatado"
            ),
            new OA\Response(
                response: 500,
                description: "Erro ao processar webhook simulado"
            )
        ]
    )]
    public function simularWebhook(Request $request, Response $response): Response
    {
        try {
            $query = $request->getQueryParams();
            
            // Parâmetros
            $externalReference = $query['external_reference'] ?? 'MAT-1-' . time();
            $status = $query['status'] ?? 'approved';
            $paymentType = $query['payment_type'] ?? 'credit_card';
            
            // Validar status
            if (!in_array($status, ['pending', 'approved', 'rejected', 'authorized'])) {
                $response->getBody()->write(json_encode([
                    'error' => 'Status inválido. Use: pending, approved, rejected, authorized'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            // Gerar payment_id fake mas realista
            $paymentId = (int)(mt_rand(1000000000, 9999999999));
            
            // Mapear payment_type para tipo de método de pagamento
            $paymentMethodId = match($paymentType) {
                'pix' => 'prompt_payment',
                'boleto' => 'bolbradesco',
                'credit_card' => 'visa',
                default => 'visa'
            };
            
            // Criar payload simulado (formato Mercado Pago)
            $payload = [
                'type' => 'payment',
                'data' => [
                    'id' => (string)$paymentId
                ],
                'action' => 'payment.created',
                'timestamp' => date('Y-m-d\TH:i:s.000O'),
                '__test__' => true  // Marcar como teste
            ];
            
            // Dados adicionais que seriam recuperados via API MP
            $dadosPagamento = [
                'id' => (string)$paymentId,
                'status' => $status,
                'external_reference' => $externalReference,
                'payment_type_id' => $paymentMethodId,
                'statement_descriptor' => 'AppCheckin',
                'transaction_amount' => 99.90,
                'currency_id' => 'BRL',
                'description' => 'Teste de pagamento simulado',
                'payer' => [
                    'id' => 123456789,
                    'email' => 'teste@example.com',
                    'first_name' => 'Teste',
                    'last_name' => 'Simulado'
                ],
                'point_of_interaction' => [
                    'type' => 'UNSPECIFIED'
                ]
            ];
            
            $this->logWebhook("=== WEBHOOK TESTE SIMULADO ===");
            $this->logWebhook("External Reference: {$externalReference}");
            $this->logWebhook("Status: {$status}");
            $this->logWebhook("Payment Type: {$paymentType}");
            $this->logWebhook("Payment ID: {$paymentId}");
            
            // Buscar tenant_id pela matrícula (se MAT-xxx ou PAC-xxx)
            $tenantId = null;
            if (preg_match('/^(MAT|PAC)-(\d+)-/', $externalReference, $matches)) {
                $refId = (int)$matches[2];
                
                if ($matches[1] === 'MAT') {
                    // Buscar matrícula para obter tenant_id
                    $stmt = $this->db->prepare("SELECT tenant_id FROM matriculas WHERE id = ? LIMIT 1");
                    $stmt->execute([$refId]);
                    $matData = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($matData) {
                        $tenantId = (int)$matData['tenant_id'];
                    }
                } elseif ($matches[1] === 'PAC') {
                    // Buscar contrato para obter tenant_id
                    $stmt = $this->db->prepare("SELECT tenant_id FROM pacote_contratos WHERE id = ? LIMIT 1");
                    $stmt->execute([$refId]);
                    $contData = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($contData) {
                        $tenantId = (int)$contData['tenant_id'];
                    }
                }
            }
            
            // Se não conseguiu descobrir tenant_id, usar o primeiro ou usar ENV
            if (!$tenantId) {
                $stmt = $this->db->prepare("SELECT id FROM tenants ORDER BY id ASC LIMIT 1");
                $stmt->execute();
                $tenantRow = $stmt->fetch(\PDO::FETCH_ASSOC);
                $tenantId = $tenantRow ? (int)$tenantRow['id'] : 1;
            }
            
            $this->logWebhook("Tenant ID: {$tenantId}");
            
            // Salvar webhook no banco para auditoria
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO webhook_payloads_mercadopago
                    (tenant_id, tipo, data_id, payment_id, payload, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $tenantId,
                    'payment',
                    (string)$paymentId,
                    (string)$paymentId,
                    json_encode($dadosPagamento, JSON_UNESCAPED_UNICODE),
                    $status
                ]);
                $this->logWebhook("✅ Webhook registrado no banco");
            } catch (\Exception $e) {
                $this->logWebhook("⚠️ Erro ao salvar webhook: " . $e->getMessage());
            }
            
            // Processar pagamento simulado
            try {
                $mercadoPago = $this->getMercadoPagoService($tenantId);
                
                // Processar com os dados simulados
                $this->processarPagamentoSimulado($tenantId, $dadosPagamento, $externalReference);
                
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'message' => 'Webhook de teste simulado com sucesso',
                    'data' => [
                        'payment_id' => $paymentId,
                        'external_reference' => $externalReference,
                        'status' => $status,
                        'payment_type' => $paymentType,
                        'tenant_id' => $tenantId,
                        '__test__' => true
                    ]
                ], JSON_UNESCAPED_UNICODE));
                
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                
            } catch (\Exception $e) {
                $this->logWebhook("❌ Erro ao processar webhook teste: " . $e->getMessage());
                
                $response->getBody()->write(json_encode([
                    'error' => 'Erro ao processar webhook',
                    'message' => $e->getMessage()
                ], JSON_UNESCAPED_UNICODE));
                
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
            
        } catch (\Exception $e) {
            error_log("[Webhook MP] ❌ Erro ao simular webhook: " . $e->getMessage());
            
            $response->getBody()->write(json_encode([
                'error' => 'Erro ao simular webhook',
                'message' => $e->getMessage()
            ]));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Processar pagamento simulado (sem chamar API MP)
     */
    private function processarPagamentoSimulado(
        int $tenantId,
        array $dadosPagamento,
        string $externalReference
    ): void {
        $paymentId = $dadosPagamento['id'];
        $status = $dadosPagamento['status'];
        
        $this->logWebhook("Processando pagamento simulado: ID={$paymentId}, Status={$status}");
        
        // Verificar se é pagamento para matrícula (MAT-xxx) ou pacote (PAC-xxx)
        if (preg_match('/^MAT-(\d+)-/', $externalReference, $matches)) {
            // Pagamento de matrícula
            $matriculaId = (int)$matches[1];
            
            // Buscar matrícula
            $stmt = $this->db->prepare("SELECT * FROM matriculas WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$matriculaId, $tenantId]);
            $matricula = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$matricula) {
                throw new \Exception("Matrícula #{$matriculaId} não encontrada");
            }
            
            // Buscar ou criar assinatura
            $stmtAss = $this->db->prepare("
                SELECT id FROM assinaturas 
                WHERE matricula_id = ? AND tenant_id = ? 
                ORDER BY id DESC LIMIT 1
            ");
            $stmtAss->execute([$matriculaId, $tenantId]);
            $assinatura = $stmtAss->fetch(\PDO::FETCH_ASSOC);
            
            if ($assinatura) {
                // Atualizar status da assinatura
                $statusCode = $status === 'approved' ? 'ativa' : 'pendente';
                $stmtStatus = $this->db->prepare("SELECT id FROM assinatura_status WHERE codigo = ?");
                $stmtStatus->execute([$statusCode]);
                $statusId = $stmtStatus->fetchColumn() ?: 1;
                
                $stmtUpdate = $this->db->prepare("
                    UPDATE assinaturas 
                    SET status_gateway = ?, status_id = ?, atualizado_em = NOW()
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmtUpdate->execute([$status, $statusId, $assinatura['id'], $tenantId]);
                
                $this->logWebhook("✅ Assinatura #{$assinatura['id']} atualizada para status '{$status}'");
            }
            
            // Atualizar matrícula se aprovado
            if ($status === 'approved') {
                $stmtMatStatus = $this->db->prepare("SELECT id FROM status_matricula WHERE codigo = 'ativa'");
                $stmtMatStatus->execute();
                $matStatusId = $stmtMatStatus->fetchColumn() ?: 2;
                
                $stmtUpdMat = $this->db->prepare("
                    UPDATE matriculas 
                    SET status_id = ?, updated_at = NOW()
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmtUpdMat->execute([$matStatusId, $matriculaId, $tenantId]);
                
                $this->logWebhook("✅ Matrícula #{$matriculaId} ativada");
            }
            
        } elseif (preg_match('/^PAC-(\d+)-/', $externalReference, $matches)) {
            // Pagamento de pacote/contrato
            $contratoId = (int)$matches[1];
            
            $stmt = $this->db->prepare("SELECT * FROM pacote_contratos WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$contratoId, $tenantId]);
            $contrato = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$contrato) {
                throw new \Exception("Contrato #{$contratoId} não encontrado");
            }
            
            // Atualizar status do contrato
            if ($status === 'approved') {
                $stmtUpd = $this->db->prepare("
                    UPDATE pacote_contratos 
                    SET status = 'pago', updated_at = NOW()
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmtUpd->execute([$contratoId, $tenantId]);
                
                $this->logWebhook("✅ Contrato #{$contratoId} marcado como pago");
            }
        }
    }
}
