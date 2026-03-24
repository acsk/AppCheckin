<?php
/**
 * Debug rápido: Estado da matrícula 58 em localhost
 */

$pdo = new PDO('mysql:host=127.0.0.1;dbname=api_checkin;charset=utf8mb4', 'root', '');

echo "\n=== MATRÍCULA #58 ===\n\n";

// 1. Dados da matrícula
$stmt = $pdo->query("SELECT * FROM matriculas WHERE id = 58");
$mat = $stmt->fetch(PDO::FETCH_ASSOC);

if ($mat) {
    echo "✅ Matrícula encontrada\n";
    echo "   ID: {$mat['id']}\n";
    echo "   Status ID: {$mat['status_id']}\n";
    echo "   Data Criação: {$mat['created_at']}\n";
} else {
    echo "❌ Matrícula não encontrada\n";
    exit;
}

// 2. Assinaturas
echo "\n=== ASSINATURAS ===\n";
$stmt = $pdo->query("SELECT id, status_gateway, status_id, criado_em FROM assinaturas WHERE matricula_id = 58");
$assinaturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($assinaturas as $ass) {
    echo "   Assinatura #{$ass['id']}: status_gateway={$ass['status_gateway']}, status_id={$ass['status_id']}\n";
}

// 3. Pagamentos no banco local
echo "\n=== PAGAMENTOS NO BANCO LOCAL ===\n";
$stmt = $pdo->query("SELECT id, payment_id, status, date_created FROM pagamentos_mercadopago WHERE matricula_id = 58 ORDER BY date_created DESC");
$pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($pagamentos)) {
    echo "   ❌ Nenhum pagamento encontrado\n";
} else {
    foreach ($pagamentos as $pag) {
        echo "   ID: {$pag['id']}, Payment ID: {$pag['payment_id']}, Status: {$pag['status']}, Data: {$pag['date_created']}\n";
    }
}

echo "\n";
