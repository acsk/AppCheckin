<?php
/**
 * Analisar e processar um webhook payment real
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

// Buscar um webhook payment bem-sucedido
$sql = "SELECT id, tipo, payload FROM webhook_payloads_mercadopago WHERE tipo='payment' AND status='sucesso' ORDER BY id LIMIT 1";
$result = $db->query($sql);

if (!$result || $result->num_rows === 0) {
    echo "❌ Nenhum webhook payment encontrado\n";
    exit(1);
}

$webhook = $result->fetch_assoc();
$payload = json_decode($webhook['payload'], true);

echo "📋 WEBHOOK DE PAGAMENTO #" . $webhook['id'] . ":\n";
echo "   Tipo: " . $webhook['tipo'] . "\n";
echo "   Payload:\n";
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// ID do payment
$payment_id = $payload['data']['id'] ?? null;

if (!$payment_id) {
    echo "❌ Não há data.id no webhook\n";
    exit(1);
}

echo "🔍 Payment ID encontrado: {$payment_id}\n\n";

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
echo "🔑 DADOS IMPORTANTES:\n";
echo "   ID: " . $payment['id'] . "\n";
echo "   Status: " . $payment['status'] . "\n";
echo "   External Reference: " . ($payment['external_reference'] ?? 'NULL') . "\n";
echo "   Transaction Amount: " . $payment['transaction_amount'] . "\n";
echo "   Payer Email: " . ($payment['payer']['email'] ?? 'N/A') . "\n";
echo "   Date Approved: " . ($payment['date_approved'] ?? 'N/A') . "\n";
echo "\n";

// Extrair external_reference
$external_ref = $payment['external_reference'] ?? null;

if (!$external_ref) {
    echo "❌ Payment não tem external_reference\n";
    echo "   Sem external_reference, não conseguimos saber a qual matrícula/assinatura pertence!\n";
    exit(1);
}

echo "✅ External Reference: {$external_ref}\n\n";

// Tentar decodificar external_reference
echo "📝 Analisando external_reference:\n";
echo "   Formato esperado: MAT-{matriculaId}-{timestamp} ou PAC-{contratoId}-{timestamp}\n";

$parts = explode('-', $external_ref);
if (count($parts) >= 2) {
    $prefix = $parts[0];
    $id_value = $parts[1] ?? null;
    
    echo "   Prefixo: {$prefix}\n";
    echo "   ID: {$id_value}\n";
    
    if ($prefix === 'MAT' && $id_value) {
        echo "   → Tipo: MATRÍCULA\n";
        echo "   → Matrícula ID: {$id_value}\n";
        
        // Buscar matrícula
        $sql_m = "SELECT id, aluno_id FROM matriculas WHERE id = ? LIMIT 1";
        $stmt_m = $db->prepare($sql_m);
        $stmt_m->bind_param("i", $id_value);
        $stmt_m->execute();
        $result_m = $stmt_m->get_result();
        
        if ($result_m && $result_m->num_rows > 0) {
            $matricula = $result_m->fetch_assoc();
            echo "\n✅ MATRÍCULA ENCONTRADA:\n";
            echo "   ID: " . $matricula['id'] . "\n";
            echo "   Aluno ID: " . $matricula['aluno_id'] . "\n";
            
            // Agora precisamos criar o pagamento_plano
            echo "\n💾 Verificando se já existe pagamento_plano...\n";
            
            $sql_pag = "
                SELECT pp.id, pp.status_pagamento_id, pp.valor, sp.nome as status
                FROM pagamentos_plano pp
                LEFT JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
                WHERE pp.matricula_id = ? AND pp.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
                ORDER BY pp.created_at DESC
                LIMIT 1
            ";
            
            $stmt_pag = $db->prepare($sql_pag);
            $stmt_pag->bind_param("i", $id_value);
            $stmt_pag->execute();
            $result_pag = $stmt_pag->get_result();
            
            if ($result_pag && $result_pag->num_rows > 0) {
                $pagamento = $result_pag->fetch_assoc();
                echo "⚠️ PAGAMENTO_PLANO JÁ EXISTE:\n";
                echo "   ID: " . $pagamento['id'] . "\n";
                echo "   Status: " . $pagamento['status'] . "\n";
                echo "   Valor: " . $pagamento['valor'] . "\n";
            } else {
                echo "❌ NENHUM PAGAMENTO_PLANO ENCONTRADO!\n";
                echo "   O sistema precisaria criar um, mas isso exige conhecer:\n";
                echo "   - plano_id\n";
                echo "   - valor\n";
                echo "   - data_vencimento\n";
                echo "   Essa informação deveria vir de uma assinatura ou ser extraída de algum lugar.\n";
                
                // Procurar assinatura
                $sql_ass = "
                    SELECT id, plano_id, valor, proxima_cobranca
                    FROM assinaturas
                    WHERE matricula_id = ?
                    LIMIT 1
                ";
                $stmt_ass = $db->prepare($sql_ass);
                $stmt_ass->bind_param("i", $id_value);
                $stmt_ass->execute();
                $result_ass = $stmt_ass->get_result();
                
                if ($result_ass && $result_ass->num_rows > 0) {
                    $assinatura = $result_ass->fetch_assoc();
                    echo "\n✅ ASSINATURA ENCONTRADA:\n";
                    echo "   ID: " . $assinatura['id'] . "\n";
                    echo "   Plano ID: " . $assinatura['plano_id'] . "\n";
                    echo "   Valor: " . $assinatura['valor'] . "\n";
                    echo "   Próxima Cobrança: " . $assinatura['proxima_cobranca'] . "\n";
                    echo "\n💡 AÇÃO NECESSÁRIA:\n";
                    echo "   Usar os dados da assinatura para criar o pagamento_plano:\n";
                    echo "   - matricula_id = {$id_value}\n";
                    echo "   - plano_id = " . $assinatura['plano_id'] . "\n";
                    echo "   - valor = " . $assinatura['valor'] . "\n";
                    echo "   - data_vencimento = " . $assinatura['proxima_cobranca'] . "\n";
                    echo "   - status_pagamento_id = 1 (Aguardando)\n";
                } else {
                    echo "\n❌ NENHUMA ASSINATURA ENCONTRADA PARA ESSA MATRÍCULA!\n";
                }
            }
            
        } else {
            echo "❌ Matrícula {$id_value} não encontrada no banco!\n";
        }
        
    } else {
        echo "   → Tipo: DESCONHECIDO ({$prefix})\n";
    }
} else {
    echo "   ❌ Formato inválido, não consegue decodificar\n";
}

$db->close();
