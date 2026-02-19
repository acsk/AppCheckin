<?php
$db = require 'config/database.php';

echo "=== WEBHOOK PAYMENT 146079536501 ===\n";
$stmt = $db->prepare("
    SELECT id, status, payment_id, preapproval_id, external_reference, 
           payload, resultado_processamento, erro_processamento
    FROM webhook_payloads_mercadopago 
    WHERE payment_id = 146079536501
    ORDER BY id DESC
");
$stmt->execute();
$webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($webhooks as $wh) {
    echo "\nID: {$wh['id']}\n";
    echo "Status: {$wh['status']}\n";
    echo "External Ref: {$wh['external_reference']}\n";
    echo "Erro: {$wh['erro_processamento']}\n";
    echo "Payload: " . json_encode(json_decode($wh['payload'], true), JSON_PRETTY_PRINT) . "\n";
    if ($wh['resultado_processamento']) {
        echo "Resultado: " . json_encode(json_decode($wh['resultado_processamento'], true), JSON_PRETTY_PRINT) . "\n";
    }
}

echo "\n\n=== TABELA ASSINATURAS STRUCTURE ===\n";
$stmt = $db->query("DESC assinaturas");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo "{$col['Field']}: {$col['Type']} (Null: {$col['Null']}, Default: {$col['Default']})\n";
}

echo "\n=== PACOTE_CONTRATOS COM ID = 4 ===\n";
$stmt = $db->prepare("SELECT * FROM pacote_contratos WHERE id = 4");
$stmt->execute();
$contrato = $stmt->fetch(PDO::FETCH_ASSOC);
if ($contrato) {
    echo json_encode($contrato, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo "Contrato 4 não encontrado\n";
}

echo "\n=== MATRÍCULAS DO CONTRATO 4 ===\n";
$stmt = $db->prepare("SELECT id, aluno_id, pacote_contrato_id, status_id FROM matriculas WHERE pacote_contrato_id = 4");
$stmt->execute();
$matriculas = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Total: " . count($matriculas) . "\n";
foreach ($matriculas as $m) {
    echo "Matrícula {$m['id']}: aluno_id={$m['aluno_id']}, status_id={$m['status_id']}\n";
}
