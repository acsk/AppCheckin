<?php
/**
 * Fake Mercado Pago API Server
 * 
 * Simula os principais endpoints da API do Mercado Pago para testes locais.
 * 
 * Uso:
 *   php tools/fake-mp-api/server.php
 *   # ou com PHP built-in server:
 *   php -S localhost:8085 tools/fake-mp-api/server.php
 * 
 * Endpoints simulados:
 *   POST /checkout/preferences     → Criar preferência de pagamento
 *   POST /v1/payments              → Criar pagamento (PIX/direto)
 *   GET  /v1/payments/{id}         → Consultar pagamento
 *   GET  /v1/payments/search       → Buscar pagamentos
 *   POST /preapproval_plan         → Criar plano de assinatura
 *   POST /preapproval              → Criar preapproval (assinatura)
 *   GET  /preapproval/{id}         → Consultar preapproval
 *   PUT  /preapproval/{id}         → Atualizar preapproval
 *   GET  /authorized_payments/{id} → Consultar pagamento autorizado
 * 
 * Configuração no .env da API:
 *   MP_FAKE_API_URL=http://localhost:8085
 */

// ============================================================
// Storage em memória / arquivo para persistir durante a sessão
// ============================================================
$storageFile = __DIR__ . '/storage.json';

function loadStorage(): array {
    global $storageFile;
    if (file_exists($storageFile)) {
        $data = json_decode(file_get_contents($storageFile), true);
        return is_array($data) ? $data : getDefaultStorage();
    }
    return getDefaultStorage();
}

