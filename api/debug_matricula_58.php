<?php
/**
 * Debug: Analisar Matrícula #58 e seus pagamentos
 * 
 * Uso: php debug_matricula_58.php
 */

$root = __DIR__;
require_once $root . '/config/database.php';

if (!isset($pdo)) {
    $pdo = require $root . '/config/database.php';
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "DEBUG: MATRÍCULA #58\n";
echo str_repeat("=", 80) . "\n\n";

// 1. Dados da Matrícula
echo "📋 DADOS DA MATRÍCULA #58:\n";
echo str_repeat("-", 80) . "\n";
$stmt = $pdo->prepare("SELECT * FROM matriculas WHERE id = 58");
$stmt->execute();
$matricula = $stmt->fetch(PDO::FETCH_ASSOC);

if ($matricula) {
    echo "ID: {$matricula['id']}\n";
    echo "Aluno ID: {$matricula['aluno_id']}\n";
    echo "Plano ID: {$matricula['plano_id']}\n";
    echo "Tipo Cobrança: {$matricula['tipo_cobranca']}\n";
    echo "Data Matrícula: {$matricula['data_matricula']}\n";
    echo "Data Início: {$matricula['data_inicio']}\n";
    echo "Data Vencimento: {$matricula['data_vencimento']}\n";
    echo "Valor: {$matricula['valor']}\n";
    echo "Status ID: {$matricula['status_id']}\n";
    echo "Criado em: {$matricula['created_at']}\n";
    echo "Atualizado em: {$matricula['updated_at']}\n";
} else {
    echo "❌ Matrícula #58 não encontrada!\n";
    exit(1);
}

// 2. Assinaturas associadas
echo "\n\n📌 ASSINATURAS ASSOCIADAS:\n";
echo str_repeat("-", 80) . "\n";
$stmt = $pdo->prepare("SELECT * FROM assinaturas WHERE matricula_id = 58");
$stmt->execute();
$assinaturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($assinaturas)) {
    echo "❌ Nenhuma assinatura encontrada para matricula_id=58\n";
} else {
    foreach ($assinaturas as $ass) {
        echo "\n  Assinatura ID: {$ass['id']}\n";
        echo "  Status ID: {$ass['status_id']}\n";
        echo "  Status Gateway: {$ass['status_gateway']}\n";
        echo "  Gateway ID: {$ass['gateway_id']}\n";
        echo "  Gateway Assinatura ID: {$ass['gateway_assinatura_id']}\n";
        echo "  Valor: {$ass['valor']}\n";
        echo "  Tipo Cobrança: {$ass['tipo_cobranca']}\n";
        echo "  Criado em: {$ass['criado_em']}\n";
        echo "  Atualizado em: {$ass['atualizado_em']}\n";
        echo "  External Reference: {$ass['external_reference']}\n";
    }
}

// 3. Pagamentos associados
echo "\n\n💳 PAGAMENTOS MERCADO PAGO:\n";
echo str_repeat("-", 80) . "\n";
$stmt = $pdo->prepare(
    "SELECT id, payment_id, status, status_id, date_created, valor, metodo_pagamento 
     FROM pagamentos_mercadopago 
     WHERE matricula_id = 58 
     ORDER BY date_created DESC"
);
$stmt->execute();
$pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($pagamentos)) {
    echo "❌ Nenhum pagamento registrado para matricula_id=58\n";
} else {
    echo sprintf("%-5s | %-12s | %-15s | %-6s | %-20s | %-20s\n", "ID", "Payment ID", "Status", "ID", "Valor", "Data");
    echo str_repeat("-", 90) . "\n";
    
    foreach ($pagamentos as $pg) {
        $status_indicator = "";
        if ($pg['status_id'] == 6) {
            $status_indicator = " ✅";
        } elseif ($pg['status'] == 'pending') {
            $status_indicator = " ⏳";
        }
        
        echo sprintf(
            "%-5s | %-12s | %-15s | %-6s | %-20s | %-20s%s\n",
            $pg['id'],
            substr($pg['payment_id'], 0, 12),
            $pg['status'],
            $pg['status_id'] ?? 'NULL',
            $pg['valor'],
            date('d/m/Y H:i', strtotime($pg['date_created'])),
            $status_indicator
        );
    }
}

// 4. Status Details
echo "\n\n📊 RESUMO DE STATUS:\n";
echo str_repeat("-", 80) . "\n";

$stmt = $pdo->prepare(
    "SELECT COUNT(*) as total, 
            SUM(CASE WHEN status_id = 6 THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
     FROM pagamentos_mercadopago 
     WHERE matricula_id = 58"
);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Total de pagamentos: {$stats['total']}\n";
echo "✅ Aprovados (status_id=6): {$stats['approved']}\n";
echo "⏳ Pendentes: {$stats['pending']}\n";

// 5. Problema identificado
echo "\n\n⚠️  DIAGNÓSTICO:\n";
echo str_repeat("-", 80) . "\n";

if ($stats['pending'] > 0 && $stats['approved'] > 0) {
    echo "🔴 INCONSISTÊNCIA DETECTADA:\n";
    echo "   - Há ${{$stats['pending']}} pagamento(s) com status 'pending'\n";
    echo "   - Mas há ${{$stats['approved']}} pagamento(s) com status_id=6 (approved)\n";
    echo "   - Banco local DESATUALIZADO em relação ao Mercado Pago\n";
} elseif ($stats['pending'] > 0) {
    echo "⏳ Pagamentos aguardando:\n";
    echo "   - Execute: php jobs/atualizar_pagamentos_mp.php --matricula-id=58\n";
}

// 6. Instruções para reprocessar
echo "\n\n🔧 COMO REPROCESSAR:\n";
echo str_repeat("-", 80) . "\n";
echo "1️⃣  Simulação (dry-run):\n";
echo "   php jobs/atualizar_pagamentos_mp.php --matricula-id=58 --dry-run\n\n";
echo "2️⃣  Reprocessar (execução real):\n";
echo "   php jobs/atualizar_pagamentos_mp.php --matricula-id=58\n\n";
echo "3️⃣  Reprocessar últimos 7 dias:\n";
echo "   php jobs/atualizar_pagamentos_mp.php --days=7 --tenant=3\n\n";

echo str_repeat("=", 80) . "\n\n";
