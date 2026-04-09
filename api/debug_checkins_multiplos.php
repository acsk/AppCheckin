<?php
/**
 * Debug: check-ins considerados "múltiplos no mesmo dia" pela auditoria
 * Investiga IDs específicos para identificar falso positivo vs duplicata real
 *
 * Uso: php debug_checkins_multiplos.php [checkin_ids]
 * Ex:  php debug_checkins_multiplos.php 864,889
 */

require_once __DIR__ . '/vendor/autoload.php';
$db = require __DIR__ . '/config/database.php';
date_default_timezone_set('America/Sao_Paulo');

$idsArg = $argv[1] ?? '864,889';
$ids = array_map('intval', explode(',', $idsArg));
$placeholders = implode(',', array_fill(0, count($ids), '?'));

echo "====== DEBUG CHECK-INS MÚLTIPLOS — IDs: {$idsArg} ======\n";
echo "Data BRT: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Dados completos de cada check-in
echo "1. DADOS COMPLETOS DOS CHECK-INS\n";
echo str_repeat('-', 80) . "\n";

$sql = "
    SELECT
        c.id                                   AS checkin_id,
        c.aluno_id,
        a.nome                                 AS aluno_nome,
        c.turma_id,
        c.horario_id,
        c.presente,
        c.registrado_por_admin,
        c.admin_id,
        DATE(c.created_at)                     AS created_at_date,
        c.created_at                           AS created_at_full,
        c.data_checkin                         AS data_checkin_col,
        c.data_checkin_date                    AS data_checkin_date_col,
        COALESCE(c.data_checkin_date, DATE(c.created_at)) AS data_usada_auditoria
    FROM checkins c
    INNER JOIN alunos a ON a.id = c.aluno_id
    WHERE c.id IN ($placeholders)
    ORDER BY c.id
";
$stmt = $db->prepare($sql);
$stmt->execute($ids);
$checkins = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($checkins as $c) {
    echo "  Checkin #{$c['checkin_id']}\n";
    echo "    Aluno           : {$c['aluno_nome']} (aluno_id={$c['aluno_id']})\n";
    echo "    turma_id        : " . ($c['turma_id'] ?? 'NULL') . "\n";
    echo "    horario_id      : " . ($c['horario_id'] ?? 'NULL') . "\n";
    echo "    presente        : " . ($c['presente'] === null ? 'NULL (pendente)' : ($c['presente'] ? 'SIM' : 'NÃO (falta)')) . "\n";
    echo "    registrado_admin: " . ($c['registrado_por_admin'] ? "SIM (admin_id={$c['admin_id']})" : 'NÃO') . "\n";
    echo "    created_at      : {$c['created_at_full']}\n";
    echo "    data_checkin    : " . ($c['data_checkin_col'] ?? 'NULL') . "\n";
    echo "    data_checkin_date: " . ($c['data_checkin_date_col'] ?? 'NULL') . "\n";
    echo "    ► DATA usada na auditoria (COALESCE): {$c['data_usada_auditoria']}\n";
    echo "\n";
}

// 2. Dados da turma e do dia real de cada check-in
echo "2. TURMA / DIA REAL DE CADA CHECK-IN\n";
echo str_repeat('-', 80) . "\n";

$sqlTurma = "
    SELECT
        c.id                                   AS checkin_id,
        c.turma_id,
        t.nome                                 AS turma_nome,
        t.horario_inicio,
        t.horario_fim,
        t.dia_id,
        d.data                                 AS dia_data,
        mo.id                                  AS modalidade_id,
        mo.nome                                AS modalidade_nome,
        t.tenant_id                            AS turma_tenant_id
    FROM checkins c
    LEFT JOIN turmas t    ON t.id  = c.turma_id
    LEFT JOIN dias   d    ON d.id  = t.dia_id
    LEFT JOIN modalidades mo ON mo.id = t.modalidade_id
    WHERE c.id IN ($placeholders)
    ORDER BY c.id
";
$stmt = $db->prepare($sqlTurma);
$stmt->execute($ids);
$turmas = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($turmas as $t) {
    echo "  Checkin #{$t['checkin_id']} → turma_id=" . ($t['turma_id'] ?? 'NULL') . "\n";
    echo "    Turma           : " . ($t['turma_nome'] ?? 'N/A') . " ({$t['horario_inicio']} – {$t['horario_fim']})\n";
    echo "    Modalidade      : " . ($t['modalidade_nome'] ?? 'N/A') . " (id={$t['modalidade_id']})\n";
    echo "    dia_id          : " . ($t['dia_id'] ?? 'NULL') . "\n";
    echo "    ► DATA REAL da aula (dias.data) : " . ($t['dia_data'] ?? 'NULL') . "\n";
    echo "\n";
}

