<?php
/**
 * Job: Reprocessar pagamentos para assinaturas
 *
 * Regras:
 * - Por padrão: seleciona assinaturas criadas HOJE
 * - Para cada assinatura, busca pagamentos com status 'pending'
 * - Chama endpoint de reprocessamento para cada pagamento
 * - Desvia de pagamentos já com status_id=6 (approved)
 *
 * Modos de uso:
 *  php jobs/atualizar_pagamentos_mp.php [--dry-run]
 *  php jobs/atualizar_pagamentos_mp.php --matricula-id=58               (reprocessa matrícula específica)
 *  php jobs/atualizar_pagamentos_mp.php --assinatura-id=51             (reprocessa assinatura específica)
 *  php jobs/atualizar_pagamentos_mp.php --days=7                       (últimos 7 dias)
 *  php jobs/atualizar_pagamentos_mp.php --matricula-id=58 --dry-run    (simula)
 *
 * ⚠️  IMPORTANTE: Este job depende de webhooks retornarem para atualizar o banco localmente.
 *    Se a webhook falhar, o banco permanecerá desatualizado.
 */

require_once __DIR__ . '/../vendor/autoload.php';

define('LOCK_FILE', '/tmp/atualizar_pagamentos_mp.lock');
define('MAX_EXECUTION_TIME', 300);

