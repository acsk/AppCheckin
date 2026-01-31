<?php
// Diagnóstico rápido: contagem de check-ins por tenant no mês atual
$pdo = require __DIR__ . '/../config/database.php';

function out($label, $value) {
    echo str_pad($label, 30) . ": " . $value . "\n";
}

try {
    out('Data atual', date('Y-m-d H:i:s'));

    // Todos os tenants existentes
    $tenants = $pdo->query("SELECT id, nome FROM tenants ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    echo "\nTenants:\n";
    foreach ($tenants as $t) {
        out("- Tenant {$t['id']}", $t['nome']);
    }

    echo "\nContagem de check-ins por tenant (mês atual):\n";
    $sql = "SELECT c.tenant_id, COUNT(*) AS total
            FROM checkins c
            WHERE MONTH(c.data_checkin_date) = MONTH(CURRENT_DATE())
              AND YEAR(c.data_checkin_date) = YEAR(CURRENT_DATE())
            GROUP BY c.tenant_id
            ORDER BY c.tenant_id";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        echo "(sem registros no mês atual)\n";
    } else {
        foreach ($rows as $row) {
            out("- Tenant " . ($row['tenant_id'] ?? 'NULL'), $row['total']);
        }
    }

    echo "\nTop 5 alunos por tenant (mês atual):\n";
    $sql2 = "SELECT c.tenant_id, a.nome, COUNT(c.id) AS total
             FROM checkins c
             INNER JOIN alunos a ON a.id = c.aluno_id
             WHERE MONTH(c.data_checkin_date) = MONTH(CURRENT_DATE())
               AND YEAR(c.data_checkin_date) = YEAR(CURRENT_DATE())
             GROUP BY c.tenant_id, a.id, a.nome
             ORDER BY c.tenant_id, total DESC
             LIMIT 25";
    $rows2 = $pdo->query($sql2)->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows2)) {
        echo "(sem registros)\n";
    } else {
        $currentTenant = null;
        foreach ($rows2 as $r) {
            if ($currentTenant !== $r['tenant_id']) {
                $currentTenant = $r['tenant_id'];
                echo "\nTenant " . ($currentTenant ?? 'NULL') . ":\n";
            }
            out("  - " . $r['nome'], $r['total']);
        }
    }

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    exit(1);
}
