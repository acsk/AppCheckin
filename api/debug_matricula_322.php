<?php
/**
 * Debug Matrícula #322
 * Investigação: matrícula é uma DIÁRIA (plano com duracao_dias=1), porém está
 * ativa há 30 dias e continua liberando checkin no mobile.
 *
 * Hipóteses:
 *  - data_vencimento foi calculada como +30 dias em vez de +1.
 *  - tipo_cobranca diferente do esperado (recorrente vs avulso).
 *  - Plano cadastrado com duracao_dias errado.
 *  - Job de atualização de status não considera diárias / não cancela após checkin.
 *  - Não existe job para cancelar a matrícula após o checkin do dia.
 *
 * Uso: php debug_matricula_322.php
 */

require_once __DIR__ . '/vendor/autoload.php';

$db = require __DIR__ . '/config/database.php';
$matriculaId = 322;

date_default_timezone_set('America/Sao_Paulo');

echo "====== DEBUG MATRÍCULA #{$matriculaId} (DIÁRIA) ======\n";
echo "Data BRT (script): " . date('Y-m-d H:i:s') . "\n";
echo "Data UTC (server): " . gmdate('Y-m-d H:i:s') . " UTC\n\n";

// ============================================================
// 1. DADOS DA MATRÍCULA + PLANO
// ============================================================
echo "1. DADOS DA MATRÍCULA + PLANO\n";
echo str_repeat("-", 80) . "\n";
$stmt = $db->prepare("
    SELECT
        m.*,
        sm.codigo AS status_codigo,
        sm.nome   AS status_nome,
        a.nome    AS aluno_nome,
        a.id      AS aluno_id_real,
        u.id      AS usuario_id,
        u.email   AS aluno_email,
        p.nome    AS plano_nome,
        p.valor   AS plano_valor,
        p.duracao_dias,
        p.checkins_semanais,
        p.modalidade_id,
        mod.nome  AS modalidade_nome,
        pc.meses  AS ciclo_meses,
        pc.valor  AS ciclo_valor,
        af.nome   AS ciclo_nome,
        t.nome    AS tenant_nome
    FROM matriculas m
    INNER JOIN alunos a            ON a.id  = m.aluno_id
    INNER JOIN usuarios u          ON u.id  = a.usuario_id
    INNER JOIN planos p            ON p.id  = m.plano_id
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    INNER JOIN tenants t           ON t.id  = m.tenant_id
    LEFT  JOIN modalidades mod     ON mod.id = p.modalidade_id
    LEFT  JOIN plano_ciclos pc     ON pc.id  = m.plano_ciclo_id
    LEFT  JOIN assinatura_frequencias af ON af.id = pc.assinatura_frequencia_id
    WHERE m.id = ?
");
$stmt->execute([$matriculaId]);
$matricula = $stmt->fetch(\PDO::FETCH_ASSOC);

if (!$matricula) {
    echo "❌ Matrícula #{$matriculaId} não encontrada!\n";
    exit(1);
}

$alunoId  = (int)$matricula['aluno_id'];
$tenantId = (int)$matricula['tenant_id'];
$planoId  = (int)$matricula['plano_id'];
$duracaoDias = (int)$matricula['duracao_dias'];

echo "ID matrícula:          {$matricula['id']}\n";
echo "Tenant:                {$matricula['tenant_nome']} (id={$tenantId})\n";
echo "Aluno:                 {$matricula['aluno_nome']} (aluno_id={$alunoId}, usuario_id={$matricula['usuario_id']})\n";
echo "Email:                 {$matricula['aluno_email']}\n";
echo "Plano:                 {$matricula['plano_nome']} (plano_id={$planoId})\n";
echo "Modalidade:            " . ($matricula['modalidade_nome'] ?? '-') . " (id=" . ($matricula['modalidade_id'] ?? '-') . ")\n";
echo "Valor plano:           R$ " . number_format((float)$matricula['plano_valor'], 2, ',', '.') . "\n";
echo "duracao_dias (plano):  {$duracaoDias}\n";
echo "checkins_semanais:     " . ($matricula['checkins_semanais'] ?? '-') . "\n";
echo "Ciclo:                 " . ($matricula['ciclo_nome'] ?? '-') . " | meses=" . ($matricula['ciclo_meses'] ?? '-') . " | R$ " . number_format((float)($matricula['ciclo_valor'] ?? 0), 2, ',', '.') . "\n";
echo "tipo_cobranca:         " . ($matricula['tipo_cobranca'] ?? '-') . "\n";
echo "Status:                {$matricula['status_nome']} ({$matricula['status_codigo']}) — status_id={$matricula['status_id']}\n";
echo "\n";
echo "data_matricula:        {$matricula['data_matricula']}\n";
echo "data_inicio:           {$matricula['data_inicio']}\n";
echo "data_vencimento:       {$matricula['data_vencimento']}\n";
echo "proxima_data_vencimento: " . ($matricula['proxima_data_vencimento'] ?? 'NULL') . "\n";
echo "created_at:            {$matricula['created_at']}\n";
echo "updated_at:            {$matricula['updated_at']}\n";

// ============================================================
// 2. DIAGNÓSTICO ESPECÍFICO DE DIÁRIA
// ============================================================
echo "\n\n2. DIAGNÓSTICO DA DIÁRIA\n";
echo str_repeat("-", 80) . "\n";

$ehDiaria = ($duracaoDias === 1);
echo "É diária (duracao_dias == 1)? " . ($ehDiaria ? 'SIM' : 'NÃO') . "\n";

if ($matricula['data_inicio'] && $matricula['data_vencimento']) {
    $ini = new \DateTimeImmutable($matricula['data_inicio']);
    $ven = new \DateTimeImmutable($matricula['data_vencimento']);
    $diff = (int)$ini->diff($ven)->format('%r%a');
    echo "Dias entre data_inicio e data_vencimento: {$diff}\n";

    $esperado = max(1, $duracaoDias);
    if ($ehDiaria && $diff !== 0 && $diff !== 1) {
        echo "❌ DIVERGÊNCIA: plano é diária mas vencimento está {$diff} dias após o início (esperado 0 ou 1).\n";
    } elseif ($diff !== $esperado && $diff !== ($esperado - 1)) {
        echo "⚠️  Diferença ({$diff}) não bate com duracao_dias do plano ({$esperado}).\n";
    } else {
        echo "✅ Vencimento coerente com duracao_dias.\n";
    }
}

$hoje = date('Y-m-d');
if ($matricula['data_vencimento']) {
    $venceu = $matricula['data_vencimento'] < $hoje;
    echo "Hoje:                  {$hoje}\n";
    echo "Já venceu?             " . ($venceu ? 'SIM' : 'NÃO') . "\n";
    if ($venceu && in_array($matricula['status_codigo'], ['ativa', 'ATIVA'], true)) {
        echo "❌ Matrícula está ATIVA mas data_vencimento já passou ({$matricula['data_vencimento']}).\n";
    }
}

// ============================================================
// 3. CHECK-INS REGISTRADOS PARA ESTA MATRÍCULA / ALUNO
// ============================================================
echo "\n\n3. CHECK-INS\n";
echo str_repeat("-", 80) . "\n";
// Tentamos matricula_id direto e fallback por aluno_id no período da matrícula
$colsStmt = $db->query("SHOW COLUMNS FROM checkins");
$cols = array_column($colsStmt->fetchAll(\PDO::FETCH_ASSOC), 'Field');
$temMatriculaId = in_array('matricula_id', $cols, true);

if ($temMatriculaId) {
    $sqlCk = "SELECT id, aluno_id, matricula_id, data_checkin, created_at
              FROM checkins WHERE matricula_id = ? ORDER BY data_checkin DESC, id DESC LIMIT 50";
    $stmt = $db->prepare($sqlCk);
    $stmt->execute([$matriculaId]);
} else {
    $ini = $matricula['data_inicio'] ?? $matricula['data_matricula'];
    $sqlCk = "SELECT id, aluno_id, data_checkin, created_at
              FROM checkins WHERE aluno_id = ? AND data_checkin >= ?
              ORDER BY data_checkin DESC, id DESC LIMIT 50";
    $stmt = $db->prepare($sqlCk);
    $stmt->execute([$alunoId, $ini]);
}
$checkins = $stmt->fetchAll(\PDO::FETCH_ASSOC);

echo "Total de checkins listados: " . count($checkins) . "\n";
$primeiroCk = null;
foreach ($checkins as $ck) {
    echo "  CK #{$ck['id']} | data={$ck['data_checkin']} | created_at={$ck['created_at']}"
       . (isset($ck['matricula_id']) ? " | matricula_id={$ck['matricula_id']}" : '')
       . "\n";
    $primeiroCk = $ck; // último armazenado é o mais antigo da lista (ORDER DESC)
}

if (!empty($checkins)) {
    $primeiro = end($checkins);
    $ultimo   = reset($checkins);
    echo "\nPrimeiro checkin no período: {$primeiro['data_checkin']}\n";
    echo "Último checkin no período:   {$ultimo['data_checkin']}\n";

    if ($ehDiaria) {
        $datasUnicas = array_unique(array_map(fn($c) => substr($c['data_checkin'], 0, 10), $checkins));
        echo "Dias distintos com checkin:  " . count($datasUnicas) . "\n";
        if (count($datasUnicas) > 1) {
            echo "❌ Diária com checkins em MAIS DE UM DIA — matrícula deveria ter sido encerrada após o 1º checkin.\n";
        }
    }
}

// ============================================================
// 4. ASSINATURAS / PAGAMENTOS
// ============================================================
echo "\n\n4. ASSINATURAS\n";
echo str_repeat("-", 80) . "\n";
$stmt = $db->prepare("
    SELECT a.id, a.status_id, a.status_gateway, a.tipo_cobranca, a.valor,
           a.gateway_assinatura_id, a.external_reference,
           a.data_inicio, a.data_fim, a.proxima_cobranca, a.ultima_cobranca,
           a.criado_em, a.atualizado_em,
           ast.codigo AS status_codigo, ast.nome AS status_nome,
           f.nome AS frequencia_nome, f.meses AS frequencia_meses
    FROM assinaturas a
    LEFT JOIN assinatura_status ast      ON ast.id = a.status_id
    LEFT JOIN assinatura_frequencias f   ON f.id   = a.frequencia_id
    WHERE a.matricula_id = ?
    ORDER BY a.id DESC
");
$stmt->execute([$matriculaId]);
$assinaturas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
echo "Total: " . count($assinaturas) . "\n";
foreach ($assinaturas as $ass) {
    echo "  Ass #{$ass['id']} | status={$ass['status_codigo']} ({$ass['status_nome']}) | gateway={$ass['status_gateway']} | tipo={$ass['tipo_cobranca']}\n";
    echo "    frequencia: " . ($ass['frequencia_nome'] ?? '-') . " (" . ($ass['frequencia_meses'] ?? '-') . " mes)\n";
    echo "    data_inicio={$ass['data_inicio']} | data_fim=" . ($ass['data_fim'] ?? 'NULL') . "\n";
    echo "    proxima_cobranca=" . ($ass['proxima_cobranca'] ?? 'NULL') . " | ultima=" . ($ass['ultima_cobranca'] ?? 'NULL') . "\n";
    echo "    valor=R$ " . number_format((float)$ass['valor'], 2, ',', '.') . "\n";
    echo "    gateway_assinatura_id=" . ($ass['gateway_assinatura_id'] ?? 'NULL') . " | ext_ref=" . ($ass['external_reference'] ?? 'NULL') . "\n";
}

echo "\n\n5. PARCELAS (pagamentos_plano)\n";
echo str_repeat("-", 80) . "\n";
$stmt = $db->prepare("
    SELECT pp.id, pp.data_vencimento, pp.data_pagamento,
           sp.nome AS status_nome, pp.status_pagamento_id,
           pp.valor, pp.observacoes
    FROM pagamentos_plano pp
    LEFT JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
    WHERE pp.matricula_id = ?
    ORDER BY pp.data_vencimento ASC, pp.id ASC
");
$stmt->execute([$matriculaId]);
$parcelas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
echo "Total: " . count($parcelas) . "\n";
foreach ($parcelas as $p) {
    echo "  Pag #{$p['id']} | venc={$p['data_vencimento']} | pag=" . ($p['data_pagamento'] ?? '-')
       . " | {$p['status_nome']} | R\$ " . number_format((float)$p['valor'], 2, ',', '.') . "\n";
    if (!empty($p['observacoes'])) echo "       obs: {$p['observacoes']}\n";
}

// ============================================================
// 6. PLANO ORIGINAL (verificar se cadastro está correto)
// ============================================================
echo "\n\n6. CONFIGURAÇÃO DO PLANO\n";
echo str_repeat("-", 80) . "\n";
$stmt = $db->prepare("SELECT * FROM planos WHERE id = ?");
$stmt->execute([$planoId]);
$plano = $stmt->fetch(\PDO::FETCH_ASSOC);
foreach (['id','nome','descricao','valor','duracao_dias','checkins_semanais','ativo','tenant_id','modalidade_id','created_at','updated_at'] as $k) {
    if (array_key_exists($k, $plano ?? [])) {
        echo str_pad($k, 22) . ": " . ($plano[$k] ?? 'NULL') . "\n";
    }
}

// ============================================================
// 7. STATUS DISPONÍVEIS PARA MATRÍCULA (referência p/ job)
// ============================================================
echo "\n\n7. STATUS DE MATRÍCULA (catálogo)\n";
echo str_repeat("-", 80) . "\n";
$stmt = $db->query("SELECT id, codigo, nome FROM status_matricula ORDER BY id");
foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $s) {
    echo "  id={$s['id']} | codigo={$s['codigo']} | nome={$s['nome']}\n";
}

// ============================================================
// 8. OUTRAS DIÁRIAS DO TENANT (tem mais casos?)
// ============================================================
echo "\n\n8. OUTRAS MATRÍCULAS DIÁRIAS DO TENANT (mesma situação?)\n";
echo str_repeat("-", 80) . "\n";
$stmt = $db->prepare("
    SELECT m.id, m.aluno_id, a.nome AS aluno_nome,
           m.data_inicio, m.data_vencimento,
           sm.codigo AS status_codigo,
           p.nome AS plano_nome, p.duracao_dias,
           DATEDIFF(m.data_vencimento, m.data_inicio) AS dias_vigencia
    FROM matriculas m
    INNER JOIN planos p ON p.id = m.plano_id
    INNER JOIN alunos a ON a.id = m.aluno_id
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    WHERE m.tenant_id = ?
      AND p.duracao_dias = 1
    ORDER BY m.id DESC
    LIMIT 30
");
$stmt->execute([$tenantId]);
$outras = $stmt->fetchAll(\PDO::FETCH_ASSOC);
echo "Total diárias do tenant (últimas 30): " . count($outras) . "\n";
foreach ($outras as $o) {
    $marker = ((int)$o['dias_vigencia'] > 1) ? '❌' : '  ';
    echo "  {$marker} mat #{$o['id']} | {$o['aluno_nome']} | {$o['plano_nome']} | "
       . "ini={$o['data_inicio']} venc={$o['data_vencimento']} ({$o['dias_vigencia']}d) | status={$o['status_codigo']}\n";
}

// ============================================================
// SUMÁRIO / PROPOSTA
// ============================================================
echo "\n\n====== SUMÁRIO ======\n";
$problemas = [];
if (!$ehDiaria) $problemas[] = "Plano não tem duracao_dias=1 (valor atual={$duracaoDias}). Confirmar se realmente é diária.";
if ($matricula['data_inicio'] && $matricula['data_vencimento']) {
    $diff = (int)(new \DateTimeImmutable($matricula['data_inicio']))->diff(new \DateTimeImmutable($matricula['data_vencimento']))->format('%r%a');
    if ($ehDiaria && $diff > 1) $problemas[] = "Vencimento {$diff} dias após o início (esperado 0/1) — cálculo errado na criação da matrícula.";
}
if (!empty($checkins) && $ehDiaria) {
    $datasUnicas = array_unique(array_map(fn($c) => substr($c['data_checkin'],0,10), $checkins));
    if (count($datasUnicas) > 1) {
        $problemas[] = "Aluno fez checkin em " . count($datasUnicas) . " dias distintos com a mesma matrícula diária.";
    }
}
if (in_array($matricula['status_codigo'], ['ativa','ATIVA'], true) && $matricula['data_vencimento'] && $matricula['data_vencimento'] < $hoje) {
    $problemas[] = "Status ainda 'ativa' depois do vencimento — job atualizar_status_matriculas não cobriu este caso.";
}

if (!$problemas) {
    echo "Nenhuma anomalia óbvia detectada. Revisar manualmente.\n";
} else {
    foreach ($problemas as $i => $p) echo " " . ($i+1) . ". {$p}\n";
}

echo "\nPróximos passos sugeridos:\n";
echo " - Confirmar se a regra de negócio é: diária expira no fim do dia OU ao 1º checkin.\n";
echo " - Criar job (ou hook no CheckinController) que, ao registrar checkin de matrícula com duracao_dias=1,\n";
echo "   defina data_vencimento=hoje e mude status para 'cancelada'/'encerrada'.\n";
echo " - Backfill: rodar script para corrigir as matrículas diárias listadas no item 8 acima.\n";
