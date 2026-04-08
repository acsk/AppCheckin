<?php
/**
 * Debug Check-ins - JOSÉ MURILO FORTUNATO PEREIRA
 * Contrato #235 • Natação - 3x por Semana
 *
 * Verifica se o limite de 3 check-ins por semana está sendo respeitado.
 *
 * Uso: php debug_checkins_jose_murilo.php
 */

require_once __DIR__ . '/vendor/autoload.php';

$db = require __DIR__ . '/config/database.php';

date_default_timezone_set('America/Sao_Paulo');

echo "====== DEBUG CHECK-INS - JOSÉ MURILO FORTUNATO PEREIRA ======\n";
echo "Data BRT (script): " . date('Y-m-d H:i:s') . "\n\n";

// ============================================================
// 1. DADOS DO ALUNO E MATRÍCULA
// ============================================================
echo "1. DADOS DO ALUNO E MATRÍCULA\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare("
    SELECT
        u.id as usuario_id, u.nome, u.email, u.cpf,
        a.id as aluno_id,
        m.id as matricula_id, m.tenant_id, m.data_inicio, m.data_vencimento,
        m.proxima_data_vencimento, m.tipo_cobranca, m.valor,
        sm.nome as status_matricula, sm.codigo as status_codigo,
        p.nome as plano_nome, p.checkins_semanais, p.modalidade_id,
        mo.nome as modalidade_nome,
        pc.id as plano_ciclo_id, pc.nome as ciclo_nome, pc.meses,
        pc.permite_reposicao,
        t.nome as tenant_nome
    FROM usuarios u
    INNER JOIN alunos a ON a.usuario_id = u.id
    INNER JOIN matriculas m ON m.aluno_id = a.id
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    INNER JOIN planos p ON p.id = m.plano_id
    LEFT JOIN modalidades mo ON mo.id = p.modalidade_id
    LEFT JOIN plano_ciclos pc ON pc.id = m.plano_ciclo_id
    INNER JOIN tenants t ON t.id = m.tenant_id
    WHERE u.nome LIKE '%JOSÉ MURILO%PEREIRA%'
       OR u.nome LIKE '%Jose Murilo%Pereira%'
       OR u.nome LIKE '%josé murilo%pereira%'
    ORDER BY m.id DESC
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    echo "⚠️  Nenhum aluno encontrado com esse nome.\n";
    exit(1);
}

// Usar a primeira linha / matrícula mais recente
$aluno = $rows[0];
$usuarioId = (int) $aluno['usuario_id'];
$alunoId   = (int) $aluno['aluno_id'];
$tenantId  = (int) $aluno['tenant_id'];
$matId     = (int) $aluno['matricula_id'];
$modalidadeId = (int) $aluno['modalidade_id'];
$limiteSemanal = (int) $aluno['checkins_semanais'];

foreach ($rows as $r) {
    echo "  Aluno    : {$r['nome']} (usuario_id={$r['usuario_id']}, aluno_id={$r['aluno_id']})\n";
    echo "  Email    : {$r['email']}\n";
    echo "  Tenant   : {$r['tenant_nome']} (id={$r['tenant_id']})\n";
    echo "  Matrícula: #{$r['matricula_id']} | {$r['status_matricula']} ({$r['status_codigo']})\n";
    echo "  Plano    : {$r['plano_nome']} | Modalidade: {$r['modalidade_nome']} (id={$r['modalidade_id']})\n";
    echo "  Ciclo    : " . ($r['ciclo_nome'] ?? 'N/A') . " ({$r['meses']} meses)\n";
    echo "  Limite   : {$r['checkins_semanais']} check-ins/semana";
    echo " | Reposição: " . ($r['permite_reposicao'] ? '✅ SIM' : '❌ NÃO') . "\n";
    echo "  Validade : {$r['data_inicio']} → venc: {$r['data_vencimento']} | próx: {$r['proxima_data_vencimento']}\n";
    echo "\n";
}

