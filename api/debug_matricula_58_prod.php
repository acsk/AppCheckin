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
$sql = "SELECT id, aluno_id, plano_id, tenant_id, data_matricula, data_inicio, 
               data_vencimento, status, valor, mes_entrada, ano_entrada
        FROM matriculas WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$matriculaId]);
$matricula = $stmt->fetch(PDO::FETCH_ASSOC);

if ($matricula) {
    printf("ID: %d\n", $matricula['id']);
    printf("Status: %s\n", $matricula['status'] ?? 'N/A');
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
$sql = "SELECT id, payment_id, status, status_detail, status_code,
               date_created, data_processada, valor, tipo_pagamento
        FROM pagamentos_mercadopago 
        WHERE matricula_id = ? 
        ORDER BY date_created DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$matriculaId]);
$pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];

printf("Total: %d pagamentos\n\n", count($pagamentos));

foreach ($pagamentos as $pag) {
    printf("  💳 Payment Record ID: %d\n", $pag['id']);
    printf("     Payment ID (MP): %s\n", $pag['payment_id'] ?? 'N/A');
    printf("     Status: %s (code: %s)\n", 
           $pag['status'] ?? 'N/A', 
           $pag['status_code'] ?? 'N/A');
    printf("     Status Detail: %s\n", $pag['status_detail'] ?? 'N/A');
    printf("     Tipo: %s\n", $pag['tipo_pagamento'] ?? 'N/A');
    printf("     Valor: %.2f\n", $pag['valor'] ?? 0);
    printf("     Data Criação: %s\n", $pag['date_created'] ?? 'N/A');
    printf("     Data Processada: %s\n\n", $pag['data_processada'] ?? 'N/A');
}

// ============ PAGAMENTOS PLANO ============
echo "\n📋 PAGAMENTOS PLANO DA MATRÍCULA #$matriculaId\n";
echo str_repeat("=", 50) . "\n";
$sql = "SELECT id, matricula_id, valor, status, data_pagamento,
               data_vencimento, criado_em
        FROM pagamentos_plano 
        WHERE matricula_id = ? 
        ORDER BY criado_em DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$matriculaId]);
$pagamentosPlano = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];

printf("Total: %d registros\n\n", count($pagamentosPlano));

foreach ($pagamentosPlano as $pp) {
    printf("  💰 ID: %d\n", $pp['id']);
    printf("     Status: %s\n", $pp['status'] ?? 'N/A');
    printf("     Valor: %.2f\n", $pp['valor'] ?? 0);
    printf("     Data Pagamento: %s\n", $pp['data_pagamento'] ?? 'N/A');
    printf("     Data Vencimento: %s\n", $pp['data_vencimento'] ?? 'N/A');
    printf("     Criado: %s\n\n", $pp['criado_em'] ?? 'N/A');
}

echo "\n✅ Debug concluído\n";
