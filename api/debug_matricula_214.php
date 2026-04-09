<?php
/**
 * Debug e Correção — Matrícula #214 (JESSICA ASSUNÇÃO)
 * Problema: proxima_data_vencimento não foi atualizado após renovação
 * External ref: MAT-214-1775656122
 */

require_once __DIR__ . '/vendor/autoload.php';
$db = require __DIR__ . '/config/database.php';
date_default_timezone_set('America/Sao_Paulo');

// Opção: passar ?corrigir=1 na linha de comando para aplicar o fix
$CORRIGIR = in_array('--corrigir', $argv ?? []);

echo "====== DEBUG MATRÍCULA #214 — JESSICA ASSUNÇÃO ======\n";
echo "Data BRT: " . date('Y-m-d H:i:s') . "\n";
echo ($CORRIGIR ? "⚠️  MODO CORRETIVO ATIVO\n" : "ℹ️  Modo somente leitura (passe --corrigir para aplicar)\n") . "\n";

// ============================================================
// 1. ESTADO ATUAL DA MATRÍCULA
// ============================================================
echo "1. MATRÍCULA #214\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare("
    SELECT
        m.id, m.aluno_id, m.tenant_id, m.plano_id, m.plano_ciclo_id,
        m.data_matricula, m.data_inicio, m.data_vencimento,
        m.proxima_data_vencimento, m.dia_vencimento,
        m.tipo_cobranca, m.valor, m.status_id,
        sm.nome as status_nome, sm.codigo as status_cod,
        p.nome as plano_nome, p.duracao_dias,
        pc.meses as ciclo_meses,
        af.meses as freq_meses, af.nome as freq_nome,
        u.nome as aluno_nome,
        m.updated_at
    FROM matriculas m
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    INNER JOIN planos p ON p.id = m.plano_id
    LEFT JOIN plano_ciclos pc ON pc.id = m.plano_ciclo_id
    LEFT JOIN assinatura_frequencias af ON af.id = pc.assinatura_frequencia_id
    INNER JOIN alunos a ON a.id = m.aluno_id
    INNER JOIN usuarios u ON u.id = a.usuario_id
    WHERE m.id = 214
");
$stmt->execute();
$mat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mat) {
    echo "❌ Matrícula #214 não encontrada.\n";
    exit(1);
}

echo "  Aluno               : {$mat['aluno_nome']} (aluno_id={$mat['aluno_id']}, tenant={$mat['tenant_id']})\n";
echo "  Status              : {$mat['status_nome']} ({$mat['status_cod']})\n";
echo "  Plano               : {$mat['plano_nome']} | duracao_dias={$mat['duracao_dias']}\n";
echo "  Ciclo               : " . ($mat['freq_nome'] ?? 'N/A') . " (ciclo_meses={$mat['ciclo_meses']}, freq_meses={$mat['freq_meses']})\n";
echo "  data_inicio         : {$mat['data_inicio']}\n";
echo "  data_vencimento     : {$mat['data_vencimento']}\n";
echo "  proxima_data_venc   : " . ($mat['proxima_data_vencimento'] ?? 'NULL') . "\n";
echo "  tipo_cobranca       : {$mat['tipo_cobranca']}\n";
echo "  valor               : R$ {$mat['valor']}\n";
echo "  updated_at          : {$mat['updated_at']}\n";

// ============================================================
// 2. ASSINATURAS VINCULADAS
// ============================================================
echo "\n2. ASSINATURAS (matricula_id=214)\n";
echo str_repeat("-", 80) . "\n";

