<?php
/**
 * Debug matrícula #113 — análise de check-ins e campo presente
 * Uso: docker exec appcheckin_php php /var/www/html/debug_matricula_113.php
 */

require_once __DIR__ . '/vendor/autoload.php';

$db = require __DIR__ . '/config/database.php';

$matriculaId = 113;

echo "=== DEBUG MATRÍCULA #{$matriculaId} ===\n";
echo "Data/Hora: " . date('Y-m-d H:i:s') . "\n\n";

// ────────────────────────────────────────────────────────────────
// SEÇÃO 1: Dados da matrícula
// ────────────────────────────────────────────────────────────────
echo "--- SEÇÃO 1: DADOS DA MATRÍCULA ---\n";
$stmt = $db->prepare("
    SELECT m.id, m.aluno_id, m.plano_id, m.tenant_id, m.status_id,
           m.proxima_data_vencimento, m.data_matricula, m.plano_ciclo_id,
           sm.codigo as status_codigo, sm.nome as status_nome,
           p.nome as plano_nome, p.checkins_semanais, p.modalidade_id,
           a.nome as aluno_nome, a.usuario_id,
           pc.permite_reposicao, pc.id as ciclo_id
    FROM matriculas m
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    INNER JOIN planos p ON p.id = m.plano_id
    INNER JOIN alunos a ON a.id = m.aluno_id
    LEFT JOIN plano_ciclos pc ON pc.id = m.plano_ciclo_id
    WHERE m.id = :id
");
$stmt->execute(['id' => $matriculaId]);
$mat = $stmt->fetch();

if (!$mat) {
    echo "❌ Matrícula #{$matriculaId} NÃO ENCONTRADA!\n";
    exit(1);
}

echo "Matrícula ID: {$mat['id']}\n";
echo "Aluno: {$mat['aluno_nome']} (aluno_id={$mat['aluno_id']}, usuario_id={$mat['usuario_id']})\n";
echo "Tenant: {$mat['tenant_id']}\n";
echo "Status: {$mat['status_nome']} ({$mat['status_codigo']})\n";
echo "Data matrícula: {$mat['data_matricula']}\n";
echo "Próxima data vencimento: {$mat['proxima_data_vencimento']}\n";
echo "Plano: {$mat['plano_nome']} (id={$mat['plano_id']}, modalidade_id={$mat['modalidade_id']})\n";
echo "Checkins semanais (plano): {$mat['checkins_semanais']}\n";
echo "Ciclo: plano_ciclo_id=" . ($mat['plano_ciclo_id'] ?? 'NULL') . " (ciclo_id=" . ($mat['ciclo_id'] ?? 'NULL') . ")\n";
echo "Permite reposição: " . ($mat['permite_reposicao'] === null ? 'N/A' : ($mat['permite_reposicao'] ? 'SIM' : 'NÃO')) . "\n\n";

$userId  = (int) $mat['usuario_id'];
$tenantId = (int) $mat['tenant_id'];
$modalidadeId = $mat['modalidade_id'] ? (int) $mat['modalidade_id'] : null;

// ────────────────────────────────────────────────────────────────
// SEÇÃO 2: Cálculo de semanas do mês atual (regra bônus)
// ────────────────────────────────────────────────────────────────
echo "--- SEÇÃO 2: CÁLCULO DE SEMANAS DO MÊS (REGRA BÔNUS) ---\n";
$primeiroDiaMes = new DateTime(date('Y-m-01'));
$diaSemanaInicio = (int) $primeiroDiaMes->format('w');
$diasNoMes = (int) $primeiroDiaMes->format('t');
$semanasNoMes = (int) ceil(($diasNoMes + $diaSemanaInicio) / 7);
$bonusCincoSemanas = ($semanasNoMes >= 5) ? 1 : 0;

$diasSemana = ['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'];
echo "Mês/Ano: " . date('m/Y') . "\n";
echo "Primeiro dia do mês: {$diasSemana[$diaSemanaInicio]} (w={$diaSemanaInicio})\n";
echo "Dias no mês: {$diasNoMes}\n";
echo "Semanas no mês: {$semanasNoMes}\n";
echo "Bônus 5 semanas: " . ($bonusCincoSemanas ? "+1 (ATIVO)" : "0 (inativo)") . "\n";

$limiteSemanais = (int) $mat['checkins_semanais'];
$permiteReposicao = (bool) ($mat['permite_reposicao'] ?? false);
if ($permiteReposicao) {
    $limiteMensal = ($limiteSemanais * 4) + $bonusCincoSemanas;
    echo "Limite efetivo MENSAL (reposição): {$limiteSemanais}×4 + {$bonusCincoSemanas} = {$limiteMensal}\n";
} else {
    $limiteSemanal = $limiteSemanais + $bonusCincoSemanas;
    echo "Limite efetivo SEMANAL (sem reposição): {$limiteSemanais} + {$bonusCincoSemanas} = {$limiteSemanal}\n";
}
echo "\n";

// ────────────────────────────────────────────────────────────────
// SEÇÃO 3: Check-ins do mês atual (com detalhe de presença)
// ────────────────────────────────────────────────────────────────
echo "--- SEÇÃO 3: CHECK-INS DO MÊS ATUAL ---\n";
$stmt = $db->prepare("
    SELECT c.id, c.turma_id, c.data_checkin, c.created_at,
           COALESCE(c.data_checkin_date, DATE(c.created_at)) as data_efetiva,
           c.presente,
           c.presenca_confirmada_em, c.presenca_confirmada_por,
           c.registrado_por_admin, c.admin_id,
           t.nome as turma_nome, t.modalidade_id,
           m2.nome as modalidade_nome,
           conf.nome as confirmado_por_nome
    FROM checkins c
    INNER JOIN alunos a ON a.id = c.aluno_id
    LEFT JOIN turmas t ON t.id = c.turma_id
    LEFT JOIN modalidades m2 ON m2.id = t.modalidade_id
    LEFT JOIN usuarios conf ON conf.id = c.presenca_confirmada_por
    WHERE a.usuario_id = :usuario_id
      AND YEAR(COALESCE(c.data_checkin_date, DATE(c.created_at))) = YEAR(CURDATE())
      AND MONTH(COALESCE(c.data_checkin_date, DATE(c.created_at))) = MONTH(CURDATE())
    ORDER BY COALESCE(c.data_checkin_date, DATE(c.created_at)) ASC, c.created_at ASC
");
$stmt->execute(['usuario_id' => $userId]);
$checkinsMes = $stmt->fetchAll();

$totalMes = 0;
$totalPresente = 0;
$totalFalta = 0;
$totalPendente = 0;
$porModalidade = [];

foreach ($checkinsMes as $ck) {
    $presStr = $ck['presente'] === null ? 'PENDENTE(NULL)' : ($ck['presente'] ? 'PRESENTE(1)' : 'FALTA(0)');
    $tipo = $ck['registrado_por_admin'] ? 'MANUAL' : 'AUTO';
    $modId = $ck['modalidade_id'] ?? '?';
    $modNome = $ck['modalidade_nome'] ?? 'N/A';

    // Contabilizar (regra: presente=1 ou NULL contam, presente=0 não conta)
    if ($ck['presente'] === null || $ck['presente']) {
        $totalMes++;
        if (!isset($porModalidade[$modId])) {
            $porModalidade[$modId] = ['nome' => $modNome, 'contados' => 0, 'faltas' => 0];
        }
        $porModalidade[$modId]['contados']++;
    } else {
        if (!isset($porModalidade[$modId])) {
            $porModalidade[$modId] = ['nome' => $modNome, 'contados' => 0, 'faltas' => 0];
        }
        $porModalidade[$modId]['faltas']++;
    }

    if ($ck['presente'] === null) $totalPendente++;
    elseif ($ck['presente']) $totalPresente++;
    else $totalFalta++;

    $confInfo = $ck['presenca_confirmada_em']
        ? " | Confirmado em {$ck['presenca_confirmada_em']} por " . ($ck['confirmado_por_nome'] ?? "id={$ck['presenca_confirmada_por']}")
        : '';

    echo "  #{$ck['id']} | {$ck['data_efetiva']} | {$ck['turma_nome']} (turma={$ck['turma_id']}, mod={$modId} {$modNome}) | {$presStr} | {$tipo}{$confInfo}\n";
}

echo "\nResumo mês:\n";
echo "  Total registros: " . count($checkinsMes) . "\n";
echo "  Contados p/ limite (presente=1 ou NULL): {$totalMes}\n";
echo "  - Presentes (1): {$totalPresente}\n";
echo "  - Pendentes (NULL): {$totalPendente}\n";
echo "  Faltas (0, NÃO contam): {$totalFalta}\n";

if ($permiteReposicao) {
    echo "  Limite mensal: {$limiteMensal} | Usados: {$totalMes} | Restantes: " . max(0, $limiteMensal - $totalMes) . "\n";
}

echo "\nPor modalidade:\n";
foreach ($porModalidade as $mId => $info) {
    echo "  Modalidade {$mId} ({$info['nome']}): {$info['contados']} contados, {$info['faltas']} faltas\n";
}
echo "\n";

// ────────────────────────────────────────────────────────────────
// SEÇÃO 4: Check-ins da semana atual
// ────────────────────────────────────────────────────────────────
echo "--- SEÇÃO 4: CHECK-INS DA SEMANA ATUAL ---\n";
$stmt = $db->prepare("
    SELECT c.id, c.turma_id,
           COALESCE(c.data_checkin_date, DATE(c.created_at)) as data_efetiva,
           c.presente, c.created_at,
           t.nome as turma_nome, t.modalidade_id,
           m2.nome as modalidade_nome
    FROM checkins c
    INNER JOIN alunos a ON a.id = c.aluno_id
    LEFT JOIN turmas t ON t.id = c.turma_id
    LEFT JOIN modalidades m2 ON m2.id = t.modalidade_id
    WHERE a.usuario_id = :usuario_id
      AND YEARWEEK(COALESCE(c.data_checkin_date, DATE(c.created_at)), 1) = YEARWEEK(CURDATE(), 1)
      AND (c.presente IS NULL OR c.presente = 1)
    ORDER BY COALESCE(c.data_checkin_date, DATE(c.created_at)) ASC
");
$stmt->execute(['usuario_id' => $userId]);
$checkinsSemana = $stmt->fetchAll();

echo "Check-ins contados na semana: " . count($checkinsSemana) . "\n";
foreach ($checkinsSemana as $ck) {
    $presStr = $ck['presente'] === null ? 'PENDENTE' : 'PRESENTE';
    echo "  #{$ck['id']} | {$ck['data_efetiva']} | {$ck['turma_nome']} (mod={$ck['modalidade_id']} {$ck['modalidade_nome']}) | {$presStr}\n";
}

if (!$permiteReposicao) {
    echo "Limite semanal: {$limiteSemanal} | Usados: " . count($checkinsSemana) . " | Restantes: " . max(0, $limiteSemanal - count($checkinsSemana)) . "\n";
}
echo "\n";

// ────────────────────────────────────────────────────────────────
// SEÇÃO 5: Distribuição de presença (histórico completo)
// ────────────────────────────────────────────────────────────────
echo "--- SEÇÃO 5: DISTRIBUIÇÃO DE PRESENÇA (HISTÓRICO COMPLETO) ---\n";
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN c.presente = 1 THEN 1 ELSE 0 END) as presentes,
        SUM(CASE WHEN c.presente = 0 THEN 1 ELSE 0 END) as faltas,
        SUM(CASE WHEN c.presente IS NULL THEN 1 ELSE 0 END) as pendentes,
        MIN(COALESCE(c.data_checkin_date, DATE(c.created_at))) as primeiro_checkin,
        MAX(COALESCE(c.data_checkin_date, DATE(c.created_at))) as ultimo_checkin
    FROM checkins c
    INNER JOIN alunos a ON a.id = c.aluno_id
    WHERE a.usuario_id = :usuario_id
");
$stmt->execute(['usuario_id' => $userId]);
$dist = $stmt->fetch();

echo "Total geral: {$dist['total']}\n";
echo "Presentes (1): {$dist['presentes']}\n";
echo "Faltas (0): {$dist['faltas']}\n";
echo "Pendentes (NULL): {$dist['pendentes']}\n";
echo "Primeiro check-in: {$dist['primeiro_checkin']}\n";
echo "Último check-in: {$dist['ultimo_checkin']}\n";

$pctPendente = $dist['total'] > 0 ? round(($dist['pendentes'] / $dist['total']) * 100, 1) : 0;
echo "\n⚠️  {$pctPendente}% dos check-ins estão com presença PENDENTE (NULL)\n";
if ($pctPendente > 50) {
    echo "   ALERTA: Mais da metade dos check-ins não teve presença confirmada!\n";
}
echo "\n";

// ────────────────────────────────────────────────────────────────
// SEÇÃO 6: Distribuição por mês (últimos 3 meses)
// ────────────────────────────────────────────────────────────────
echo "--- SEÇÃO 6: CHECK-INS POR MÊS (ÚLTIMOS 3 MESES) ---\n";
$stmt = $db->prepare("
    SELECT 
        DATE_FORMAT(COALESCE(c.data_checkin_date, DATE(c.created_at)), '%Y-%m') as mes,
        COUNT(*) as total,
        SUM(CASE WHEN c.presente = 1 THEN 1 ELSE 0 END) as presentes,
        SUM(CASE WHEN c.presente = 0 THEN 1 ELSE 0 END) as faltas,
        SUM(CASE WHEN c.presente IS NULL THEN 1 ELSE 0 END) as pendentes,
        SUM(CASE WHEN (c.presente IS NULL OR c.presente = 1) THEN 1 ELSE 0 END) as contados_limite
    FROM checkins c
    INNER JOIN alunos a ON a.id = c.aluno_id
    WHERE a.usuario_id = :usuario_id
      AND COALESCE(c.data_checkin_date, DATE(c.created_at)) >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
    GROUP BY mes
    ORDER BY mes DESC
");
$stmt->execute(['usuario_id' => $userId]);
$porMes = $stmt->fetchAll();

foreach ($porMes as $m) {
    echo "  {$m['mes']}: total={$m['total']}, presentes={$m['presentes']}, faltas={$m['faltas']}, pendentes={$m['pendentes']}, contados_p_limite={$m['contados_limite']}\n";
}
echo "\n";

// ────────────────────────────────────────────────────────────────
// SEÇÃO 7: Últimos 10 check-ins detalhados (presença + horário)
// ────────────────────────────────────────────────────────────────
echo "--- SEÇÃO 7: ÚLTIMOS 10 CHECK-INS DETALHADOS ---\n";
$stmt = $db->prepare("
    SELECT c.id, c.turma_id, c.data_checkin, c.created_at,
           COALESCE(c.data_checkin_date, DATE(c.created_at)) as data_efetiva,
           c.presente,
           c.presenca_confirmada_em, c.presenca_confirmada_por,
           c.registrado_por_admin, c.admin_id,
           t.nome as turma_nome, t.horario_inicio, t.horario_fim,
           t.modalidade_id, m2.nome as modalidade_nome,
           d.data as dia_data,
           adm.nome as admin_nome,
           conf.nome as confirmado_por_nome
    FROM checkins c
    INNER JOIN alunos a ON a.id = c.aluno_id
    LEFT JOIN turmas t ON t.id = c.turma_id
    LEFT JOIN modalidades m2 ON m2.id = t.modalidade_id
    LEFT JOIN dias d ON d.id = t.dia_id
    LEFT JOIN usuarios adm ON adm.id = c.admin_id
    LEFT JOIN usuarios conf ON conf.id = c.presenca_confirmada_por
    WHERE a.usuario_id = :usuario_id
    ORDER BY c.created_at DESC
    LIMIT 10
");
$stmt->execute(['usuario_id' => $userId]);
$ultimos = $stmt->fetchAll();

foreach ($ultimos as $ck) {
    $presStr = $ck['presente'] === null ? 'PENDENTE(NULL)' : ($ck['presente'] ? 'PRESENTE(1)' : 'FALTA(0)');
    $tipo = $ck['registrado_por_admin'] ? "MANUAL (por {$ck['admin_nome']})" : 'AUTO';
    $confInfo = $ck['presenca_confirmada_em']
        ? "Confirmado: {$ck['presenca_confirmada_em']} por {$ck['confirmado_por_nome']}"
        : 'Presença NÃO confirmada';

    echo "  #{$ck['id']} | Data: {$ck['data_efetiva']} | Criado: {$ck['created_at']}\n";
    echo "    Turma: {$ck['turma_nome']} (id={$ck['turma_id']}) | Horário: {$ck['horario_inicio']}-{$ck['horario_fim']}\n";
    echo "    Modalidade: {$ck['modalidade_nome']} (id={$ck['modalidade_id']})\n";
    echo "    Presença: {$presStr} | Tipo: {$tipo}\n";
    echo "    {$confInfo}\n\n";
}

// ────────────────────────────────────────────────────────────────
// SEÇÃO 8: Verificação de duplicatas no mesmo dia/modalidade
// ────────────────────────────────────────────────────────────────
echo "--- SEÇÃO 8: CHECKINS DUPLICADOS (MESMO DIA + MODALIDADE) ---\n";
$stmt = $db->prepare("
    SELECT COALESCE(c.data_checkin_date, DATE(c.created_at)) as data_efetiva,
           t.modalidade_id, m2.nome as modalidade_nome,
           COUNT(*) as qtd,
           GROUP_CONCAT(c.id ORDER BY c.id) as checkin_ids,
           GROUP_CONCAT(
               CASE WHEN c.presente IS NULL THEN 'NULL'
                    WHEN c.presente = 1 THEN '1'
                    ELSE '0'
               END ORDER BY c.id
           ) as presenca_valores
    FROM checkins c
    INNER JOIN alunos a ON a.id = c.aluno_id
    LEFT JOIN turmas t ON t.id = c.turma_id
    LEFT JOIN modalidades m2 ON m2.id = t.modalidade_id
    WHERE a.usuario_id = :usuario_id
    GROUP BY data_efetiva, t.modalidade_id
    HAVING qtd > 1
    ORDER BY data_efetiva DESC
    LIMIT 10
");
$stmt->execute(['usuario_id' => $userId]);
$duplicados = $stmt->fetchAll();

if (empty($duplicados)) {
    echo "  Nenhuma duplicata encontrada ✅\n";
} else {
    echo "  ⚠️ Encontradas " . count($duplicados) . " ocorrência(s) de duplicata:\n";
    foreach ($duplicados as $dup) {
        echo "    Data: {$dup['data_efetiva']} | Modalidade: {$dup['modalidade_nome']} (id={$dup['modalidade_id']}) | Qtd: {$dup['qtd']} | IDs: {$dup['checkin_ids']} | Presença: [{$dup['presenca_valores']}]\n";
    }
}
echo "\n";

echo "=== FIM DO DEBUG MATRÍCULA #{$matriculaId} ===\n";
