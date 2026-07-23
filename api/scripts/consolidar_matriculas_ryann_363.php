<?php
/**
 * Consolida matrículas duplicadas de Natação do Ryann na #363.
 *
 * Origem do bug: comprar-plano criava irmãs (#383, #384) em vez de reusar #363.
 * Este script:
 *  - Move pagamentos/assinaturas/PIX das órfãs para #363
 *  - Copia o estado vigente da #384 (ativa) para a #363
 *  - Cancela #383 e #384
 *  - NÃO mexe na #370 (diária cancelada — outro produto)
 *
 * Uso (dry-run padrão):
 *   PROD_DB_HOST=... PROD_DB_NAME=... PROD_DB_USER=... PROD_DB_PASS=... \
 *     php scripts/consolidar_matriculas_ryann_363.php
 *   PROD_DB_PASS=... php scripts/consolidar_matriculas_ryann_363.php --fix
 *   php scripts/consolidar_matriculas_ryann_363.php --local [--fix]
 *
 * Prod exige PROD_DB_HOST, PROD_DB_NAME, PROD_DB_USER e PROD_DB_PASS (sem fallback no código).
 */

declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

$apply = in_array('--fix', $argv, true);
$local = in_array('--local', $argv, true);

$manterId = 363;
$orfaIds = [383, 384];
$fonteAtivaId = 384; // estado vigente a copiar

if ($local) {
    $host = getenv('DB_HOST') ?: 'mysql';
    $dbname = getenv('DB_NAME') ?: 'appcheckin';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: 'root';
} else {
    $host = getenv('PROD_DB_HOST') ?: '';
    $dbname = getenv('PROD_DB_NAME') ?: '';
    $user = getenv('PROD_DB_USER') ?: '';
    $pass = getenv('PROD_DB_PASS') ?: '';
    if ($host === '' || $dbname === '' || $user === '' || $pass === '') {
        fwrite(STDERR, "Defina PROD_DB_HOST, PROD_DB_NAME, PROD_DB_USER e PROD_DB_PASS no ambiente.\n");
        fwrite(STDERR, "Ou use --local para o MySQL do Docker.\n");
        exit(1);
    }
}

function out(string $msg): void
{
    echo $msg . "\n";
}

function section(string $title): void
{
    out("\n" . str_repeat('═', 72));
    out($title);
    out(str_repeat('─', 72));
}

