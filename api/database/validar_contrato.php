<?php
/**
 * Script de validação: verifica estado completo de um pacote_contrato
 */

$dbHost = 'localhost';
$dbName = 'u304177849_api';
$dbUser = 'u304177849_api';
$dbPass = '+DEEJ&7t';

try {
    $db = new \PDO(
        "mysql:host={$dbHost};dbname={$dbName}",
        $dbUser,
        $dbPass
    );
} catch (\Exception $e) {
    die("Erro: " . $e->getMessage());
}

$contratoId = 3;
$tenantId = 3;

echo "═══════════════════════════════════════════════════════════\n";
echo "VALIDAÇÃO DO CONTRATO #{$contratoId}\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// 1. Dados do contrato
echo "1️⃣  CONTRATO:\n";
$stmt = $db->prepare("SELECT * FROM pacote_contratos WHERE id = ? AND tenant_id = ?");
$stmt->execute([$contratoId, $tenantId]);
$contrato = $stmt->fetch(\PDO::FETCH_ASSOC);

if (!$contrato) {
    echo "❌ Contrato não encontrado\n";
    exit(1);
}

echo "   ID: {$contrato['id']}\n";
echo "   Status: {$contrato['status']}\n";
echo "   Pagamento ID: {$contrato['pagamento_id']}\n";
echo "   Data Início: {$contrato['data_inicio']}\n";
echo "   Data Fim: {$contrato['data_fim']}\n";

// 2. Beneficiários
echo "\n2️⃣  BENEFICIÁRIOS:\n";
$stmt = $db->prepare("SELECT * FROM pacote_beneficiarios WHERE pacote_contrato_id = ? AND tenant_id = ? ORDER BY id");
$stmt->execute([$contratoId, $tenantId]);
$beneficiarios = $stmt->fetchAll(\PDO::FETCH_ASSOC);

if (count($beneficiarios) === 0) {
    echo "❌ Nenhum beneficiário\n";
} else {
    foreach ($beneficiarios as $ben) {
        echo "   Aluno {$ben['aluno_id']}: ";
        echo "Matrícula ID = {$ben['matricula_id']}, ";
        echo "Status = {$ben['status']}\n";
    }
}

// 3. Matrículas
echo "\n3️⃣  MATRÍCULAS CRIADAS:\n";
$stmt = $db->prepare("SELECT id, aluno_id, tipo_cobranca, valor_rateado, status_id FROM matriculas WHERE pacote_contrato_id = ? AND tenant_id = ? ORDER BY id");
$stmt->execute([$contratoId, $tenantId]);
$matriculas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

if (count($matriculas) === 0) {
    echo "❌ Nenhuma matrícula\n";
} else {
    foreach ($matriculas as $mat) {
        echo "   Matrícula #{$mat['id']}: Aluno {$mat['aluno_id']}, Valor R$ {$mat['valor_rateado']}, Tipo {$mat['tipo_cobranca']}\n";
    }
}

// 4. Assinaturas
echo "\n4️⃣  ASSINATURAS:\n";
$stmt = $db->prepare("
    SELECT a.id, a.matricula_id, a.aluno_id, a.tipo_cobranca
    FROM assinaturas a
    WHERE a.matricula_id IN (SELECT id FROM matriculas WHERE pacote_contrato_id = ?)
    AND a.tenant_id = ?
");
$stmt->execute([$contratoId, $tenantId]);
$assinaturas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

if (count($assinaturas) === 0) {
    echo "⚠️  Nenhuma assinatura (esperado: O pagante deveria ter 1)\n";
} else {
    foreach ($assinaturas as $assin) {
        echo "   Assinatura #{$assin['id']}: Matrícula {$assin['matricula_id']}, Aluno {$assin['aluno_id']}\n";
    }
}

// 5. Resumo
echo "\n📊 RESUMO:\n";
echo "   ✓ Contrato Status: " . ($contrato['status'] === 'ativo' ? '✅ ATIVO' : '❌ ' . $contrato['status']) . "\n";
echo "   ✓ Beneficiários: " . count($beneficiarios) . "\n";
echo "   ✓ Matrículas: " . count($matriculas) . "\n";
echo "   ✓ Assinaturas: " . count($assinaturas) . "\n";

if ($contrato['status'] === 'ativo' && count($matriculas) === count($beneficiarios)) {
    echo "\n✅ CONTRATO PROCESSADO COM SUCESSO!\n";
} else {
    echo "\n⚠️ CONTRATO PODE TER PROBLEMAS\n";
}

echo "\n═══════════════════════════════════════════════════════════\n";
?>
