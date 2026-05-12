<?php
/**
 * Job: Cancelar matrículas DIÁRIAS após o primeiro checkin
 *
 * Regra de negócio:
 *   - Planos com duracao_dias = 1 (diária) devem ser encerrados logo após
 *     o aluno realizar o primeiro checkin do dia pago.
 *   - Após o cancelamento:
 *       • matricula.status_id     → 3 (cancelada)
 *       • matricula.data_vencimento / proxima_data_vencimento → data_inicio
 *       • Parcelas pendentes (status 1=Aguardando / 3=Atrasado) → 4 (Cancelado)
 *
 * Inclui BACKFILL: processa também matrículas antigas já com checkin feito.
 *
 * Cron sugerido (a cada hora):
 *   0 * * * * php /path/to/jobs/cancelar_diarias_apos_checkin.php >> /var/log/cancelar_diarias.log 2>&1
 *
 * Uso manual:
 *   php jobs/cancelar_diarias_apos_checkin.php [--dry-run] [--quiet]
 */

define('LOCK_FILE', '/tmp/cancelar_diarias_apos_checkin.lock');
define('MAX_EXECUTION_TIME', 120);

$options  = getopt('', ['dry-run', 'quiet']);
$dryRun   = isset($options['dry-run']);
$quiet    = isset($options['quiet']);

function log_msg(string $msg, bool $quiet = false): void
{
    if (!$quiet) {
        echo $msg;
    }
}

// ── Lock ────────────────────────────────────────────────────────────────────
if (file_exists(LOCK_FILE)) {
    if (time() - filemtime(LOCK_FILE) > 600) {
        unlink(LOCK_FILE);
        log_msg("⚠️  Lock antigo removido.\n", $quiet);
    } else {
        log_msg("❌ Já existe uma execução em andamento.\n", $quiet);
        exit(0);
    }
}
file_put_contents(LOCK_FILE, getmypid());
register_shutdown_function(fn() => file_exists(LOCK_FILE) && unlink(LOCK_FILE));

// ── Bootstrap ───────────────────────────────────────────────────────────────
set_time_limit(MAX_EXECUTION_TIME);
require_once __DIR__ . '/../vendor/autoload.php';
date_default_timezone_set('America/Sao_Paulo');

/** @var PDO $db */
$db = require __DIR__ . '/../config/database.php';
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_TIMEOUT, 30);

log_msg("========================================\n", $quiet);
log_msg("CANCELAR DIÁRIAS APÓS CHECKIN\n", $quiet);
log_msg("Data/Hora: " . date('Y-m-d H:i:s') . "\n", $quiet);
log_msg($dryRun ? "🔍 MODO DRY-RUN (nenhuma alteração será salva)\n" : "✏️  MODO REAL\n", $quiet);
log_msg("========================================\n\n", $quiet);

$startTime     = microtime(true);
$totalProcessed = 0;
$totalCanceladas = 0;
$totalParcelas   = 0;
$erros           = [];

// ── Buscar matrículas diárias ativas COM pelo menos 1 checkin ───────────────
//
// Lógica de detecção do checkin:
//   O checkin é ligado ao aluno (aluno_id). Como a tabela checkins não possui
//   matricula_id, buscamos checkins de c.aluno_id = m.aluno_id com
//   DATE(c.created_at) >= m.data_inicio para garantir que é referente a esta
//   matrícula específica.
//
$sql = "
    SELECT
        m.id            AS matricula_id,
        m.aluno_id,
        m.tenant_id,
        m.data_inicio,
        m.data_vencimento,
        m.proxima_data_vencimento,
        a.nome          AS aluno_nome,
        p.nome          AS plano_nome,
        p.duracao_dias,
        MIN(DATE(c.created_at)) AS data_primeiro_checkin
    FROM matriculas m
    INNER JOIN planos p ON p.id = m.plano_id
    INNER JOIN alunos a ON a.id = m.aluno_id
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    INNER JOIN checkins c ON c.aluno_id = m.aluno_id
                          AND DATE(c.created_at) >= m.data_inicio
    WHERE sm.codigo = 'ativa'
      AND p.duracao_dias = 1
      AND m.tenant_id IS NOT NULL
    GROUP BY
        m.id, m.aluno_id, m.tenant_id, m.data_inicio,
        m.data_vencimento, m.proxima_data_vencimento,
        a.nome, p.nome, p.duracao_dias
    ORDER BY m.tenant_id ASC, m.id ASC
";

$stmt = $db->prepare($sql);
$stmt->execute();
$matriculas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalProcessed = count($matriculas);
log_msg("📋 Matrículas diárias ativas com checkin encontradas: {$totalProcessed}\n\n", $quiet);

if ($totalProcessed === 0) {
    log_msg("✅ Nenhuma matrícula para processar.\n", $quiet);
    exit(0);
}

