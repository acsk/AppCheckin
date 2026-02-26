<?php

declare(strict_types=1);

/**
 * Payment Gateway Simulator - Router Principal
 * 
 * Roteia todas as requisições para os controllers apropriados.
 */

// Headers CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Api-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Autoload simples
spl_autoload_register(function (string $class): void {
    $path = __DIR__ . '/app/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($path)) {
        require_once $path;
    }
});

// Carregar helpers
require_once __DIR__ . '/app/helpers.php';

// Obter URI e método
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// ============================================================
// LOG DE REQUISIÇÕES (debug - ajuda a identificar rotas chamadas)
// ============================================================
$requestLog = __DIR__ . '/logs/request_log.json';
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$existingLogs = [];
if (file_exists($requestLog)) {
    $existingLogs = json_decode(file_get_contents($requestLog), true) ?? [];
}
$existingLogs[] = [
    'time' => date('Y-m-d H:i:s'),
    'method' => $method,
    'uri' => $uri,
    'query' => $_SERVER['QUERY_STRING'] ?? '',
    'headers' => [
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
        'authorization' => !empty($_SERVER['HTTP_AUTHORIZATION']) ? 'Bearer ***' : '',
    ],
];
$existingLogs = array_slice($existingLogs, -200);
file_put_contents($requestLog, json_encode($existingLogs, JSON_PRETTY_PRINT));

// ============================================================
// NORMALIZAÇÃO DE URI - Mapeia rotas reais do Mercado Pago
// para o formato interno do simulador.
//
// API real do MP usa:
//   /v1/payments, /v1/payments/{id}, /v1/payments/{id}/refunds
//   /checkout/preferences, /checkout/preferences/{id}
//   /preapproval, /preapproval/{id}, /preapproval/search
//   /v1/preapproval, /v1/preapproval/{id}
//
// Nosso simulador usa /api/... — o mapeamento abaixo garante
// que ambas as formas funcionem.
// ============================================================

$originalUri = $uri;

// -----------------------------------------------
// /authorized_payments/{id} → busca pagamento (fallback do MP)
// -----------------------------------------------
if (preg_match('#^/authorized_payments/([a-zA-Z0-9_-]+)/?$#', $originalUri, $m) && $method === 'GET') {
    require_once __DIR__ . '/app/Controllers/PaymentController.php';
    $controller = new Controllers\PaymentController();
    $controller->show($m[1]);
    exit;
}

// -----------------------------------------------
// /preapproval_plan → Criar plano de assinatura
// Retorna objeto no formato MP (subscription plan)
// -----------------------------------------------
if (preg_match('#^(/v1)?/preapproval_plan/?$#', $originalUri) && $method === 'POST') {
    require_once __DIR__ . '/app/Controllers/PreapprovalController.php';
    $controller = new Controllers\PreapprovalController();
    $controller->createPlan();
    exit;
}
if (preg_match('#^(/v1)?/preapproval_plan/([a-zA-Z0-9_-]+)/?$#', $originalUri, $m) && $method === 'GET') {
    require_once __DIR__ . '/app/Controllers/PreapprovalController.php';
    $controller = new Controllers\PreapprovalController();
    $controller->showPlan($m[2]);
    exit;
}

// /v1/payments/... → /api/payments/...
if (preg_match('#^/v1/payments(/.*)?$#', $uri, $m)) {
    $uri = '/api/payments' . ($m[1] ?? '');
}

// /v1/payment_methods → retorna lista de métodos aceitos
if (preg_match('#^/v1/payment_methods/?$#', $originalUri)) {
    jsonResponse([
        ['id' => 'visa', 'name' => 'Visa', 'payment_type_id' => 'credit_card', 'status' => 'active'],
        ['id' => 'master', 'name' => 'Mastercard', 'payment_type_id' => 'credit_card', 'status' => 'active'],
        ['id' => 'elo', 'name' => 'Elo', 'payment_type_id' => 'credit_card', 'status' => 'active'],
        ['id' => 'amex', 'name' => 'American Express', 'payment_type_id' => 'credit_card', 'status' => 'active'],
        ['id' => 'hipercard', 'name' => 'Hipercard', 'payment_type_id' => 'credit_card', 'status' => 'active'],
        ['id' => 'pix', 'name' => 'PIX', 'payment_type_id' => 'bank_transfer', 'status' => 'active'],
        ['id' => 'bolbradesco', 'name' => 'Boleto Bradesco', 'payment_type_id' => 'ticket', 'status' => 'active'],
        ['id' => 'pec', 'name' => 'Pagamento na lotérica', 'payment_type_id' => 'ticket', 'status' => 'active'],
        ['id' => 'account_money', 'name' => 'Dinheiro em conta', 'payment_type_id' => 'account_money', 'status' => 'active'],
        ['id' => 'debit_card', 'name' => 'Cartão de débito', 'payment_type_id' => 'debit_card', 'status' => 'active'],
    ]);
    exit;
}

