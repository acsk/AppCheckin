<?php
/**
 * Job: Sincronizar apenas PIX (pagamentos_pix) quando o webhook não baixou a parcela.
 *
 * Cron padrão: varre pagamentos_pix dos últimos 7 dias, consulta MP e dá baixa local.
 * Não processa cartão, assinatura recorrente nem reprocessamento HTTP de webhook.
 *
 * Modos de uso:
 *  php jobs/atualizar_pagamentos_mp.php [--dry-run] [--tenant=3]
 *  php jobs/atualizar_pagamentos_mp.php --matricula-id=324 --tenant=3
 *  php jobs/atualizar_pagamentos_mp.php --payment-id=161301089356 --tenant=3
 *  php jobs/atualizar_pagamentos_mp.php --days=14 --tenant=3
 */

require_once __DIR__ . '/../vendor/autoload.php';

define('LOCK_FILE', '/tmp/atualizar_pagamentos_mp.lock');
define('MAX_EXECUTION_TIME', 300);

$options = getopt('', ['dry-run', 'quiet', 'tenant:','verbose','matricula-id:','assinatura-id:','payment-id:','days:']);
$dryRun = isset($options['dry-run']);
$quiet = isset($options['quiet']);
$tenantId = isset($options['tenant']) ? (int)$options['tenant'] : null;
$verbose = isset($options['verbose']);
$matriculaId = isset($options['matricula-id']) ? (int)$options['matricula-id'] : null;
$assinaturaId = isset($options['assinatura-id']) ? (int)$options['assinatura-id'] : null;
$paymentIdArg = isset($options['payment-id']) ? preg_replace('/\D/', '', (string)$options['payment-id']) : '';
$daysBack = isset($options['days']) ? (int)$options['days'] : 0; // 0 = cron padrão (7 dias)

$root = dirname(__DIR__);
$logFile = $root . '/storage/logs/atualizar_pagamentos_mp.log';

function logMsg($message, $isQuiet = false, $toFile = true) {
    global $logFile, $verbose;
    $ts = date('Y-m-d H:i:s');
    $line = "[$ts] $message";
    if (!$isQuiet) {
        echo $line . "\n";
    }
    if ($toFile) file_put_contents($logFile, $line . "\n", FILE_APPEND);
}

function pagamentoMercadoPagoEhPix(array $pagamento): bool
{
    $method = strtolower((string) ($pagamento['payment_method_id'] ?? ''));
    $type = strtolower((string) ($pagamento['payment_type_id'] ?? ''));

    return $method === 'pix'
        || str_contains($method, 'pix')
        || $type === 'bank_transfer'
        || str_contains($type, 'pix');
}

function buscarPagamentoAprovadoPorExternalReference(int $tenantId, string $externalReference): array
{
    $service = new \App\Services\MercadoPagoService($tenantId);
    $resultado = $service->buscarPagamentosPorExternalReference($externalReference);
    $pagamentos = $resultado['pagamentos'] ?? [];

    foreach ($pagamentos as $pagamento) {
        if (($pagamento['status'] ?? null) === 'approved') {
            return $pagamento;
        }
    }

    return [];
}

