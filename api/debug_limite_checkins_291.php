<?php
/**
 * Debug: limite de check-in — matrícula #291 (Matheus / Natação 2x)
 * Uso: php debug_limite_checkins_291.php
 */

require_once __DIR__ . '/vendor/autoload.php';

$db = require __DIR__ . '/config/database.php';
$checkinModel = new \App\Models\Checkin($db);

$matriculaId = 291;

echo "====== DEBUG LIMITE CHECK-INS — Matrícula #{$matriculaId} ======\n";
echo 'Data BRT: ' . date('Y-m-d H:i:s') . "\n\n";

$stmt = $db->prepare("
    SELECT m.id, m.aluno_id, m.tenant_id, m.plano_id, m.plano_ciclo_id,
           m.data_matricula, m.data_inicio, m.data_vencimento, m.proxima_data_vencimento,
           sm.codigo AS status_codigo,
           p.nome AS plano_nome, p.checkins_semanais, p.modalidade_id,
           md.nome AS modalidade_nome,
           pc.permite_reposicao, pc.meses AS ciclo_meses, af.nome AS ciclo_nome,
           a.usuario_id, u.nome AS aluno_nome
    FROM matriculas m
    INNER JOIN planos p ON p.id = m.plano_id
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    INNER JOIN alunos a ON a.id = m.aluno_id
    INNER JOIN usuarios u ON u.id = a.usuario_id
    LEFT JOIN modalidades md ON md.id = p.modalidade_id
    LEFT JOIN plano_ciclos pc ON pc.id = m.plano_ciclo_id
    LEFT JOIN assinatura_frequencias af ON af.id = pc.assinatura_frequencia_id
    WHERE m.id = ?
");
$stmt->execute([$matriculaId]);
$mat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mat) {
    echo "❌ Matrícula #{$matriculaId} não encontrada.\n";
    exit(1);
}

$usuarioId = (int) $mat['usuario_id'];
$tenantId = (int) $mat['tenant_id'];
$modalidadeId = (int) $mat['modalidade_id'];

echo "1. MATRÍCULA E PLANO\n";
echo str_repeat('-', 72) . "\n";
echo "  Aluno              : {$mat['aluno_nome']} (usuario_id={$usuarioId})\n";
echo "  Plano              : {$mat['plano_nome']}\n";
echo "  Modalidade         : {$mat['modalidade_nome']} (id={$modalidadeId})\n";
echo "  Status             : {$mat['status_codigo']}\n";
echo "  Ciclo contrato     : " . ($mat['ciclo_nome'] ?? '-') . ' (' . ($mat['ciclo_meses'] ?? '?') . " meses)\n";
echo "  checkins_semanais  : {$mat['checkins_semanais']}\n";
echo "  permite_reposicao  : " . ($mat['permite_reposicao'] ? 'SIM (limite MENSAL/ciclo)' : 'NÃO (limite SEMANAL)') . "\n";
echo "  data_inicio        : {$mat['data_inicio']}\n";
echo "  data_vencimento    : {$mat['data_vencimento']}\n";
echo "  proxima_data_venc  : {$mat['proxima_data_vencimento']}\n\n";

$planoInfo = $checkinModel->obterLimiteCheckinsPlano($usuarioId, $tenantId, $modalidadeId);

echo "2. PLANO ATIVO (obterLimiteCheckinsPlano)\n";
echo str_repeat('-', 72) . "\n";
echo '  tem_plano          : ' . ($planoInfo['tem_plano'] ? 'sim' : 'não') . "\n";
echo '  plano              : ' . ($planoInfo['plano_nome'] ?? '-') . "\n";
echo '  limite semanal     : ' . ($planoInfo['limite'] ?? 0) . "\n";
echo '  permite_reposicao  : ' . (($planoInfo['permite_reposicao'] ?? false) ? 'sim' : 'não') . "\n\n";

if (!$planoInfo['tem_plano']) {
    echo "❌ Sem matrícula ativa/vigente para esta modalidade — check-in bloqueado por outro motivo.\n";
    exit(1);
}

$ciclo = $checkinModel->obterCicloCheckins($usuarioId, $tenantId, $modalidadeId);

echo "3. CICLO DE CHECK-IN ATUAL (obterCicloCheckins)\n";
echo str_repeat('-', 72) . "\n";

