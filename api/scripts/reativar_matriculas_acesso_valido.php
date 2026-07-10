<?php
/**
 * Reativa em lote matrículas canceladas/vencidas com base no período PAGO.
 *
 * Modos:
 *   estrito (padrão)
 *     max_pago >= hoje        → ativa
 *     max_pago vencido 1-4d   → vencida (só se estava cancelada)
 *
 *   lote
 *     Reverte cancelamento em massa de uma data (ex.: cron 2026-07-10).
 *     Inclui canceladas nessa data com max_pago nos últimos N dias (padrão 75).
 *     Por padrão recalcula ativa / vencida / cancelada pelas regras atuais.
 *     Com --forcar-ativa: define todas as elegíveis como ativa (uso emergencial).
 *
 * Uso:
 *   php scripts/reativar_matriculas_acesso_valido.php --dry-run
 *   php scripts/reativar_matriculas_acesso_valido.php --modo=lote --lote-data=2026-07-10 --dry-run
 *   php scripts/reativar_matriculas_acesso_valido.php --modo=lote --lote-data=2026-07-10 --forcar-ativa --dry-run
 *   php scripts/reativar_matriculas_acesso_valido.php --tenant=3
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('America/Sao_Paulo');

$opts = getopt('', ['tenant:', 'modo:', 'lote-data:', 'dias:', 'forcar-ativa', 'dry-run', 'quiet']);
$tenantFilter = isset($opts['tenant']) ? (int) $opts['tenant'] : null;
$modo = $opts['modo'] ?? 'estrito';
$loteData = $opts['lote-data'] ?? '2026-07-10';
$diasAcesso = isset($opts['dias']) ? max(1, (int) $opts['dias']) : 75;
$forcarAtiva = isset($opts['forcar-ativa']);
$dryRun = isset($opts['dry-run']);
$quiet = isset($opts['quiet']);

if (!in_array($modo, ['estrito', 'lote'], true)) {
    fwrite(STDERR, "Modo inválido: {$modo}. Use estrito ou lote.\n");
    exit(1);
}

if ($modo === 'lote' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $loteData)) {
    fwrite(STDERR, "Data inválida para --lote-data (use YYYY-MM-DD).\n");
    exit(1);
}

if ($forcarAtiva && $modo !== 'lote') {
    fwrite(STDERR, "--forcar-ativa só pode ser usado com --modo=lote.\n");
    exit(1);
}

$pdo = require __DIR__ . '/../config/database.php';

$say = static function (string $msg) use ($quiet): void {
    if (!$quiet) {
        echo $msg . PHP_EOL;
    }
};

$say('=== Reativar matrículas com acesso válido (max parcela paga) ===');
$say(date('Y-m-d H:i:s'));
$say("Modo: {$modo}" . ($modo === 'lote' ? " | lote {$loteData} | últimos {$diasAcesso} dias" : ''));
if ($forcarAtiva) {
    $say('ATENÇÃO: --forcar-ativa ignora regra de 5+ dias e define todas elegíveis como ATIVA');
}
if ($dryRun) {
    $say('MODO DRY-RUN — nenhuma alteração será feita');
}

$tenantSql = $tenantFilter ? 'AND m.tenant_id = :tenant_id' : '';
$params = $tenantFilter ? ['tenant_id' => $tenantFilter] : [];

$statusNovoExpr = $forcarAtiva
    ? "'ativa'"
    : "CASE
            WHEN mp.max_pago >= CURDATE() THEN 'ativa'
            WHEN DATEDIFF(CURDATE(), mp.max_pago) BETWEEN 1 AND 4 THEN 'vencida'
            ELSE 'cancelada'
        END";

$whereExtra = '';
if ($modo === 'estrito') {
    $whereExtra = "
        AND (
            mp.max_pago >= CURDATE()
            OR (sm.codigo = 'cancelada' AND DATEDIFF(CURDATE(), mp.max_pago) BETWEEN 1 AND 4)
        )";
} else {
    $params['lote_data'] = $loteData;
    $whereExtra = "
        AND sm.codigo = 'cancelada'
        AND DATE(m.updated_at) = :lote_data
        AND mp.max_pago >= DATE_SUB(CURDATE(), INTERVAL {$diasAcesso} DAY)";
}

$candidatasSql = "
    SELECT m.id, m.tenant_id, m.tipo_cobranca, sm.codigo AS status_atual,
           mp.max_pago,
           DATEDIFF(CURDATE(), mp.max_pago) AS dias_desde_max_pago,
           {$statusNovoExpr} AS status_novo
    FROM matriculas m
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    INNER JOIN (
        SELECT matricula_id, tenant_id, MAX(data_vencimento) AS max_pago
        FROM pagamentos_plano
        WHERE status_pagamento_id = 2
        GROUP BY matricula_id, tenant_id
    ) mp ON mp.matricula_id = m.id AND mp.tenant_id = m.tenant_id
    WHERE sm.codigo IN ('cancelada', 'vencida')
      {$tenantSql}
      {$whereExtra}
    ORDER BY m.tenant_id, mp.max_pago DESC
";

$stmt = $pdo->prepare($candidatasSql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$say('Candidatas: ' . count($rows));

if ($rows === []) {
    $say($modo === 'estrito'
        ? 'Nenhuma matrícula elegível (max parcela paga >= hoje, ou cancelada há 1-4 dias).'
        : "Nenhuma matrícula elegível no lote {$loteData} com max_pago nos últimos {$diasAcesso} dias.");
    exit(0);
}

$contagem = ['ativa' => 0, 'vencida' => 0, 'cancelada' => 0];
foreach ($rows as $r) {
    $statusNovo = $r['status_novo'];
    $contagem[$statusNovo] = ($contagem[$statusNovo] ?? 0) + 1;
    // Lista detalhada só fora de --quiet (resumo sempre via $say)
    if (!$quiet) {
        $say(sprintf(
            '  #%d tenant=%d | %s → %s | max_pago=%s (%sd) | tipo=%s',
            $r['id'],
            $r['tenant_id'],
            $r['status_atual'],
            $statusNovo,
            $r['max_pago'],
            $r['dias_desde_max_pago'],
            $r['tipo_cobranca']
        ));
    }
}

$say(sprintf(
    'Resumo: %d → ativa, %d → vencida, %d → cancelada',
    $contagem['ativa'],
    $contagem['vencida'],
    $contagem['cancelada']
));

if ($modo === 'lote' && !$forcarAtiva && $contagem['ativa'] < 10) {
    $say('');
    $say('Nota: no modo lote sem --forcar-ativa, a maioria pode continuar cancelada');
    $say('(período pago expirou há 5+ dias). Para reverter o lote inteiro como ATIVA, use --forcar-ativa.');
}

if ($dryRun) {
    exit(0);
}

$pdo->beginTransaction();

try {
    $statusFilter = $modo === 'estrito'
        ? "sm.codigo IN ('cancelada', 'vencida')"
        : "sm.codigo = 'cancelada' AND DATE(m.updated_at) = :lote_data";

    // Escopo do lote (últimos N dias) vs critério de ATIVA (max_pago >= hoje).
    // Sem --forcar-ativa: só vira ativa quem ainda tem período pago vigente;
    // vencida (1-4d) e alinhamento de canceladas (5+d) ficam nos UPDATEs abaixo.
    // Com --forcar-ativa: todas as elegíveis do lote viram ativa.
    $maxPagoFilter = $modo === 'estrito'
        ? 'mp.max_pago >= CURDATE()'
        : "mp.max_pago >= DATE_SUB(CURDATE(), INTERVAL {$diasAcesso} DAY)";

    $ativaVigenteFilter = $forcarAtiva ? '' : 'AND mp.max_pago >= CURDATE()';

    $sqlAtiva = "
        UPDATE matriculas m
        INNER JOIN (
            SELECT matricula_id, tenant_id, MAX(data_vencimento) AS max_pago
            FROM pagamentos_plano WHERE status_pagamento_id = 2
            GROUP BY matricula_id, tenant_id
        ) mp ON mp.matricula_id = m.id AND mp.tenant_id = m.tenant_id
        INNER JOIN status_matricula sm ON sm.id = m.status_id
        SET m.status_id = (SELECT id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1),
            m.data_vencimento = mp.max_pago,
            m.proxima_data_vencimento = mp.max_pago,
            m.updated_at = NOW()
        WHERE {$statusFilter}
          AND {$maxPagoFilter}
          {$ativaVigenteFilter}
          {$tenantSql}
    ";
    $stmtAtiva = $pdo->prepare($sqlAtiva);
    $stmtAtiva->execute($params);
    $ativadas = $stmtAtiva->rowCount();

    $vencidas = 0;
    $canceladas = 0;

    if (!$forcarAtiva) {
        $sqlVencida = "
            UPDATE matriculas m
            INNER JOIN (
                SELECT matricula_id, tenant_id, MAX(data_vencimento) AS max_pago
                FROM pagamentos_plano WHERE status_pagamento_id = 2
                GROUP BY matricula_id, tenant_id
            ) mp ON mp.matricula_id = m.id AND mp.tenant_id = m.tenant_id
            INNER JOIN status_matricula sm ON sm.id = m.status_id
            SET m.status_id = (SELECT id FROM status_matricula WHERE codigo = 'vencida' LIMIT 1),
                m.data_vencimento = mp.max_pago,
                m.proxima_data_vencimento = mp.max_pago,
                m.updated_at = NOW()
            WHERE {$statusFilter}
              AND mp.max_pago < CURDATE()
              AND DATEDIFF(CURDATE(), mp.max_pago) BETWEEN 1 AND 4
              {$tenantSql}
        ";
        $stmtVencida = $pdo->prepare($sqlVencida);
        $stmtVencida->execute($params);
        $vencidas = $stmtVencida->rowCount();

        if ($modo === 'lote') {
            $sqlCancelada = "
                UPDATE matriculas m
                INNER JOIN (
                    SELECT matricula_id, tenant_id, MAX(data_vencimento) AS max_pago
                    FROM pagamentos_plano WHERE status_pagamento_id = 2
                    GROUP BY matricula_id, tenant_id
                ) mp ON mp.matricula_id = m.id AND mp.tenant_id = m.tenant_id
                INNER JOIN status_matricula sm ON sm.id = m.status_id
                SET m.data_vencimento = mp.max_pago,
                    m.proxima_data_vencimento = mp.max_pago,
                    m.updated_at = NOW()
                WHERE sm.codigo = 'cancelada'
                  AND DATE(m.updated_at) = :lote_data
                  AND mp.max_pago >= DATE_SUB(CURDATE(), INTERVAL {$diasAcesso} DAY)
                  AND DATEDIFF(CURDATE(), mp.max_pago) >= 5
                  {$tenantSql}
            ";
            $stmtCancelada = $pdo->prepare($sqlCancelada);
            $stmtCancelada->execute($params);
            $canceladas = $stmtCancelada->rowCount();
        }
    }

    $pdo->commit();

    $say("✅ Concluído: {$ativadas} ativa(s), {$vencidas} vencida(s)" . ($canceladas > 0 ? ", {$canceladas} com datas alinhadas (permanecem canceladas)" : ''));
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'ERRO: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
