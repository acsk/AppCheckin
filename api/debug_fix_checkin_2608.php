<?php
/**
 * Corrige check-in #2608 do aluno 266:
 * Inclusão manual pelo professor em 01/07 sem pagamento.
 * Marca como falta (presente=0) para liberar a cota semanal da diária de 03/07.
 *
 * Uso:
 *   php debug_fix_checkin_2608.php          # dry-run
 *   php debug_fix_checkin_2608.php --fix # aplica
 */

declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

$host = getenv('PROD_DB_HOST') ?: 'srv1314.hstgr.io';
$dbname = getenv('PROD_DB_NAME') ?: 'u304177849_api';
$user = getenv('PROD_DB_USER') ?: 'u304177849_api';
$pass = getenv('PROD_DB_PASS') ?: '+DEEJ&7t';

$db = new PDO(
    "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$fix = in_array('--fix', $argv ?? [], true);
$checkinId = 2608;

$stmt = $db->prepare("
    SELECT c.id, c.aluno_id, c.turma_id, c.data_checkin, c.data_checkin_date,
           c.presente, c.registrado_por_admin, c.admin_id, c.created_at,
           a.nome AS aluno_nome, t.nome AS turma_nome
    FROM checkins c
    INNER JOIN alunos a ON a.id = c.aluno_id
    LEFT JOIN turmas t ON t.id = c.turma_id
    WHERE c.id = ?
");
$stmt->execute([$checkinId]);
$c = $stmt->fetch();

if (!$c) {
    echo "Check-in #{$checkinId} não encontrado.\n";
    exit(1);
}

echo "=== CHECK-IN #{$checkinId} ===\n";
echo "Aluno: {$c['aluno_nome']} (#{$c['aluno_id']})\n";
echo "Turma: " . ($c['turma_nome'] ?? $c['turma_id']) . "\n";
echo "Data check-in: {$c['data_checkin']} ({$c['data_checkin_date']})\n";
echo "Presente: " . var_export($c['presente'], true) . "\n";
echo "Registrado por admin: " . var_export($c['registrado_por_admin'], true) . " | admin_id: " . ($c['admin_id'] ?? 'null') . "\n";
echo "Criado em: {$c['created_at']}\n\n";

// Pagamentos da matrícula 353 em torno de 01/07
$pag = $db->query("
    SELECT id, valor, status_pagamento_id, data_vencimento, data_pagamento, observacoes
    FROM pagamentos_plano
    WHERE matricula_id = 353
      AND data_vencimento BETWEEN '2026-06-28' AND '2026-07-05'
    ORDER BY id
")->fetchAll();
echo "=== PAGAMENTOS MAT #353 (28/06–05/07) ===\n";
foreach ($pag as $p) {
    $status = match ((int) $p['status_pagamento_id']) {
        1 => 'Aguardando',
        2 => 'Pago',
        3 => 'Atrasado',
        4 => 'Cancelado',
        default => (string) $p['status_pagamento_id'],
    };
    echo "#{$p['id']} {$status} R\${$p['valor']} venc {$p['data_vencimento']}"
        . ($p['data_pagamento'] ? " pago {$p['data_pagamento']}" : '')
        . "\n";
    if ($p['observacoes']) {
        echo "  obs: {$p['observacoes']}\n";
    }
}

echo "\n=== AÇÃO ===\n";
echo "Marcar presente=0 (falta) no check-in #{$checkinId}.\n";
echo "Motivo: inclusão manual sem pagamento em 01/07; libera limite semanal da diária paga em 03/07.\n";
echo "Faltas (presente=0) NÃO contam no limite semanal.\n\n";

if (!$fix) {
    echo "Dry-run. Execute com --fix para aplicar.\n";
    exit(0);
}

$upd = $db->prepare("
    UPDATE checkins
    SET presente = 0,
        updated_at = NOW()
    WHERE id = ? AND aluno_id = 266
");
$upd->execute([$checkinId]);

echo "OK: check-in #{$checkinId} atualizado para presente=0.\n";

$after = $db->prepare("SELECT id, presente, data_checkin_date FROM checkins WHERE id = ?");
$after->execute([$checkinId]);
print_r($after->fetch());

// Contagem semanal após correção
$semana = $db->query("
    SELECT COUNT(*) FROM checkins c
    INNER JOIN alunos a ON a.id = c.aluno_id
    INNER JOIN turmas t ON t.id = c.turma_id
    WHERE a.usuario_id = 279
      AND YEARWEEK(COALESCE(c.data_checkin_date, DATE(c.created_at)), 1) = YEARWEEK(CURDATE(), 1)
      AND (c.presente IS NULL OR c.presente = 1)
      AND t.modalidade_id = 3
")->fetchColumn();

echo "\nCheck-ins que contam no limite semanal (Natação) após correção: {$semana}\n";
echo "Aluno pode fazer check-in com a diária de hoje.\n";
