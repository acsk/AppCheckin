<?php
/**
 * Fix: Corrigir proxima_data_vencimento divergente da próxima parcela pendente
 *
 * Detecta matrículas ativas onde proxima_data_vencimento não corresponde a
 * MIN(data_vencimento) das parcelas com status Aguardando (1) ou Atrasado (3),
 * e sincroniza o campo com a data real da parcela.
 *
 * Uso:
 *   php database/fix_proxima_data_vencimento_divergente.php           (dry-run)
 *   php database/fix_proxima_data_vencimento_divergente.php --execute (aplica)
 *   php database/fix_proxima_data_vencimento_divergente.php --execute --tenant=2
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

$dryRun   = !in_array('--execute', $argv);
$opts     = getopt('', ['tenant:']);
$tenantId = isset($opts['tenant']) ? (int)$opts['tenant'] : null;

echo "=== Fix: proxima_data_vencimento divergente da parcela pendente ===\n";
echo "Modo  : " . ($dryRun ? "DRY-RUN (passe --execute para aplicar)" : "EXECUTANDO") . "\n";
echo "Tenant: " . ($tenantId ? "#{$tenantId}" : "todos") . "\n\n";

// -------------------------------------------------------------------
// 1. Detectar divergências
// -------------------------------------------------------------------
$sql = "
    SELECT
        m.id          AS matricula_id,
        m.tenant_id,
        a.nome        AS aluno_nome,
        p.nome        AS plano_nome,
        m.proxima_data_vencimento AS valor_atual,
        MIN(pp.data_vencimento)   AS valor_correto
    FROM matriculas m
    INNER JOIN status_matricula sm ON sm.id = m.status_id AND sm.codigo = 'ativa'
    INNER JOIN alunos a            ON a.id  = m.aluno_id
    INNER JOIN planos p            ON p.id  = m.plano_id
    INNER JOIN pagamentos_plano pp ON pp.matricula_id = m.id
        AND pp.status_pagamento_id IN (1, 3)
    WHERE 1=1
";

$params = [];
if ($tenantId) {
    $sql     .= " AND m.tenant_id = ?";
    $params[] = $tenantId;
}

$sql .= "
    GROUP BY m.id, m.tenant_id, a.nome, p.nome, m.proxima_data_vencimento
    HAVING m.proxima_data_vencimento IS NULL
        OR m.proxima_data_vencimento != MIN(pp.data_vencimento)
    ORDER BY a.nome, m.id
";

$stmt  = $pdo->prepare($sql);
$stmt->execute($params);
$casos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($casos);
echo "Divergências encontradas: {$total}\n\n";

if ($total === 0) {
    echo "Nada a corrigir. ✅\n";
    exit(0);
}

// -------------------------------------------------------------------
// 2. Exibir e (opcionalmente) corrigir
// -------------------------------------------------------------------
$stmtUpdate = $pdo->prepare("
    UPDATE matriculas
    SET proxima_data_vencimento = ?,
        updated_at = NOW()
    WHERE id = ?
      AND tenant_id = ?
");

$corrigidas = 0;
$erros      = 0;

foreach ($casos as $caso) {
    $atual   = $caso['valor_atual'] ?? 'NULL';
    $correto = $caso['valor_correto'];
    echo sprintf(
        "  Mat #%-4d | tenant %-2d | %-30s | %s → %s",
        $caso['matricula_id'],
        $caso['tenant_id'],
        mb_substr($caso['aluno_nome'], 0, 30),
        $atual,
        $correto
    );

    if ($dryRun) {
        echo "\n";
        continue;
    }

    try {
        $stmtUpdate->execute([$correto, $caso['matricula_id'], $caso['tenant_id']]);
        if ($stmtUpdate->rowCount() > 0) {
            echo " ✅\n";
            $corrigidas++;
        } else {
            echo " ⚠️  rowCount=0\n";
        }
    } catch (\Throwable $e) {
        echo " ❌ " . $e->getMessage() . "\n";
        $erros++;
    }
}

echo "\n";

if ($dryRun) {
    echo "Total a corrigir : {$total}\n";
    echo "Execute com --execute para aplicar.\n";
} else {
    echo "Corrigidas : {$corrigidas}\n";
    if ($erros > 0) {
        echo "Erros      : {$erros}\n";
    }
}

exit($erros > 0 ? 1 : 0);
