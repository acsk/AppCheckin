<?php
require_once __DIR__ . '/vendor/autoload.php';
$db = require __DIR__ . '/config/database.php';

echo "=== DIAGNÓSTICO permite_reposicao MATRÍCULA #113 ===\n\n";

// 1) Verificar plano_ciclos do plano 16, tenant 3
$stmt = $db->query('SELECT pc.id, pc.plano_id, pc.tenant_id, pc.permite_reposicao, pc.ativo FROM plano_ciclos pc WHERE pc.plano_id = 16 AND pc.tenant_id = 3');
$ciclos = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "plano_ciclos do plano 16 (tenant 3): " . count($ciclos) . " registros\n";
foreach ($ciclos as $r) {
    echo "  id={$r['id']} | permite_reposicao={$r['permite_reposicao']} | ativo={$r['ativo']}\n";
}
if (empty($ciclos)) {
    echo "  NENHUM ciclo encontrado para este plano!\n";
}

// 2) Simular exatamente o que obterLimiteCheckinsPlano retorna
$stmt2 = $db->prepare("
    SELECT p.checkins_semanais, p.nome as plano_nome, p.modalidade_id,
           m.plano_ciclo_id,
           CASE
               WHEN m.plano_ciclo_id IS NOT NULL THEN COALESCE(pc.permite_reposicao, 0)
               ELSE COALESCE((
                   SELECT MAX(pc2.permite_reposicao)
                   FROM plano_ciclos pc2
                   WHERE pc2.plano_id = p.id
                     AND pc2.tenant_id = m.tenant_id
                     AND pc2.ativo = 1
               ), 0)
           END as permite_reposicao
     FROM matriculas m
     INNER JOIN planos p ON m.plano_id = p.id
     LEFT JOIN plano_ciclos pc ON pc.id = m.plano_ciclo_id AND pc.tenant_id = m.tenant_id
     INNER JOIN alunos a ON a.id = m.aluno_id
     INNER JOIN status_matricula sm ON sm.id = m.status_id
     WHERE a.usuario_id = 84
     AND m.tenant_id = 3
     AND sm.codigo = 'ativa'
     AND m.proxima_data_vencimento >= CURDATE()
     AND p.modalidade_id = 3
     ORDER BY m.proxima_data_vencimento DESC LIMIT 1
");
$stmt2->execute();
$r = $stmt2->fetch(PDO::FETCH_ASSOC);

echo "\nobterLimiteCheckinsPlano resultado real:\n";
echo "  plano_nome: {$r['plano_nome']}\n";
echo "  checkins_semanais: {$r['checkins_semanais']}\n";
echo "  modalidade_id: {$r['modalidade_id']}\n";
echo "  plano_ciclo_id: " . ($r['plano_ciclo_id'] ?? 'NULL') . "\n";
echo "  permite_reposicao: {$r['permite_reposicao']}\n";
echo "  permite_reposicao (bool): " . ($r['permite_reposicao'] ? 'TRUE → MENSAL' : 'FALSE → SEMANAL') . "\n";

if ($r['permite_reposicao']) {
    $limMensal = (int) $r['checkins_semanais'] * 4;
    echo "\n  LIMITE MENSAL (sem bônus): {$limMensal}\n";
    echo "  LIMITE MENSAL (com bônus 5 semanas): " . ($limMensal + 1) . "\n";
    
    // Contar checkins no mês
    $stmtC = $db->prepare("
        SELECT COUNT(*) FROM checkins c
        INNER JOIN alunos a ON a.id = c.aluno_id
        INNER JOIN turmas t ON t.id = c.turma_id
        WHERE a.usuario_id = 84
          AND YEAR(COALESCE(c.data_checkin_date, DATE(c.created_at))) = YEAR(CURDATE())
          AND MONTH(COALESCE(c.data_checkin_date, DATE(c.created_at))) = MONTH(CURDATE())
          AND (c.presente IS NULL OR c.presente = 1)
          AND t.modalidade_id = 3
    ");
    $stmtC->execute();
    $checkinsNoMes = (int) $stmtC->fetchColumn();
    
    echo "  Check-ins no mês (modalidade 3): {$checkinsNoMes}\n";
    echo "  Bloquearia SEM bônus? " . ($checkinsNoMes >= $limMensal ? 'SIM ❌' : 'NÃO ✅') . "\n";
    echo "  Bloquearia COM bônus? " . ($checkinsNoMes >= ($limMensal + 1) ? 'SIM ❌' : 'NÃO ✅') . "\n";
} else {
    echo "\n  LIMITE SEMANAL: {$r['checkins_semanais']}\n";
}

echo "\n=== FIM ===\n";