// 3. Comparação: dia real vs data usada pela auditoria
echo "3. DIAGNÓSTICO DE DIVERGÊNCIA\n";
echo str_repeat('-', 80) . "\n";

// Merge das duas queries pelo checkin_id
$byId = [];
foreach ($checkins as $c) { $byId[$c['checkin_id']] = $c; }
foreach ($turmas as $t)   { $byId[$t['checkin_id']] = array_merge($byId[$t['checkin_id']] ?? [], $t); }

$datasAuditoria = [];
$datasReais     = [];
foreach ($byId as $cid => $row) {
    $datasAuditoria[$cid] = $row['data_usada_auditoria'];
    $datasReais[$cid]     = $row['dia_data'] ?? null;
}

$todosMesmaDataAuditoria = count(array_unique($datasAuditoria)) === 1;
$todosMesmaDataReal      = $datasReais && count(array_unique(array_filter($datasReais))) === 1;
$hasDivergencia          = false;

foreach ($byId as $cid => $row) {
    $audData  = $row['data_usada_auditoria'];
    $realData = $row['dia_data'] ?? 'N/A';
    $igual    = $audData === $realData;
    if (!$igual) $hasDivergencia = true;
    $flag     = $igual ? '✅' : '⚠️ DIVERGENTE';
    echo "  Checkin #{$cid}: auditoria={$audData} | dias.data={$realData} {$flag}\n";
}

echo "\n";

if ($hasDivergencia) {
    echo "  ⚠️  FALSO POSITIVO: os check-ins são de AULAS DIFERENTES.\n";
    echo "  O audit usa DATE(created_at) mas a data real da turma (dias.data) é diferente.\n";
    echo "  Solução: corrigir a query da auditoria para usar DATE(d.data) em vez de DATE(c.created_at).\n";
} elseif ($todosMesmaDataReal) {
    echo "  🔴 DUPLICATA REAL: ambos os check-ins são na mesma data de aula.\n";
    // Verificar se é a mesma turma
    $turmaIds = array_unique(array_column(array_values($byId), 'turma_id'));
    if (count($turmaIds) === 1) {
        echo "  🔴 MESMA TURMA — possível bug de inserção dupla.\n";
    } else {
        echo "  🟡 TURMAS DIFERENTES no mesmo dia — a aluna entrou em duas turmas da mesma modalidade.\n";
        echo "  A validação usuarioTemCheckinNoDiaNaModalidade deveria ter bloqueado isso.\n";
    }
} else {
    echo "  ℹ️  Verificação inconclusiva (dia_data NULL em algum registro).\n";
}

// 4. Como a aluna passou pela validação (se duplicata real)
if (!$hasDivergencia && $datasReais) {
    echo "\n4. COMO A VALIDAÇÃO FOI BYPASSADA\n";
    echo str_repeat('-', 80) . "\n";

    foreach ($byId as $row) {
        if (empty($row['turma_id'])) continue;
        // Verificar se usuarioTemCheckinNoDiaNaModalidade teria bloqueado
        $alunoId     = $row['aluno_id'];
        $modalidadeId = $row['modalidade_id'];
        $diaData     = $row['dia_data'];

        $sqlCheck = "
            SELECT c.id, DATE(d.data) AS dia_data_check, t.modalidade_id
            FROM checkins c
            INNER JOIN alunos a ON a.id = c.aluno_id
            INNER JOIN turmas t ON t.id = c.turma_id
            INNER JOIN dias   d ON d.id = t.dia_id
            WHERE a.id = ?
              AND DATE(d.data) = ?
              AND t.modalidade_id = ?
            ORDER BY c.id
        ";
        $stmtC = $db->prepare($sqlCheck);
        $stmtC->execute([$alunoId, $diaData, $modalidadeId]);
        $duplicates = $stmtC->fetchAll(PDO::FETCH_ASSOC);
        echo "  Aluno #{$alunoId} | modalidade_id={$modalidadeId} | dia={$diaData}:\n";
        foreach ($duplicates as $d) {
            echo "    → checkin #{$d['id']} (dia_data={$d['dia_data_check']}, modalidade={$d['modalidade_id']})\n";
        }
    }
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "Fim do diagnóstico.\n";