try {
    $pdo = new PDO(
        "mysql:host={$host};port=3306;dbname={$dbname};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "Erro de conexão: {$e->getMessage()}\n");
    exit(1);
}

out(($apply ? 'MODO: APPLY (--fix)' : 'MODO: DRY-RUN (nenhuma alteração)') . " | host={$host} db={$dbname}");

$todosIds = array_merge([$manterId], $orfaIds);
$placeholders = implode(',', array_fill(0, count($todosIds), '?'));

section('1) Matrículas envolvidas');
$stmt = $pdo->prepare("
    SELECT m.id, m.aluno_id, m.tenant_id, m.plano_id, m.plano_ciclo_id, m.valor,
           sm.codigo AS status, m.data_inicio, m.data_vencimento, m.proxima_data_vencimento,
           m.tipo_cobranca, m.dia_vencimento, m.motivo_id,
           a.nome AS aluno, p.nome AS plano, p.modalidade_id, md.nome AS modalidade
    FROM matriculas m
    JOIN status_matricula sm ON sm.id = m.status_id
    JOIN alunos a ON a.id = m.aluno_id
    JOIN planos p ON p.id = m.plano_id
    LEFT JOIN modalidades md ON md.id = p.modalidade_id
    WHERE m.id IN ({$placeholders})
    ORDER BY m.id
");
$stmt->execute($todosIds);
$matriculas = [];
foreach ($stmt->fetchAll() as $row) {
    $matriculas[(int) $row['id']] = $row;
    out(sprintf(
        '  #%d | %s | %s | R$ %s | %s→%s | status=%s | aluno=%s (#%d) | modalidade=%s (#%d)',
        $row['id'],
        $row['plano'],
        $row['tipo_cobranca'] ?? '-',
        number_format((float) $row['valor'], 2, ',', '.'),
        $row['data_inicio'] ?? '-',
        $row['proxima_data_vencimento'] ?? $row['data_vencimento'] ?? '-',
        $row['status'],
        $row['aluno'],
        $row['aluno_id'],
        $row['modalidade'] ?? '-',
        $row['modalidade_id']
    ));
}

foreach (array_merge([$manterId, $fonteAtivaId], $orfaIds) as $needId) {
    if (!isset($matriculas[$needId])) {
        fwrite(STDERR, "Matrícula #{$needId} não encontrada. Abortando.\n");
        exit(1);
    }
}

$manter = $matriculas[$manterId];
$fonte = $matriculas[$fonteAtivaId];
$alunoId = (int) $manter['aluno_id'];
$tenantId = (int) $manter['tenant_id'];
$modalidadeId = (int) $manter['modalidade_id'];

foreach ($orfaIds as $oid) {
    $o = $matriculas[$oid];
    if ((int) $o['aluno_id'] !== $alunoId || (int) $o['tenant_id'] !== $tenantId) {
        fwrite(STDERR, "Órfã #{$oid} não pertence ao mesmo aluno/tenant. Abortando.\n");
        exit(1);
    }
    if ((int) $o['modalidade_id'] !== $modalidadeId) {
        fwrite(STDERR, "Órfã #{$oid} é de outra modalidade. Abortando.\n");
        exit(1);
    }
}

if ($fonte['status'] !== 'ativa') {
    out("⚠ Fonte #{$fonteAtivaId} não está 'ativa' (status={$fonte['status']}). Continuará mesmo assim.");
}

section('2) Pagamentos / assinaturas / PIX');
$stmtPag = $pdo->prepare("
    SELECT pp.id, pp.matricula_id, pp.valor, pp.data_vencimento, pp.data_pagamento,
           pp.status_pagamento_id, sp.nome AS status
    FROM pagamentos_plano pp
    LEFT JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
    WHERE pp.matricula_id IN ({$placeholders})
    ORDER BY pp.matricula_id, pp.id
");
$stmtPag->execute($todosIds);
$pagamentos = $stmtPag->fetchAll();
foreach ($pagamentos as $p) {
    out(sprintf(
        '  pp#%d mat=#%d R$%s venc=%s pago=%s status=%s',
        $p['id'],
        $p['matricula_id'],
        number_format((float) $p['valor'], 2, ',', '.'),
        $p['data_vencimento'] ?? '-',
        $p['data_pagamento'] ?? '-',
        $p['status'] ?? $p['status_pagamento_id']
    ));
}

$stmtAss = $pdo->prepare("
    SELECT a.id, a.matricula_id, a.valor, s.codigo AS status, a.external_reference, a.criado_em
    FROM assinaturas a
    LEFT JOIN assinatura_status s ON s.id = a.status_id
    WHERE a.matricula_id IN ({$placeholders})
    ORDER BY a.matricula_id, a.id
");
$stmtAss->execute($todosIds);
$assinaturas = $stmtAss->fetchAll();
foreach ($assinaturas as $a) {
    out(sprintf(
        '  ass#%d mat=#%d R$%s status=%s ref=%s',
        $a['id'],
        $a['matricula_id'],
        number_format((float) $a['valor'], 2, ',', '.'),
        $a['status'] ?? '-',
        $a['external_reference'] ?? '-'
    ));
}

$stmtPix = $pdo->prepare("
    SELECT id, matricula_id, payment_id, status, created_at
    FROM pagamentos_pix
    WHERE matricula_id IN ({$placeholders})
    ORDER BY matricula_id, id
");
$stmtPix->execute($todosIds);
$pixRows = $stmtPix->fetchAll();
foreach ($pixRows as $px) {
    out(sprintf('  pix#%d mat=#%d payment=%s status=%s', $px['id'], $px['matricula_id'], $px['payment_id'], $px['status']));
}

$statusAtivaId = (int) $pdo->query("SELECT id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1")->fetchColumn();
$statusCanceladaId = (int) $pdo->query("SELECT id FROM status_matricula WHERE codigo = 'cancelada' LIMIT 1")->fetchColumn();
$statusAssCanceladaId = (int) $pdo->query("SELECT id FROM assinatura_status WHERE codigo = 'cancelada' LIMIT 1")->fetchColumn();
$motivoRenovacaoId = (int) ($pdo->query("SELECT id FROM motivo_matricula WHERE codigo = 'renovacao' LIMIT 1")->fetchColumn() ?: ($manter['motivo_id'] ?? 1));

if ($statusAtivaId <= 0 || $statusCanceladaId <= 0) {
    fwrite(STDERR, "Status ativa/cancelada (matrícula) não encontrados. Abortando.\n");
    exit(1);
}
if ($statusAssCanceladaId <= 0) {
    fwrite(STDERR, "Status cancelada (assinatura) não encontrado em assinatura_status. Abortando.\n");
    exit(1);
}

section('3) Plano de consolidação');
out("  Manter: #{$manterId}");
out("  Cancelar: #" . implode(', #', $orfaIds));
out("  Copiar estado de #{$fonteAtivaId} → #{$manterId}:");
out(sprintf(
    '    plano_id=%s ciclo=%s valor=%s inicio=%s venc=%s prox=%s status=ativa',
    $fonte['plano_id'],
    $fonte['plano_ciclo_id'] ?? 'null',
    $fonte['valor'],
    $fonte['data_inicio'],
    $fonte['data_vencimento'],
    $fonte['proxima_data_vencimento']
));
out('  Reapontar pagamentos_plano / assinaturas / pagamentos_pix / pagamentos_mercadopago / assinaturas_mercadopago das órfãs → #363');

// IDs da fonte ativa a preservar (pagos + abertos dela); demais abertos na #363 serão cancelados.
$stmtKeepPag = $pdo->prepare("SELECT id FROM pagamentos_plano WHERE matricula_id = ?");
$stmtKeepPag->execute([$fonteAtivaId]);
$keepPagIds = array_map('intval', $stmtKeepPag->fetchAll(PDO::FETCH_COLUMN));
out('  Preservar pagamentos da #' . $fonteAtivaId . ': #' . implode(', #', $keepPagIds ?: ['(nenhum)']));

$stmtKeepAss = $pdo->prepare("SELECT id FROM assinaturas WHERE matricula_id = ? ORDER BY id DESC");
$stmtKeepAss->execute([$fonteAtivaId]);
$keepAssIds = array_map('intval', $stmtKeepAss->fetchAll(PDO::FETCH_COLUMN));
$keepAssIdPrincipal = $keepAssIds[0] ?? 0;
out('  Assinatura principal a manter: #' . ($keepAssIdPrincipal ?: '(nenhuma)'));

if (!$apply) {
    section('Dry-run concluído');
    out('Execute com --fix para aplicar.');
    exit(0);
}

section('4) Aplicando');
$pdo->beginTransaction();
try {
    $orfasPh = implode(',', array_fill(0, count($orfaIds), '?'));

    $move = static function (PDO $pdo, string $table, array $orfaIds, int $manterId, string $orfasPh): int {
        $sql = "UPDATE {$table} SET matricula_id = ? WHERE matricula_id IN ({$orfasPh})";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$manterId], $orfaIds));
        return $stmt->rowCount();
    };

    $nPp = $move($pdo, 'pagamentos_plano', $orfaIds, $manterId, $orfasPh);
    out("  pagamentos_plano movidos: {$nPp}");

    // assinaturas.matricula_id é UNIQUE: libera a #363, aponta a paga da fonte para ela, anula as demais.
    if ($keepAssIdPrincipal > 0) {
        $stmtNullAss = $pdo->prepare("
            UPDATE assinaturas
            SET matricula_id = NULL,
                status_id = ?,
                status_gateway = 'cancelled',
                atualizado_em = NOW()
            WHERE tenant_id = ?
              AND id <> ?
              AND (
                matricula_id = ?
                OR matricula_id IN ({$orfasPh})
              )
        ");
        $stmtNullAss->execute(array_merge(
            [$statusAssCanceladaId, $tenantId, $keepAssIdPrincipal, $manterId],
            $orfaIds
        ));
        out('  Assinaturas pendentes desvinculadas/canceladas: ' . $stmtNullAss->rowCount());

        $stmtMoveAss = $pdo->prepare("
            UPDATE assinaturas
            SET matricula_id = ?, atualizado_em = NOW()
            WHERE id = ? AND tenant_id = ?
        ");
        $stmtMoveAss->execute([$manterId, $keepAssIdPrincipal, $tenantId]);
        out("  Assinatura #{$keepAssIdPrincipal} → matrícula #{$manterId}");
    } else {
        // Sem assinatura na fonte ativa: não deixar órfãs apontando para matrículas canceladas.
        $stmtNullOrfas = $pdo->prepare("
            UPDATE assinaturas
            SET matricula_id = NULL,
                status_id = ?,
                status_gateway = 'cancelled',
                atualizado_em = NOW()
            WHERE tenant_id = ?
              AND matricula_id IN ({$orfasPh})
        ");
        $stmtNullOrfas->execute(array_merge([$statusAssCanceladaId, $tenantId], $orfaIds));
        out('  Assinaturas das órfãs desvinculadas/canceladas: ' . $stmtNullOrfas->rowCount());
    }

    $nPix = $move($pdo, 'pagamentos_pix', $orfaIds, $manterId, $orfasPh);
    out("  pagamentos_pix movidos: {$nPix}");

    foreach (['pagamentos_mercadopago', 'assinaturas_mercadopago', 'matricula_descontos', 'pacote_beneficiarios'] as $tbl) {
        try {
            $n = $move($pdo, $tbl, $orfaIds, $manterId, $orfasPh);
            if ($n > 0) {
                out("  {$tbl} movidos: {$n}");
            }
        } catch (Throwable $e) {
            out("  {$tbl}: ignorado ({$e->getMessage()})");
        }
    }

    // Cancela parcelas abertas na #363 que NÃO vieram da fonte ativa (evita dívida fantasma).
    // Se a fonte não tinha pagamentos, cancela todas as abertas movidas/já existentes.
    $sqlCancelOpen = "
        UPDATE pagamentos_plano
        SET status_pagamento_id = 4,
            observacoes = CONCAT(COALESCE(observacoes, ''), ' | Cancelado na consolidação → #{$manterId}'),
            updated_at = NOW()
        WHERE matricula_id = ?
          AND tenant_id = ?
          AND status_pagamento_id IN (1, 3)
          AND data_pagamento IS NULL
    ";
    $paramsCancelOpen = [$manterId, $tenantId];
    if ($keepPagIds !== []) {
        $keepPh = implode(',', array_fill(0, count($keepPagIds), '?'));
        $sqlCancelOpen .= " AND id NOT IN ({$keepPh})";
        $paramsCancelOpen = array_merge($paramsCancelOpen, $keepPagIds);
    }
    $stmtCancelOpen = $pdo->prepare($sqlCancelOpen);
    $stmtCancelOpen->execute($paramsCancelOpen);
    out('  Parcelas abertas duplicadas canceladas: ' . $stmtCancelOpen->rowCount());

    // Atualiza #363 com estado vigente da #384
    $diaVencimentoFonte = $fonte['dia_vencimento'] ?? null;
    if ($diaVencimentoFonte === null || $diaVencimentoFonte === '') {
        $dataInicioFonte = $fonte['data_inicio'] ?? null;
        if (is_string($dataInicioFonte) && $dataInicioFonte !== '' && $dataInicioFonte !== '0000-00-00') {
            $ts = strtotime($dataInicioFonte);
            $diaVencimentoFonte = ($ts !== false) ? (int) date('d', $ts) : (int) date('d');
        } else {
            $diaVencimentoFonte = (int) date('d');
        }
    } else {
        $diaVencimentoFonte = (int) $diaVencimentoFonte;
    }

    $stmtUp = $pdo->prepare("
        UPDATE matriculas
        SET plano_id = ?,
            plano_ciclo_id = ?,
            valor = ?,
            tipo_cobranca = ?,
            data_inicio = ?,
            data_vencimento = ?,
            proxima_data_vencimento = ?,
            dia_vencimento = ?,
            status_id = ?,
            motivo_id = ?,
            updated_at = NOW()
        WHERE id = ? AND tenant_id = ?
    ");
    $stmtUp->execute([
        $fonte['plano_id'],
        $fonte['plano_ciclo_id'],
        $fonte['valor'],
        $fonte['tipo_cobranca'] ?? 'avulso',
        $fonte['data_inicio'],
        $fonte['data_vencimento'],
        $fonte['proxima_data_vencimento'],
        $diaVencimentoFonte,
        $statusAtivaId,
        $motivoRenovacaoId,
        $manterId,
        $tenantId,
    ]);
    out("  #{$manterId} atualizada com estado de #{$fonteAtivaId} (ativa)");

    $stmtCancel = $pdo->prepare("
        UPDATE matriculas
        SET status_id = ?, updated_at = NOW()
        WHERE id IN ({$orfasPh}) AND tenant_id = ?
    ");
    $stmtCancel->execute(array_merge([$statusCanceladaId], $orfaIds, [$tenantId]));
    out('  Órfãs canceladas: #' . implode(', #', $orfaIds));

    try {
        $pdo->prepare("
            INSERT INTO historico_planos
            (usuario_id, plano_anterior_id, plano_novo_id, data_inicio, data_vencimento, valor_pago, motivo, observacoes, criado_por, created_at)
            SELECT a.usuario_id, ?, ?, ?, ?, ?, 'renovacao', ?, a.usuario_id, NOW()
            FROM alunos a WHERE a.id = ?
        ")->execute([
            $manter['plano_id'],
            $fonte['plano_id'],
            $fonte['data_inicio'],
            $fonte['proxima_data_vencimento'] ?? $fonte['data_vencimento'],
            $fonte['valor'],
            "Consolidação manual: órfãs #" . implode(',', $orfaIds) . " → #{$manterId}",
            $alunoId,
        ]);
        out('  historico_planos registrado');
    } catch (Throwable $e) {
        out('  historico_planos: ' . $e->getMessage());
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Falha — rollback: {$e->getMessage()}\n");
    exit(1);
}

section('5) Estado final');
$stmt->execute($todosIds);
foreach ($stmt->fetchAll() as $row) {
    out(sprintf(
        '  #%d | %s | R$ %s | %s→%s | status=%s',
        $row['id'],
        $row['plano'],
        number_format((float) $row['valor'], 2, ',', '.'),
        $row['data_inicio'] ?? '-',
        $row['proxima_data_vencimento'] ?? $row['data_vencimento'] ?? '-',
        $row['status']
    ));
}

$stmtPagFinal = $pdo->prepare("
    SELECT pp.id, pp.matricula_id, pp.valor, pp.data_vencimento, pp.data_pagamento,
           pp.status_pagamento_id, sp.nome AS status
    FROM pagamentos_plano pp
    LEFT JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
    WHERE pp.matricula_id = ?
    ORDER BY pp.id
");
$stmtPagFinal->execute([$manterId]);
out("\n  Pagamentos agora na #{$manterId}:");
foreach ($stmtPagFinal->fetchAll() as $p) {
    out(sprintf(
        '    pp#%d R$%s venc=%s pago=%s status=%s',
        $p['id'],
        number_format((float) $p['valor'], 2, ',', '.'),
        $p['data_vencimento'] ?? '-',
        $p['data_pagamento'] ?? '-',
        $p['status'] ?? $p['status_pagamento_id']
    ));
}

out("\nConsolidação concluída.");
