<?php
require __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = require __DIR__ . '/../config/database.php';

$matriculaId = 222;
try {
    $stmt = $pdo->prepare("SELECT m.*, sm.codigo as status_codigo, sm.nome as status_nome FROM matriculas m LEFT JOIN status_matricula sm ON sm.id = m.status_id WHERE m.id = ? LIMIT 1");
    $stmt->execute([$matriculaId]);
    $matricula = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt2 = $pdo->prepare("SELECT pp.* FROM pagamentos_plano pp WHERE pp.matricula_id = ? ORDER BY pp.data_vencimento ASC");
    $stmt2->execute([$matriculaId]);
    $pagamentos = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    $stmt3 = $pdo->prepare("SELECT * FROM pagamentos_mercadopago WHERE matricula_id = ? ORDER BY date_created DESC");
    $stmt3->execute([$matriculaId]);
    $pagamentosMp = $stmt3->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'matricula' => $matricula,
        'pagamentos_plano' => $pagamentos,
        'pagamentos_mercadopago' => $pagamentosMp
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
