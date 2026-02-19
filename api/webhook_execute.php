#!/usr/bin/env php
<?php
/**
 * Executar webhook do Mercado Pago direto via CLI
 * SEM dependências do Composer
 * 
 * Uso: php webhook_execute.php [external_reference] [status] [payment_type]
 * 
 * Exemplos:
 *   php webhook_execute.php MAT-158-1771524282 approved credit_card
 *   php webhook_execute.php MAT-158-1771524282
 *   php webhook_execute.php
 */

// Carregar .env manualmente (sem Composer)
function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') === false || $line[0] === '#') continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, '\'"');
        if (!isset($_ENV[$key]) && !isset($_SERVER[$key])) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

loadEnv(__DIR__ . '/.env');

// Carregar variáveis de ambiente
$host = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? getenv('DB_HOST') ?? 'localhost';
$dbname = $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? getenv('DB_NAME') ?? 'appcheckin';
$user = $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? getenv('DB_USER') ?? 'root';
$pass = $_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? getenv('DB_PASS') ?? '';

// Conectar ao banco direto
try {
    $db = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '-03:00'"
        ]
    );
    $db->exec("SET CHARACTER SET utf8mb4");
    try {
        $db->exec("SET time_zone = 'America/Sao_Paulo'");
    } catch (PDOException $e) {
        $db->exec("SET time_zone = '-03:00'");
    }
} catch (PDOException $e) {
    echo "❌ Erro ao conectar ao banco: " . $e->getMessage() . "\n\n";
    exit(1);
}

$db = $GLOBALS['db'] ?? require __DIR__ . '/config/database.php';

// Pegar parâmetros da linha de comando
$externalReference = $argv[1] ?? 'MAT-1-' . time();
$status = $argv[2] ?? 'approved';
$paymentType = $argv[3] ?? 'credit_card';

echo "\n";
echo "╔════════════════════════════════════════╗\n";
echo "║     EXECUÇÃO WEBHOOK MERCADO PAGO      ║\n";
echo "╚════════════════════════════════════════╝\n\n";

echo "🔹 Parâmetros:\n";
echo "   External Reference: $externalReference\n";
echo "   Status: $status\n";
echo "   Payment Type: $paymentType\n\n";

try {
    // Simular payment_id
    $paymentId = mt_rand(1000000000, 9999999999);
    
    // Dados do pagamento
    $dadosPagamento = [
        'id' => (string)$paymentId,
        'status' => $status,
        'external_reference' => $externalReference,
        'payment_type_id' => $paymentType === 'pix' ? 'prompt_payment' : 'visa',
        'transaction_amount' => 99.90,
        'currency_id' => 'BRL'
    ];
    
    echo "🔹 Payment ID: $paymentId\n\n";
    
    // Buscar tenant_id pela matrícula
    $tenantId = 1;
    if (preg_match('/^MAT-(\d+)-/', $externalReference, $matches)) {
        $matriculaId = (int)$matches[1];
        
        $stmt = $db->prepare("SELECT tenant_id FROM matriculas WHERE id = ? LIMIT 1");
        $stmt->execute([$matriculaId]);
        $matData = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($matData) {
            $tenantId = (int)$matData['tenant_id'];
            echo "✅ Matrícula #$matriculaId encontrada (Tenant: $tenantId)\n";
        } else {
            echo "⚠️  Matrícula #$matriculaId NÃO ENCONTRADA\n";
        }
    }
    
    // Guardar webhook no banco
    echo "\n🔹 Gravando webhook no banco...\n";
    $stmt = $db->prepare("
        INSERT INTO webhook_payloads_mercadopago
        (tenant_id, tipo, data_id, payment_id, payload, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $tenantId,
        'payment',
        (string)$paymentId,
        (string)$paymentId,
        json_encode($dadosPagamento),
        $status
    ]);
    echo "✅ Webhook_payloads_mercadopago registrado\n";
    
    // Processar o pagamento (atualizar status)
    if (preg_match('/^MAT-(\d+)-/', $externalReference, $matches)) {
        $matriculaId = (int)$matches[1];
        
        echo "\n🔹 Atualizando assinatura...\n";
        // Buscar assinatura
        $stmt = $db->prepare("SELECT id FROM assinaturas WHERE matricula_id = ? AND tenant_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$matriculaId, $tenantId]);
        $assinatura = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($assinatura) {
            // Atualizar status
            $statusCode = $status === 'approved' ? 'ativa' : 'pendente';
            $stmt = $db->prepare("SELECT id FROM assinatura_status WHERE codigo = ?");
            $stmt->execute([$statusCode]);
            $statusId = $stmt->fetchColumn() ?: 1;
            
            $stmt = $db->prepare("
                UPDATE assinaturas 
                SET status_gateway = ?, status_id = ?, atualizado_em = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$status, $statusId, $assinatura['id'], $tenantId]);
            echo "✅ Assinatura #" . $assinatura['id'] . " → status_gateway: '$status', status_id: $statusId\n";
        } else {
            echo "⚠️  Nenhuma assinatura encontrada para matrícula #$matriculaId\n";
        }
        
        // Se aprovado, ativar matrícula
        if ($status === 'approved') {
            echo "\n🔹 Ativando matrícula...\n";
            $stmt = $db->prepare("SELECT id FROM status_matricula WHERE codigo = 'ativa'");
            $stmt->execute();
            $matStatusId = $stmt->fetchColumn() ?: 2;
            
            $stmt = $db->prepare("
                UPDATE matriculas 
                SET status_id = ?, updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$matStatusId, $matriculaId, $tenantId]);
            echo "✅ Matrícula #$matriculaId → status_id: $matStatusId (ativa)\n";
        }
    }
    
    echo "\n";
    echo "╔════════════════════════════════════════╗\n";
    echo "║   ✅ WEBHOOK PROCESSADO COM SUCESSO! ║\n";
    echo "╚════════════════════════════════════════╝\n\n";
    
} catch (Exception $e) {
    echo "\n";
    echo "╔════════════════════════════════════════╗\n";
    echo "║            ❌ ERRO!                    ║\n";
    echo "╚════════════════════════════════════════╝\n\n";
    echo "Erro: " . $e->getMessage() . "\n";
    echo "Stack: " . $e->getTraceAsString() . "\n\n";
    exit(1);
}

exit(0);
?>
