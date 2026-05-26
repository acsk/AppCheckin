<?php
/**
 * Diagnóstico: por que um pagamento MP aprovado não deu baixa em pagamentos_plano?
 *
 * Uso (no servidor / container com acesso ao banco de produção):
 *   php debug_pagamento_mp.php 160879679884
 *   php debug_pagamento_mp.php 160879679884 --mp --tenant=1
 *   php debug_pagamento_mp.php 160879679884 --fix
 *
 * Opções:
 *   --mp              Consulta a API do Mercado Pago (requer --tenant ou registro local)
 *   --tenant=N        Tenant para credenciais MP
 *   --fix             Tenta sincronizar baixa (mesma lógica do job atualizar_pagamentos_mp)
 *   --json            Saída JSON no final (para automação)
 */

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

date_default_timezone_set('America/Sao_Paulo');

$paymentId = null;
$options = [
    'mp' => false,
    'fix' => false,
    'json' => false,
    'tenant' => null,
];

foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--tenant=(\d+)$/', $arg, $m)) {
        $options['tenant'] = (int) $m[1];
    } elseif ($arg === '--mp') {
        $options['mp'] = true;
    } elseif ($arg === '--fix') {
        $options['fix'] = true;
    } elseif ($arg === '--json') {
        $options['json'] = true;
    } elseif (preg_match('/^\d+$/', $arg)) {
        $paymentId = $arg;
    }
}

if ($paymentId === null) {
    fwrite(STDERR, "Uso: php debug_pagamento_mp.php <payment_id> [--mp] [--tenant=N] [--fix] [--json]\n");
    exit(1);
}

$pdo = require __DIR__ . '/config/database.php';

$section = static function (string $title): void {
    echo "\n" . str_repeat('═', 72) . "\n";
    echo $title . "\n";
    echo str_repeat('─', 72) . "\n";
};

$line = static function (string $label, $value): void {
    $display = $value === null || $value === '' ? '(vazio)' : (string) $value;
    echo sprintf("  %-28s %s\n", $label . ':', $display);
};

$diagnostico = [
    'payment_id' => $paymentId,
    'timestamp' => date('Y-m-d H:i:s'),
    'causas_provaveis' => [],
    'pode_corrigir_com_fix' => false,
    'matricula_id' => null,
    'tenant_id' => null,
];

echo "DEBUG PAGAMENTO MERCADO PAGO\n";
echo "Payment ID: {$paymentId}\n";
echo 'Data execução: ' . date('Y-m-d H:i:s') . "\n";

// ─── 1. Registro local pagamentos_mercadopago ─────────────────────────────
$section('1. pagamentos_mercadopago (espelho local)');