// /checkout/preferences → /api/preferences
if (preg_match('#^/checkout/preferences(/.*)?$#', $uri, $m)) {
    $uri = '/api/preferences' . ($m[1] ?? '');
}

// /v1/checkout/preferences → /api/preferences
if (preg_match('#^/v1/checkout/preferences(/.*)?$#', $originalUri, $m)) {
    $uri = '/api/preferences' . ($m[1] ?? '');
}

// /preapproval/... → /api/preapproval/...  (sem prefixo /v1)
if (preg_match('#^/preapproval(/.*)?$#', $uri, $m)) {
    $uri = '/api/preapproval' . ($m[1] ?? '');
}

// /v1/preapproval/... → /api/preapproval/...
if (preg_match('#^/v1/preapproval(/.*)?$#', $originalUri, $m)) {
    $uri = '/api/preapproval' . ($m[1] ?? '');
}

// /v1/refunds e /v1/payments/{id}/refunds → /api/payments/{id}/refund
if (preg_match('#^/api/payments/([a-zA-Z0-9_-]+)/refunds/?$#', $uri, $m)) {
    $uri = '/api/payments/' . $m[1] . '/refund';
}

// /preapproval/search e /api/preapproval/search → list
if (preg_match('#^/api/preapproval/search/?$#', $uri) && $method === 'GET') {
    require_once __DIR__ . '/app/Controllers/PreapprovalController.php';
    $controller = new Controllers\PreapprovalController();
    $controller->list();
    exit;
}

// /v1/payments/search → list
if (preg_match('#^/api/payments/search/?$#', $uri) && $method === 'GET') {
    require_once __DIR__ . '/app/Controllers/PaymentController.php';
    $controller = new Controllers\PaymentController();
    $controller->list();
    exit;
}

// ============================================================
// ROTAS (usando URI normalizada)
// ============================================================

// --- API de Assinaturas (Preapproval) ---

// POST /api/preapproval - Criar assinatura
if (preg_match('#^/api/preapproval/?$#', $uri) && $method === 'POST') {
    require_once __DIR__ . '/app/Controllers/PreapprovalController.php';
    $controller = new Controllers\PreapprovalController();
    $controller->create();
    exit;
}

// GET /api/preapproval - Listar assinaturas
if (preg_match('#^/api/preapproval/?$#', $uri) && $method === 'GET') {
    require_once __DIR__ . '/app/Controllers/PreapprovalController.php';
    $controller = new Controllers\PreapprovalController();
    $controller->list();
    exit;
}

// GET /api/preapproval/{id} - Consultar assinatura
if (preg_match('#^/api/preapproval/([a-fA-F0-9]+)/?$#', $uri, $matches) && $method === 'GET') {
    require_once __DIR__ . '/app/Controllers/PreapprovalController.php';
    $controller = new Controllers\PreapprovalController();
    $controller->show($matches[1]);
    exit;
}

// PUT /api/preapproval/{id} - Atualizar assinatura
if (preg_match('#^/api/preapproval/([a-fA-F0-9]+)/?$#', $uri, $matches) && ($method === 'PUT' || $method === 'PATCH')) {
    require_once __DIR__ . '/app/Controllers/PreapprovalController.php';
    $controller = new Controllers\PreapprovalController();
    $controller->update($matches[1]);
    exit;
}

// POST /api/preapproval/{id}/pay - Gerar pagamento da assinatura
if (preg_match('#^/api/preapproval/([a-fA-F0-9]+)/pay/?$#', $uri, $matches) && $method === 'POST') {
    require_once __DIR__ . '/app/Controllers/PreapprovalController.php';
    $controller = new Controllers\PreapprovalController();
    $controller->generatePayment($matches[1]);
    exit;
}

// --- API de Pagamentos ---

// POST /api/preferences - Criar preferência (fluxo Mercado Pago)
if (preg_match('#^/api/preferences/?$#', $uri) && $method === 'POST') {
    require_once __DIR__ . '/app/Controllers/PaymentController.php';
    $controller = new Controllers\PaymentController();
    $controller->createPreference();
    exit;
}

// GET /api/preferences/{id} - Consultar preferência
if (preg_match('#^/api/preferences/([a-zA-Z0-9_-]+)/?$#', $uri, $matches) && $method === 'GET') {
    $preferences = readJsonFile('preferences.json');
    if (isset($preferences[$matches[1]])) {
        jsonResponse($preferences[$matches[1]]);
    } else {
        jsonResponse(['error' => 'Preferência não encontrada.', 'id' => $matches[1]], 404);
    }
    exit;
}