if (empty($ciclo['tem_plano'])) {
    echo "  ❌ Ciclo não calculado.\n";
} else {
    $fimInclusivo = date('Y-m-d', strtotime($ciclo['ciclo_fim'] . ' -1 day'));
    echo "  Período            : {$ciclo['ciclo_inicio']} a {$fimInclusivo} (fim exclusivo: {$ciclo['ciclo_fim']})\n";
    echo "  Dias no ciclo      : {$ciclo['dias_no_ciclo']} | semanas: {$ciclo['semanas']}\n";
    echo "  Bônus 5ª semana    : " . ($ciclo['bonus_cinco_semanas'] ? 'sim (+1)' : 'não') . "\n";
    echo "  Limite no ciclo    : {$ciclo['limite_mensal']} ({$ciclo['checkins_semanais']}×4 + bônus)\n";
    echo "  Check-ins no ciclo : {$ciclo['checkins_no_ciclo']}\n";
    echo '  Saldo mensal       : ' . max(0, $ciclo['limite_mensal'] - $ciclo['checkins_no_ciclo']) . "\n";

    if (!empty($ciclo['contrato_multimes'])) {
        $pFimInc = date('Y-m-d', strtotime($ciclo['periodo_fim'] . ' -1 day'));
        echo "\n  CONTRATO MULTI-MÊS ({$ciclo['meses_ciclo']} meses)\n";
        echo "  Período contrato   : {$ciclo['periodo_inicio']} a {$pFimInc}\n";
        echo "  Limite no contrato : {$ciclo['limite_periodo']}\n";
        echo "  Check-ins contrato : {$ciclo['checkins_no_periodo']}\n";
        echo '  Saldo contrato     : ' . max(0, $ciclo['limite_periodo'] - $ciclo['checkins_no_periodo']) . "\n";
        echo '  Último mês?        : ' . (!empty($ciclo['ultimo_mes_contrato']) ? 'sim (teto mensal vale)' : 'não (só trava se estourar total)') . "\n";
    }
    echo "\n";

    if (!empty($ciclo['dias_checkin'])) {
        echo "  Check-ins contados:\n";
        foreach ($ciclo['dias_checkin'] as $i => $c) {
            $adm = !empty($c['registrado_por_admin']) ? ' [admin]' : '';
            echo sprintf(
                "    %d) %s %s | %s | %s%s\n",
                $i + 1,
                $c['data'],
                $c['horario'] ?? '--:--',
                $c['modalidade'] ?? '-',
                $c['status'] ?? '-',
                $adm
            );
        }
        echo "\n";
    }
}

$detalhes = null;
if ($planoInfo['permite_reposicao']) {
    $detalhes = $checkinModel->avaliarLimiteMensalReposicao($usuarioId, $tenantId, $modalidadeId, $planoInfo);
}

echo "4. RESULTADO DA VALIDAÇÃO\n";
echo str_repeat('-', 72) . "\n";

if ($planoInfo['permite_reposicao']) {
    if ($detalhes === null) {
        echo "  ✅ PERMITIRIA check-in agora (dentro do limite do ciclo).\n";
    } else {
        echo "  ⛔ BLOQUEARIA check-in — limite do ciclo atingido.\n";
        echo "  Mensagem app     : Você atingiu o limite de check-ins deste mês\n";
        echo "  Ciclo ref        : " . ($detalhes['mes_referencia'] ?? '-') . "\n";
        echo "  Usados / limite  : {$detalhes['checkins_mes']} / {$detalhes['limite_mensal']}\n";
    }
} else {
    $sem = $checkinModel->contarCheckinsNaSemana($usuarioId, $modalidadeId);
    $lim = (int) $planoInfo['limite'];
    echo "  Check-ins semana   : {$sem} / {$lim}\n";
    echo ($sem >= $lim ? "  ⛔ BLOQUEARIA (limite semanal)\n" : "  ✅ PERMITIRIA check-in\n");
}

echo "\n5. CHECK-INS RECENTES (Natação, últimos 60 dias)\n";
echo str_repeat('-', 72) . "\n";

$stmt = $db->prepare("
    SELECT c.id, DATE(d.data) AS data_aula, t.horario_inicio, c.presente,
           c.registrado_por_admin, m2.nome AS modalidade
    FROM checkins c
    INNER JOIN alunos a ON a.id = c.aluno_id
    INNER JOIN turmas t ON t.id = c.turma_id
    INNER JOIN dias d ON d.id = t.dia_id
    INNER JOIN modalidades m2 ON m2.id = t.modalidade_id
    WHERE a.usuario_id = ?
      AND t.modalidade_id = ?
      AND d.data >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
    ORDER BY d.data DESC, t.horario_inicio DESC
    LIMIT 30
");
$stmt->execute([$usuarioId, $modalidadeId]);
$recentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($recentes === []) {
    echo "  (nenhum)\n";
} else {
    foreach ($recentes as $r) {
        $st = $r['presente'] === null ? 'pend' : ((int) $r['presente'] === 1 ? 'ok' : 'falta');
        echo "  #{$r['id']} {$r['data_aula']} {$r['horario_inicio']} | {$st}\n";
    }
}

echo "\n6. NOTAS\n";
echo str_repeat('-', 72) . "\n";
echo "  • Plano mensal (1 mês): teto de 8–9 check-ins por ciclo (âncora no vencimento).\n";
echo "  • Plano bimestral+: limite = soma do contrato; teto mensal só no último mês ou se estourar o total.\n";
echo "  • Ciclo atual usa proxima_data_vencimento ({$mat['proxima_data_vencimento']}) como âncora.\n";

echo "\n" . str_repeat('=', 72) . "\n";
