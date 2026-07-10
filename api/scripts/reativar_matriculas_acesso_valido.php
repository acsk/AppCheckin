<?php
/**
 * Reativa em lote matrículas canceladas/vencidas cujo período PAGO ainda é válido.
 *
 * Critérios (baseados em MAX(data_vencimento) das parcelas pagas):
 *   - max_pago >= hoje        → status ativa
 *   - max_pago vencido 1-4d   → status vencida (só se estava cancelada)
 *
 * Também alinha data_vencimento / proxima_data_vencimento ao max_pago (avulso e demais).
 *
 * Uso:
 *   php scripts/reativar_matriculas_acesso_valido.php --dry-run
 *   php scripts/reativar_matriculas_acesso_valido.php --tenant=3
 *   php scripts/reativar_matriculas_acesso_valido.php --tenant=3 --quiet
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('America/Sao_Paulo');

$opts = getopt('', ['tenant:', 'dry-run', 'quiet']);
$tenantFilter = isset($opts['tenant']) ? (int) $opts['tenant'] : null;
$dryRun = isset($opts['dry-run']);
$quiet = isset($opts['quiet']);

$pdo = require __DIR__ . '/../config/database.php';

$say = static function (string $msg) use ($quiet): void {
    if (!$quiet) {
        echo $msg . PHP_EOL;
    }
};

$say('=== Reativar matrículas com acesso válido (max parcela paga) ===');
$say(date('Y-m-d H:i:s'));
if ($dryRun) {
    $say('MODO DRY-RUN — nenhuma alteração será feita');
}

$tenantSql = $tenantFilter ? 'AND m.tenant_id = :tenant_id' : '';
$params = $tenantFilter ? ['tenant_id' => $tenantFilter] : [];

$candidatasSql = "
    SELECT m.id, m.tenant_id, m.tipo_cobranca, sm.codigo AS status_atual,
           mp.max_pago,
           DATEDIFF(CURDATE(), mp.max_pago) AS dias_desde_max_pago,
           CASE
               WHEN mp.max_pago >= CURDATE() THEN 'ativa'
               WHEN DATEDIFF(CURDATE(), mp.max_pago) BETWEEN 1 AND 4 THEN 'vencida'
               ELSE NULL
           END AS status_novo
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
      AND (
          mp.max_pago >= CURDATE()
          OR (sm.codigo = 'cancelada' AND DATEDIFF(CURDATE(), mp.max_pago) BETWEEN 1 AND 4)
      )
    ORDER BY m.tenant_id, mp.max_pago DESC
";

$stmt = $pdo->prepare($candidatasSql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$say('Candidatas: ' . count($rows));

if ($rows === []) {
    $say('Nenhuma matrícula elegível (max parcela paga >= hoje, ou cancelada há 1-4 dias).');
    exit(0);
}

$paraAtiva = 0;
$paraVencida = 0;
foreach ($rows as $r) {
    $say(sprintf(
        '  #%d tenant=%d | %s → %s | max_pago=%s | tipo=%s',
        $r['id'],
        $r['tenant_id'],
        $r['status_atual'],
        $r['status_novo'],
        $r['max_pago'],
        $r['tipo_cobranca']
    ));
    if ($r['status_novo'] === 'ativa') {
        $paraAtiva++;
    } else {
        $paraVencida++;
    }
}

if ($dryRun) {
    $say("Resumo dry-run: {$paraAtiva} → ativa, {$paraVencida} → vencida");
    exit(0);
}

$pdo->beginTransaction();

try {
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
        WHERE sm.codigo IN ('cancelada', 'vencida')
          AND mp.max_pago >= CURDATE()
          {$tenantSql}
    ";
    $stmtAtiva = $pdo->prepare($sqlAtiva);
    $stmtAtiva->execute($params);
    $ativadas = $stmtAtiva->rowCount();

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
        WHERE sm.codigo = 'cancelada'
          AND mp.max_pago < CURDATE()
          AND DATEDIFF(CURDATE(), mp.max_pago) BETWEEN 1 AND 4
          {$tenantSql}
    ";
    $stmtVencida = $pdo->prepare($sqlVencida);
    $stmtVencida->execute($params);
    $vencidas = $stmtVencida->rowCount();

    $pdo->commit();

    $say("✅ Concluído: {$ativadas} reativada(s) como ativa, {$vencidas} ajustada(s) para vencida");
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'ERRO: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