function atualizarVigenciaMatriculaAprovada(PDO $pdo, int $matriculaId, ?string $dataReferencia, bool $quiet): void
{
    $stmtMatricula = $pdo->prepare("
        SELECT m.id, m.status_id, m.data_inicio, m.data_vencimento, m.proxima_data_vencimento,
               p.duracao_dias, pc.meses
        FROM matriculas m
        INNER JOIN planos p ON p.id = m.plano_id
        LEFT JOIN plano_ciclos pc ON pc.id = m.plano_ciclo_id
        WHERE m.id = ?
        LIMIT 1
    ");
    $stmtMatricula->execute([$matriculaId]);
    $matricula = $stmtMatricula->fetch(PDO::FETCH_ASSOC);

    if (!$matricula) {
        logMsg("❌ Matrícula {$matriculaId} não encontrada ao atualizar vigência", $quiet);
        return;
    }

    $stmtStatusAtiva = $pdo->query("SELECT id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1");
    $statusAtivaId = (int) ($stmtStatusAtiva->fetchColumn() ?: 0);

    if ($statusAtivaId <= 0) {
        logMsg("❌ Status 'ativa' não encontrado ao atualizar vigência da matrícula {$matriculaId}", $quiet);
        return;
    }

    $dataBase = !empty($dataReferencia)
        ? DateTimeImmutable::createFromFormat('Y-m-d', date('Y-m-d', strtotime($dataReferencia)))
        : new DateTimeImmutable(date('Y-m-d'));

    if (!$dataBase) {
        $dataBase = new DateTimeImmutable(date('Y-m-d'));
    }

    $duracaoMeses = (int) ($matricula['meses'] ?? 0);
    if ($duracaoMeses > 0) {
        $dataVencimento = $dataBase->modify("+{$duracaoMeses} months")->format('Y-m-d');
    } else {
        $duracaoDias = max(1, (int) ($matricula['duracao_dias'] ?? 30));
        $dataVencimento = $dataBase->modify("+{$duracaoDias} days")->format('Y-m-d');
    }

    $dataInicio = !empty($matricula['data_inicio']) ? $matricula['data_inicio'] : $dataBase->format('Y-m-d');

    // Usar a data da próxima parcela pendente real (se existir) como proxima_data_vencimento
    // para evitar divergência com gerarProximoPagamentoAutomatico (que usa ciclo_meses, não duracao_dias)
    $stmtPendReal = $pdo->prepare("
        SELECT MIN(data_vencimento) FROM pagamentos_plano
        WHERE matricula_id = ? AND status_pagamento_id IN (1, 3)
    ");
    $stmtPendReal->execute([$matriculaId]);
    $proximaParcelaPendente = $stmtPendReal->fetchColumn() ?: null;

    $proximaDataVencimento = $proximaParcelaPendente ?? $dataVencimento;

    if ((int) $matricula['status_id'] === $statusAtivaId
        && $matricula['data_inicio'] === $dataInicio
        && $matricula['data_vencimento'] === $dataVencimento
        && $matricula['proxima_data_vencimento'] === $proximaDataVencimento
    ) {
        logMsg("ℹ️  Matrícula {$matriculaId} já está alinhada até {$dataVencimento} (próx. parcela: {$proximaDataVencimento})", $quiet);
        return;
    }

    $stmtUpdate = $pdo->prepare("
        UPDATE matriculas
        SET status_id = ?,
            data_inicio = ?,
            data_vencimento = ?,
            proxima_data_vencimento = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmtUpdate->execute([$statusAtivaId, $dataInicio, $dataVencimento, $proximaDataVencimento, $matriculaId]);

    if ($stmtUpdate->rowCount() > 0) {
        logMsg("✅ Matrícula {$matriculaId} sincronizada até {$dataVencimento} (próx. parcela: {$proximaDataVencimento})", $quiet);
    }
}

function logParcelasPendentesMatricula(PDO $pdo, int $matriculaId, bool $quiet): void
{
    $stmt = $pdo->prepare("
        SELECT pp.id, pp.valor, pp.data_vencimento, sp.nome AS status_nome,
               DATEDIFF(CURDATE(), pp.data_vencimento) AS dias_atraso
        FROM pagamentos_plano pp
        INNER JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
        WHERE pp.matricula_id = ?
          AND pp.status_pagamento_id IN (1, 3)
          AND pp.data_pagamento IS NULL
        ORDER BY pp.data_vencimento ASC
        LIMIT 5
    ");
    $stmt->execute([$matriculaId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if ($rows === []) {
        logMsg("   (sem parcelas pendentes/atrasadas em pagamentos_plano)", $quiet);
        return;
    }

    foreach ($rows as $row) {
        logMsg(sprintf(
            '   → parcela #%s | %s | R$ %s | venc: %s | atraso: %s dia(s)',
            $row['id'],
            $row['status_nome'],
            number_format((float) $row['valor'], 2, ',', '.'),
            $row['data_vencimento'],
            $row['dias_atraso']
        ), $quiet);
    }
}

function sincronizarPagamentoAprovado(PDO $pdo, int $matriculaId, array $pagamento, string $externalReference, bool $quiet): void
{
    $paymentId = (string)($pagamento['id'] ?? '');
    if ($paymentId === '') {
        logMsg("⚠️  Pagamento aprovado sem ID retornado pelo MP para matrícula {$matriculaId}", $quiet);
        return;
    }

    $stmtJa = $pdo->prepare("SELECT id FROM pagamentos_mercadopago WHERE payment_id = ? LIMIT 1");
    $stmtJa->execute([$paymentId]);
    $registroExistenteId = $stmtJa->fetchColumn();

    $stmtMat = $pdo->prepare("SELECT tenant_id, aluno_id FROM matriculas WHERE id = ? LIMIT 1");
    $stmtMat->execute([$matriculaId]);
    $matricula = $stmtMat->fetch(PDO::FETCH_ASSOC);

    if (!$matricula) {
        logMsg("❌ Matrícula {$matriculaId} não encontrada ao sincronizar pagamento {$paymentId}", $quiet);
        return;
    }

    $dateApproved = !empty($pagamento['date_approved'])
        ? date('Y-m-d H:i:s', strtotime((string)$pagamento['date_approved']))
        : null;
    $dateCreated = !empty($pagamento['date_created'])
        ? date('Y-m-d H:i:s', strtotime((string)$pagamento['date_created']))
        : date('Y-m-d H:i:s');

    if ($registroExistenteId) {
        $stmtUpdateMp = $pdo->prepare("
            UPDATE pagamentos_mercadopago
            SET status = ?,
                status_detail = ?,
                transaction_amount = ?,
                payment_method_id = ?,
                payment_type_id = ?,
                installments = ?,
                date_approved = ?,
                payer_email = COALESCE(?, payer_email),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmtUpdateMp->execute([
            $pagamento['status'] ?? 'approved',
            $pagamento['status_detail'] ?? null,
            $pagamento['transaction_amount'] ?? 0,
            $pagamento['payment_method_id'] ?? null,
            $pagamento['payment_type_id'] ?? null,
            $pagamento['installments'] ?? 1,
            $dateApproved,
            $pagamento['payer']['email'] ?? null,
            $registroExistenteId,
        ]);
        logMsg("✅ pagamentos_mercadopago atualizado para payment {$paymentId}", $quiet);
    } else {
        $stmtInsertMp = $pdo->prepare("
            INSERT INTO pagamentos_mercadopago (
                tenant_id, matricula_id, aluno_id, usuario_id,
                payment_id, external_reference, status, status_detail,
                transaction_amount, payment_method_id, payment_type_id,
                installments, date_approved, date_created,
                payer_email, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmtInsertMp->execute([
            $matricula['tenant_id'],
            $matriculaId,
            $matricula['aluno_id'],
            $pagamento['metadata']['usuario_id'] ?? null,
            $paymentId,
            $externalReference,
            $pagamento['status'] ?? 'approved',
            $pagamento['status_detail'] ?? null,
            $pagamento['transaction_amount'] ?? 0,
            $pagamento['payment_method_id'] ?? null,
            $pagamento['payment_type_id'] ?? null,
            $pagamento['installments'] ?? 1,
            $dateApproved,
            $dateCreated,
            $pagamento['payer']['email'] ?? null,
        ]);
        logMsg("✅ pagamentos_mercadopago inserido para payment {$paymentId}", $quiet);
    }

    $paymentType = strtolower((string)($pagamento['payment_type_id'] ?? $pagamento['payment_method_id'] ?? ''));
    $formaPagamentoId = match (true) {
        str_contains($paymentType, 'credit') => 9,
        str_contains($paymentType, 'debit') => 10,
        $paymentType === 'bank_transfer', str_contains($paymentType, 'pix') => 8,
        default => 8,
    };

    $valorMp = (float) ($pagamento['transaction_amount'] ?? 0);

    $stmtPend = $pdo->prepare("
        SELECT id, valor FROM pagamentos_plano
        WHERE matricula_id = ?
          AND status_pagamento_id IN (1, 3)
          AND data_pagamento IS NULL
        ORDER BY ABS(valor - ?) ASC, data_vencimento DESC, id DESC
        LIMIT 1
    ");
    $stmtPend->execute([$matriculaId, $valorMp]);
    $pendRow = $stmtPend->fetch(PDO::FETCH_ASSOC);
    $pagamentoPlanoId = $pendRow['id'] ?? null;

    $stmtJaBaixado = $pdo->prepare("
        SELECT id, valor FROM pagamentos_plano
        WHERE matricula_id = ?
          AND status_pagamento_id = 2
          AND (observacoes LIKE ? OR observacoes LIKE ?)
        LIMIT 1
    ");
    $patternId = "%ID: {$paymentId}%";
    $patternLegacy = "%Payment #{$paymentId}%";
    $stmtJaBaixado->execute([$matriculaId, $patternId, $patternLegacy]);
    $jaBaixadoRow = $stmtJaBaixado->fetch(PDO::FETCH_ASSOC);
    $pagamentoJaBaixado = $jaBaixadoRow['id'] ?? null;

    $obsBaixa = "Pago via Mercado Pago - ID: {$paymentId} (detectado via polling)";

    if ($pagamentoJaBaixado) {
        $valorJaBaixado = (float) ($jaBaixadoRow['valor'] ?? 0);
        $parcelaCorretaPendente = $pendRow && abs((float) $pendRow['valor'] - $valorMp) < abs($valorJaBaixado - $valorMp);
        if ($parcelaCorretaPendente && (int) $pendRow['id'] !== (int) $pagamentoJaBaixado) {
            logMsg(
                "⚠️  Payment {$paymentId} está na parcela #{$pagamentoJaBaixado} (R$ {$valorJaBaixado}) "
                . "mas parcela #{$pendRow['id']} (R$ {$pendRow['valor']}) combina melhor com o valor MP — "
                . "use: php jobs/corrigir_baixa_parcela_mp.php --parcela-id={$pendRow['id']} --payment-id={$paymentId} "
                . "--tenant={$matricula['tenant_id']} --reverter-parcela-errada={$pagamentoJaBaixado}",
                $quiet
            );
        } else {
            logMsg("ℹ️  Pagamento {$paymentId} já estava baixado em pagamentos_plano (#{$pagamentoJaBaixado})", $quiet);
        }
    } elseif ($pagamentoPlanoId) {
        $stmtBaixa = $pdo->prepare("
            UPDATE pagamentos_plano
            SET status_pagamento_id = 2,
                data_pagamento = ?,
                forma_pagamento_id = ?,
                tipo_baixa_id = 4,
                observacoes = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmtBaixa->execute([
            $dateApproved ?: date('Y-m-d H:i:s'),
            $formaPagamentoId,
            $obsBaixa,
            $pagamentoPlanoId,
        ]);
        logMsg("✅ pagamentos_plano {$pagamentoPlanoId} baixado como pago", $quiet);
    } else {
        logMsg("ℹ️  Nenhum pagamento_plano pendente encontrado para matrícula {$matriculaId}", $quiet);
    }

    $tenantIdMat = (int) $matricula['tenant_id'];
    $pagamentoModel = new \App\Models\PagamentoPlano($pdo);

    $parcelaReferencia = $pagamentoPlanoId ?: ($pagamentoJaBaixado ? (int) $pagamentoJaBaixado : 0);
    $valorRef = $parcelaReferencia > 0
        ? (float) ($pendRow['valor'] ?? $jaBaixadoRow['valor'] ?? $valorMp)
        : 0.0;
    if ($parcelaReferencia > 0 && $valorRef > 0) {
        $nDup = $pagamentoModel->cancelarParcelasDuplicadasAposBaixa(
            $tenantIdMat,
            $matriculaId,
            $parcelaReferencia,
            $valorRef
        );
        if ($nDup > 0) {
            logMsg("🧹 {$nDup} parcela(s) duplicada(s) cancelada(s) após baixa #{$parcelaReferencia}", $quiet);
        }
    }

    try {
        $proximaParcela = $pagamentoModel->gerarProximoPagamentoAutomatico($matriculaId, $dateApproved);
        if ($proximaParcela) {
            logMsg("✅ Próximo pagamento #{$proximaParcela['id']} para {$proximaParcela['data_vencimento']} (matrícula {$matriculaId})", $quiet);
        }
    } catch (\Exception $e) {
        logMsg("⚠️  Erro ao gerar próximo pagamento para matrícula {$matriculaId}: " . $e->getMessage(), $quiet);
    }

    // Status correto (ativa/vencida/cancelada) conforme parcelas pendentes — não forçar ativa antes disso
    $pagamentoModel->atualizarStatusMatricula($tenantIdMat, $matriculaId);

    $stmtStatusCodigo = $pdo->prepare("
        SELECT sm.codigo
        FROM matriculas m
        INNER JOIN status_matricula sm ON sm.id = m.status_id
        WHERE m.id = ?
        LIMIT 1
    ");
    $stmtStatusCodigo->execute([$matriculaId]);
    $statusCodigo = (string) ($stmtStatusCodigo->fetchColumn() ?: '');

    if ($statusCodigo === 'ativa') {
        atualizarVigenciaMatriculaAprovada($pdo, $matriculaId, $dateApproved, $quiet);
    } else {
        logMsg("⚠️  Matrícula {$matriculaId} permanece como '{$statusCodigo}' após sync do payment {$paymentId}", $quiet);
        logParcelasPendentesMatricula($pdo, $matriculaId, $quiet);
    }

    $stmtStatusAss = $pdo->query("SELECT id FROM assinatura_status WHERE codigo IN ('paga', 'ativa') ORDER BY FIELD(codigo, 'paga', 'ativa') LIMIT 1");
    $statusAssId = $stmtStatusAss->fetchColumn() ?: 2;

    $stmtAss = $pdo->prepare("
        UPDATE assinaturas
        SET status_id = ?,
            status_gateway = 'approved',
            ultima_cobranca = CURDATE(),
            atualizado_em = NOW()
        WHERE matricula_id = ?
          AND status_gateway != 'approved'
    ");
    $stmtAss->execute([$statusAssId, $matriculaId]);
    if ($stmtAss->rowCount() > 0) {
        logMsg("✅ Assinatura da matrícula {$matriculaId} atualizada para approved", $quiet);
    }
}

/**
 * Sincroniza pagamentos MP de uma matrícula (pending reprocess + approved sem baixa).
 */
function processarMatriculaPagamentos(
    PDO $pdo,
    int $mId,
    ?string $externalReference,
    int $tenantIdAss,
    bool $dryRun,
    bool $quiet,
    ?int $assinaturaIdFilter,
    int $daysBack,
    bool $skipDateFilterOnPayments
): void {
    $sqlP = "SELECT id, payment_id, status, status_detail, date_created
             FROM pagamentos_mercadopago
             WHERE matricula_id = ?
             AND LOWER(status) = 'pending'";

    $paramsP = [$mId];

    if (!$skipDateFilterOnPayments && $daysBack === 0) {
        $sqlP .= " AND DATE(date_created) = CURDATE()";
    } elseif ($daysBack > 0 && !$skipDateFilterOnPayments) {
        $sqlP .= " AND DATE(date_created) >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
        $paramsP[] = $daysBack;
    }

    $stmtP = $pdo->prepare($sqlP);
    $stmtP->execute($paramsP);
    $pagamentos = $stmtP->fetchAll(PDO::FETCH_ASSOC) ?? [];

    $ctx = $assinaturaIdFilter ? "assinatura {$assinaturaIdFilter}" : "matrícula {$mId}";
    logMsg("{$ctx} — pagamentos_mercadopago pending: " . count($pagamentos), $quiet);

    if (count($pagamentos) === 0 && !empty($externalReference) && $tenantIdAss > 0) {
        logMsg("ℹ️  Sem pending local; consultando MP por external_reference {$externalReference}", $quiet);

        try {
            $pagamentoAprovado = buscarPagamentoAprovadoPorExternalReference($tenantIdAss, $externalReference);

            if (!empty($pagamentoAprovado)) {
                logMsg("✅ Approved no MP: payment {$pagamentoAprovado['id']} (matrícula {$mId})", $quiet);
                if (!$dryRun) {
                    sincronizarPagamentoAprovado($pdo, $mId, $pagamentoAprovado, $externalReference, $quiet);
                } else {
                    logMsg("[dry-run] Sincronizaria payment {$pagamentoAprovado['id']}", $quiet);
                }
            } else {
                logMsg("ℹ️  Nenhum approved no MP para {$externalReference}", $quiet);
            }
        } catch (Throwable $mpError) {
            logMsg("❌ Erro MP {$externalReference}: {$mpError->getMessage()}", $quiet);
        }
    }

    $sqlAprov = "
        SELECT pm.payment_id, pm.status, pm.status_detail, pm.transaction_amount,
               pm.payment_method_id, pm.payment_type_id, pm.installments,
               pm.date_approved, pm.date_created, pm.payer_email
        FROM pagamentos_mercadopago pm
        WHERE pm.matricula_id = ?
          AND LOWER(pm.status) = 'approved'
          AND NOT EXISTS (
              SELECT 1 FROM pagamentos_plano pp
              WHERE pp.matricula_id = pm.matricula_id
                AND pp.status_pagamento_id = 2
                AND (pp.observacoes LIKE CONCAT('%ID: ', pm.payment_id, '%')
                  OR pp.observacoes LIKE CONCAT('%Payment #', pm.payment_id, '%'))
          )
    ";
    $stmtAprov = $pdo->prepare($sqlAprov);
    $stmtAprov->execute([$mId]);
    $pagamentosAprovadosNaoSincronizados = $stmtAprov->fetchAll(PDO::FETCH_ASSOC) ?? [];

    foreach ($pagamentosAprovadosNaoSincronizados as $pgAprov) {
        logMsg("ℹ️  Approved sem baixa em pagamentos_plano: payment {$pgAprov['payment_id']} (matrícula {$mId})", $quiet);
        if (!$dryRun) {
            $pagamentoData = [
                'id'                 => $pgAprov['payment_id'],
                'status'             => 'approved',
                'status_detail'      => $pgAprov['status_detail'],
                'transaction_amount' => $pgAprov['transaction_amount'],
                'payment_method_id'  => $pgAprov['payment_method_id'],
                'payment_type_id'    => $pgAprov['payment_type_id'],
                'installments'       => $pgAprov['installments'],
                'date_approved'      => $pgAprov['date_approved'],
                'date_created'       => $pgAprov['date_created'],
                'payer'              => ['email' => $pgAprov['payer_email']],
            ];
            sincronizarPagamentoAprovado($pdo, $mId, $pagamentoData, $externalReference ?? '', $quiet);
        } else {
            logMsg("[dry-run] Sincronizaria approved {$pgAprov['payment_id']}", $quiet);
        }
    }

    foreach ($pagamentos as $pg) {
        $paymentId = $pg['payment_id'] ?? null;

        if (empty($paymentId)) {
            logMsg("Registro pagamentos_mercadopago #{$pg['id']} sem payment_id — pulando", $quiet);
            continue;
        }

        $url = "https://api.appcheckin.com.br/api/webhooks/mercadopago/payment/{$paymentId}/reprocess";

        if ($dryRun) {
            logMsg("[dry-run] POST {$url} [status={$pg['status']}]", $quiet);
            continue;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            logMsg("❌ Reprocess {$paymentId}: {$err}", $quiet);
        } elseif ($httpCode >= 200 && $httpCode <= 299) {
            logMsg("✅ Reprocess {$paymentId} HTTP {$httpCode}", $quiet);
        } else {
            logMsg("⚠️  Reprocess {$paymentId} HTTP {$httpCode}: " . substr((string)$resp, 0, 500), $quiet);
        }
    }
}

/**
 * PIX gerados pelo app (pagamentos_pix) sem baixa em pagamentos_plano — cobre webhook ausente.
 */
function processarPagamentosPixSemBaixa(
    PDO $pdo,
    ?int $tenantFilter,
    int $daysBack,
    bool $dryRun,
    bool $quiet,
    ?int $matriculaFilter = null
): void {
    $check = $pdo->query("SHOW TABLES LIKE 'pagamentos_pix'");
    if ($check->rowCount() === 0) {
        logMsg('ℹ️  Tabela pagamentos_pix não existe — pulando varredura PIX', $quiet);
        return;
    }

    $sql = "
        SELECT px.tenant_id, px.matricula_id, px.payment_id, MAX(px.created_at) AS ultimo_pix
        FROM pagamentos_pix px
        WHERE px.payment_id IS NOT NULL AND TRIM(px.payment_id) != ''
          AND px.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ";
    $params = [$daysBack];

    if ($tenantFilter) {
        $sql .= ' AND px.tenant_id = ?';
        $params[] = $tenantFilter;
    }
    if ($matriculaFilter) {
        $sql .= ' AND px.matricula_id = ?';
        $params[] = $matriculaFilter;
    }

    $sql .= ' GROUP BY px.tenant_id, px.matricula_id, px.payment_id ORDER BY ultimo_pix DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    logMsg('Varredura pagamentos_pix (últimos ' . $daysBack . ' dias): ' . count($rows) . ' payment(s)', $quiet);

    $stmtJaBaixado = $pdo->prepare("
        SELECT id, valor FROM pagamentos_plano
        WHERE matricula_id = ?
          AND status_pagamento_id = 2
          AND (observacoes LIKE ? OR observacoes LIKE ?)
        LIMIT 1
    ");
    $pagamentoModelPix = new \App\Models\PagamentoPlano($pdo);

    foreach ($rows as $row) {
        $paymentId = preg_replace('/\D/', '', (string) ($row['payment_id'] ?? ''));
        $mId = (int) ($row['matricula_id'] ?? 0);
        $tenantPix = (int) ($row['tenant_id'] ?? 0);

        if ($paymentId === '' || $mId <= 0 || $tenantPix <= 0) {
            continue;
        }

        $patternId = "%ID: {$paymentId}%";
        $patternLegacy = "%Payment #{$paymentId}%";
        $stmtJaBaixado->execute([$mId, $patternId, $patternLegacy]);
        $parcelaJaPaga = $stmtJaBaixado->fetch(PDO::FETCH_ASSOC);
        if ($parcelaJaPaga) {
            $nDup = $pagamentoModelPix->cancelarParcelasDuplicadasAposBaixa(
                $tenantPix,
                $mId,
                (int) $parcelaJaPaga['id'],
                (float) $parcelaJaPaga['valor']
            );
            if ($nDup > 0) {
                logMsg("🧹 PIX {$paymentId}: {$nDup} duplicata(s) cancelada(s) (matrícula {$mId})", $quiet);
                $pagamentoModelPix->atualizarStatusMatricula($tenantPix, $mId);
            }
            continue;
        }

        logMsg("PIX payment {$paymentId} sem baixa (matrícula {$mId}, tenant {$tenantPix})", $quiet);

        try {
            $mp = new \App\Services\MercadoPagoService($tenantPix);
            $pagamento = $mp->buscarPagamento($paymentId);
        } catch (Throwable $e) {
            logMsg("❌ MP payment {$paymentId}: {$e->getMessage()}", $quiet);
            continue;
        }

        if (strtolower((string) ($pagamento['status'] ?? '')) !== 'approved') {
            logMsg("ℹ️  Payment {$paymentId} status=" . ($pagamento['status'] ?? '?') . ' — aguardando', $quiet);
            continue;
        }

        if (!pagamentoMercadoPagoEhPix($pagamento)) {
            logMsg("ℹ️  Payment {$paymentId} ignorado (não é PIX)", $quiet);
            continue;
        }

        $externalRef = (string) ($pagamento['external_reference'] ?? "MAT-{$mId}");

        if ($dryRun) {
            logMsg("[dry-run] Sincronizaria PIX {$paymentId} na matrícula {$mId}", $quiet);
            continue;
        }

        sincronizarPagamentoAprovado($pdo, $mId, $pagamento, $externalRef, $quiet);
    }
}

// Lock
if (file_exists(LOCK_FILE)) {
    $lockTime = filemtime(LOCK_FILE);
    if (time() - $lockTime > 600) {
        unlink(LOCK_FILE);
        logMsg("⚠️  Lock antigo removido", $quiet);
    } else {
        logMsg("❌ Job já está em execução (lock ativo)", $quiet);
        exit(1);
    }
}
file_put_contents(LOCK_FILE, getmypid());
set_time_limit(MAX_EXECUTION_TIME);

try {
    logMsg("🚀 Iniciando job: atualizar_pagamentos_mp" . ($dryRun ? ' (dry-run)' : ''), $quiet);
    
    if ($paymentIdArg !== '') {
        logMsg("↪️  Modo: Payment ID {$paymentIdArg}", $quiet);
    }
    if ($matriculaId) {
        logMsg("↪️  Modo: Matrícula #{$matriculaId} (filtro PIX)", $quiet);
    }
    if ($assinaturaId) {
        logMsg('⚠️  --assinatura-id ignorado: este job processa somente PIX (pagamentos_pix)', $quiet);
    }
    if ($daysBack > 0) {
        logMsg("↪️  Janela de tempo: últimos $daysBack dias", $quiet);
    }

    require_once __DIR__ . '/../config/database.php';
    if (!isset($pdo) || !$pdo) {
        $pdo = require __DIR__ . '/../config/database.php';
    }
    if (!isset($pdo)) {
        throw new Exception('Erro ao conectar ao banco');
    }

    // Modo direto por payment_id (PIX approved sem baixa e sem espelho local)
    if ($paymentIdArg !== '') {
        $stmtPay = $pdo->prepare("
            SELECT pm.*, m.tenant_id AS matricula_tenant_id
            FROM pagamentos_mercadopago pm
            LEFT JOIN matriculas m ON m.id = pm.matricula_id
            WHERE pm.payment_id = ?
            LIMIT 1
        ");
        $stmtPay->execute([$paymentIdArg]);
        $rowPay = $stmtPay->fetch(PDO::FETCH_ASSOC) ?: null;

        $mIdDirect = (int) ($matriculaId ?: ($rowPay['matricula_id'] ?? 0));
        $tenantDirect = (int) ($tenantId ?: ($rowPay['tenant_id'] ?? $rowPay['matricula_tenant_id'] ?? 0));

        $pagamentoSync = null;
        $externalRef = (string) ($rowPay['external_reference'] ?? '');

        if ($rowPay && strtolower((string) $rowPay['status']) === 'approved') {
            $pagamentoSync = [
                'id' => $paymentIdArg,
                'status' => 'approved',
                'status_detail' => $rowPay['status_detail'],
                'transaction_amount' => $rowPay['transaction_amount'],
                'payment_method_id' => $rowPay['payment_method_id'],
                'payment_type_id' => $rowPay['payment_type_id'],
                'installments' => $rowPay['installments'] ?? 1,
                'date_approved' => $rowPay['date_approved'],
                'date_created' => $rowPay['date_created'],
                'payer' => ['email' => $rowPay['payer_email'] ?? null],
                'metadata' => [],
            ];
        } elseif ($tenantDirect > 0) {
            logMsg("Consultando MP para payment {$paymentIdArg} (tenant {$tenantDirect})", $quiet);
            $mp = new \App\Services\MercadoPagoService($tenantDirect);
            $pagamentoSync = $mp->buscarPagamento($paymentIdArg);
            $externalRef = (string) ($pagamentoSync['external_reference'] ?? $externalRef);
        }

        if ($mIdDirect <= 0 && is_array($pagamentoSync)) {
            $meta = is_array($pagamentoSync['metadata'] ?? null) ? $pagamentoSync['metadata'] : [];
            $mIdDirect = (int) ($meta['matricula_id'] ?? 0);
            if ($mIdDirect <= 0 && preg_match('/MAT-(\d+)-/', $externalRef, $matMatch)) {
                $mIdDirect = (int) $matMatch[1];
            }
            if ($mIdDirect > 0) {
                logMsg("Matrícula #{$mIdDirect} identificada via MP (metadata/external_reference)", $quiet);
            }
        }

        if ($mIdDirect <= 0) {
            throw new Exception(
                "payment_id {$paymentIdArg} sem matricula_id local — informe --tenant=N (resolve via MP) ou --matricula-id=N"
            );
        }

        if (empty($pagamentoSync) || strtolower((string) ($pagamentoSync['status'] ?? '')) !== 'approved') {
            throw new Exception("Payment {$paymentIdArg} não está approved no banco nem no MP");
        }

        if (!pagamentoMercadoPagoEhPix($pagamentoSync)) {
            throw new Exception("Payment {$paymentIdArg} não é PIX — este job só sincroniza PIX");
        }

        if (!$dryRun) {
            sincronizarPagamentoAprovado($pdo, $mIdDirect, $pagamentoSync, $externalRef, $quiet);
        } else {
            logMsg("[dry-run] Sincronizaria payment {$paymentIdArg} na matrícula {$mIdDirect}", $quiet);
        }

        if (file_exists(LOCK_FILE)) {
            unlink(LOCK_FILE);
        }
        logMsg('Job finalizado (payment-id PIX)', $quiet);
        exit(0);
    }

    logMsg('↪️  Modo: somente PIX (pagamentos_pix)', $quiet);

    $janelaPix = $daysBack > 0 ? $daysBack : 7;
    processarPagamentosPixSemBaixa(
        $pdo,
        $tenantId > 0 ? $tenantId : null,
        $janelaPix,
        $dryRun,
        $quiet,
        $matriculaId > 0 ? $matriculaId : null
    );

    if (file_exists(LOCK_FILE)) {
        unlink(LOCK_FILE);
    }
    logMsg('Job finalizado (PIX)', $quiet);
    exit(0);

} catch (Exception $e) {
    if (file_exists(LOCK_FILE)) unlink(LOCK_FILE);
    logMsg('❌ ERRO: ' . $e->getMessage());
    exit(1);
}
