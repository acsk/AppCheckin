<?php
/**
 * Script para testar webhook simulado do Mercado Pago - Versão direta
 * Sem dependência de cURL externo, executa localmente via URL interna
 * 
 * Uso:
 * php test_webhook_direct.php [external_reference] [status] [payment_type]
 * 
 * Exemplos:
 * php test_webhook_direct.php MAT-158-1771524282 approved credit_card
 * php test_webhook_direct.php MAT-1-1708380000 approved pix
 */

// Parâmetros
$externalReference = $argv[1] ?? 'MAT-1-' . time();
$status = $argv[2] ?? 'approved';
$paymentType = $argv[3] ?? 'credit_card';

echo "\n╔════════════════════════════════════════════════════════════╗\n";
echo "║    TESTE WEBHOOK MERCADO PAGO (Execução Direta)           ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

echo "📋 Parâmetros:\n";
echo "   External Reference: {$externalReference}\n";
echo "   Status: {$status}\n";
echo "   Payment Type: {$paymentType}\n\n";

echo "🔄 Tentando formas de conexão...\n\n";

// FORMA 1: Testar via curl com 127.0.0.1:80 (HTTP padrão)
echo "1️⃣ Tentando via 127.0.0.1:80 (HTTP padrão)...\n";

$queryParams = [
    'external_reference' => $externalReference,
    'status' => $status,
    'payment_type' => $paymentType
];

$testUrl = "http://127.0.0.1/api/webhooks/mercadopago/test?" . http_build_query($queryParams);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $testUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo "   ❌ Falhou: {$curlError}\n\n";
    
    // FORMA 2: Tentar sem a porta explícita
    echo "2️⃣ Tentando via localhost (sem porta)...\n";
    $testUrl = "http://localhost/api/webhooks/mercadopago/test?" . http_build_query($queryParams);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $testUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        echo "   ❌ Falhou: {$curlError}\n\n";
        
        // FORMA 3: Ver qual está rodando no servidor
        echo "3️⃣ Verificando processos do servidor...\n";
        $processes = shell_exec("ps aux | grep -E 'apache|nginx|php|httpd' | grep -v grep | head -10") ?? "Não encontrado";
        echo "Processos encontrados:\n{$processes}\n";
        
        echo "4️⃣ Tentando acesso direto ao código PHP...\n";
        // Incluir a aplicação e chamar diretamente
        try {
            // Simular request global
            $_GET = $queryParams;
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/api/webhooks/mercadopago/test';
            $_SERVER['SCRIPT_NAME'] = '/api/webhooks/mercadopago/test';
            
            require_once __DIR__ . '/vendor/autoload.php';
            
            // Criar request manual
            $db = require __DIR__ . '/config/database.php';
            
            // Instanciar controller
            $controller = new \App\Controllers\MercadoPagoWebhookController();
            
            // Mock objects para Slim
            $request = new class {
                private $query = [];
                public function __construct($q) { $this->query = $q; }
                public function getQueryParams() { return $this->query; }
            }($queryParams);
            
            $response = new class {
                private $body = '';
                private $status = 200;
                public function getBody() { return new class($this->body) {
                    private $content;
                    public function __construct($c) { $this->content = $c; }
                    public function __toString() { return $this->content; }
                }($this->body); }
                public function withStatus($s) { $this->status = $s; return $this; }
                public function getStatus() { return $this->status; }
            };
            
            // Tentar chamar método
            $result = $controller->simularWebhook($request, $response);
            $responseBody = (string) $result->getBody();
            $statusCode = $result->getStatus();
            
            echo "✅ Resposta HTTP: {$statusCode}\n\n";
            
            $responseData = json_decode($responseBody, true);
            if ($responseData) {
                echo "📊 Resposta da API:\n";
                echo json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
                
                if (!empty($responseData['success'])) {
                    echo "✅ ✅ ✅ WEBHOOK SIMULADO COM SUCESSO! ✅ ✅ ✅\n";
                }
            }
        } catch (\Exception $e) {
            echo "❌ Erro ao chamar controller: " . $e->getMessage() . "\n";
        }
        
        exit(1);
    }
}

// Se chegou aqui, a requisição funcionou
echo "✅ Resposta HTTP: {$httpCode}\n\n";

$responseData = json_decode($response, true);

if ($responseData) {
    echo "📊 Resposta da API:\n";
    echo json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    if (!empty($responseData['success'])) {
        echo "✅ ✅ ✅ WEBHOOK SIMULADO COM SUCESSO! ✅ ✅ ✅\n\n";
        
        if (!empty($responseData['data'])) {
            echo "📌 Detalhes:\n";
            foreach ($responseData['data'] as $key => $value) {
                echo "   {$key}: {$value}\n";
            }
        }
    } else {
        echo "⚠️  Resposta retornou success=false\n";
        if (!empty($responseData['error'])) {
            echo "   Erro: {$responseData['error']}\n";
        }
    }
} else {
    echo "❌ Erro ao decodificar resposta JSON\n";
    echo "Resposta bruta:\n{$response}\n";
}

echo "\n";
?>

