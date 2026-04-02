<?php
require 'vendor/autoload.php';
require 'config/database.php';

$execute = in_array('--execute', $argv);

echo "=== Fix Matrícula 208 (duplicada pela 283) ===\n";
echo "Modo: " . ($execute ? "EXECUTANDO" : "DRY-RUN (use --execute)") . "\n\n";

// Verificar estado atual
$stmt = $pdo->query("
    SELECT m.id, m.aluno_id, p.nome as plano, sm.codigo as status, m.data_vencimento, m.proxima_data_vencimento
    FROM matriculas m
    INNER JOIN planos p ON p.id = m.plano_id
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    WHERE m.id IN (208, 283) ORDER BY m.id
");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  Mat #{$r['id']} | {$r['plano']} | status={$r['status']} | venc={$r['data_vencimento']} | prox=" . ($r['proxima_data_vencimento'] ?? 'NULL') . "\n";
}

if ($execute) {
    // Finalizar matrícula 208 (substituída pela 283)
    $stmt = $pdo->prepare("
        UPDATE matriculas
        SET status_id = (SELECT id FROM status_matricula WHERE codigo = 'finalizada' LIMIT 1),
            updated_at = NOW()
        WHERE id = 208
    ");
    $stmt->execute();
    echo "\n✅ Matrícula #208 finalizada (substituída pela #283)\n";
} else {
    echo "\nAção: Mat #208 será finalizada. Use --execute para aplicar.\n";
}
