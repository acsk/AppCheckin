<?php
/**
 * Debug: Limite de check-ins — matrícula 235, aluno 71
 * Uso: php debug_limite_checkins_235.php
 */

$db = require __DIR__ . '/config/database.php';

$matriculaId = 235;
$alunoId     = 71;
$mes         = 3;
$ano         = 2026;

echo "====== DEBUG LIMITE CHECK-INS — Matrícula #{$matriculaId} / Aluno #{$alunoId} ======\n";
echo "Data BRT: " . date('Y-m-d H:i:s') . "\n\n";

// ─── 1. Dados da matrícula e plano ──────────────────────────────────────────
echo "1. MATRÍCULA E PLANO\n";
echo str_repeat('-', 80) . "\n";

$stmt = $db->prepare("
    SELECT m.id, m.aluno_id, m.plano_id, m.status_id, m.plano_ciclo_id,
           m.data_vencimento, m.proxima_data_vencimento,
           sm.codigo AS status_codigo,
           p.nome AS plano_nome, p.checkins_semanais, p.modalidade_id,
           modalidade.nome AS modalidade_nome,
           pc.permite_reposicao AS pc_permite_reposicao,
           a.nome AS aluno_nome, a.usuario_id
    FROM matriculas m
    INNER JOIN planos p ON p.id = m.plano_id
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    INNER JOIN alunos a ON a.id = m.aluno_id
    LEFT JOIN modalidades modalidade ON modalidade.id = p.modalidade_id
    LEFT JOIN plano_ciclos pc ON pc.id = m.plano_ciclo_id
    WHERE m.id = :id
");
$stmt->execute(['id' => $matriculaId]);
$mat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mat) {
    echo "  ❌ Matrícula #{$matriculaId} não encontrada!\n";
    exit(1);
}

echo "  Aluno              : {$mat['aluno_nome']} (aluno_id={$mat['aluno_id']}, usuario_id={$mat['usuario_id']})\n";
echo "  Plano              : {$mat['plano_nome']} (plano_id={$mat['plano_id']})\n";
echo "  Modalidade         : {$mat['modalidade_nome']} (id={$mat['modalidade_id']})\n";
echo "  Status             : {$mat['status_codigo']}\n";
echo "  data_vencimento    : {$mat['data_vencimento']}\n";
echo "  proxima_data_venc  : {$mat['proxima_data_vencimento']}\n";
echo "  checkins_semanais  : {$mat['checkins_semanais']}\n";
echo "  plano_ciclo_id     : " . ($mat['plano_ciclo_id'] ?? 'NULL') . "\n";
echo "  permite_reposicao  : " . ($mat['pc_permite_reposicao'] ?? 'NULL (sem ciclo)') . "\n";

// ─── 2. plano_ciclos do plano ────────────────────────────────────────────────
echo "\n2. PLANO_CICLOS DO PLANO {$mat['plano_id']}\n";
echo str_repeat('-', 80) . "\n";

$stmt = $db->prepare("
    SELECT pc.id, pc.tenant_id, pc.ativo, pc.permite_reposicao
    FROM plano_ciclos pc
    WHERE pc.plano_id = :plano_id
    ORDER BY pc.id DESC
");
$stmt->execute(['plano_id' => $mat['plano_id']]);
$ciclos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$ciclos) {
    echo "  Nenhum ciclo cadastrado.\n";
} else {
    foreach ($ciclos as $c) {
        $ativo    = $c['ativo'] ? '✅' : '❌';
        $repos    = $c['permite_reposicao'] ? 'SIM' : 'NÃO';
        echo "  Ciclo #{$c['id']} {$ativo} | permite_reposicao={$repos} | tenant={$c['tenant_id']}\n";
    }
}

// ─── 3. Check-ins de março com data real (dias.data) ────────────────────────
echo "\n3. CHECK-INS EM {$mes}/{$ano} (data_aula = dias.data)\n";
echo str_repeat('-', 80) . "\n";

