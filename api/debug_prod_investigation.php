<?php
/**
 * Script de investigação de produção
 * Problema: Assinatura #32 (MAT-172) mostra "paga/approved" mas nenhum dado de pagamento encontrado
 * 
 * Executar: php debug_prod_investigation.php
 */

$host = 'srv1314.hstgr.io';
$dbname = 'u304177849_api';
$user = 'u304177849_api';
$pass = '+DEEJ&7t';

try {
    $db = new PDO(
        "mysql:host={$host};port=3306;dbname={$dbname};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✅ Conectado ao banco remoto\n\n";
} catch (PDOException $e) {
    die("❌ Erro de conexão: " . $e->getMessage() . "\n");
}

$tenantId = 3;
$matriculaId = 172;
$assinaturaId = 32;
$externalRef = 'MAT-172-1771971439';

echo "=== 1. ASSINATURA #$assinaturaId ===\n";
$stmt = $db->prepare("SELECT * FROM assinaturas WHERE id = ?");
$stmt->execute([$assinaturaId]);
$ass = $stmt->fetch(PDO::FETCH_ASSOC);
if ($ass) {
    echo "  tenant_id: {$ass['tenant_id']}\n";
    echo "  matricula_id: {$ass['matricula_id']}\n";
    echo "  aluno_id: {$ass['aluno_id']}\n";
    echo "  tipo_cobranca: {$ass['tipo_cobranca']}\n";
    echo "  status_id: {$ass['status_id']}\n";
    echo "  status_gateway: {$ass['status_gateway']}\n";
    echo "  external_reference: {$ass['external_reference']}\n";
    echo "  gateway_preference_id: {$ass['gateway_preference_id']}\n";
    echo "  gateway_assinatura_id: {$ass['gateway_assinatura_id']}\n";
    echo "  payment_url: {$ass['payment_url']}\n";
    echo "  criado_em: {$ass['criado_em']}\n";
    echo "  atualizado_em: {$ass['atualizado_em']}\n";
} else {
    echo "  NÃO ENCONTRADA!\n";
}

echo "\n=== 2. MATRÍCULA #$matriculaId ===\n";
$stmt = $db->prepare("SELECT m.*, sm.codigo as status_codigo, sm.nome as status_nome FROM matriculas m LEFT JOIN status_matricula sm ON sm.id = m.status_id WHERE m.id = ?");
$stmt->execute([$matriculaId]);
$mat = $stmt->fetch(PDO::FETCH_ASSOC);
if ($mat) {
    echo "  tenant_id: {$mat['tenant_id']}\n";
    echo "  aluno_id: {$mat['aluno_id']}\n";
    echo "  plano_id: {$mat['plano_id']}\n";
    echo "  status_id: {$mat['status_id']} ({$mat['status_codigo']} - {$mat['status_nome']})\n";
    echo "  tipo_cobranca: " . ($mat['tipo_cobranca'] ?? 'NULL') . "\n";
    echo "  valor: {$mat['valor']}\n";
    echo "  data_matricula: {$mat['data_matricula']}\n";
    echo "  data_inicio: {$mat['data_inicio']}\n";
    echo "  data_vencimento: {$mat['data_vencimento']}\n";
} else {
    echo "  NÃO ENCONTRADA!\n";
}

echo "\n=== 3. PAGAMENTOS_PLANO (matricula_id=$matriculaId) ===\n";
$stmt = $db->prepare("SELECT id, tenant_id, matricula_id, external_reference, status_pagamento_id, valor, data_pagamento, data_vencimento, payment_id_mp, preference_id FROM pagamentos_plano WHERE matricula_id = ?");
$stmt->execute([$matriculaId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "  Total: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo "  [ID:{$r['id']}] tenant={$r['tenant_id']}, ext_ref={$r['external_reference']}, status={$r['status_pagamento_id']}, valor={$r['valor']}, payment_mp={$r['payment_id_mp']}\n";
}

echo "\n=== 4. PAGAMENTOS_PLANO (external_reference='$externalRef' SEM filtro tenant) ===\n";
$stmt = $db->prepare("SELECT id, tenant_id, matricula_id, external_reference, status_pagamento_id, valor FROM pagamentos_plano WHERE external_reference = ?");
$stmt->execute([$externalRef]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "  Total: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo "  [ID:{$r['id']}] tenant={$r['tenant_id']}, mat={$r['matricula_id']}, status={$r['status_pagamento_id']}\n";
}

echo "\n=== 5. PAGAMENTOS_MERCADOPAGO (matricula_id=$matriculaId OU external_reference) ===\n";
$stmt = $db->prepare("SELECT id, tenant_id, matricula_id, payment_id, external_reference, status, status_detail, transaction_amount FROM pagamentos_mercadopago WHERE matricula_id = ? OR external_reference = ?");
$stmt->execute([$matriculaId, $externalRef]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "  Total: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo "  [ID:{$r['id']}] tenant={$r['tenant_id']}, payment={$r['payment_id']}, ext_ref={$r['external_reference']}, status={$r['status']}, valor={$r['transaction_amount']}\n";
}

echo "\n=== 6. WEBHOOK_PAYLOADS_MERCADOPAGO (últimos 15) ===\n";
$stmt = $db->query("SELECT id, tenant_id, tipo, data_id, payment_id, external_reference, status, erro_processamento, created_at FROM webhook_payloads_mercadopago ORDER BY id DESC LIMIT 15");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "  Total registros: " . count($rows) . "\n";
foreach ($rows as $r) {
    $erro = $r['erro_processamento'] ? substr($r['erro_processamento'], 0, 80) : '-';
    echo "  [ID:{$r['id']}] t={$r['tenant_id']}, tipo={$r['tipo']}, data_id={$r['data_id']}, pay={$r['payment_id']}, ext={$r['external_reference']}, st={$r['status']}, erro={$erro}, at={$r['created_at']}\n";
}

echo "\n=== 7. CONFIGURAÇÃO MP DO TENANT $tenantId ===\n";
$stmt = $db->prepare("SELECT id, tenant_id, chave, valor FROM configuracoes WHERE tenant_id = ? AND chave LIKE '%mercadopago%'");
$stmt->execute([$tenantId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "  Total: " . count($rows) . "\n";
foreach ($rows as $r) {
    $val = strlen($r['valor']) > 20 ? substr($r['valor'], 0, 20) . '...' : $r['valor'];
    echo "  [{$r['chave']}] = {$val}\n";
}

echo "\n=== 8. PREFERENCE_ID DA ASSINATURA ===\n";
if ($ass && !empty($ass['gateway_preference_id'])) {
    echo "  preference_id: {$ass['gateway_preference_id']}\n";
} else {
    echo "  Sem preference_id na assinatura\n";
}

// Verificar se pagamentos_plano tem dados para QUALQUER matricula desse tenant
echo "\n=== 9. ÚLTIMOS PAGAMENTOS_PLANO DO TENANT $tenantId ===\n";
$stmt = $db->prepare("SELECT id, tenant_id, matricula_id, external_reference, status_pagamento_id, valor, data_pagamento, created_at FROM pagamentos_plano WHERE tenant_id = ? ORDER BY id DESC LIMIT 5");
$stmt->execute([$tenantId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "  Total: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo "  [ID:{$r['id']}] mat={$r['matricula_id']}, ext={$r['external_reference']}, status={$r['status_pagamento_id']}, valor={$r['valor']}, pago={$r['data_pagamento']}, criado={$r['created_at']}\n";
}

echo "\n=== 10. ÚLTIMOS PAGAMENTOS_MERCADOPAGO DO TENANT $tenantId ===\n";
$stmt = $db->prepare("SELECT id, tenant_id, matricula_id, payment_id, external_reference, status, transaction_amount, created_at FROM pagamentos_mercadopago WHERE tenant_id = ? ORDER BY id DESC LIMIT 5");
$stmt->execute([$tenantId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "  Total: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo "  [ID:{$r['id']}] mat={$r['matricula_id']}, payment={$r['payment_id']}, ext={$r['external_reference']}, status={$r['status']}, valor={$r['transaction_amount']}, at={$r['created_at']}\n";
}

echo "\n=== DIAGNÓSTICO ===\n";
echo "- Assinatura #32 status_gateway = " . ($ass['status_gateway'] ?? 'N/A') . "\n";
echo "- Matrícula #172 status = " . ($mat['status_codigo'] ?? 'N/A') . "\n";
echo "- pagamentos_plano com external_reference '$externalRef': " . (count($rows) > 0 ? 'ENCONTRADO' : 'NENHUM') . "\n";
echo "- Webhooks recebidos para essa matrícula: verificar acima\n";

echo "\nDone.\n";