function saveStorage(array $storage): void {
    global $storageFile;
    file_put_contents($storageFile, json_encode($storage, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function getDefaultStorage(): array {
    return [
        'preferences' => [],
        'payments' => [],
        'plans' => [],
        'preapprovals' => [],
        'counters' => [
            'preference' => 1000000000,
            'payment' => 80000000000,
            'plan' => 2000000000,
            'preapproval' => 3000000000,
        ]
    ];
}

function nextId(array &$storage, string $type): string {
    $storage['counters'][$type]++;
    saveStorage($storage);
    return (string) $storage['counters'][$type];
}

// ============================================================
// Roteamento
// ============================================================
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$query = $_SERVER['QUERY_STRING'] ?? '';

// Ler body JSON
$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody, true) ?? [];

// Headers de resposta
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Idempotency-Key');

// Preflight CORS
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Extrair token (validação fake)
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);

// Log da requisição
$logLine = date('Y-m-d H:i:s') . " | {$method} {$uri} | Token: " . substr($token, 0, 20) . "...";
error_log("[FakeMP] {$logLine}");
if ($body) {
    error_log("[FakeMP] Body: " . json_encode($body, JSON_UNESCAPED_UNICODE));
}

$storage = loadStorage();

// ============================================================
// Rotas
// ============================================================

// POST /checkout/preferences
if ($method === 'POST' && $uri === '/checkout/preferences') {
    $id = nextId($storage, 'preference');
    
    $preference = [
        'id' => $id,
        'items' => $body['items'] ?? [],
        'payer' => $body['payer'] ?? [],
        'metadata' => $body['metadata'] ?? [],
        'external_reference' => $body['external_reference'] ?? null,
        'notification_url' => $body['notification_url'] ?? null,
        'back_urls' => $body['back_urls'] ?? [],
        'auto_return' => $body['auto_return'] ?? null,
        'payment_methods' => $body['payment_methods'] ?? [],
        'statement_descriptor' => $body['statement_descriptor'] ?? null,
        'init_point' => "https://www.mercadopago.com.br/checkout/v1/redirect?pref_id={$id}",
        'sandbox_init_point' => "https://sandbox.mercadopago.com.br/checkout/v1/redirect?pref_id={$id}",
        'date_created' => date('c'),
        'collector_id' => 123456789,
        'client_id' => '9876543210',
    ];
    
    $storage['preferences'][$id] = $preference;
    saveStorage($storage);
    
    error_log("[FakeMP] ✅ Preferência criada: {$id}");
    http_response_code(201);
    echo json_encode($preference, JSON_UNESCAPED_UNICODE);
    exit;
}

// POST /v1/payments (pagamento direto / PIX)
if ($method === 'POST' && $uri === '/v1/payments') {
    $id = nextId($storage, 'payment');
    $isPix = ($body['payment_method_id'] ?? '') === 'pix';
    
    // Gerar QR code fake se for PIX
    $transactionData = null;
    if ($isPix) {
        $qrCode = "00020126580014br.gov.bcb.pix0136fake-pix-key-{$id}520400005303986540" 
                 . number_format($body['transaction_amount'] ?? 0, 2, '', '') 
                 . "5802BR5913FAKE ACADEMIA6009SAO PAULO62070503***6304ABCD";
        
        $transactionData = [
            'qr_code' => $qrCode,
            'qr_code_base64' => base64_encode("FAKE_QR_IMAGE_FOR_PAYMENT_{$id}"),
            'ticket_url' => "https://sandbox.mercadopago.com.br/payments/{$id}/ticket",
            'bank_info' => [
                'payer' => ['id' => null],
                'collector' => ['id' => 123456789]
            ]
        ];
    }
    
    $payment = [
        'id' => (int) $id,
        'date_created' => date('c'),
        'date_approved' => null,
        'date_last_updated' => date('c'),
        'date_of_expiration' => date('c', strtotime('+30 minutes')),
        'money_release_date' => null,
        'status' => $isPix ? 'pending' : 'approved',
        'status_detail' => $isPix ? 'pending_waiting_transfer' : 'accredited',
        'operation_type' => 'regular_payment',
        'description' => $body['description'] ?? 'Pagamento via Fake MP API',
        'external_reference' => $body['external_reference'] ?? null,
        'transaction_amount' => (float) ($body['transaction_amount'] ?? 0),
        'transaction_amount_refunded' => 0,
        'net_received_amount' => (float) ($body['transaction_amount'] ?? 0) * 0.95,
        'total_paid_amount' => (float) ($body['transaction_amount'] ?? 0),
        'currency_id' => 'BRL',
        'payment_method_id' => $body['payment_method_id'] ?? 'pix',
        'payment_type_id' => $isPix ? 'bank_transfer' : 'credit_card',
        'installments' => (int) ($body['installments'] ?? 1),
        'payer' => $body['payer'] ?? [],
        'metadata' => $body['metadata'] ?? [],
        'notification_url' => $body['notification_url'] ?? null,
        'preference_id' => null,
        'collector_id' => 123456789,
        'point_of_interaction' => $isPix ? [
            'type' => 'PIX',
            'transaction_data' => $transactionData
        ] : null,
    ];
    
    $storage['payments'][$id] = $payment;
    saveStorage($storage);
    
    error_log("[FakeMP] ✅ Pagamento criado: {$id} (status: {$payment['status']})");
    http_response_code(201);
    echo json_encode($payment, JSON_UNESCAPED_UNICODE);
    exit;
}

// GET /v1/payments/search
if ($method === 'GET' && $uri === '/v1/payments/search') {
    parse_str($query, $params);
    $results = array_values($storage['payments']);
    
    // Filtrar por external_reference se informado
    if (!empty($params['external_reference'])) {
        $results = array_filter($results, function($p) use ($params) {
            return ($p['external_reference'] ?? '') === $params['external_reference'];
        });
        $results = array_values($results);
    }
    
    echo json_encode([
        'results' => $results,
        'paging' => [
            'total' => count($results),
            'offset' => 0,
            'limit' => 30
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// GET /v1/payments/{id}
if ($method === 'GET' && preg_match('#^/v1/payments/(\d+)$#', $uri, $matches)) {
    $id = $matches[1];
    
    if (isset($storage['payments'][$id])) {
        echo json_encode($storage['payments'][$id], JSON_UNESCAPED_UNICODE);
    } else {
        // Retornar pagamento fake genérico
        echo json_encode([
            'id' => (int) $id,
            'status' => 'approved',
            'status_detail' => 'accredited',
            'external_reference' => null,
            'transaction_amount' => 0,
            'currency_id' => 'BRL',
            'payment_method_id' => 'credit_card',
            'payment_type_id' => 'credit_card',
            'installments' => 1,
            'date_created' => date('c'),
            'date_approved' => date('c'),
            'payer' => ['email' => 'fake@test.com'],
            'metadata' => [],
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// GET /authorized_payments/{id}
if ($method === 'GET' && preg_match('#^/authorized_payments/(\d+)$#', $uri, $matches)) {
    $id = $matches[1];
    
    // Primeiro tenta buscar nos payments normais
    $payment = $storage['payments'][$id] ?? null;
    
    $result = [
        'id' => (int) $id,
        'status' => $payment['status'] ?? 'authorized',
        'status_detail' => $payment['status_detail'] ?? 'accredited',
        'external_reference' => $payment['external_reference'] ?? null,
        'transaction_amount' => $payment['transaction_amount'] ?? 0,
        'currency_id' => 'BRL',
        'payment_method_id' => $payment['payment_method_id'] ?? 'account_money',
        'payment_type' => 'account_money',
        'date_created' => $payment['date_created'] ?? date('c'),
        'date_approved' => date('c'),
        'last_modified' => date('c'),
        'reason' => 'Pagamento autorizado (fake)',
        'preapproval_id' => null,
        'payer' => $payment['payer'] ?? ['email' => 'fake@test.com'],
        'metadata' => $payment['metadata'] ?? [],
    ];
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

// POST /preapproval_plan
if ($method === 'POST' && $uri === '/preapproval_plan') {
    $id = nextId($storage, 'plan');
    
    $plan = [
        'id' => $id,
        'reason' => $body['reason'] ?? 'Plano Fake',
        'status' => 'active',
        'auto_recurring' => $body['auto_recurring'] ?? [],
        'back_url' => $body['back_url'] ?? null,
        'external_reference' => $body['external_reference'] ?? null,
        'metadata' => $body['metadata'] ?? [],
        'date_created' => date('c'),
        'last_modified' => date('c'),
        'init_point' => "https://sandbox.mercadopago.com.br/preapproval/plan/{$id}",
        'sandbox_init_point' => "https://sandbox.mercadopago.com.br/preapproval/plan/{$id}",
        'collector_id' => 123456789,
    ];
    
    $storage['plans'][$id] = $plan;
    saveStorage($storage);
    
    error_log("[FakeMP] ✅ Plano criado: {$id}");
    http_response_code(201);
    echo json_encode($plan, JSON_UNESCAPED_UNICODE);
    exit;
}

// POST /preapproval
if ($method === 'POST' && $uri === '/preapproval') {
    $id = nextId($storage, 'preapproval');
    
    $preapproval = [
        'id' => $id,
        'plan_id' => $body['plan_id'] ?? null,
        'reason' => $body['reason'] ?? 'Assinatura Fake',
        'external_reference' => $body['external_reference'] ?? null,
        'payer_email' => $body['payer_email'] ?? null,
        'payer_id' => rand(100000000, 999999999),
        'status' => 'pending',
        'auto_recurring' => $body['auto_recurring'] ?? [],
        'back_url' => $body['back_url'] ?? null,
        'metadata' => $body['metadata'] ?? [],
        'date_created' => date('c'),
        'last_modified' => date('c'),
        'init_point' => "https://www.mercadopago.com.br/subscriptions/checkout?preapproval_id={$id}",
        'sandbox_init_point' => "https://sandbox.mercadopago.com.br/subscriptions/checkout?preapproval_id={$id}",
        'collector_id' => 123456789,
        'summarized' => [
            'quotas' => null,
            'charged_quantity' => 0,
            'pending_charge_quantity' => 0,
            'charged_amount' => 0,
            'pending_charge_amount' => (float) ($body['auto_recurring']['transaction_amount'] ?? 0),
            'semaphore' => 'green',
            'last_charged_date' => null,
            'last_charged_amount' => null
        ]
    ];
    
    $storage['preapprovals'][$id] = $preapproval;
    saveStorage($storage);
    
    error_log("[FakeMP] ✅ Preapproval criada: {$id}");
    http_response_code(201);
    echo json_encode($preapproval, JSON_UNESCAPED_UNICODE);
    exit;
}

// GET /preapproval/{id}
if ($method === 'GET' && preg_match('#^/preapproval/(\w+)$#', $uri, $matches)) {
    $id = $matches[1];
    
    if (isset($storage['preapprovals'][$id])) {
        echo json_encode($storage['preapprovals'][$id], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(404);
        echo json_encode(['message' => 'Preapproval not found', 'error' => 'not_found', 'status' => 404]);
    }
    exit;
}

// PUT /preapproval/{id}
if ($method === 'PUT' && preg_match('#^/preapproval/(\w+)$#', $uri, $matches)) {
    $id = $matches[1];
    
    if (isset($storage['preapprovals'][$id])) {
        // Merge updates
        foreach ($body as $key => $value) {
            $storage['preapprovals'][$id][$key] = $value;
        }
        $storage['preapprovals'][$id]['last_modified'] = date('c');
        saveStorage($storage);
        
        error_log("[FakeMP] ✅ Preapproval atualizada: {$id}");
        echo json_encode($storage['preapprovals'][$id], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(404);
        echo json_encode(['message' => 'Preapproval not found', 'error' => 'not_found', 'status' => 404]);
    }
    exit;
}

// ============================================================
// Endpoints de controle (para testes)
// ============================================================

// POST /fake/webhook - Simular envio de webhook para a API local
if ($method === 'POST' && $uri === '/fake/webhook') {
    $paymentId = $body['payment_id'] ?? null;
    $status = $body['status'] ?? 'approved';
    $webhookUrl = $body['webhook_url'] ?? 'http://localhost:8080/api/webhooks/mercadopago';
    
    if (!$paymentId) {
        http_response_code(400);
        echo json_encode(['error' => 'payment_id é obrigatório']);
        exit;
    }
    
    // Atualizar status do pagamento no storage
    if (isset($storage['payments'][$paymentId])) {
        $storage['payments'][$paymentId]['status'] = $status;
        $storage['payments'][$paymentId]['status_detail'] = $status === 'approved' ? 'accredited' : ($status === 'rejected' ? 'cc_rejected_other_reason' : $status);
        if ($status === 'approved') {
            $storage['payments'][$paymentId]['date_approved'] = date('c');
        }
        $storage['payments'][$paymentId]['date_last_updated'] = date('c');
        saveStorage($storage);
    }
    
    // Montar payload do webhook
    $webhookPayload = [
        'id' => rand(10000000000, 99999999999),
        'live_mode' => false,
        'type' => 'payment',
        'date_created' => date('c'),
        'user_id' => 123456789,
        'api_version' => 'v1',
        'action' => 'payment.updated',
        'data' => [
            'id' => $paymentId
        ]
    ];
    
    // Enviar webhook para API
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $webhookUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($webhookPayload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'User-Agent: MercadoPago WebHook v1.0 (Fake)'
        ],
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    $result = [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'payment_id' => $paymentId,
        'new_status' => $status,
        'webhook_sent_to' => $webhookUrl,
        'webhook_http_code' => $httpCode,
        'webhook_response' => json_decode($response, true) ?? $response,
    ];
    
    if ($error) {
        $result['curl_error'] = $error;
    }
    
    error_log("[FakeMP] 📨 Webhook enviado: payment {$paymentId} → {$status} (HTTP {$httpCode})");
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// POST /fake/approve-payment - Aprovar um pagamento e disparar webhook
if ($method === 'POST' && $uri === '/fake/approve-payment') {
    $paymentId = $body['payment_id'] ?? null;
    if (!$paymentId || !isset($storage['payments'][$paymentId])) {
        http_response_code(400);
        echo json_encode(['error' => 'payment_id inválido ou não encontrado']);
        exit;
    }
    
    $storage['payments'][$paymentId]['status'] = 'approved';
    $storage['payments'][$paymentId]['status_detail'] = 'accredited';
    $storage['payments'][$paymentId]['date_approved'] = date('c');
    $storage['payments'][$paymentId]['date_last_updated'] = date('c');
    saveStorage($storage);
    
    error_log("[FakeMP] ✅ Pagamento {$paymentId} aprovado!");
    echo json_encode($storage['payments'][$paymentId], JSON_UNESCAPED_UNICODE);
    exit;
}

// POST /fake/reject-payment - Rejeitar um pagamento
if ($method === 'POST' && $uri === '/fake/reject-payment') {
    $paymentId = $body['payment_id'] ?? null;
    $reason = $body['reason'] ?? 'cc_rejected_other_reason';
    
    if (!$paymentId || !isset($storage['payments'][$paymentId])) {
        http_response_code(400);
        echo json_encode(['error' => 'payment_id inválido ou não encontrado']);
        exit;
    }
    
    $storage['payments'][$paymentId]['status'] = 'rejected';
    $storage['payments'][$paymentId]['status_detail'] = $reason;
    $storage['payments'][$paymentId]['date_last_updated'] = date('c');
    saveStorage($storage);
    
    error_log("[FakeMP] ❌ Pagamento {$paymentId} rejeitado ({$reason})!");
    echo json_encode($storage['payments'][$paymentId], JSON_UNESCAPED_UNICODE);
    exit;
}

// GET /fake/storage - Ver todo o storage (debug)
if ($method === 'GET' && $uri === '/fake/storage') {
    echo json_encode($storage, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// DELETE /fake/storage - Limpar storage
if ($method === 'DELETE' && $uri === '/fake/storage') {
    $storage = getDefaultStorage();
    saveStorage($storage);
    echo json_encode(['message' => 'Storage limpo com sucesso']);
    exit;
}

// GET /fake/health - Health check
if ($method === 'GET' && ($uri === '/fake/health' || $uri === '/')) {
    echo json_encode([
        'status' => 'ok',
        'service' => 'Fake Mercado Pago API',
        'version' => '1.0.0',
        'endpoints' => [
            'POST /checkout/preferences' => 'Criar preferência de pagamento',
            'POST /v1/payments' => 'Criar pagamento (PIX/direto)',
            'GET  /v1/payments/{id}' => 'Consultar pagamento',
            'GET  /v1/payments/search' => 'Buscar pagamentos',
            'POST /preapproval_plan' => 'Criar plano de assinatura',
            'POST /preapproval' => 'Criar preapproval (assinatura)',
            'GET  /preapproval/{id}' => 'Consultar preapproval',
            'PUT  /preapproval/{id}' => 'Atualizar preapproval',
            'GET  /authorized_payments/{id}' => 'Consultar pagamento autorizado',
            '--- Controle (testes) ---' => '',
            'POST /fake/webhook' => 'Enviar webhook fake para API',
            'POST /fake/approve-payment' => 'Aprovar pagamento',
            'POST /fake/reject-payment' => 'Rejeitar pagamento',
            'GET  /fake/storage' => 'Ver dados armazenados',
            'DELETE /fake/storage' => 'Limpar dados',
            'GET  /fake/health' => 'Health check',
        ],
        'storage_stats' => [
            'preferences' => count($storage['preferences']),
            'payments' => count($storage['payments']),
            'plans' => count($storage['plans']),
            'preapprovals' => count($storage['preapprovals']),
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ============================================================
// Rota não encontrada
// ============================================================
http_response_code(404);
echo json_encode([
    'message' => "Endpoint não implementado: {$method} {$uri}",
    'error' => 'not_found',
    'status' => 404
], JSON_UNESCAPED_UNICODE);