// ============================================================
// 2. CONTAGEM DA SEMANA ATUAL (mesma lógica do MobileController)
// ============================================================
echo "2. SEMANA ATUAL\n";
echo str_repeat("-", 80) . "\n";

// Calcular início e fim da semana ISO (segunda a domingo) — igual ao YEARWEEK(..., 1)
$hoje = new DateTime(date('Y-m-d'));
$diaSemanaISO = (int) $hoje->format('N'); // 1=segunda...7=domingo
$segundaFeira = clone $hoje;
$segundaFeira->modify('-' . ($diaSemanaISO - 1) . ' days');
$domingoSemana = clone $segundaFeira;
$domingoSemana->modify('+6 days');

echo "  Período semana ISO: {$segundaFeira->format('Y-m-d')} (seg) → {$domingoSemana->format('Y-m-d')} (dom)\n";
echo "  YEARWEEK(CURDATE(),1) = " . $db->query("SELECT YEARWEEK(CURDATE(), 1)")->fetchColumn() . "\n\n";

// Contar semana atual na modalidade
$stmtSemana = $db->prepare("
    SELECT COUNT(*) as total
    FROM checkins c
    INNER JOIN alunos a ON a.id = c.aluno_id
    INNER JOIN turmas t ON t.id = c.turma_id
    WHERE a.usuario_id = ?
      AND t.modalidade_id = ?
      AND YEARWEEK(COALESCE(c.data_checkin_date, DATE(c.created_at)), 1) = YEARWEEK(CURDATE(), 1)
      AND (c.presente IS NULL OR c.presente = 1)
");
$stmtSemana->execute([$usuarioId, $modalidadeId]);
$totalSemana = (int) $stmtSemana->fetchColumn();

// Verificar mês com 5 semanas (bônus)
$primeiroDiaMes = new DateTime(date('Y-m-01'));
$diaSemanaInicio = (int) $primeiroDiaMes->format('w'); // 0=domingo
$diasNoMes = (int) $primeiroDiaMes->format('t');
$semanasNoMes = (int) ceil(($diasNoMes + $diaSemanaInicio) / 7);
$bonusCincoSemanas = ($semanasNoMes >= 5) ? 1 : 0;
$limiteEfetivo = $limiteSemanal + $bonusCincoSemanas;

echo "  Checkins na semana (modalidade={$modalidadeId}): {$totalSemana}\n";
echo "  Limite semanal do plano: {$limiteSemanal}" . ($bonusCincoSemanas ? " +1 bônus (mês com {$semanasNoMes} semanas) = {$limiteEfetivo}" : " (sem bônus)") . "\n";

if ($totalSemana >= $limiteEfetivo) {
    echo "  STATUS: ⛔ LIMITE ATINGIDO ({$totalSemana}/{$limiteEfetivo})\n";
} else {
    echo "  STATUS: ✅ Abaixo do limite ({$totalSemana}/{$limiteEfetivo}) — ainda pode fazer " . ($limiteEfetivo - $totalSemana) . " check-in(s)\n";
}

// ============================================================
// 3. CHECKINS DA SEMANA ATUAL (detalhe)
// ============================================================
echo "\n3. DETALHE DOS CHECK-INS DA SEMANA ATUAL\n";
echo str_repeat("-", 80) . "\n";

$stmtDetalhe = $db->prepare("
    SELECT c.id, c.turma_id,
           COALESCE(c.data_checkin_date, DATE(c.created_at)) as data_checkin,
           c.created_at,
           c.presente,
           t.nome as turma_nome,
           t.modalidade_id,
           mo.nome as modalidade_nome,
           d.data as dia_data,
           t.horario_inicio
    FROM checkins c
    INNER JOIN alunos a ON a.id = c.aluno_id
    INNER JOIN turmas t ON t.id = c.turma_id
    LEFT JOIN modalidades mo ON mo.id = t.modalidade_id
    LEFT JOIN dias d ON d.id = t.dia_id
    WHERE a.usuario_id = ?
      AND t.modalidade_id = ?
      AND YEARWEEK(COALESCE(c.data_checkin_date, DATE(c.created_at)), 1) = YEARWEEK(CURDATE(), 1)
    ORDER BY c.created_at DESC
");
$stmtDetalhe->execute([$usuarioId, $modalidadeId]);
$checkinsSemana = $stmtDetalhe->fetchAll(PDO::FETCH_ASSOC);

if (empty($checkinsSemana)) {
    echo "  Nenhum check-in registrado esta semana.\n";
} else {
    foreach ($checkinsSemana as $c) {
        $presente = $c['presente'] === null ? '⏳ pendente' : ($c['presente'] ? '✅ presente' : '❌ falta');
        echo "  CK #{$c['id']} | {$c['data_checkin']} | turma: {$c['turma_nome']} ({$c['horario_inicio']})";
        echo " | {$presente}\n";
    }
}

// ============================================================
// 4. HISTÓRICO POR SEMANA (últimas 8 semanas)
// ============================================================
echo "\n4. HISTÓRICO POR SEMANA (últimas 8 semanas)\n";
echo str_repeat("-", 80) . "\n";

$stmtHist = $db->prepare("
    SELECT
        YEARWEEK(COALESCE(c.data_checkin_date, DATE(c.created_at)), 1) as semana_iso,
        MIN(COALESCE(c.data_checkin_date, DATE(c.created_at))) as semana_inicio,
        MAX(COALESCE(c.data_checkin_date, DATE(c.created_at))) as semana_fim,
        COUNT(*) as total,
        SUM(CASE WHEN c.presente = 0 THEN 1 ELSE 0 END) as faltas,
        SUM(CASE WHEN c.presente IS NULL OR c.presente = 1 THEN 1 ELSE 0 END) as presentes
    FROM checkins c
    INNER JOIN alunos a ON a.id = c.aluno_id
    INNER JOIN turmas t ON t.id = c.turma_id
    WHERE a.usuario_id = ?
      AND t.modalidade_id = ?
      AND COALESCE(c.data_checkin_date, DATE(c.created_at)) >= DATE_SUB(CURDATE(), INTERVAL 8 WEEK)
    GROUP BY YEARWEEK(COALESCE(c.data_checkin_date, DATE(c.created_at)), 1)
    ORDER BY semana_iso DESC
");
$stmtHist->execute([$usuarioId, $modalidadeId]);
$historico = $stmtHist->fetchAll(PDO::FETCH_ASSOC);

if (empty($historico)) {
    echo "  Nenhum check-in nos últimos 2 meses.\n";
} else {
    $semanaAtualISO = $db->query("SELECT YEARWEEK(CURDATE(), 1)")->fetchColumn();
    printf("  %-12s %-12s %-12s %-8s %-8s %-8s %s\n", "Semana ISO", "Início", "Fim", "Total", "Presentes", "Faltas", "Status");
    echo "  " . str_repeat("-", 76) . "\n";
    foreach ($historico as $h) {
        $isAtual = $h['semana_iso'] == $semanaAtualISO ? ' ◀ atual' : '';
        $semPresentes = (int) $h['presentes'];
        $icon = $semPresentes > $limiteEfetivo ? '⚠️ EXCEDEU' : ($semPresentes == $limiteEfetivo ? '🔶 LIMITE' : '✅');
        printf("  %-12s %-12s %-12s %-8s %-8s %-8s %s%s\n",
            $h['semana_iso'],
            $h['semana_inicio'],
            $h['semana_fim'],
            $h['total'],
            $h['presentes'],
            $h['faltas'],
            $icon,
            $isAtual
        );
    }
}

// ============================================================
// 5. TODOS OS CHECK-INS DO MÊS ATUAL
// ============================================================
echo "\n5. CHECK-INS DO MÊS ATUAL (" . date('m/Y') . ")\n";
echo str_repeat("-", 80) . "\n";

$stmtMes = $db->prepare("
    SELECT c.id,
           COALESCE(c.data_checkin_date, DATE(c.created_at)) as data_checkin,
           DAYOFWEEK(COALESCE(c.data_checkin_date, DATE(c.created_at))) as dia_semana_num,
           YEARWEEK(COALESCE(c.data_checkin_date, DATE(c.created_at)), 1) as semana_iso,
           c.presente,
           t.nome as turma_nome, t.horario_inicio,
           mo.nome as modalidade_nome
    FROM checkins c
    INNER JOIN alunos a ON a.id = c.aluno_id
    INNER JOIN turmas t ON t.id = c.turma_id
    LEFT JOIN modalidades mo ON mo.id = t.modalidade_id
    WHERE a.usuario_id = ?
      AND t.modalidade_id = ?
      AND YEAR(COALESCE(c.data_checkin_date, DATE(c.created_at))) = YEAR(CURDATE())
      AND MONTH(COALESCE(c.data_checkin_date, DATE(c.created_at))) = MONTH(CURDATE())
    ORDER BY data_checkin ASC, c.created_at ASC
");
$stmtMes->execute([$usuarioId, $modalidadeId]);
$checkinsMes = $stmtMes->fetchAll(PDO::FETCH_ASSOC);

$diasSemana = [1 => 'Dom', 2 => 'Seg', 3 => 'Ter', 4 => 'Qua', 5 => 'Qui', 6 => 'Sex', 7 => 'Sáb'];
$totalMes = 0;
$semanaAtual = null;
$contSemana = 0;
$semanaAtualISO = $db->query("SELECT YEARWEEK(CURDATE(), 1)")->fetchColumn();

if (empty($checkinsMes)) {
    echo "  Nenhum check-in neste mês.\n";
} else {
    foreach ($checkinsMes as $c) {
        if ($c['semana_iso'] !== $semanaAtual) {
            if ($semanaAtual !== null) {
                $icon = $contSemana > $limiteEfetivo ? '⚠️ EXCEDEU' : ($contSemana == $limiteEfetivo ? '🔶 LIMITE' : '✅');
                echo "  → Subtotal semana {$semanaAtual}: {$contSemana} check-ins {$icon}\n\n";
            }
            $semanaAtual = $c['semana_iso'];
            $contSemana = 0;
            echo "  -- Semana ISO {$c['semana_iso']}" . ($c['semana_iso'] == $semanaAtualISO ? " (atual)" : "") . "\n";
        }

        if ($c['presente'] === null || $c['presente']) {
            $contSemana++;
            $totalMes++;
        }
        $diaLabel = $diasSemana[(int)$c['dia_semana_num']] ?? '?';
        $presente = $c['presente'] === null ? '⏳' : ($c['presente'] ? '✅' : '❌');
        echo "    CK #{$c['id']} | {$diaLabel} {$c['data_checkin']} | {$c['turma_nome']} {$c['horario_inicio']} | {$presente}\n";
    }
    if ($semanaAtual !== null) {
        $icon = $contSemana > $limiteEfetivo ? '⚠️ EXCEDEU' : ($contSemana == $limiteEfetivo ? '🔶 LIMITE' : '✅');
        echo "  → Subtotal semana {$semanaAtual}: {$contSemana} check-ins {$icon}\n";
    }
    echo "\n  Total no mês: {$totalMes} check-ins\n";
}

// ============================================================
// 6. SIMULAÇÃO DA VALIDAÇÃO DO MOBILLECONTROLLER
// ============================================================
echo "\n6. SIMULAÇÃO DA VALIDAÇÃO (como o MobileController vê agora)\n";
echo str_repeat("-", 80) . "\n";

// obterLimiteCheckinsPlano equivalente
$stmtPlano = $db->prepare("
    SELECT p.checkins_semanais, p.nome as plano_nome, p.modalidade_id,
           CASE
               WHEN m.plano_ciclo_id IS NOT NULL THEN COALESCE(pc.permite_reposicao, 0)
               ELSE COALESCE((
                   SELECT MAX(pc2.permite_reposicao)
                   FROM plano_ciclos pc2
                   WHERE pc2.plano_id = p.id AND pc2.tenant_id = m.tenant_id AND pc2.ativo = 1
               ), 0)
           END as permite_reposicao
    FROM matriculas m
    INNER JOIN planos p ON m.plano_id = p.id
    LEFT JOIN plano_ciclos pc ON pc.id = m.plano_ciclo_id AND pc.tenant_id = m.tenant_id
    INNER JOIN alunos a ON a.id = m.aluno_id
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    WHERE a.usuario_id = ?
      AND m.tenant_id = ?
      AND sm.codigo = 'ativa'
      AND COALESCE(m.proxima_data_vencimento, m.data_vencimento) >= CURDATE()
      AND p.modalidade_id = ?
    ORDER BY m.proxima_data_vencimento DESC
    LIMIT 1
");
$stmtPlano->execute([$usuarioId, $tenantId, $modalidadeId]);
$planoInfo = $stmtPlano->fetch(PDO::FETCH_ASSOC);

if (!$planoInfo) {
    echo "  ⚠️  obterLimiteCheckinsPlano retorna: tem_plano=false (nenhuma matrícula ativa encontrada!)\n";
} else {
    $limite   = (int) $planoInfo['checkins_semanais'];
    $permRep  = (bool) $planoInfo['permite_reposicao'];
    echo "  tem_plano         : true\n";
    echo "  plano_nome        : {$planoInfo['plano_nome']}\n";
    echo "  limite semanal    : {$limite}\n";
    echo "  permite_reposicao : " . ($permRep ? 'true' : 'false') . "\n";

    if ($permRep) {
        // Caminho mensal
        $limiteMensal = $limite * 4 + $bonusCincoSemanas;
        $stmtMesCount = $db->prepare("
            SELECT COUNT(*) FROM checkins c
            INNER JOIN alunos a ON a.id = c.aluno_id
            INNER JOIN turmas t ON c.turma_id = t.id
            WHERE a.usuario_id = ?
              AND t.modalidade_id = ?
              AND YEAR(COALESCE(c.data_checkin_date, DATE(c.created_at))) = YEAR(CURDATE())
              AND MONTH(COALESCE(c.data_checkin_date, DATE(c.created_at))) = MONTH(CURDATE())
              AND (c.presente IS NULL OR c.presente = 1)
        ");
        $stmtMesCount->execute([$usuarioId, $modalidadeId]);
        $checkinsNoMes = (int) $stmtMesCount->fetchColumn();
        echo "  → Caminho MENSAL  : limite_mes={$limiteMensal}, checkins_mes={$checkinsNoMes}\n";
        if ($checkinsNoMes >= $limiteMensal) {
            echo "  → RESULTADO       : ⛔ BLOQUEARIA check-in (limite mensal atingido)\n";
        } else {
            echo "  → RESULTADO       : ✅ PERMITIRIA check-in ({$checkinsNoMes}/{$limiteMensal})\n";
        }
    } else {
        // Caminho semanal
        $limiteEfetivo2 = $limite + $bonusCincoSemanas;
        $stmtSemCount = $db->prepare("
            SELECT COUNT(*) FROM checkins c
            INNER JOIN alunos a ON a.id = c.aluno_id
            INNER JOIN turmas t ON c.turma_id = t.id
            WHERE a.usuario_id = ?
              AND t.modalidade_id = ?
              AND YEARWEEK(COALESCE(c.data_checkin_date, DATE(c.created_at)), 1) = YEARWEEK(CURDATE(), 1)
              AND (c.presente IS NULL OR c.presente = 1)
        ");
        $stmtSemCount->execute([$usuarioId, $modalidadeId]);
        $checkinsNaSemana = (int) $stmtSemCount->fetchColumn();
        echo "  → Caminho SEMANAL : limite_sem={$limiteEfetivo2}, checkins_semana={$checkinsNaSemana}\n";
        if ($checkinsNaSemana >= $limiteEfetivo2) {
            echo "  → RESULTADO       : ⛔ BLOQUEARIA check-in (limite semanal atingido)\n";
        } else {
            echo "  → RESULTADO       : ✅ PERMITIRIA check-in ({$checkinsNaSemana}/{$limiteEfetivo2})\n";
        }
    }
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "Fim do diagnóstico.\n";
