<?php
/**
 * Fix: Atualizar proxima_data_vencimento para matrículas que têm
 * data_vencimento preenchido mas proxima_data_vencimento NULL.
 * 
 * Uso:
 *   php database/fix_proxima_data_vencimento_null.php [--dry-run]
 *   php database/fix_proxima_data_vencimento_null.php --execute
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

$dryRun = !in_array('--execute', $argv);

echo "=== Fix proxima_data_vencimento NULL ===\n";
echo "Modo: " . ($dryRun ? "DRY-RUN (use --execute para aplicar)" : "EXECUTANDO") . "\n\n";

// Buscar matrículas com proxima_data_vencimento NULL mas data_vencimento preenchido
$stmt = $pdo->query("
    SELECT m.id, m.tenant_id, m.aluno_id, m.tipo_cobranca, m.status_id,
           m.data_inicio, m.data_vencimento, m.proxima_data_vencimento,
           sm.codigo as status_codigo,
           a.nome as aluno_nome
    FROM matriculas m
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    LEFT JOIN alunos al ON al.id = m.aluno_id
    LEFT JOIN usuarios a ON a.id = al.usuario_id
    WHERE m.proxima_data_vencimento IS NULL
    AND m.data_vencimento IS NOT NULL
    ORDER BY m.id
");

$matriculas = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($matriculas);

echo "Encontradas: {$total} matrículas com proxima_data_vencimento NULL e data_vencimento preenchido\n\n";

if ($total === 0) {
    echo "Nada a corrigir.\n";
    exit(0);
}

$stmtUpdate = $pdo->prepare("
    UPDATE matriculas 
    SET proxima_data_vencimento = data_vencimento,
        updated_at = NOW()
    WHERE id = ? AND proxima_data_vencimento IS NULL
");

$corrigidas = 0;
foreach ($matriculas as $m) {
    $status = $m['status_codigo'];
    $nome = $m['aluno_nome'] ?? 'N/A';
    echo "  Matrícula #{$m['id']} | {$nome} | status={$status} | tipo={$m['tipo_cobranca']} | data_vencimento={$m['data_vencimento']} → proxima_data_vencimento={$m['data_vencimento']}";
    
    if (!$dryRun) {
        $stmtUpdate->execute([$m['id']]);
        if ($stmtUpdate->rowCount() > 0) {
            echo " ✅";
            $corrigidas++;
        } else {
            echo " (sem alteração)";
        }
    } else {
        echo " [dry-run]";
    }
    echo "\n";
}

echo "\n=== Resultado ===\n";
if ($dryRun) {
    echo "{$total} matrículas seriam corrigidas. Use --execute para aplicar.\n";
} else {
    echo "{$corrigidas} de {$total} matrículas corrigidas.\n";
}
