<?php
/**
 * Debug: Inspeciona matrícula #58 em prod
 * Uso: php debug_matricula_58_prod.php
 */

// Carregar config de DB corretamente
$root = dirname(__DIR__);
require_once __DIR__ . '/config/database.php';

if (!isset($pdo) || !$pdo) {
    $pdo = require __DIR__ . '/config/database.php';
}
if (!$pdo) {
    die("❌ Erro ao conectar ao banco\n");
}

echo "✅ Conectado ao banco\n\n";

$matriculaId = 58;

// ============ MATRÍCULA ============
echo "📋 MATRÍCULA #$matriculaId\n";
echo str_repeat("=", 50) . "\n";
$sql = "SELECT m.*, sm.codigo AS status_codigo, sm.nome AS status_nome
        FROM matriculas m
        LEFT JOIN status_matricula sm ON sm.id = m.status_id
        WHERE m.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$matriculaId]);
$matricula = $stmt->fetch(PDO::FETCH_ASSOC);

if ($matricula) {
    printf("ID: %d\n", $matricula['id']);
    printf("Status ID: %s\n", $matricula['status_id'] ?? 'N/A');
    printf("Status: %s - %s\n", $matricula['status_codigo'] ?? 'N/A', $matricula['status_nome'] ?? 'N/A');
    printf("Data Matrícula: %s\n", $matricula['data_matricula'] ?? 'N/A');
    printf("Data Início: %s\n", $matricula['data_inicio'] ?? 'N/A');
    printf("Data Vencimento: %s\n", $matricula['data_vencimento'] ?? 'N/A');
    printf("Plano: %d\n", $matricula['plano_id'] ?? 'N/A');
    printf("Aluno: %d\n", $matricula['aluno_id'] ?? 'N/A');
    printf("Valor: %.2f\n", $matricula['valor'] ?? 0);
} else {
    echo "❌ Matrícula #$matriculaId NÃO ENCONTRADA\n";
    exit(1);
}

// ============ ASSINATURAS ============
echo "\n\n📋 ASSINATURAS DA MATRÍCULA #$matriculaId\n";
echo str_repeat("=", 50) . "\n";
$sql = "SELECT id, gateway_id, gateway_assinatura_id, status_gateway, status_id, 
               criado_em, atualizado_em
        FROM assinaturas WHERE matricula_id = ? ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$matriculaId]);
$assinaturas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];

printf("Total: %d assinaturas\n\n", count($assinaturas));

foreach ($assinaturas as $ass) {
    printf("  🔹 Assinatura ID: %d\n", $ass['id']);
    printf("     Gateway: %s\n", $ass['gateway_id'] ?? 'N/A');
    printf("     Gateway Subscription ID: %s\n", $ass['gateway_assinatura_id'] ?? 'N/A');
    printf("     Status Gateway: %s\n", $ass['status_gateway'] ?? 'N/A');
    printf("     Status ID: %s\n", $ass['status_id'] ?? 'N/A');
    printf("     Criado: %s\n", $ass['criado_em'] ?? 'N/A');
    printf("     Atualizado: %s\n\n", $ass['atualizado_em'] ?? 'N/A');
}

// ============ PAGAMENTOS MERCADOPAGO ============
echo "\n📋 PAGAMENTOS MERCADOPAGO DA MATRÍCULA #$matriculaId\n";
echo str_repeat("=", 50) . "\n";
$sql = "SELECT id, tenant_id, matricula_id, payment_id, external_reference,
           status, status_detail, transaction_amount, date_created, created_at
        FROM pagamentos_mercadopago 
        WHERE matricula_id = ? 
    ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$matriculaId]);
$pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];

printf("Total: %d pagamentos\n\n", count($pagamentos));

foreach ($pagamentos as $pag) {
    printf("  💳 Payment Record ID: %d\n", $pag['id']);
    printf("     Payment ID (MP): %s\n", $pag['payment_id'] ?? 'N/A');
    printf("     Tenant: %s | Matrícula: %s\n", $pag['tenant_id'] ?? 'N/A', $pag['matricula_id'] ?? 'N/A');
    printf("     Status: %s\n", $pag['status'] ?? 'N/A');
    printf("     Status Detail: %s\n", $pag['status_detail'] ?? 'N/A');
    printf("     External Ref: %s\n", $pag['external_reference'] ?? 'N/A');
    printf("     Valor: %.2f\n", $pag['transaction_amount'] ?? 0);
    printf("     Data Criação: %s\n", $pag['date_created'] ?? 'N/A');
    printf("     Registrado em: %s\n\n", $pag['created_at'] ?? 'N/A');
}

// ============ PAGAMENTOS PLANO ============
echo "\n📋 PAGAMENTOS PLANO DA MATRÍCULA #$matriculaId\n";
echo str_repeat("=", 50) . "\n";
$sql = "SELECT id, tenant_id, matricula_id, status_pagamento_id,
           valor, data_pagamento, data_vencimento, created_at
        FROM pagamentos_plano 
        WHERE matricula_id = ? 
        ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$matriculaId]);
$pagamentosPlano = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];

printf("Total: %d registros\n\n", count($pagamentosPlano));

foreach ($pagamentosPlano as $pp) {
    printf("  💰 ID: %d\n", $pp['id']);
    printf("     Tenant: %s | Matrícula: %s\n", $pp['tenant_id'] ?? 'N/A', $pp['matricula_id'] ?? 'N/A');
    printf("     Status Pagamento ID: %s\n", $pp['status_pagamento_id'] ?? 'N/A');
    printf("     Valor: %.2f\n", $pp['valor'] ?? 0);
    printf("     Data Pagamento: %s\n", $pp['data_pagamento'] ?? 'N/A');
    printf("     Data Vencimento: %s\n", $pp['data_vencimento'] ?? 'N/A');
    printf("     Criado: %s\n\n", $pp['created_at'] ?? 'N/A');
}

echo "\n✅ Debug concluído\n";