$stmtAss = $db->prepare("
    SELECT a.id, a.tipo_cobranca, a.status_id,
           as_.codigo as status_cod, as_.nome as status_nome,
           a.status_gateway,
           a.external_reference, a.gateway_preference_id,
           a.data_inicio, a.data_fim,
           a.ultima_cobranca, a.proxima_cobranca,
           a.criado_em, a.atualizado_em
    FROM assinaturas a
    LEFT JOIN assinatura_status as_ ON as_.id = a.status_id
    WHERE a.matricula_id = 214
    ORDER BY a.id DESC
");
$stmtAss->execute();
$assinaturas = $stmtAss->fetchAll(PDO::FETCH_ASSOC);

if (empty($assinaturas)) {
    echo "  Nenhuma assinatura encontrada.\n";
} else {
    foreach ($assinaturas as $a) {
        echo "  Assinatura #{$a['id']}\n";
        echo "    tipo          : {$a['tipo_cobranca']}\n";
        echo "    status        : {$a['status_nome']} ({$a['status_cod']}) | gateway: {$a['status_gateway']}\n";
        echo "    external_ref  : " . ($a['external_reference'] ?? '-') . "\n";
        echo "    data_inicio   : " . ($a['data_inicio'] ?? 'NULL') . "\n";
        echo "    data_fim      : " . ($a['data_fim'] ?? 'NULL') . "\n";
        echo "    ultima_cobr   : " . ($a['ultima_cobranca'] ?? 'NULL') . "\n";
        echo "    proxima_cobr  : " . ($a['proxima_cobranca'] ?? 'NULL') . "\n";
        echo "    criado_em     : {$a['criado_em']}\n";
        echo "\n";
    }
}

// ============================================================
// 3. PAGAMENTOS_PLANO (últimos 5)
// ============================================================
echo "3. PAGAMENTOS_PLANO (matricula_id=214)\n";
echo str_repeat("-", 80) . "\n";

$stmtPag = $db->prepare("
    SELECT pp.id, pp.valor, pp.data_vencimento, pp.data_pagamento,
           sp.nome as status_nome,
           pp.observacoes, pp.tipo_baixa_id, pp.created_at
    FROM pagamentos_plano pp
    LEFT JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
    WHERE pp.matricula_id = 214
    ORDER BY pp.id DESC
    LIMIT 10
");
$stmtPag->execute();
$pags = $stmtPag->fetchAll(PDO::FETCH_ASSOC);

printf("  %-8s %-12s %-12s %-16s %-14s %s\n", "ID", "Vencimento", "Pagamento", "Status", "Valor", "Observações");
echo "  " . str_repeat("-", 80) . "\n";
foreach ($pags as $p) {
    printf("  %-8s %-12s %-12s %-16s %-14s %s\n",
        "#" . $p['id'],
        $p['data_vencimento'] ?? '-',
        $p['data_pagamento'] ?? '-',
        $p['status_nome'] ?? '-',
        "R$ " . number_format($p['valor'], 2, ',', '.'),
        substr($p['observacoes'] ?? '', 0, 40)
    );
}

// ============================================================
// 4. WEBHOOK LOG (external_reference MAT-214-...)
// ============================================================
echo "\n4. WEBHOOKS PROCESSADOS (MAT-214-...)\n";
echo str_repeat("-", 80) . "\n";

$stmtWh = $db->prepare("
    SELECT id, tipo, data_id, status, external_reference, payment_id, created_at, erro_processamento
    FROM webhook_payloads_mercadopago
    WHERE external_reference LIKE 'MAT-214-%'
       OR payment_id IN (
           SELECT payment_id FROM pagamentos_mercadopago WHERE matricula_id = 214
       )
    ORDER BY id DESC
    LIMIT 10
");
$stmtWh->execute();
$webhooks = $stmtWh->fetchAll(PDO::FETCH_ASSOC);

if (empty($webhooks)) {
    echo "  Nenhum webhook encontrado.\n";
} else {
    foreach ($webhooks as $wh) {
        echo "  WH #{$wh['id']} | {$wh['created_at']} | tipo={$wh['tipo']} | status={$wh['status']}\n";
        echo "    external_ref: " . ($wh['external_reference'] ?? '-') . " | payment_id: " . ($wh['payment_id'] ?? '-') . "\n";
        if (!empty($wh['erro_processamento'])) {
            echo "    ⚠️ ERRO: " . substr($wh['erro_processamento'], 0, 120) . "\n";
        }
    }
}

// ============================================================
// 5. DIAGNÓSTICO E CÁLCULO DO VALOR CORRETO
// ============================================================
echo "\n5. DIAGNÓSTICO\n";
echo str_repeat("-", 80) . "\n";

// Qual deve ser a proxima_data_vencimento correta?
$assinaturaAtiva = null;
foreach ($assinaturas as $a) {
    if (!empty($a['data_fim']) && $a['status_gateway'] === 'approved') {
        $assinaturaAtiva = $a;
        break;
    }
}

$dataFimAssinatura = $assinaturaAtiva['data_fim'] ?? null;

// Buscar parcela "Aguardando" da matrícula
$stmtPend = $db->prepare("
    SELECT id, data_vencimento FROM pagamentos_plano
    WHERE matricula_id = 214 AND status_pagamento_id IN (1, 3)
    ORDER BY data_vencimento ASC LIMIT 1
");
$stmtPend->execute();
$parcelaPendente = $stmtPend->fetch(PDO::FETCH_ASSOC);

// Calcular via data_pagamento da última paga + duracao
$stmtUltimoPago = $db->prepare("
    SELECT data_vencimento, data_pagamento FROM pagamentos_plano
    WHERE matricula_id = 214 AND status_pagamento_id = 2
    ORDER BY data_vencimento DESC LIMIT 1
");
$stmtUltimoPago->execute();
$ultimoPago = $stmtUltimoPago->fetch(PDO::FETCH_ASSOC);

$duracaoDias = (int) ($mat['duracao_dias'] ?? 30);
$cicloMeses = (int) ($mat['ciclo_meses'] ?? $mat['freq_meses'] ?? 0);

$dataBaseCalc = null;
if ($ultimoPago) {
    $dvPago = new DateTime($ultimoPago['data_vencimento']);
    $dpPago = new DateTime($ultimoPago['data_pagamento'] ?? $ultimoPago['data_vencimento']);
    $dataBaseCalc = $dpPago > $dvPago ? $dpPago : $dvPago;
}

$dataCorreta = null;
if ($dataFimAssinatura) {
    $dataCorreta = $dataFimAssinatura;
    echo "  📅 data_fim da assinatura approved: {$dataFimAssinatura}\n";
} elseif ($dataBaseCalc) {
    if ($cicloMeses > 0) {
        $dataCorreta = (clone $dataBaseCalc)->modify("+{$cicloMeses} months")->format('Y-m-d');
    } else {
        $dataCorreta = (clone $dataBaseCalc)->modify("+{$duracaoDias} days")->format('Y-m-d');
    }
    echo "  📅 Calculada via último pago ({$dataBaseCalc->format('Y-m-d')} + {$cicloMeses}m/{$duracaoDias}d): {$dataCorreta}\n";
}

echo "\n  ┌──────────────────────────────────────────\n";
echo "  │ proxima_data_vencimento ATUAL : " . ($mat['proxima_data_vencimento'] ?? 'NULL') . "\n";
echo "  │ data_fim assinatura approved  : " . ($dataFimAssinatura ?? 'N/A') . "\n";
echo "  │ Parcela pendente              : " . ($parcelaPendente ? "#{$parcelaPendente['id']} venc={$parcelaPendente['data_vencimento']}" : 'Nenhuma') . "\n";
echo "  │ DATA CORRETA calculada        : " . ($dataCorreta ?? '⚠️ Não foi possível calcular') . "\n";
echo "  └──────────────────────────────────────────\n";

if ($mat['proxima_data_vencimento'] === $dataCorreta) {
    echo "\n  ✅ proxima_data_vencimento já está correto ({$dataCorreta}). Nenhuma correção necessária.\n";
} else {
    echo "\n  ⚠️  DIVERGÊNCIA: proxima_data_vencimento={$mat['proxima_data_vencimento']} mas deveria ser {$dataCorreta}\n";

    // Também verificar parcela pendente com data errada
    if ($parcelaPendente && $dataCorreta && $parcelaPendente['data_vencimento'] !== $dataCorreta) {
        echo "  ⚠️  Parcela #{$parcelaPendente['id']} tem data_vencimento={$parcelaPendente['data_vencimento']} errada (deveria ser {$dataCorreta})\n";
    }
}

// ============================================================
// 6. APLICAR CORREÇÃO (se --corrigir foi passado)
// ============================================================
if ($CORRIGIR && $dataCorreta && $mat['proxima_data_vencimento'] !== $dataCorreta) {
    echo "\n6. APLICANDO CORREÇÃO\n";
    echo str_repeat("-", 80) . "\n";

    $db->beginTransaction();
    try {
        // 6.1 Corrigir proxima_data_vencimento e data_vencimento na matrícula
        $stmtFix = $db->prepare("
            UPDATE matriculas
            SET proxima_data_vencimento = ?,
                data_vencimento = ?,
                updated_at = NOW()
            WHERE id = 214
        ");
        $stmtFix->execute([$dataCorreta, $dataCorreta]);
        echo "  ✅ matriculas #214: proxima_data_vencimento + data_vencimento → {$dataCorreta}\n";

        // 6.2 Corrigir parcela pendente com data errada
        if ($parcelaPendente && $parcelaPendente['data_vencimento'] !== $dataCorreta) {
            $stmtFixPag = $db->prepare("
                UPDATE pagamentos_plano
                SET data_vencimento = ?, updated_at = NOW()
                WHERE id = ? AND status_pagamento_id IN (1, 3)
            ");
            $stmtFixPag->execute([$dataCorreta, $parcelaPendente['id']]);
            if ($stmtFixPag->rowCount() > 0) {
                echo "  ✅ pagamentos_plano #{$parcelaPendente['id']}: data_vencimento → {$dataCorreta}\n";
            }
        }

        $db->commit();
        echo "\n  ✅ Correção aplicada com sucesso!\n";
    } catch (\Exception $e) {
        $db->rollBack();
        echo "  ❌ ERRO: " . $e->getMessage() . "\n";
    }
} elseif ($CORRIGIR) {
    echo "\n6. Nenhuma correção necessária.\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "Fim do diagnóstico.\n";
