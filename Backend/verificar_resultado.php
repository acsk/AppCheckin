<?php
$db = require __DIR__ . '/config/database.php';

echo "=== ESTADO FINAL DAS MATRÍCULAS ===\n\n";

$result = $db->query("
SELECT m.id, m.data_matricula, m.status, p.nome as plano, mo.nome as modalidade,
       COUNT(DISTINCT pp.id) as pagamentos
FROM matriculas m
INNER JOIN planos p ON m.plano_id = p.id
INNER JOIN modalidades mo ON p.modalidade_id = mo.id
LEFT JOIN pagamentos_plano pp ON m.id = pp.matricula_id
WHERE m.usuario_id = 11 AND m.tenant_id = 5
GROUP BY m.id
ORDER BY mo.nome, m.data_matricula DESC, m.created_at DESC
")->fetchAll(\PDO::FETCH_ASSOC);

foreach ($result as $m) {
    $status = $m['status'] === 'cancelada' ? '❌ CANCELADA' : '✅ ' . strtoupper($m['status']);
    $pgto = $m['pagamentos'] > 0 ? $m['pagamentos'] . ' pgto(s)' : 'SEM pgto';
    printf("[%2d] %-25s - %-12s | %s | %s | %s\n", 
        $m['id'], 
        substr($m['plano'], 0, 25),
        $m['modalidade'],
        $m['data_matricula'], 
        $pgto, 
        $status
    );
}

echo "\n=== RESUMO ===\n";
$ativas = $db->query('SELECT COUNT(*) as total FROM matriculas WHERE usuario_id = 11 AND tenant_id = 5 AND status IN ("ativa", "pendente")')->fetch(\PDO::FETCH_ASSOC);
$canceladas = $db->query('SELECT COUNT(*) as total FROM matriculas WHERE usuario_id = 11 AND tenant_id = 5 AND status = "cancelada"')->fetch(\PDO::FETCH_ASSOC);

echo "✅ Ativas/Pendentes: " . $ativas['total'] . "\n";
echo "❌ Canceladas: " . $canceladas['total'] . "\n";
?>
