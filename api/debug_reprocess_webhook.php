<?php
/**
 * Reprocessar webhook subscription_authorized_payment direto
 * SEM COMPOSER (compatível com PHP 7.4)
 */

// Carregar .env
$env_file = __DIR__ . '/.env';
$env_vars = [];
if (file_exists($env_file)) {
    foreach (file($env_file) as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === '#') continue;
        
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1], '\'"');
            $env_vars[$key] = $value;
            putenv("$key=$value");
        }
    }
}

// Conectar ao banco
$db = new mysqli(
    $env_vars['DB_HOST'] ?? 'localhost',
    $env_vars['DB_USER'] ?? '',
    $env_vars['DB_PASS'] ?? '',
    $env_vars['DB_NAME'] ?? ''
);

if ($db->connect_error) {
    die("❌ Erro ao conectar: " . $db->connect_error);
}

echo "✅ Conectado ao banco\n\n";

// Buscar webhook #29
$sql = "SELECT id, tipo, payload FROM webhook_payloads_mercadopago WHERE id = 29 LIMIT 1";
$result = $db->query($sql);

if (!$result || $result->num_rows === 0) {
    echo "❌ Webhook #29 não encontrado\n";
    exit(1);
}

$webhook = $result->fetch_assoc();
$payload = json_decode($webhook['payload'], true);

echo "📋 WEBHOOK #29:\n";
echo "   Tipo: " . $webhook['tipo'] . "\n";
echo "   Payload: " . json_encode($payload, JSON_PRETTY_PRINT) . "\n\n";

// ID do payment que vem no webhook
$payment_id = $payload['data']['id'] ?? null;

if (!$payment_id) {
    echo "❌ Não há data.id no webhook\n";
    exit(1);
}

echo "🔍 Tentando buscar payment ID: {$payment_id}\n\n";

// Determinar token MP
$environment = $env_vars['MP_ENVIRONMENT'] ?? 'test';
$token_key = ($environment === 'prod') ? 'MP_ACCESS_TOKEN_PROD' : 'MP_ACCESS_TOKEN_TEST';
$mp_token = $env_vars[$token_key] ?? null;

if (!$mp_token) {
    echo "❌ Token MP não configurado\n";
    exit(1);
}

// Buscar payment no MP
echo "📍 Consultando MP API para payment ID: {$payment_id}\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/v1/payments/{$payment_id}");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $mp_token",
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    echo "❌ Erro ao buscar payment no MP: HTTP {$http_code}\n";
    echo $response . "\n";
    exit(1);
}

$payment = json_decode($response, true);

echo "✅ Payment encontrado no MP!\n\n";
echo "🔑 DADOS DO PAYMENT:\n";
echo "   ID: " . $payment['id'] . "\n";
echo "   Status: " . $payment['status'] . "\n";
echo "   External Reference: " . ($payment['external_reference'] ?? 'NULL') . "\n";
echo "   Transaction Amount: " . ($payment['transaction_amount'] ?? 'N/A') . "\n";
echo "   Payer Email: " . ($payment['payer']['email'] ?? 'N/A') . "\n";
echo "\n";

// Agora vamos tentar conectar à matrícula/assinatura
$external_ref = $payment['external_reference'] ?? null;

if (!$external_ref) {
    echo "❌ Payment não tem external_reference. Sem como conectar à matrícula!\n";
    exit(1);
}

echo "🔗 Tentando encontrar matrícula com external_reference: {$external_ref}\n";

// Buscar matrícula pela external_reference
$sql_matric = "
    SELECT m.id, m.aluno_id, m.classe_id
    FROM matriculas m
    WHERE m.external_reference = ? OR m.id LIKE ?
    LIMIT 1
";

$stmt = $db->prepare($sql_matric);
$search = "%{$external_ref}%";
$stmt->bind_param("ss", $external_ref, $search);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $matricula = $result->fetch_assoc();
    echo "✅ Matrícula encontrada!\n";
    echo "   ID: " . $matricula['id'] . "\n";
    echo "   Aluno ID: " . $matricula['aluno_id'] . "\n\n";
    
    // Agora criar pagamento_plano se não existir
    echo "💾 Verificando se já existe pagamento_plano...\n";
    
    $sql_check = "
        SELECT id FROM pagamentos_plano
        WHERE matricula_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        LIMIT 1
    ";
    
    $stmt_check = $db->prepare($sql_check);
    $stmt_check->bind_param("i", $matricula['id']);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check && $result_check->num_rows > 0) {
        $pag = $result_check->fetch_assoc();
        echo "⚠️ Pagamento recente já existe (ID: " . $pag['id'] . ")\n";
    } else {
        echo "✅ Nenhum pagamento recente encontrado. PRECISARIA CRIAR UM!\n";
        echo "   Mas isso requer mais lógica para saber: plano_id, valor, vencimento, etc.\n";
        echo "   Essa informação deveria vir do webhook ou da assinatura.\n";
    }
    
} else {
    echo "❌ Matrícula não encontrada com external_reference: {$external_ref}\n";
    echo "   Isso significa que o pagamento não consegue ser associado a uma matrícula!\n";
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════════════\n";
echo "RESUMO DO PROBLEMA:\n";
echo "═══════════════════════════════════════════════════════════════════════\n";
echo "✅ Webhook subscription_authorized_payment tem: data.id = {$payment_id}\n";
echo "✅ Consultamos MP e achamos o payment\n";
echo "✅ Payment tem: external_reference = {$external_ref}\n";
echo "✅ Conseguimos encontrar a matrícula\n";
echo "⚠️ FALTA: Saber qual PLANO/VALOR/VENCIMENTO deve ter o pagamento\n";
echo "\nEssa info deveria vir:\n";
echo "1. Do webhook (metadata)\n";
echo "2. Ou da assinatura da matrícula\n";
echo "3. Ou de um campo no payment que indique o plano\n";

$db->close();