// ── IDs dos status ──────────────────────────────────────────────────────────
$statusCancelada = (int) $db->query(
    "SELECT id FROM status_matricula WHERE codigo = 'cancelada' LIMIT 1"
)->fetchColumn();

if (!$statusCancelada) {
    log_msg("❌ Status 'cancelada' não encontrado na tabela status_matricula. Abortando.\n", $quiet);
    exit(1);
}

// ── Processar cada matrícula ─────────────────────────────────────────────────
foreach ($matriculas as $m) {
    $matriculaId    = (int) $m['matricula_id'];
    $dataInicio     = $m['data_inicio'];
    $dataCancelamento = $m['data_primeiro_checkin'] ?? $dataInicio;
    $alunoCod       = "mat#{$matriculaId} – {$m['aluno_nome']} (aluno_id={$m['aluno_id']}, tenant={$m['tenant_id']})";

    log_msg("  ▶  {$alunoCod}\n", $quiet);
    log_msg("      Plano: {$m['plano_nome']} | duracao_dias={$m['duracao_dias']}\n", $quiet);
    log_msg("      data_inicio={$dataInicio} | data_vencimento={$m['data_vencimento']} | 1º checkin={$dataCancelamento}\n", $quiet);

    try {
        if (!$dryRun) {
            $db->beginTransaction();
        }

        // 1. Cancelar parcelas pendentes (status 1=Aguardando, 3=Atrasado → 4=Cancelado)
        $sqlParcelas = "
            UPDATE pagamentos_plano
            SET status_pagamento_id = 4,
                observacoes = CONCAT(COALESCE(observacoes, ''), ' | Cancelado automaticamente: matrícula diária encerrada após checkin em {$dataCancelamento}.'),
                updated_at = NOW()
            WHERE matricula_id = :matricula_id
              AND status_pagamento_id IN (1, 3)
        ";
        $stmtParcelas = $db->prepare($sqlParcelas);

        if (!$dryRun) {
            $stmtParcelas->execute(['matricula_id' => $matriculaId]);
            $parcelasCanceladas = $stmtParcelas->rowCount();
        } else {
            // Dry-run: só conta quantas seriam afetadas
            $stmtCount = $db->prepare(
                "SELECT COUNT(*) FROM pagamentos_plano
                  WHERE matricula_id = ? AND status_pagamento_id IN (1, 3)"
            );
            $stmtCount->execute([$matriculaId]);
            $parcelasCanceladas = (int) $stmtCount->fetchColumn();
        }
        log_msg("      Parcelas pendentes canceladas: {$parcelasCanceladas}\n", $quiet);
        $totalParcelas += $parcelasCanceladas;

        // 2. Cancelar a matrícula e corrigir data_vencimento
        $sqlMatricula = "
            UPDATE matriculas
            SET status_id              = :status_id,
                data_vencimento        = :data_cancelamento,
                proxima_data_vencimento = :data_cancelamento,
                updated_at             = NOW()
            WHERE id = :id
        ";
        $stmtMatricula = $db->prepare($sqlMatricula);

        if (!$dryRun) {
            $stmtMatricula->execute([
                'status_id'         => $statusCancelada,
                'data_cancelamento' => $dataCancelamento,
                'id'                => $matriculaId,
            ]);
        }

        if (!$dryRun) {
            $db->commit();
        }

        log_msg("      ✅ Matrícula cancelada. data_vencimento → {$dataCancelamento}\n\n", $quiet);
        $totalCanceladas++;

    } catch (\Throwable $e) {
        if (!$dryRun && $db->inTransaction()) {
            $db->rollBack();
        }
        $msg = "      ❌ ERRO em mat#{$matriculaId}: " . $e->getMessage() . "\n";
        log_msg($msg, $quiet);
        $erros[] = "mat#{$matriculaId}: " . $e->getMessage();
    }
}

// ── Sumário ──────────────────────────────────────────────────────────────────
$elapsed = round(microtime(true) - $startTime, 2);
log_msg("========================================\n", $quiet);
log_msg("SUMÁRIO\n", $quiet);
log_msg("========================================\n", $quiet);
log_msg("Matrículas encontradas: {$totalProcessed}\n", $quiet);
log_msg("Matrículas canceladas:  {$totalCanceladas}\n", $quiet);
log_msg("Parcelas canceladas:    {$totalParcelas}\n", $quiet);
log_msg("Erros:                  " . count($erros) . "\n", $quiet);
if ($erros) {
    foreach ($erros as $e) log_msg("  - {$e}\n", $quiet);
}
log_msg("Tempo total:            {$elapsed}s\n", $quiet);
if ($dryRun) {
    log_msg("\n🔍 DRY-RUN concluído — nenhuma alteração foi salva.\n", $quiet);
}
log_msg("========================================\n", $quiet);