$options = getopt('', ['dry-run', 'quiet', 'tenant:','verbose','matricula-id:','assinatura-id:','days:']);
$dryRun = isset($options['dry-run']);
$quiet = isset($options['quiet']);
$tenantId = isset($options['tenant']) ? (int)$options['tenant'] : null;
$verbose = isset($options['verbose']);
$matriculaId = isset($options['matricula-id']) ? (int)$options['matricula-id'] : null;
$assinaturaId = isset($options['assinatura-id']) ? (int)$options['assinatura-id'] : null;
$daysBack = isset($options['days']) ? (int)$options['days'] : 0; // 0 = apenas hoje, 7 = últimos 7 dias

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

    if ((int) $matricula['status_id'] === $statusAtivaId
        && $matricula['data_inicio'] === $dataInicio
        && $matricula['data_vencimento'] === $dataVencimento
        && $matricula['proxima_data_vencimento'] === $dataVencimento
    ) {
        logMsg("ℹ️  Matrícula {$matriculaId} já está alinhada até {$dataVencimento}", $quiet);
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
    $stmtUpdate->execute([$statusAtivaId, $dataInicio, $dataVencimento, $dataVencimento, $matriculaId]);

    if ($stmtUpdate->rowCount() > 0) {
        logMsg("✅ Matrícula {$matriculaId} sincronizada até {$dataVencimento}", $quiet);
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

    $stmtPend = $pdo->prepare("
        SELECT id FROM pagamentos_plano
        WHERE matricula_id = ?
          AND status_pagamento_id IN (1, 3)
          AND data_pagamento IS NULL
        ORDER BY data_vencimento ASC
        LIMIT 1
    ");
    $stmtPend->execute([$matriculaId]);
    $pagamentoPlanoId = $stmtPend->fetchColumn();

    $stmtJaBaixado = $pdo->prepare("
        SELECT id FROM pagamentos_plano
        WHERE matricula_id = ?
          AND status_pagamento_id = 2
          AND (observacoes LIKE ? OR observacoes LIKE ?)
        LIMIT 1
    ");
    $patternId = "%ID: {$paymentId}%";
    $patternLegacy = "%Payment #{$paymentId}%";
    $stmtJaBaixado->execute([$matriculaId, $patternId, $patternLegacy]);
    $pagamentoJaBaixado = $stmtJaBaixado->fetchColumn();

    if ($pagamentoJaBaixado) {
        logMsg("ℹ️  Pagamento {$paymentId} já estava baixado em pagamentos_plano", $quiet);
        atualizarVigenciaMatriculaAprovada($pdo, $matriculaId, $dateApproved, $quiet);
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
            "Pago via Mercado Pago - ID: {$paymentId} (detectado via polling)",
            $pagamentoPlanoId,
        ]);
        logMsg("✅ pagamentos_plano {$pagamentoPlanoId} baixado como pago", $quiet);
        atualizarVigenciaMatriculaAprovada($pdo, $matriculaId, $dateApproved, $quiet);
    } else {
        logMsg("ℹ️  Nenhum pagamento_plano pendente encontrado para matrícula {$matriculaId}", $quiet);
    }

    // Gerar próximo pagamento pendente para manter o ciclo de cobrança
    try {
        $pagamentoModel = new \App\Models\PagamentoPlano($pdo);
        $proximaParcela = $pagamentoModel->gerarProximoPagamentoAutomatico($matriculaId, $dateApproved);
        if ($proximaParcela) {
            logMsg("✅ Próximo pagamento #{$proximaParcela['id']} gerado para {$proximaParcela['data_vencimento']} (matrícula {$matriculaId})", $quiet);
        }
    } catch (\Exception $e) {
        logMsg("⚠️  Erro ao gerar próximo pagamento para matrícula {$matriculaId}: " . $e->getMessage(), $quiet);
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
    
    if ($matriculaId) {
        logMsg("↪️  Modo: Reprocessar Matrícula #$matriculaId", $quiet);
    }
    if ($assinaturaId) {
        logMsg("↪️  Modo: Reprocessar Assinatura #$assinaturaId", $quiet);
    }
    if ($daysBack > 0) {
        logMsg("↪️  Janela de tempo: últimos $daysBack dias", $quiet);
    }

    // Conectar DB
    require_once __DIR__ . '/../config/database.php';
    if (!isset($pdo) || !$pdo) {
        // config/database.php retorna PDO, capture em $pdo
        $pdo = require __DIR__ . '/../config/database.php';
    }
    if (!isset($pdo)) throw new Exception('Erro ao conectar ao banco');

    // Construir query de assinaturas com filtros flexíveis
    $sqlAss = "SELECT id, tenant_id, matricula_id, gateway_id, gateway_assinatura_id, external_reference, criado_em FROM assinaturas WHERE 1=1";
    $paramsAss = [];
    
    if ($assinaturaId) {
        $sqlAss .= " AND id = ?";
        $paramsAss[] = $assinaturaId;
    } else {
        // Se não especificou assinatura, filtrar por data (com flexibilidade)
        if ($daysBack > 0) {
            $sqlAss .= " AND DATE(criado_em) >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
            $paramsAss[] = $daysBack;
        } else {
            // Janela padrão: últimos 2 dias (hoje + ontem) para não perder assinaturas criadas no fim do dia anterior
            $sqlAss .= " AND DATE(criado_em) >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)";
        }
    }
    
    if ($tenantId) {
        $sqlAss .= " AND tenant_id = ?";
        $paramsAss[] = $tenantId;
    }

    $stmt = $pdo->prepare($sqlAss);
    $stmt->execute($paramsAss);
    $assinaturas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];
    logMsg('Assinaturas encontradas: ' . count($assinaturas), $quiet);

    foreach ($assinaturas as $ass) {
        $matriculaIdAss = $ass['matricula_id'] ?? null;
        $assinaturaIdCur = $ass['id'];
        $externalReference = $ass['external_reference'] ?? null;
        $tenantIdAss = (int)($ass['tenant_id'] ?? 0);

        if (empty($matriculaIdAss) && !$matriculaId) {
            logMsg("Assinatura {$assinaturaIdCur} sem matricula_id — pulando", $quiet);
            continue;
        }

        // Se foi especificada uma matrícula via CLI, usar essa
        $mId = $matriculaId ?? $matriculaIdAss;

        // Construir query de pagamentos com filtros flexíveis
        $sqlP = "SELECT id, payment_id, status, status_detail, date_created 
                 FROM pagamentos_mercadopago 
                 WHERE matricula_id = ? 
                 AND LOWER(status) = 'pending'";
        
        $paramsP = [$mId];
        
        // Se especificou assinatura ou matrícula, não filtrar por data
        // Senão, filtrar por data (flexível com --days)
        if (!$assinaturaId && !$matriculaId && $daysBack == 0) {
            $sqlP .= " AND DATE(date_created) = CURDATE()";
        } elseif ($daysBack > 0) {
            $sqlP .= " AND DATE(date_created) >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
            $paramsP[] = $daysBack;
        }
        
        $stmtP = $pdo->prepare($sqlP);
        $stmtP->execute($paramsP);
        $pagamentos = $stmtP->fetchAll(PDO::FETCH_ASSOC) ?? [];

        logMsg("Assinatura {$assinaturaIdCur} (matricula {$mId}) - pagamentos pendentes: " . count($pagamentos), $quiet);

        if (count($pagamentos) === 0 && !empty($externalReference) && $tenantIdAss > 0) {
            logMsg("ℹ️  Sem espelho local em pagamentos_mercadopago para matrícula {$mId}; consultando MP por external_reference {$externalReference}", $quiet);

            try {
                $pagamentoAprovado = buscarPagamentoAprovadoPorExternalReference($tenantIdAss, $externalReference);

                if (!empty($pagamentoAprovado)) {
                    logMsg("✅ Pagamento aprovado encontrado no MP para matrícula {$mId}: payment {$pagamentoAprovado['id']}", $quiet);
                    sincronizarPagamentoAprovado($pdo, (int)$mId, $pagamentoAprovado, $externalReference, $quiet);
                } else {
                    logMsg("ℹ️  Nenhum pagamento approved encontrado no MP para external_reference {$externalReference}", $quiet);
                }
            } catch (Throwable $mpError) {
                logMsg("❌ Erro ao consultar MP por external_reference {$externalReference}: {$mpError->getMessage()}", $quiet);
            }
        }

        // Verificar pagamentos approved em pagamentos_mercadopago que ainda não foram baixados em pagamentos_plano
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
            logMsg("ℹ️  Pagamento approved não sincronizado para matrícula {$mId}: payment {$pgAprov['payment_id']}", $quiet);
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
                sincronizarPagamentoAprovado($pdo, (int)$mId, $pagamentoData, $externalReference ?? '', $quiet);
            } else {
                logMsg("[dry-run] Sincronizaria pagamento approved {$pgAprov['payment_id']} para matrícula {$mId}", $quiet);
            }
        }

        foreach ($pagamentos as $pg) {
            $paymentId = $pg['payment_id'] ?? null;
            $statusText = $pg['status'] ?? 'unknown';
            
            if (empty($paymentId)) {
                logMsg("Pagamento registro {$pg['id']} sem payment_id — pulando", $quiet);
                continue;
            }

            $url = "https://api.appcheckin.com.br/api/webhooks/mercadopago/payment/{$paymentId}/reprocess";

            if ($dryRun) {
                logMsg("[dry-run] POST {$url} [status={$statusText}]", $quiet);
                continue;
            }

            // Fazer requisição POST (sem payload) com timeout
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

            $resp = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err) {
                logMsg("❌ Erro ao chamar reprocess para payment {$paymentId}: {$err}", $quiet);
            } else {
                if ($httpCode >= 200 && $httpCode <= 299) {
                    logMsg("✅ Reprocess payment {$paymentId} - HTTP {$httpCode} OK", $quiet);
                } else {
                    logMsg("⚠️  Reprocess payment {$paymentId} - HTTP {$httpCode} - resposta: " . substr($resp ?? '', 0, 500), $quiet);
                }
            }
        }
    }

    if (file_exists(LOCK_FILE)) unlink(LOCK_FILE);
    logMsg('Job finalizado', $quiet);
    exit(0);

} catch (Exception $e) {
    if (file_exists(LOCK_FILE)) unlink(LOCK_FILE);
    logMsg('❌ ERRO: ' . $e->getMessage());
    exit(1);
}