if (preg_match('#^/api/payments/?$#', $uri) && $method === 'POST') {
    require_once __DIR__ . '/app/Controllers/PaymentController.php';
    $controller = new Controllers\PaymentController();
    $controller->create();
    exit;
}

if (preg_match('#^/api/payments/([a-zA-Z0-9_-]+)/?$#', $uri, $matches) && $method === 'GET') {
    require_once __DIR__ . '/app/Controllers/PaymentController.php';
    $controller = new Controllers\PaymentController();
    $controller->show($matches[1]);
    exit;
}

// PUT /api/payments/{id} - Atualizar pagamento (MP usa PUT para capture/cancel)
if (preg_match('#^/api/payments/([a-zA-Z0-9_-]+)/?$#', $uri, $matches) && ($method === 'PUT' || $method === 'PATCH')) {
    require_once __DIR__ . '/app/Controllers/PaymentController.php';
    $input = getJsonInput();
    $controller = new Controllers\PaymentController();
    // Se status=cancelled, cancelar; se captured=true, capturar
    if (($input['status'] ?? '') === 'cancelled') {
        $controller->cancel($matches[1]);
    } else {
        $controller->capture($matches[1]);
    }
    exit;
}

if (preg_match('#^/api/payments/([a-zA-Z0-9_-]+)/capture/?$#', $uri, $matches) && $method === 'POST') {
    require_once __DIR__ . '/app/Controllers/PaymentController.php';
    $controller = new Controllers\PaymentController();
    $controller->capture($matches[1]);
    exit;
}

if (preg_match('#^/api/payments/([a-zA-Z0-9_-]+)/cancel/?$#', $uri, $matches) && $method === 'POST') {
    require_once __DIR__ . '/app/Controllers/PaymentController.php';
    $controller = new Controllers\PaymentController();
    $controller->cancel($matches[1]);
    exit;
}

if (preg_match('#^/api/payments/([a-zA-Z0-9_-]+)/refund/?$#', $uri, $matches) && $method === 'POST') {
    require_once __DIR__ . '/app/Controllers/PaymentController.php';
    $controller = new Controllers\PaymentController();
    $controller->refund($matches[1]);
    exit;
}

if (preg_match('#^/api/payments/?$#', $uri) && $method === 'GET') {
    require_once __DIR__ . '/app/Controllers/PaymentController.php';
    $controller = new Controllers\PaymentController();
    $controller->list();
    exit;
}

// --- Configuração de Webhook ---
if (preg_match('#^/api/webhooks/?$#', $uri) && $method === 'POST') {
    require_once __DIR__ . '/app/Controllers/WebhookController.php';
    $controller = new Controllers\WebhookController();
    $controller->register();
    exit;
}

if (preg_match('#^/api/webhooks/?$#', $uri) && $method === 'GET') {
    require_once __DIR__ . '/app/Controllers/WebhookController.php';
    $controller = new Controllers\WebhookController();
    $controller->list();
    exit;
}

if (preg_match('#^/api/webhooks/([a-zA-Z0-9_-]+)/?$#', $uri, $matches) && $method === 'DELETE') {
    require_once __DIR__ . '/app/Controllers/WebhookController.php';
    $controller = new Controllers\WebhookController();
    $controller->delete($matches[1]);
    exit;
}

// --- Webhook Log ---
if (preg_match('#^/api/webhook-logs/?$#', $uri) && $method === 'GET') {
    require_once __DIR__ . '/app/Controllers/WebhookController.php';
    $controller = new Controllers\WebhookController();
    $controller->logs();
    exit;
}

// --- Simulador Manual (forçar status) ---
if (preg_match('#^/api/simulate/?$#', $uri) && $method === 'POST') {
    require_once __DIR__ . '/app/Controllers/SimulatorController.php';
    $controller = new Controllers\SimulatorController();
    $controller->simulate();
    exit;
}

// --- Regras de Simulação ---
if (preg_match('#^/api/rules/?$#', $uri) && $method === 'POST') {
    require_once __DIR__ . '/app/Controllers/SimulatorController.php';
    $controller = new Controllers\SimulatorController();
    $controller->createRule();
    exit;
}

if (preg_match('#^/api/rules/?$#', $uri) && $method === 'GET') {
    require_once __DIR__ . '/app/Controllers/SimulatorController.php';
    $controller = new Controllers\SimulatorController();
    $controller->listRules();
    exit;
}

