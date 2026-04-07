<?php
/**
 * Debug Matrícula #288
 * Investigação: data_inicio registrada como 06/04/2026 sendo que o cadastro
 * ocorreu em 07/04/2026 às 11:07 (problema de timezone - server UTC vs BRT).
 * 
 * Uso: php debug_matricula_288.php
 */

require_once __DIR__ . '/vendor/autoload.php';

$db = require __DIR__ . '/config/database.php';
$matriculaId = 288;

date_default_timezone_set('America/Sao_Paulo');

echo "====== DEBUG MATRÍCULA #288 ======\n";
echo "Data BRT (script): " . date('Y-m-d H:i:s') . "\n";
echo "Date UTC (server): " . gmdate('Y-m-d H:i:s') . " UTC\n";
echo "Timezone PHP:      " . ini_get('date.timezone') . " (ini_get)\n\n";

// ============================================================
// 1. DADOS DA MATRÍCULA
// ============================================================
echo "1. DADOS DA MATRÍCULA\n";
echo str_repeat("-", 80) . "\n";
$stmt = $db->prepare("
    SELECT 
        m.*,
        sm.codigo as status_codigo,
        sm.nome   as status_nome,
        a.nome    as aluno_nome,
        a.id      as aluno_id_real,
        u.id      as usuario_id,
        u.email   as aluno_email,
        p.nome    as plano_nome,
        p.duracao_dias,
        pc.meses  as ciclo_meses,
        pc.valor  as ciclo_valor,
        pc.nome   as ciclo_nome
    FROM matriculas m
    INNER JOIN alunos a    ON a.id  = m.aluno_id
    INNER JOIN usuarios u  ON u.id  = a.usuario_id
    INNER JOIN planos p    ON p.id  = m.plano_id
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    LEFT  JOIN plano_ciclos pc ON pc.id = m.plano_ciclo_id
    WHERE m.id = ?
");
$stmt->execute([$matriculaId]);
$matricula = $stmt->fetch(\PDO::FETCH_ASSOC);

if (!$matricula) {
    echo "❌ Matrícula #{$matriculaId} não encontrada!\n";
    exit(1);
}

$alunoId  = $matricula['aluno_id'];
$tenantId = $matricula['tenant_id'];

echo "ID:                    {$matricula['id']}\n";
echo "Aluno:                 {$matricula['aluno_nome']} (aluno_id={$alunoId}, usuario_id={$matricula['usuario_id']})\n";
echo "Email:                 {$matricula['aluno_email']}\n";
echo "Tenant:                {$tenantId}\n";
echo "Plano:                 {$matricula['plano_nome']} (plano_id={$matricula['plano_id']})\n";
echo "Ciclo:                 " . ($matricula['ciclo_nome'] ?? '-') . " | {$matricula['ciclo_meses']} mes(es) | R$ " . number_format((float)($matricula['ciclo_valor'] ?? 0), 2, ',', '.') . "\n";
echo "Tipo cobrança:         " . ($matricula['tipo_cobranca'] ?? '-') . "\n";
echo "Status:                {$matricula['status_nome']} ({$matricula['status_codigo']}) — status_id={$matricula['status_id']}\n";
echo "\n";
echo "data_matricula:        {$matricula['data_matricula']}\n";
echo "data_inicio:           {$matricula['data_inicio']}\n";
echo "data_vencimento:       {$matricula['data_vencimento']}\n";
echo "proxima_data_vencimento: " . ($matricula['proxima_data_vencimento'] ?? 'NULL') . "\n";
echo "created_at:            {$matricula['created_at']}\n";
echo "updated_at:            {$matricula['updated_at']}\n";

// Diagnóstico de data_inicio
$hojeUTC = gmdate('Y-m-d');
$hojesBRT = date('Y-m-d');
echo "\n--- Diagnóstico data_inicio ---\n";
echo "Hoje UTC:  {$hojeUTC}\n";
echo "Hoje BRT:  {$hojesBRT}\n";
echo "data_inicio registrada: {$matricula['data_inicio']}\n";
if ($matricula['data_inicio'] === $hojeUTC && $hojeUTC !== $hojesBRT) {
    echo "⚠️  data_inicio bate com UTC mas NÃO com BRT — confirmado bug de timezone!\n";
} elseif ($matricula['data_inicio'] === $hojesBRT) {
    echo "✅  data_inicio bate com BRT (correto)\n";
} else {
    echo "ℹ️  data_inicio difere tanto de UTC quanto de BRT — verificar origem manual\n";
}

// ============================================================
// 2. ASSINATURAS DA MATRÍCULA
// ============================================================
echo "\n\n2. ASSINATURAS DA MATRÍCULA\n";
echo str_repeat("-", 80) . "\n";
$stmt = $db->prepare("
    SELECT a.*,
           ast.codigo as status_codigo_local,
           ast.nome   as status_label,
           f.meses    as frequencia_meses,
           f.nome     as frequencia_nome
    FROM assinaturas a
    LEFT JOIN assinatura_status  ast ON ast.id = a.status_id
    LEFT JOIN assinatura_frequencias f ON f.id = a.frequencia_id
    WHERE a.matricula_id = ?
    ORDER BY a.id DESC
");
$stmt->execute([$matriculaId]);
$assinaturas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

echo "Total de assinaturas vinculadas: " . count($assinaturas) . "\n\n";

foreach ($assinaturas as $ass) {
    echo "  Assinatura ID:         {$ass['id']}\n";
    echo "  Status local:          " . ($ass['status_label'] ?? '?') . " ({$ass['status_codigo_local']}) — status_id={$ass['status_id']}\n";
    echo "  Status Gateway (MP):   " . ($ass['status_gateway'] ?? 'NULL') . "\n";
    echo "  Tipo cobrança:         " . ($ass['tipo_cobranca'] ?? '-') . "\n";
    echo "  Frequência:            " . ($ass['frequencia_nome'] ?? '-') . " ({$ass['frequencia_meses']} mes(es))\n";
    echo "  Gateway assinatura ID: " . ($ass['gateway_assinatura_id'] ?? 'NULL') . "\n";
    echo "  External reference:    " . ($ass['external_reference'] ?? 'NULL') . "\n";
    echo "  Valor:                 R$ " . number_format((float)$ass['valor'], 2, ',', '.') . "\n";
    echo "  dia_cobranca:          " . ($ass['dia_cobranca'] ?? 'NULL') . "\n";
    echo "  data_inicio:           " . ($ass['data_inicio'] ?? 'NULL') . "\n";
    echo "  data_fim:              " . ($ass['data_fim'] ?? 'NULL') . "\n";
    echo "  proxima_cobranca:      " . ($ass['proxima_cobranca'] ?? 'NULL') . "\n";
    echo "  ultima_cobranca:       " . ($ass['ultima_cobranca'] ?? 'NULL') . "\n";
    echo "  criado_em:             {$ass['criado_em']}\n";
    echo "  atualizado_em:         {$ass['atualizado_em']}\n";

    // Diagnóstico de data_inicio na assinatura
    echo "\n  --- Diagnóstico data_inicio (assinatura) ---\n";
    $assDataInicio = $ass['data_inicio'] ?? null;
    $assReg        = substr($ass['criado_em'] ?? '', 0, 10); // data de registro UTC
    if ($assDataInicio && $assReg) {
        // Para registro BRT, se criado_em está em UTC, precisamos recalcular
        $criadoEmUTC = new \DateTimeImmutable($ass['criado_em'], new \DateTimeZone('UTC'));
        $criadoEmBRT = $criadoEmUTC->setTimezone(new \DateTimeZone('America/Sao_Paulo'));
        $dataRegBRT  = $criadoEmBRT->format('Y-m-d');
        $dataRegUTC  = $criadoEmUTC->format('Y-m-d');

        echo "  criado_em (UTC):       {$dataRegUTC}\n";
        echo "  criado_em (BRT):       {$dataRegBRT}\n";
        echo "  data_inicio guardada:  {$assDataInicio}\n";

        if ($assDataInicio === $dataRegBRT) {
            echo "  ✅ data_inicio bate com a data de criação em BRT\n";
        } elseif ($assDataInicio === $dataRegUTC && $dataRegUTC !== $dataRegBRT) {
            echo "  ❌ data_inicio bate com UTC mas NÃO com BRT — BUG TIMEZONE!\n";
        } else {
            echo "  ⚠️  data_inicio difere da data de criação — verificar se foi editada manualmente\n";
        }
    }

    // Flags de anomalia
    $flags = [];
    if (($ass['status_gateway'] ?? '') === 'authorized' && ($ass['status_codigo_local'] ?? '') !== 'ativa') {
        $flags[] = "DIVERGÊNCIA: Gateway=authorized mas status local={$ass['status_codigo_local']}";
    }
    if (empty($ass['gateway_assinatura_id'])) {
        $flags[] = "SEM gateway_assinatura_id — MP pode não ter processado esta assinatura";
    }
    if (!empty($flags)) {
        echo "\n  *** ANOMALIAS ***\n";
        foreach ($flags as $f) echo "    - {$f}\n";
    }
    echo "\n";
}

// ============================================================
// 3. PARCELAS (pagamentos_plano)
// ============================================================
echo "3. PARCELAS (pagamentos_plano)\n";
echo str_repeat("-", 80) . "\n";
$stmt = $db->prepare("
    SELECT pp.id, pp.data_vencimento, pp.data_pagamento,
           sp.nome as status_nome, pp.status_pagamento_id,
           pp.valor, pp.observacoes, pp.created_at
    FROM pagamentos_plano pp
    LEFT JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
    WHERE pp.matricula_id = ?
    ORDER BY pp.data_vencimento ASC
");
$stmt->execute([$matriculaId]);
$parcelas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

if (!$parcelas) {
    echo "  Nenhuma parcela encontrada\n";
} else {
    foreach ($parcelas as $p) {
        $marker = match((int)$p['status_pagamento_id']) {
            2 => '✅',  // pago
            3 => '⚠️ ', // atrasado
            default => (($p['data_vencimento'] ?? '') < date('Y-m-d') ? '❌' : '⏳'),
        };
        echo "  {$marker} Pag #{$p['id']} | venc={$p['data_vencimento']} | pag=" . ($p['data_pagamento'] ?? '-') . " | {$p['status_nome']} | R\${$p['valor']}\n";
        if (!empty($p['observacoes'])) echo "       obs: {$p['observacoes']}\n";
    }
}

$stmt = $db->prepare("SELECT MIN(data_vencimento) FROM pagamentos_plano WHERE matricula_id = ? AND status_pagamento_id IN (1,3)");
$stmt->execute([$matriculaId]);
$proxPendente = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT MAX(data_vencimento) FROM pagamentos_plano WHERE matricula_id = ? AND status_pagamento_id = 2");
$stmt->execute([$matriculaId]);
$maxPago = $stmt->fetchColumn();

echo "\n  Próxima pendente: " . ($proxPendente ?: 'nenhuma') . "\n";
echo "  Último venc pago: " . ($maxPago ?: 'nenhum') . "\n";

$pdv = $matricula['proxima_data_vencimento'] ?? null;
if ($pdv && $proxPendente && $pdv !== $proxPendente) {
    echo "  ⚠️  DIVERGÊNCIA: proxima_data_vencimento da matrícula ({$pdv}) != próxima parcela pendente ({$proxPendente})\n";
} elseif ($pdv && $proxPendente && $pdv === $proxPendente) {
    echo "  ✅  proxima_data_vencimento alinhada com parcela pendente\n";
}

// ============================================================
// 4. PAGAMENTOS MERCADO PAGO
// ============================================================
echo "\n\n4. PAGAMENTOS MERCADO PAGO (pagamentos_mercadopago)\n";
echo str_repeat("-", 80) . "\n";
$stmt = $db->prepare("
    SELECT pm.*
    FROM pagamentos_mercadopago pm
    WHERE pm.matricula_id = ?
    ORDER BY pm.id DESC
");
$stmt->execute([$matriculaId]);
$mps = $stmt->fetchAll(\PDO::FETCH_ASSOC);

if (!$mps) {
    echo "  Nenhum registro em pagamentos_mercadopago\n";
} else {
    foreach ($mps as $mp) {
        echo "  MP #{$mp['id']} | payment_id={$mp['payment_id']} | status={$mp['status']} | valor=R\${$mp['transaction_amount']}\n";
        echo "    external_reference: " . ($mp['external_reference'] ?? 'NULL') . "\n";
        echo "    date_approved:      " . ($mp['date_approved'] ?? 'NULL') . "\n";
        echo "    date_created:       " . ($mp['date_created'] ?? 'NULL') . "\n";
        echo "    payer_email:        " . ($mp['payer_email'] ?? 'NULL') . "\n";
        echo "    created_at:         {$mp['created_at']}\n";
    }
}

// ============================================================
// 5. WEBHOOK LOGS
// ============================================================
echo "\n\n5. WEBHOOK LOGS (webhook_payloads_mercadopago)\n";
echo str_repeat("-", 80) . "\n";
// Buscar por matricula_id direto ou por external_reference MAT-288-%
$stmt = $db->prepare("
    SELECT id, payment_id, external_reference, status, topic, created_at
    FROM webhook_payloads_mercadopago
    WHERE (external_reference LIKE 'MAT-288-%' OR external_reference = 'MAT-288')
       OR payment_id IN (SELECT payment_id FROM pagamentos_mercadopago WHERE matricula_id = ? AND payment_id IS NOT NULL)
    ORDER BY id DESC
    LIMIT 20
");
$stmt->execute([$matriculaId]);
$webhooks = $stmt->fetchAll(\PDO::FETCH_ASSOC);

if (!$webhooks) {
    echo "  Nenhum webhook encontrado\n";
} else {
    foreach ($webhooks as $wh) {
        echo "  WH #{$wh['id']} | payment_id={$wh['payment_id']} | status={$wh['status']} | topic={$wh['topic']}\n";
        echo "    ext_ref:    " . ($wh['external_reference'] ?? 'NULL') . "\n";
        echo "    created_at: {$wh['created_at']}\n";
    }
}

// ============================================================
// 6. TIMEZONE DIAGNÓSTICO AVANÇADO
// ============================================================
echo "\n\n6. DIAGNÓSTICO DE TIMEZONE\n";
echo str_repeat("-", 80) . "\n";

// Verificar timezone do MySQL/MariaDB
try {
    $stmtTz = $db->query("SELECT @@global.time_zone AS global_tz, @@session.time_zone AS session_tz, NOW() AS server_now, UTC_TIMESTAMP() AS utc_now");
    $tzInfo = $stmtTz->fetch(\PDO::FETCH_ASSOC);
    echo "MySQL global_tz:   {$tzInfo['global_tz']}\n";
    echo "MySQL session_tz:  {$tzInfo['session_tz']}\n";
    echo "MySQL NOW():       {$tzInfo['server_now']}\n";
    echo "MySQL UTC_NOW():   {$tzInfo['utc_now']}\n";
    $dbDiff = (new \DateTimeImmutable($tzInfo['server_now']))->getTimestamp() - (new \DateTimeImmutable($tzInfo['utc_now']))->getTimestamp();
    echo "Diferença DB vs UTC: " . ($dbDiff / 3600) . "h\n";
} catch (\Throwable $e) {
    echo "Erro ao verificar timezone MySQL: " . $e->getMessage() . "\n";
}

// Verificar PHP timezone configurado na app (index.php / bootstrap)
echo "\nPHP date():          " . date('Y-m-d H:i:s') . " (" . date_default_timezone_get() . ")\n";
echo "PHP gmdate():        " . gmdate('Y-m-d H:i:s') . " (UTC)\n";
$tzOffset = (new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get())))->format('P');
echo "PHP timezone offset: {$tzOffset}\n";

// Simular o que date('Y-m-d') retornaria se NÃO tivesse timezone set corretamente
$tzUtc = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
$tzBrt = new \DateTimeImmutable('now', new \DateTimeZone('America/Sao_Paulo'));
echo "\nSimulação:\n";
echo "  date() sem timezone (UTC):  " . $tzUtc->format('Y-m-d H:i:s') . "\n";
echo "  date() com BRT:             " . $tzBrt->format('Y-m-d H:i:s') . "\n";
if ($tzUtc->format('Y-m-d') !== $tzBrt->format('Y-m-d')) {
    echo "  ⚠️  Datas DIVERGEM entre UTC e BRT — bug de timezone ATIVO agora!\n";
} else {
    echo "  ✅ Datas iguais UTC vs BRT neste momento (bug não reproduzível agora, verificar horário de criação)\n";
}

// ============================================================
// SUMÁRIO
// ============================================================
echo "\n\n====== SUMÁRIO ======\n";
$assDataInicio = !empty($assinaturas) ? ($assinaturas[0]['data_inicio'] ?? null) : null;
$criadoEm      = !empty($assinaturas) ? ($assinaturas[0]['criado_em'] ?? null) : null;

if ($assDataInicio && $criadoEm) {
    $criadoUTC = new \DateTimeImmutable($criadoEm, new \DateTimeZone('UTC'));
    $criadoBRT = $criadoUTC->setTimezone(new \DateTimeZone('America/Sao_Paulo'));

    echo "Assinatura #" . $assinaturas[0]['id'] . "\n";
    echo "  data_inicio guardada:  {$assDataInicio}\n";
    echo "  criado_em (UTC):       " . $criadoUTC->format('Y-m-d H:i:s') . "\n";
    echo "  criado_em (BRT):       " . $criadoBRT->format('Y-m-d H:i:s') . "\n";

    if ($assDataInicio !== $criadoBRT->format('Y-m-d')) {
        echo "\n  ❌ PROBLEMA CONFIRMADO: data_inicio={$assDataInicio} != data em BRT=" . $criadoBRT->format('Y-m-d') . "\n";
        echo "  CAUSA PROVÁVEL: AssinaturaController::criar() usa date('Y-m-d') sem timezone\n";
        echo "  FIX NECESSÁRIO: Adicionar date_default_timezone_set('America/Sao_Paulo') no bootstrap\n";
        echo "               OU usar: (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d')\n";
    } else {
        echo "\n  ✅ data_inicio está correta\n";
    }
}

echo "\nFIM DO DEBUG\n";
