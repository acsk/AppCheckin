<?php
/**
 * Debug Matrícula #118
 * Analisa cancelamento indevido e anomalias de parcelas
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

$db = require __DIR__ . '/config/database.php';
$matriculaId = 118;

echo "====== DEBUG MATRÍCULA #118 ======\n";
echo "Data: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Dados da Matrícula
echo "1. DADOS DA MATRÍCULA\n";
echo str_repeat("-", 80) . "\n";
$sql = "
    SELECT 
        m.id,
        m.aluno_id,
        m.plano_id,
        m.plano_ciclo_id,
        m.pacote_contrato_id,
        m.tipo_cobranca,
        m.data_matricula,
        m.data_inicio,
        m.data_vencimento,
        m.proxima_data_vencimento,
        m.valor,
        m.status_id,
        m.motivo_id,
        m.matricula_anterior_id,
        sm.codigo as status_codigo,
        sm.nome as status_nome,
        a.nome as aluno_nome,
        u.email as aluno_email,
        p.nome as plano_nome,
        p.duracao_dias,
        pc.meses as ciclo_meses,
        m.created_at,
        m.updated_at,
        m.cancelado_por,
        m.data_cancelamento,
        m.motivo_cancelamento
    FROM matriculas m
    INNER JOIN alunos a ON m.aluno_id = a.id
    INNER JOIN usuarios u ON a.usuario_id = u.id
    INNER JOIN planos p ON m.plano_id = p.id
    INNER JOIN status_matricula sm ON m.status_id = sm.id
    LEFT JOIN plano_ciclos pc ON m.plano_ciclo_id = pc.id
    WHERE m.id = ?
";
$stmt = $db->prepare($sql);
$stmt->execute([$matriculaId]);
$matricula = $stmt->fetch(\PDO::FETCH_ASSOC);

if (!$matricula) {
    echo "❌ Matrícula não encontrada!\n";
    exit(1);
}

echo "ID: {$matricula['id']}\n";
echo "Aluno: {$matricula['aluno_nome']} (ID: {$matricula['aluno_id']}, Email: {$matricula['aluno_email']})\n";
echo "Plano: {$matricula['plano_nome']} (ID: {$matricula['plano_id']})\n";
echo "Tipo Cobrança: {$matricula['tipo_cobranca']}\n";
echo "Status: {$matricula['status_nome']} ({$matricula['status_codigo']}) - ID {$matricula['status_id']}\n";
echo "Data Matrícula: {$matricula['data_matricula']}\n";
echo "Data Início: {$matricula['data_inicio']}\n";
echo "Data Vencimento: {$matricula['data_vencimento']}\n";
echo "Próxima Data Vencimento: {$matricula['proxima_data_vencimento']}\n";
echo "Valor: R$ " . number_format($matricula['valor'], 2, ',', '.') . "\n";
echo "Duração: {$matricula['duracao_dias']} dias\n";
if ($matricula['ciclo_meses']) {
    echo "Ciclo: {$matricula['ciclo_meses']} mês(es)\n";
}
echo "Criada em: {$matricula['created_at']}\n";
echo "Última atualização: {$matricula['updated_at']}\n";

if ($matricula['cancelado_por']) {
    echo "⚠️ CANCELADA\n";
    echo "   Cancelada por: {$matricula['cancelado_por']} (ID do usuário)\n";
    echo "   Data cancelamento: {$matricula['data_cancelamento']}\n";
    echo "   Motivo: {$matricula['motivo_cancelamento']}\n";
}

if ($matricula['matricula_anterior_id']) {
    echo "Matrícula anterior: {$matricula['matricula_anterior_id']}\n";
}

if ($matricula['pacote_contrato_id']) {
    echo "Pacote contrato: {$matricula['pacote_contrato_id']}\n";
}

echo "\n";

// 2. Parcelas (Pagamentos do Plano)
echo "2. PARCELAS\n";
echo str_repeat("-", 80) . "\n";
$sql = "
    SELECT 
        pp.id,
        pp.valor,
        pp.data_vencimento,
        pp.data_pagamento,
        pp.status_pagamento_id,
        sp.nome as status_pagamento,
        pp.forma_pagamento_id,
        fp.nome as forma_pagamento,
        pp.observacoes,
        pp.created_at,
        pp.updated_at
    FROM pagamentos_plano pp
    LEFT JOIN status_pagamento sp ON pp.status_pagamento_id = sp.id
    LEFT JOIN formas_pagamento fp ON pp.forma_pagamento_id = fp.id
    WHERE pp.matricula_id = ?
    ORDER BY pp.data_vencimento ASC, pp.id ASC
";
$stmt = $db->prepare($sql);
$stmt->execute([$matriculaId]);
$parcelas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

echo "Total de parcelas: " . count($parcelas) . "\n\n";
foreach ($parcelas as $idx => $parcela) {
    $dataPagamento = $parcela['data_pagamento'] ?: 'Nao paga';
    $formaPagamento = $parcela['forma_pagamento'] ?: '-';
    $numeroParcela = $idx + 1;
    echo "Parcela #{$numeroParcela} (ID: {$parcela['id']})\n";
    echo "  Valor: R$ " . number_format($parcela['valor'], 2, ',', '.') . "\n";
    echo "  Data Vencimento: {$parcela['data_vencimento']}\n";
    echo "  Status: {$parcela['status_pagamento']}\n";
    echo "  Data Pagamento: {$dataPagamento}\n";
    echo "  Forma Pagamento: {$formaPagamento}\n";
    if ($parcela['observacoes']) {
        echo "  Observações: {$parcela['observacoes']}\n";
    }
    echo "  Criada em: {$parcela['created_at']}\n";
    echo "  Atualizada em: {$parcela['updated_at']}\n";
    echo "\n";
}

// 3. Pagamentos MercadoPago
echo "3. PAGAMENTOS MERCADOPAGO\n";
echo str_repeat("-", 80) . "\n";
$sql = "
    SELECT 
        pm.id,
        pm.payment_id,
        pm.external_reference,
        pm.status,
        pm.status_detail,
        pm.date_approved,
        pm.date_created,
        pm.date_last_updated,
        pm.amount,
        pm.description,
        pm.payer_email,
        pm.tenant_id,
        pm.assinatura_id,
        pm.assinatura_parcela_id,
        pm.pagamento_simples_id,
        pm.aluno_id,
        pm.created_at,
        pm.updated_at
    FROM pagamentos_mercadopago pm
    WHERE pm.matricula_id = ?
    ORDER BY pm.created_at DESC
";
$pagamentosMp = [];
try {
    $stmt = $db->prepare($sql);
    $stmt->execute([$matriculaId]);
    $pagamentosMp = $stmt->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    echo "Aviso: falha na consulta detalhada de pagamentos_mercadopago: " . $e->getMessage() . "\n";
    $sqlFallback = "SELECT * FROM pagamentos_mercadopago WHERE matricula_id = ? ORDER BY id DESC";
    $stmt = $db->prepare($sqlFallback);
    $stmt->execute([$matriculaId]);
    $pagamentosMp = $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

echo "Total de pagamentos MP: " . count($pagamentosMp) . "\n\n";
foreach ($pagamentosMp as $pm) {
    $paymentId = $pm['payment_id'] ?? ($pm['id'] ?? '-');
    $status = $pm['status'] ?? '-';
    $statusDetail = $pm['status_detail'] ?? '-';
    $externalRef = $pm['external_reference'] ?? '-';
    $dateApproved = $pm['date_approved'] ?? '-';
    $dateCreated = $pm['date_created'] ?? ($pm['created_at'] ?? '-');
    $amount = isset($pm['amount']) ? (float)$pm['amount'] : (isset($pm['transaction_amount']) ? (float)$pm['transaction_amount'] : 0);
    $description = $pm['description'] ?? '-';
    $payerEmail = $pm['payer_email'] ?? '-';

    echo "Payment ID: {$paymentId}\n";
    echo "  Status: {$status} - {$statusDetail}\n";
    echo "  External Reference: {$externalRef}\n";
    echo "  Data Aprovação: {$dateApproved}\n";
    echo "  Data Criação: {$dateCreated}\n";
    echo "  Valor: R$ " . number_format($amount, 2, ',', '.') . "\n";
    echo "  Description: {$description}\n";
    echo "  Payer Email: {$payerEmail}\n";
    
    if ($pm['assinatura_id']) {
        echo "  Assinatura: {$pm['assinatura_id']}\n";
    }
    if ($pm['assinatura_parcela_id']) {
        echo "  Assinatura Parcela: {$pm['assinatura_parcela_id']}\n";
    }
    if ($pm['pagamento_simples_id']) {
        echo "  Pagamento Simples: {$pm['pagamento_simples_id']}\n";
    }
    
    echo "  Criado em: {$pm['created_at']}\n";
    echo "  Atualizado em: {$pm['updated_at']}\n";
    echo "\n";
}

// 4. Webhooks relacionados
echo "4. WEBHOOKS RELACIONADOS\n";
echo str_repeat("-", 80) . "\n";
$sql = "
    SELECT 
        wp.id,
        wp.event,
        wp.action,
        wp.payment_id,
        wp.external_reference,
        wp.status,
        wp.status_detail,
        wp.amount,
        wp.date_approved,
        wp.processed_at,
        wp.processed_result,
        wp.created_at
    FROM webhook_payloads_mercadopago wp
    WHERE wp.external_reference LIKE ?
    ORDER BY wp.created_at DESC
";
$webhooks = [];
try {
    $stmt = $db->prepare($sql);
    $stmt->execute(['MAT-' . $matriculaId . '%']);
    $webhooks = $stmt->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    echo "Aviso: falha na consulta detalhada de webhook_payloads_mercadopago: " . $e->getMessage() . "\n";
    $sqlFallback = "SELECT * FROM webhook_payloads_mercadopago WHERE external_reference LIKE ? ORDER BY id DESC";
    $stmt = $db->prepare($sqlFallback);
    $stmt->execute(['MAT-' . $matriculaId . '%']);
    $webhooks = $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

echo "Total de webhooks: " . count($webhooks) . "\n\n";
foreach ($webhooks as $wh) {
    $event = $wh['event'] ?? ($wh['topic'] ?? '-');
    $action = $wh['action'] ?? '-';
    $paymentId = $wh['payment_id'] ?? '-';
    $externalRef = $wh['external_reference'] ?? '-';
    $status = $wh['status'] ?? '-';
    $statusDetail = $wh['status_detail'] ?? '-';
    $amount = isset($wh['amount']) ? (float)$wh['amount'] : 0;
    $dateApproved = $wh['date_approved'] ?? '-';
    $processedAt = $wh['processed_at'] ?? '-';
    $processedResult = $wh['processed_result'] ?? '-';
    $createdAt = $wh['created_at'] ?? '-';

    echo "Webhook ID: {$wh['id']}\n";
    echo "  Event: {$event}\n";
    echo "  Action: {$action}\n";
    echo "  Payment ID: {$paymentId}\n";
    echo "  External Ref: {$externalRef}\n";
    echo "  Status: {$status} - {$statusDetail}\n";
    echo "  Valor: R$ " . number_format($amount, 2, ',', '.') . "\n";
    echo "  Data Aprovação: {$dateApproved}\n";
    echo "  Processado: {$processedAt}\n";
    echo "  Resultado: {$processedResult}\n";
    echo "  Criado em: {$createdAt}\n";
    echo "\n";
}

// 5. Assinaturas relacionadas (opcional - alguns ambientes legados nao possuem as colunas)
echo "5. ASSINATURAS RELACIONADAS (OPCIONAL)\n";
echo str_repeat("-", 80) . "\n";
try {
    $sql = "
        SELECT 
            a.id,
            a.aluno_id,
            a.plano_ciclo_id,
            a.status,
            a.data_inicio,
            a.data_vencimento,
            a.proxima_data_vencimento,
            a.valor_mensal,
            pc.meses,
            pc.valor,
            a.created_at,
            a.updated_at
        FROM assinaturas a
        LEFT JOIN plano_ciclos pc ON a.plano_ciclo_id = pc.id
        WHERE a.aluno_id = ?
        ORDER BY a.created_at DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$matricula['aluno_id']]);
    $assinaturas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    echo "Total de assinaturas do aluno: " . count($assinaturas) . "\n\n";
    foreach ($assinaturas as $ass) {
        echo "Assinatura ID: " . ($ass['id'] ?? '-') . "\n";
        echo "  Aluno: " . ($ass['aluno_id'] ?? '-') . "\n";
        echo "  Plano Ciclo: " . ($ass['plano_ciclo_id'] ?? '-') . " (" . ($ass['meses'] ?? '-') . " mes(es))\n";
        echo "  Status: " . ($ass['status'] ?? '-') . "\n";
        echo "  Data Início: " . ($ass['data_inicio'] ?? '-') . "\n";
        echo "  Data Vencimento: " . ($ass['data_vencimento'] ?? '-') . "\n";
        echo "  Próxima Data Vencimento: " . ($ass['proxima_data_vencimento'] ?? '-') . "\n";
        echo "  Valor Mensal: R$ " . number_format((float) ($ass['valor_mensal'] ?? 0), 2, ',', '.') . "\n";
        echo "  Criada em: " . ($ass['created_at'] ?? '-') . "\n";
        echo "  Atualizada em: " . ($ass['updated_at'] ?? '-') . "\n";
        echo "\n";
    }
} catch (\Throwable $e) {
    echo "Seção opcional ignorada (schema sem colunas de assinatura esperadas): " . $e->getMessage() . "\n\n";
}

// 6. Histórico de matrículas do aluno
echo "6. HISTÓRICO DE MATRÍCULAS DO ALUNO\n";
echo str_repeat("-", 80) . "\n";
$sql = "
    SELECT 
        m.id,
        m.plano_id,
        p.nome as plano_nome,
        m.data_matricula,
        m.data_inicio,
        m.data_vencimento,
        m.status_id,
        sm.nome as status_nome,
        m.valor,
        m.created_at,
        m.updated_at
    FROM matriculas m
    INNER JOIN planos p ON m.plano_id = p.id
    INNER JOIN status_matricula sm ON m.status_id = sm.id
    WHERE m.aluno_id = ?
    ORDER BY m.created_at DESC
";
$stmt = $db->prepare($sql);
$stmt->execute([$matricula['aluno_id']]);
$matriculas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

echo "Total de matrículas: " . count($matriculas) . "\n\n";
foreach ($matriculas as $m) {
    $destaque = ($m['id'] == $matriculaId) ? " ← ATUAL" : "";
    echo "Matrícula #{$m['id']}{$destaque}\n";
    echo "  Plano: {$m['plano_nome']}\n";
    echo "  Status: {$m['status_nome']}\n";
    echo "  Data Matrícula: {$m['data_matricula']}\n";
    echo "  Data Início: {$m['data_inicio']}\n";
    echo "  Data Vencimento: {$m['data_vencimento']}\n";
    echo "  Valor: R$ " . number_format($m['valor'], 2, ',', '.') . "\n";
    echo "  Criada em: {$m['created_at']}\n";
    echo "  Atualizada em: {$m['updated_at']}\n";
    echo "\n";
}

// 7. ANÁLISE E ANOMALIAS
echo "7. ANÁLISE E ANOMALIAS\n";
echo str_repeat("-", 80) . "\n";

$anomalias = [];

// Verificar status cancelada
if ($matricula['status_codigo'] === 'cancelada' && count($parcelas) > 0) {
    $anomalias[] = "Matrícula cancelada mas tem " . count($parcelas) . " parcela(s) cadastrada(s)";
}

// Verificar parcelas pagas mas matrícula cancelada
$parelasPagas = array_filter($parcelas, fn($p) => strpos($p['status_pagamento'], 'Pago') !== false);
if ($matricula['status_codigo'] === 'cancelada' && count($parelasPagas) > 0) {
    $anomalias[] = "⚠️ Matrícula cancelada mas tem " . count($parelasPagas) . " parcela(s) paga(s)";
}

// Verificar duplicação de pagamentos
$groupedByDate = [];
foreach ($parcelas as $p) {
    $key = $p['data_vencimento'] . '|' . $p['valor'];
    $groupedByDate[$key][] = $p;
}
foreach ($groupedByDate as $key => $group) {
    if (count($group) > 1) {
        list($data, $valor) = explode('|', $key);
        $anomalias[] = "Parcelas duplicadas: " . count($group) . " parcela(s) com mesma data ({$data}) e valor (R$ {$valor})";
    }
}

// Verificar se há pagamentos MP aprovados mas parcelas não pagas
$pgsAprovados = array_filter($pagamentosMp, fn($p) => $p['status'] === 'approved');
$parelasPendentes = array_filter($parcelas, fn($p) => strpos($p['status_pagamento'], 'Aguardando') !== false);

if (count($pgsAprovados) > 0 && count($parelasPendentes) > 0) {
    $anomalias[] = "⚠️ Tem " . count($pgsAprovados) . " pagamento(s) aprovado(s) no MP mas " . count($parelasPendentes) . " parcela(s) ainda aguardando";
}

// Verificar parcelas antigas (antes da data de inicio da matricula atual)
$parcelasAnteriores = array_filter($parcelas, function ($p) use ($matricula) {
    return !empty($p['data_vencimento']) && !empty($matricula['data_inicio']) && $p['data_vencimento'] < $matricula['data_inicio'];
});
if (count($parcelasAnteriores) > 0) {
    $anomalias[] = "Parcelas de ciclo anterior encontradas nesta matricula: " . count($parcelasAnteriores) . " (vencimento antes de " . $matricula['data_inicio'] . ")";
}

// Verificar cancelamento inconsistente com proximo vencimento futuro
if (
    $matricula['status_codigo'] === 'cancelada'
    && !empty($matricula['proxima_data_vencimento'])
    && $matricula['proxima_data_vencimento'] >= date('Y-m-d')
) {
    $anomalias[] = "Status cancelada com proxima_data_vencimento futura (" . $matricula['proxima_data_vencimento'] . ")";
}

// Verificar datas inconsistentes
if ($matricula['data_inicio'] > $matricula['data_vencimento']) {
    $anomalias[] = "❌ CRÍTICO: Data início ({$matricula['data_inicio']}) > data vencimento ({$matricula['data_vencimento']})";
}

if (count($anomalias) > 0) {
    echo "🔴 ANOMALIAS DETECTADAS:\n\n";
    foreach ($anomalias as $anomalia) {
        echo "  {$anomalia}\n";
    }
} else {
    echo "✅ Nenhuma anomalia detectada\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "FIM DO DEBUG\n";