if (preg_match('#^/api/rules/([a-zA-Z0-9_-]+)/?$#', $uri, $matches) && $method === 'DELETE') {
    require_once __DIR__ . '/app/Controllers/SimulatorController.php';
    $controller = new Controllers\SimulatorController();
    $controller->deleteRule($matches[1]);
    exit;
}

// --- Dashboard (Frontend) ---
if ($uri === '/' || $uri === '/dashboard') {
    require_once __DIR__ . '/app/Views/dashboard.php';
    exit;
}

// --- Recurring Charges Page (simula baixas automáticas recorrentes) ---
if ($uri === '/recurring' || $uri === '/recurring/') {
    require_once __DIR__ . '/app/Views/recurring.php';
    exit;
}

// --- API Recurring: Search by external_reference ---
if (preg_match('#^/api/recurring/search/?$#', $uri) && $method === 'GET') {
    require_once __DIR__ . '/app/Controllers/PreapprovalController.php';
    $controller = new Controllers\PreapprovalController();
    $controller->searchByExternalReference();
    exit;
}

// --- API Recurring: Charge (gerar cobrança e enviar webhook) ---
if (preg_match('#^/api/recurring/charge/?$#', $uri) && $method === 'POST') {
    require_once __DIR__ . '/app/Controllers/PreapprovalController.php';
    $controller = new Controllers\PreapprovalController();
    $controller->chargeRecurring();
    exit;
}

// --- Checkout Page (simula página de pagamento do gateway) ---
if (preg_match('#^/checkout/([a-zA-Z0-9_-]+)/?$#', $uri, $matches)) {
    require_once __DIR__ . '/app/Views/checkout.php';
    exit;
}

// --- PIX Payment Page (simula página de pagamento PIX do MP) ---
if (preg_match('#^/pix/([a-zA-Z0-9_-]+)/?$#', $uri, $matches)) {
    $pixPaymentId = $matches[1];
    require_once __DIR__ . '/app/Views/pix_checkout.php';
    exit;
}

// --- PIX confirm (callback para marcar pagamento como pago) ---
if (preg_match('#^/pix/([a-zA-Z0-9_-]+)/confirm/?$#', $uri, $matches) && $method === 'POST') {
    require_once __DIR__ . '/app/Controllers/PaymentController.php';
    $controller = new Controllers\PaymentController();
    $controller->confirmPix($matches[1]);
    exit;
}

// --- Subscription Checkout Page (simula página de checkout de assinatura) ---
if (preg_match('#^/subscription/checkout/(plan/)?([a-fA-F0-9]+)/?$#', $uri, $matches)) {
    $isPlan = !empty($matches[1]);
    $subscriptionId = $matches[2];
    require_once __DIR__ . '/app/Views/subscription_checkout.php';
    exit;
}

// --- Checkout Process (processar pagamento do checkout) ---
if (preg_match('#^/checkout/([a-zA-Z0-9_-]+)/process/?$#', $uri, $matches) && $method === 'POST') {
    require_once __DIR__ . '/app/Controllers/PaymentController.php';
    $controller = new Controllers\PaymentController();
    $controller->processCheckout($matches[1]);
    exit;
}

// --- Endpoint de teste para receber webhook ---
if (preg_match('#^/api/test-webhook-receiver/?$#', $uri) && $method === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true);
    $logFile = __DIR__ . '/logs/received_webhooks.json';
    
    $existing = [];
    if (file_exists($logFile)) {
        $existing = json_decode(file_get_contents($logFile), true) ?? [];
    }
    
    $existing[] = [
        'received_at' => date('Y-m-d H:i:s'),
        'payload' => $payload,
    ];
    
    // Manter apenas os últimos 100
    $existing = array_slice($existing, -100);
    file_put_contents($logFile, json_encode($existing, JSON_PRETTY_PRINT));
    
    jsonResponse(['status' => 'received'], 200);
    exit;
}

if (preg_match('#^/api/test-webhook-receiver/?$#', $uri) && $method === 'GET') {
    $logFile = __DIR__ . '/logs/received_webhooks.json';
    $data = [];
    if (file_exists($logFile)) {
        $data = json_decode(file_get_contents($logFile), true) ?? [];
    }
    jsonResponse(array_reverse($data));
    exit;
}

// --- 404 ---
http_response_code(404);
jsonResponse([
    'error' => 'Endpoint não encontrado',
    'uri' => $uri,
    'original_uri' => $originalUri,
    'method' => $method,
    'hint' => 'Rotas aceitas: /v1/payments, /preapproval, /checkout/preferences, /api/payments, /api/preapproval, /api/preferences',
    'docs' => 'Acesse / para o dashboard ou consulte o README.md',
], 404);
