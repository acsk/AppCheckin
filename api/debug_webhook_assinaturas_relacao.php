<?php
/**
 * Debug: Analisar relação entre webhooks subscription_authorized_payment e assinaturas
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

echo "═══════════════════════════════════════════════════════════════════════\n";
echo "ANÁLISE: Webhooks subscription_authorized_payment vs Assinaturas\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

// 1. Buscar últimos webhooks subscription_authorized_payment
echo "1️⃣ ÚLTIMOS WEBHOOKS subscription_authorized_payment:\n";
echo "───────────────────────────────────────────────────────────────\n\n";

$sql = "
    SELECT 
        id,
        tipo,
        preapproval_id,
        payment_id,
        payload,
        created_at
    FROM webhook_payloads_mercadopago
    WHERE tipo = 'subscription_authorized_payment'
    ORDER BY created_at DESC
    LIMIT 5
";

$result = $db->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $payload = json_decode($row['payload'], true);
        echo "Webhook #{$row['id']} ({$row['created_at']}):\n";
        echo "  - preapproval_id: {$row['preapproval_id']}\n";
        echo "  - payment_id: {$row['payment_id']}\n";
        echo "  - entity no payload: " . ($payload['entity'] ?? 'N/A') . "\n";
        echo "  - data.id no payload: " . ($payload['data']['id'] ?? 'N/A') . "\n";
        echo "\n";
    }
} else {
    echo "❌ Nenhum webhook encontrado\n\n";
}

// 2. Buscar todas as assinaturas
echo "2️⃣ TODAS AS ASSINATURAS:\n";
echo "───────────────────────────────────────────────────────────────\n\n";

$sql = "
    SELECT 
        id,
        matricula_id,
        gateway_assinatura_id,
        external_reference,
        status_gateway
    FROM assinaturas
    ORDER BY id
";

$result = $db->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Assinatura #{$row['id']}:\n";
        echo "  - matricula_id: {$row['matricula_id']}\n";
        echo "  - gateway_assinatura_id (preapproval): {$row['gateway_assinatura_id']}\n";
        echo "  - external_reference: {$row['external_reference']}\n";
        echo "  - status_gateway: {$row['status_gateway']}\n";
        echo "\n";
    }
} else {
    echo "❌ Nenhuma assinatura encontrada\n\n";
}

// 3. Análise
echo "3️⃣ DIAGNÓSTICO:\n";
echo "───────────────────────────────────────────────────────────────\n\n";

// Contar webhooks sem conexão
$sql = "
    SELECT COUNT(*) as total
    FROM webhook_payloads_mercadopago
    WHERE tipo = 'subscription_authorized_payment'
    AND preapproval_id IS NULL
";

$result = $db->query($sql);
$row = $result->fetch_assoc();
$webhooks_sem_conexao = $row['total'];

// Contar assinaturas sem gateway_assinatura_id
$sql = "
    SELECT COUNT(*) as total
    FROM assinaturas
    WHERE gateway_assinatura_id IS NULL
";

$result = $db->query($sql);
$row = $result->fetch_assoc();
$assinaturas_sem_gateway = $row['total'];

echo "❌ Webhooks subscription_authorized_payment: {$webhooks_sem_conexao}\n";
echo "   - Todos têm preapproval_id = NULL (não conseguem conectar às assinaturas!)\n\n";

echo "❌ Assinaturas sem gateway_assinatura_id: {$assinaturas_sem_gateway}\n";
echo "   - Não têm preapproval_id registrado no servidor\n\n";

echo "🔍 PROBLEMA IDENTIFICADO:\n";
echo "   O webhook subscription_authorized_payment NÃO TEM preapproval_id\n";
echo "   Logo, o sistema não consegue saber qual assinatura criou aquele pagamento!\n\n";

echo "💡 SOLUÇÃO NECESSÁRIA:\n";
echo "   1. Extrair preapproval_id do payload do webhook\n";
echo "   2. OU: Adicionar preapproval_id manualmente na salva do webhook\n";
echo "   3. Buscar assinatura por gateway_assinatura_id = preapproval_id\n";
echo "   4. Criar pagamento_plano ligado àquela assinatura\n";

$db->close();