$stmt = $db->prepare("
    SELECT c.id, DATE(d.data) AS data_aula, t.horario_inicio,
           m2.nome AS modalidade,
           c.presente, c.registrado_por_admin, c.created_at
    FROM checkins c
    INNER JOIN turmas      t  ON t.id  = c.turma_id
    INNER JOIN dias        d  ON d.id  = t.dia_id
    INNER JOIN modalidades m2 ON m2.id = t.modalidade_id
    WHERE c.aluno_id = :aluno_id
      AND YEAR(d.data)  = :ano
      AND MONTH(d.data) = :mes
    ORDER BY d.data ASC, t.horario_inicio ASC
");
$stmt->execute(['aluno_id' => $alunoId, 'ano' => $ano, 'mes' => $mes]);
$checkins = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalMes     = count($checkins);
$presentes    = array_filter($checkins, fn($c) => $c['presente'] == 1);
$totalPresent = count($presentes);

foreach ($checkins as $i => $c) {
    $status = $c['presente'] == 1 ? '✅' : ($c['presente'] == 0 ? '❌' : '⏳');
    $admin  = $c['registrado_por_admin'] ? ' [ADMIN]' : '';
    echo "  " . ($i + 1) . ". #{$c['id']} | {$c['data_aula']} {$c['horario_inicio']} | {$c['modalidade']} | {$status}{$admin}\n";
}

echo "\n  Total check-ins no mês : {$totalMes}\n";
echo "  Presentes (presente=1) : {$totalPresent}\n";

// ─── 4. Cálculo do limite mensal com bônus 5ª semana ─────────────────────────
echo "\n4. CÁLCULO DO LIMITE — {$mes}/{$ano}\n";
echo str_repeat('-', 80) . "\n";

$primeiroDia      = new DateTime("{$ano}-{$mes}-01");
$diaSemanaInicio  = (int) $primeiroDia->format('w'); // 0=dom
$diasNoMes        = (int) $primeiroDia->format('t');
$semanasNoMes     = (int) ceil(($diasNoMes + $diaSemanaInicio) / 7);
$bonusCincoSemanas = ($semanasNoMes >= 5) ? 1 : 0;

$checkinsSemanais = (int) $mat['checkins_semanais'];
$limiteMensal     = $checkinsSemanais * 4 + $bonusCincoSemanas;

echo "  checkins_semanais      : {$checkinsSemanais}\n";
echo "  Semanas no mês         : {$semanasNoMes}\n";
echo "  Bônus 5ª semana        : {$bonusCincoSemanas}\n";
echo "  Limite mensal efetivo  : {$checkinsSemanais}×4 + {$bonusCincoSemanas} = {$limiteMensal}\n";
echo "  Check-ins realizados   : {$totalMes}\n";

$excesso = $totalMes - $limiteMensal;
if ($excesso > 0) {
    echo "  ⚠️  EXCEDEU em {$excesso} check-in(s)!\n";
} else {
    echo "  ✅ Dentro do limite (sobra: " . abs($excesso) . ").\n";
}

// ─── 5. Por que a validação não bloqueou? ─────────────────────────────────────
echo "\n5. DIAGNÓSTICO DA VALIDAÇÃO\n";
echo str_repeat('-', 80) . "\n";

// a) contarCheckinsNoMes usa CURDATE() — só valida o mês ATUAL
echo "  [a] contarCheckinsNoMes usa CURDATE() → só bloqueia no mês corrente.\n";
echo "      Março já passou: impossível bloquear retroativamente.\n\n";

// b) obterLimiteCheckinsPlano exige proxima_data_vencimento >= CURDATE()
$proxima = $mat['proxima_data_vencimento'] ?? $mat['data_vencimento'];
echo "  [b] obterLimiteCheckinsPlano filtra proxima_data_vencimento >= CURDATE()\n";
echo "      proxima_data_vencimento = {$proxima}\n";
echo "      CURDATE()              = " . date('Y-m-d') . "\n";
if ($proxima < date('Y-m-d')) {
    echo "      ⚠️  Vencida! Se estava vencida em março, validação era ignorada.\n";
} else {
    echo "      ✅ Válida — não foi esse o problema.\n";
}

// c) permite_reposicao
$permiteReposicao = $mat['pc_permite_reposicao'];
echo "\n  [c] permite_reposicao = " . ($permiteReposicao === null ? 'NULL (sem ciclo vinculado)' : ($permiteReposicao ? 'SIM' : 'NÃO')) . "\n";
if (!$permiteReposicao) {
    echo "      ⚠️  SEM permite_reposicao → validação mensal é IGNORADA no código.\n";
    echo "         (só valida limite semanal quando permite_reposicao=0, e limite mensal quando =1)\n";
}

// d) Contagem com COALESCE vs dias.data para o mês atual
echo "\n  [d] contarCheckinsNoMes usa COALESCE(data_checkin_date, DATE(created_at)) — não dias.data.\n";
echo "      Falsos positivos/negativos possíveis para aulas pré-registradas.\n";

// ─── 6. Resumo e recomendação ─────────────────────────────────────────────────
echo "\n6. RESUMO E PRÓXIMOS PASSOS\n";
echo str_repeat('-', 80) . "\n";
echo "  Matrícula #235 / Aluno 71 / Plano: {$mat['plano_nome']}\n";
echo "  Limite correto para {$mes}/{$ano}: {$limiteMensal} check-ins ({$checkinsSemanais}×4 + {$bonusCincoSemanas} bônus)\n";
echo "  Realizados: {$totalMes}\n";

if (!$permiteReposicao) {
    echo "\n  ❌ ROOT CAUSE: permite_reposicao = 0/NULL → validação mensal nunca é aplicada.\n";
    echo "     FIX: UPDATE plano_ciclos SET permite_reposicao = 1 WHERE id = ? (ciclo da matrícula)\n";
    if ($mat['plano_ciclo_id']) {
        echo "     → plano_ciclo_id = {$mat['plano_ciclo_id']}\n";
        echo "     SQL: UPDATE plano_ciclos SET permite_reposicao = 1 WHERE id = {$mat['plano_ciclo_id']};\n";
    } else {
        echo "     → matrícula sem plano_ciclo_id. Verificar ciclo ativo do plano acima.\n";
    }
}

echo "\n  ➡️  Correção em contarCheckinsNoMes:\n";
echo "     Substituir COALESCE(data_checkin_date, DATE(created_at)) por DATE(d.data)\n";
echo "     com INNER JOIN turmas t / INNER JOIN dias d ON d.id = t.dia_id\n";

echo "\n" . str_repeat('=', 80) . "\n";
echo "Fim do diagnóstico.\n";