$stmtPm = $pdo->prepare("
    SELECT pm.*,
           m.status_id AS matricula_status_id,
           sm.codigo AS matricula_status_codigo,
           m.plano_anterior_id,
           m.data_cancelamento,
           a.nome AS aluno_nome
    FROM pagamentos_mercadopago pm
    LEFT JOIN matriculas m ON m.id = pm.matricula_id
    LEFT JOIN status_matricula sm ON sm.id = m.status_id
    LEFT JOIN alunos a ON a.id = pm.aluno_id
    WHERE pm.payment_id = ?
    LIMIT 1
");
$stmtPm->execute([$paymentId]);
$pmLocal = $stmtPm->fetch(PDO::FETCH_ASSOC);

$matriculaId = null;
$tenantId = $options['tenant'];

if ($pmLocal) {
    $matriculaId = (int) ($pmLocal['matricula_id'] ?? 0) ?: null;
    $tenantId = $tenantId ?? (int) ($pmLocal['tenant_id'] ?? 0) ?: null;

    $line('ID registro', $pmLocal['id']);
    $line('Status local', $pmLocal['status']);
    $line('Status detail', $pmLocal['status_detail']);
    $line('Valor', 'R$ ' . number_format((float) ($pmLocal['transaction_amount'] ?? 0), 2, ',', '.'));
    $line('Método', $pmLocal['payment_method_id']);
    $line('Matrícula', $pmLocal['matricula_id']);
    $line('Status matrícula', ($pmLocal['matricula_status_codigo'] ?? '-') . ' (id ' . ($pmLocal['matricula_status_id'] ?? '-') . ')');
    $line('Aluno', $pmLocal['aluno_nome'] ?? $pmLocal['aluno_id']);
    $line('External reference', $pmLocal['external_reference']);
    $line('Aprovado em', $pmLocal['date_approved']);
    $line('Criado em', $pmLocal['date_created']);
    $line('Atualizado em', $pmLocal['updated_at'] ?? '-');

    if (strtolower((string) $pmLocal['status']) !== 'approved') {
        $diagnostico['causas_provaveis'][] =
            'Registro em pagamentos_mercadopago existe mas status local não é "approved" (webhook pode não ter atualizado).';
    }
    if (!empty($pmLocal['plano_anterior_id'])) {
        $diagnostico['causas_provaveis'][] =
            'Matrícula teve alteração de plano (plano_anterior_id preenchido) — reprocessamento via API pode ser bloqueado.';
    }
    if (!empty($pmLocal['data_cancelamento'])) {
        $diagnostico['causas_provaveis'][] =
            'Matrícula cancelada após o pagamento — reprocessamento via API pode ser bloqueado.';
    }
} else {
    echo "  ❌ Nenhum registro com payment_id={$paymentId}\n";
    $diagnostico['causas_provaveis'][] =
        'Pagamento não está em pagamentos_mercadopago (webhook não processou ou falhou antes de gravar espelho).';
}

// ─── 2. Webhooks recebidos ────────────────────────────────────────────────
$section('2. webhook_payloads_mercadopago');

$webhooks = [];
try {
    $check = $pdo->query("SHOW TABLES LIKE 'webhook_payloads_mercadopago'");
    if ($check->rowCount() > 0) {
        $stmtWh = $pdo->prepare("
            SELECT id, tipo, payment_id, external_reference, status,
                   erro_processamento, created_at, processed_at
            FROM webhook_payloads_mercadopago
            WHERE payment_id = ?
               OR payload LIKE ?
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $stmtWh->execute([$paymentId, '%' . $paymentId . '%']);
        $webhooks = $stmtWh->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    echo "  ⚠️  Erro ao consultar webhooks: {$e->getMessage()}\n";
}

if ($webhooks === []) {
    echo "  ❌ Nenhum webhook encontrado para este payment_id\n";
    $diagnostico['causas_provaveis'][] =
        'Nenhum webhook armazenado — MP pode não ter notificado, URL incorreta, ou falha de rede/firewall.';
} else {
    foreach ($webhooks as $wh) {
        echo "\n  Webhook #{$wh['id']} | {$wh['created_at']}\n";
        $line('    Tipo', $wh['tipo']);
        $line('    Status processamento', $wh['status']);
        $line('    External ref', $wh['external_reference']);
        if (!empty($wh['erro_processamento'])) {
            $line('    ERRO', $wh['erro_processamento']);
            $diagnostico['causas_provaveis'][] = 'Webhook #' . $wh['id'] . ' falhou: ' . $wh['erro_processamento'];
        }
        if (($wh['status'] ?? '') === 'erro' || !empty($wh['erro_processamento'])) {
            if (stripos((string) $wh['erro_processamento'], 'Matrícula não identificada') !== false) {
                $diagnostico['causas_provaveis'][] =
                    'external_reference/metadata sem MAT-{id} — webhook não conseguiu achar matrícula.';
            }
        }
    }
}

// ─── 3. API Mercado Pago ──────────────────────────────────────────────────
$mpPagamento = null;
$externalFromMp = null;

if ($options['mp']) {
    $section('3. API Mercado Pago (live)');

    if (!$tenantId) {
        echo "  ⚠️  Informe --tenant=N ou garanta registro em pagamentos_mercadopago\n";
    } else {
        try {
            $service = new \App\Services\MercadoPagoService((int) $tenantId);
            $mpPagamento = $service->buscarPagamento($paymentId);
            $externalFromMp = $mpPagamento['external_reference'] ?? null;

            $line('Status MP', $mpPagamento['status'] ?? '-');
            $line('Status detail', $mpPagamento['status_detail'] ?? '-');
            $line('Valor', 'R$ ' . number_format((float) ($mpPagamento['transaction_amount'] ?? 0), 2, ',', '.'));
            $line('Método', $mpPagamento['payment_method_id'] ?? '-');
            $line('External reference', $externalFromMp);
            $line('Metadata matricula_id', $mpPagamento['metadata']['matricula_id'] ?? '-');
            $line('Metadata aluno_id', $mpPagamento['metadata']['aluno_id'] ?? '-');
            $line('Date approved', $mpPagamento['date_approved'] ?? '-');
            $line('Date created', $mpPagamento['date_created'] ?? '-');

            if (preg_match('/MAT-(\d+)/', (string) $externalFromMp, $m)) {
                $matriculaId = $matriculaId ?: (int) $m[1];
                echo "  → Matrícula extraída do MP: {$matriculaId}\n";
            } elseif (preg_match('/PAC-(\d+)/', (string) $externalFromMp, $m)) {
                $diagnostico['causas_provaveis'][] =
                    'Pagamento de PACOTE (PAC-' . $m[1] . ') — fluxo diferente de matrícula avulsa; verificar pacote_contrato.';
            }
        } catch (Throwable $e) {
            echo "  ❌ Erro API MP: {$e->getMessage()}\n";
            $diagnostico['causas_provaveis'][] = 'Falha ao consultar MP: ' . $e->getMessage();
        }
    }
}

// Resolver matrícula por external_reference dos webhooks se ainda não temos
if (!$matriculaId && $webhooks !== []) {
    foreach ($webhooks as $wh) {
        $ref = (string) ($wh['external_reference'] ?? '');
        if (preg_match('/MAT-(\d+)/', $ref, $m)) {
            $matriculaId = (int) $m[1];
            break;
        }
    }
}

$diagnostico['matricula_id'] = $matriculaId;
$diagnostico['tenant_id'] = $tenantId;

// ─── 4. Matrícula, assinatura, parcelas ───────────────────────────────────
if ($matriculaId) {
    $section("4. Matrícula #{$matriculaId}");

    $stmtMat = $pdo->prepare("
        SELECT m.*, sm.codigo AS status_codigo, sm.nome AS status_nome,
               p.nome AS plano_nome, a.nome AS aluno_nome
        FROM matriculas m
        INNER JOIN status_matricula sm ON sm.id = m.status_id
        INNER JOIN planos p ON p.id = m.plano_id
        INNER JOIN alunos a ON a.id = m.aluno_id
        WHERE m.id = ?
    ");
    $stmtMat->execute([$matriculaId]);
    $mat = $stmtMat->fetch(PDO::FETCH_ASSOC);

    if ($mat) {
        $tenantId = $tenantId ?? (int) $mat['tenant_id'];
        $diagnostico['tenant_id'] = $tenantId;

        $line('Aluno', $mat['aluno_nome']);
        $line('Plano', $mat['plano_nome']);
        $line('Status', $mat['status_codigo'] . ' (' . $mat['status_nome'] . ')');
        $line('Vencimento', $mat['data_vencimento']);
        $line('Próx. vencimento', $mat['proxima_data_vencimento'] ?? '-');
        $line('Valor matrícula', 'R$ ' . number_format((float) $mat['valor'], 2, ',', '.'));
    } else {
        echo "  ❌ Matrícula não existe no banco\n";
        $diagnostico['causas_provaveis'][] = 'matricula_id apontada mas registro inexistente.';
    }

    $section('5. Assinaturas vinculadas');

    $stmtAss = $pdo->prepare("
        SELECT id, status_id, status_gateway, gateway_id, external_reference,
               criado_em, atualizado_em
        FROM assinaturas
        WHERE matricula_id = ?
        ORDER BY id DESC
        LIMIT 5
    ");
    $stmtAss->execute([$matriculaId]);
    $assinaturas = $stmtAss->fetchAll(PDO::FETCH_ASSOC);

    if ($assinaturas === []) {
        echo "  (nenhuma assinatura)\n";
    } else {
        foreach ($assinaturas as $ass) {
            echo "  Assinatura #{$ass['id']} | gateway: {$ass['status_gateway']} | ref: {$ass['external_reference']}\n";
        }
    }

    $section('6. pagamentos_plano (parcelas)');

    $stmtPp = $pdo->prepare("
        SELECT pp.id, pp.valor, pp.data_vencimento, pp.data_pagamento,
               sp.codigo AS status_codigo, pp.observacoes, pp.updated_at
        FROM pagamentos_plano pp
        INNER JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
        WHERE pp.matricula_id = ?
        ORDER BY pp.data_vencimento ASC, pp.id ASC
        LIMIT 20
    ");
    $stmtPp->execute([$matriculaId]);
    $parcelas = $stmtPp->fetchAll(PDO::FETCH_ASSOC);

    $jaBaixadoComEstePayment = false;
    $pendentes = [];

    foreach ($parcelas as $pp) {
        $obs = (string) ($pp['observacoes'] ?? '');
        $marcador = '';
        if (str_contains($obs, $paymentId) || str_contains($obs, "ID: {$paymentId}")) {
            $marcador = ' ← ESTE PAYMENT_ID';
            $jaBaixadoComEstePayment = $pp['status_codigo'] === 'pago';
        }
        if ($pp['status_codigo'] !== 'pago' && empty($pp['data_pagamento'])) {
            $pendentes[] = $pp;
        }
        echo sprintf(
            "  #%d | %s | R$ %s | venc: %s | pago: %s%s\n",
            $pp['id'],
            $pp['status_codigo'],
            number_format((float) $pp['valor'], 2, ',', '.'),
            $pp['data_vencimento'],
            $pp['data_pagamento'] ?: '—',
            $marcador
        );
    }

    if ($jaBaixadoComEstePayment) {
        echo "\n  ✅ Baixa OK: parcela referencia payment_id {$paymentId}\n";
    } else {
        echo "\n  ❌ Nenhuma parcela paga referencia payment_id {$paymentId}\n";

        if ($pmLocal && strtolower((string) $pmLocal['status']) === 'approved') {
            $diagnostico['causas_provaveis'][] =
                'Espelho MP approved mas pagamentos_plano sem baixa — webhook falhou na etapa baixarPagamentoPlano ou job não rodou.';
            $diagnostico['pode_corrigir_com_fix'] = true;
        } elseif (!$pmLocal && ($mpPagamento['status'] ?? '') === 'approved') {
            $diagnostico['causas_provaveis'][] =
                'MP approved, sem espelho local e sem baixa — webhook nunca processou com sucesso.';
            $diagnostico['pode_corrigir_com_fix'] = true;
        } elseif (!$pmLocal) {
            $diagnostico['causas_provaveis'][] =
                'Sem espelho local — use --mp para confirmar status no MP antes de --fix.';
        }

        if ($pendentes === []) {
            $diagnostico['causas_provaveis'][] =
                'Não há parcela pendente (status pendente/vencido) — baixa deveria INSERT nova parcela; verificar logs do webhook.';
        } else {
            echo '  Parcelas pendentes: ' . count($pendentes) . " (a mais antiga seria baixada no --fix)\n";
        }
    }
} else {
    $section('4–6. Matrícula / parcelas');
    echo "  ❌ matricula_id não identificada (sem PM local, webhook sem MAT-*, MP não consultado)\n";
    if (!$options['mp']) {
        echo "  💡 Rode com --mp --tenant=N para extrair external_reference do Mercado Pago\n";
    }
}

// ─── 7. Diagnóstico resumido ──────────────────────────────────────────────
$section('7. DIAGNÓSTICO — causas prováveis');

$causas = array_values(array_unique($diagnostico['causas_provaveis']));
if ($causas === []) {
    echo "  Nenhum problema óbvio detectado. Verifique manualmente logs PHP [Webhook MP].\n";
} else {
    foreach ($causas as $i => $c) {
        echo '  ' . ($i + 1) . ". {$c}\n";
    }
}

$section('8. Ações recomendadas (produção)');

echo "  1. Ver último webhook com erro:\n";
echo "     php database/show_webhook_payload.php last erro\n\n";
echo "  2. Consultar pagamento no MP (com token do tenant):\n";
echo "     curl -H \"Authorization: Bearer <JWT>\" \\\n";
echo "       \"https://api.appcheckin.com.br/api/webhooks/mercadopago/payment/{$paymentId}\"\n\n";
echo "  3. Reprocessar via API (após corrigir causa):\n";
echo "     curl -X POST -H \"Authorization: Bearer <JWT>\" \\\n";
echo "       \"https://api.appcheckin.com.br/api/webhooks/mercadopago/payment/{$paymentId}/reprocess\"\n\n";
echo "  4. Ou sincronizar localmente (se matrícula conhecida):\n";
echo "     php debug_pagamento_mp.php {$paymentId} --fix\n";
if ($matriculaId) {
    echo "     php jobs/atualizar_pagamentos_mp.php --matricula-id={$matriculaId}\n";
}

// ─── --fix ────────────────────────────────────────────────────────────────
if ($options['fix']) {
    $section('9. EXECUTANDO --fix');

    if (!$matriculaId) {
        echo "  ❌ Abortado: matricula_id desconhecida\n";
        exit(1);
    }

    $pagamentoSync = $mpPagamento;
    if (!$pagamentoSync && $pmLocal) {
        $pagamentoSync = [
            'id' => $paymentId,
            'status' => $pmLocal['status'],
            'status_detail' => $pmLocal['status_detail'],
            'transaction_amount' => $pmLocal['transaction_amount'],
            'payment_method_id' => $pmLocal['payment_method_id'],
            'payment_type_id' => $pmLocal['payment_type_id'],
            'installments' => $pmLocal['installments'] ?? 1,
            'date_approved' => $pmLocal['date_approved'],
            'date_created' => $pmLocal['date_created'],
            'payer' => ['email' => $pmLocal['payer_email']],
        ];
    }

    if (!$pagamentoSync || strtolower((string) ($pagamentoSync['status'] ?? '')) !== 'approved') {
        echo "  ❌ Pagamento não está approved — rode com --mp ou confirme no painel MP\n";
        exit(1);
    }

    $externalRef = (string) (
        $pmLocal['external_reference']
        ?? $externalFromMp
        ?? ('MAT-' . $matriculaId)
    );

    try {
        executarFixBaixa($pdo, $matriculaId, $pagamentoSync, $externalRef, $paymentId);
        echo "  ✅ Fix concluído. Rode o script novamente sem --fix para validar.\n";
    } catch (Throwable $e) {
        echo "  ❌ Erro no fix: {$e->getMessage()}\n";
        exit(1);
    }
}

if ($options['json']) {
    echo "\n" . json_encode($diagnostico, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

/**
 * Sincroniza espelho MP + baixa pagamentos_plano (espelho de jobs/atualizar_pagamentos_mp).
 */
function executarFixBaixa(
    PDO $pdo,
    int $matriculaId,
    array $pagamento,
    string $externalReference,
    string $paymentId
): void {
    $pdo->beginTransaction();

    $stmtMat = $pdo->prepare('SELECT tenant_id, aluno_id, plano_id FROM matriculas WHERE id = ? LIMIT 1');
    $stmtMat->execute([$matriculaId]);
    $matricula = $stmtMat->fetch(PDO::FETCH_ASSOC);
    if (!$matricula) {
        throw new RuntimeException("Matrícula {$matriculaId} não encontrada");
    }

    $dateApproved = !empty($pagamento['date_approved'])
        ? date('Y-m-d H:i:s', strtotime((string) $pagamento['date_approved']))
        : date('Y-m-d H:i:s');

    $stmtJa = $pdo->prepare('SELECT id FROM pagamentos_mercadopago WHERE payment_id = ? LIMIT 1');
    $stmtJa->execute([$paymentId]);
    $registroMpId = $stmtJa->fetchColumn();

    if ($registroMpId) {
        $pdo->prepare('
            UPDATE pagamentos_mercadopago
            SET status = ?, status_detail = ?, transaction_amount = ?,
                payment_method_id = ?, date_approved = ?, updated_at = NOW()
            WHERE id = ?
        ')->execute([
            'approved',
            $pagamento['status_detail'] ?? 'accredited',
            $pagamento['transaction_amount'] ?? 0,
            $pagamento['payment_method_id'] ?? 'pix',
            $dateApproved,
            $registroMpId,
        ]);
        echo "  → pagamentos_mercadopago #{$registroMpId} atualizado\n";
    } else {
        $pdo->prepare('
            INSERT INTO pagamentos_mercadopago (
                tenant_id, matricula_id, aluno_id, payment_id, external_reference,
                status, status_detail, transaction_amount, payment_method_id,
                payment_type_id, installments, date_approved, date_created, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ')->execute([
            $matricula['tenant_id'],
            $matriculaId,
            $matricula['aluno_id'],
            $paymentId,
            $externalReference,
            'approved',
            $pagamento['status_detail'] ?? 'accredited',
            $pagamento['transaction_amount'] ?? 0,
            $pagamento['payment_method_id'] ?? 'pix',
            $pagamento['payment_type_id'] ?? 'bank_transfer',
            $pagamento['installments'] ?? 1,
            $dateApproved,
            !empty($pagamento['date_created'])
                ? date('Y-m-d H:i:s', strtotime((string) $pagamento['date_created']))
                : $dateApproved,
        ]);
        echo "  → pagamentos_mercadopago inserido\n";
    }

    $stmtIdem = $pdo->prepare("
        SELECT id FROM pagamentos_plano
        WHERE matricula_id = ? AND status_pagamento_id = 2
          AND (observacoes LIKE ? OR observacoes LIKE ?)
        LIMIT 1
    ");
    $stmtIdem->execute([$matriculaId, "%ID: {$paymentId}%", "%Payment #{$paymentId}%"]);
    if ($stmtIdem->fetchColumn()) {
        echo "  → pagamentos_plano já baixado para este payment_id\n";
        $pdo->commit();
        return;
    }

    $stmtPend = $pdo->prepare("
        SELECT id FROM pagamentos_plano
        WHERE matricula_id = ? AND status_pagamento_id IN (1, 3) AND data_pagamento IS NULL
        ORDER BY data_vencimento ASC LIMIT 1
    ");
    $stmtPend->execute([$matriculaId]);
    $parcelaId = $stmtPend->fetchColumn();

    $paymentType = strtolower((string) ($pagamento['payment_type_id'] ?? $pagamento['payment_method_id'] ?? 'pix'));
    $formaPagamentoId = match (true) {
        str_contains($paymentType, 'credit') => 9,
        str_contains($paymentType, 'debit') => 10,
        default => 8,
    };
    $obs = "Pago via Mercado Pago - ID: {$paymentId} (debug_pagamento_mp --fix)";

    if ($parcelaId) {
        $pdo->prepare('
            UPDATE pagamentos_plano
            SET status_pagamento_id = 2, data_pagamento = ?, forma_pagamento_id = ?,
                tipo_baixa_id = 4, observacoes = ?, updated_at = NOW()
            WHERE id = ?
        ')->execute([$dateApproved, $formaPagamentoId, $obs, $parcelaId]);
        echo "  → pagamentos_plano #{$parcelaId} baixado\n";
    } else {
        $pdo->prepare('
            INSERT INTO pagamentos_plano (
                tenant_id, aluno_id, matricula_id, plano_id,
                valor, data_vencimento, data_pagamento,
                status_pagamento_id, forma_pagamento_id, observacoes, tipo_baixa_id,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, CURDATE(), ?, 2, ?, ?, 4, NOW(), NOW())
        ')->execute([
            $matricula['tenant_id'],
            $matricula['aluno_id'],
            $matriculaId,
            $matricula['plano_id'],
            $pagamento['transaction_amount'] ?? 0,
            $dateApproved,
            $formaPagamentoId,
            $obs,
        ]);
        echo '  → pagamentos_plano novo registro pago #' . $pdo->lastInsertId() . "\n";
    }

    $stmtStatusAtiva = $pdo->query("SELECT id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1");
    $statusAtivaId = (int) ($stmtStatusAtiva->fetchColumn() ?: 0);
    if ($statusAtivaId > 0) {
        $pdo->prepare('UPDATE matriculas SET status_id = ?, updated_at = NOW() WHERE id = ? AND status_id != ?')
            ->execute([$statusAtivaId, $matriculaId, $statusAtivaId]);
    }

    $pdo->commit();
}
