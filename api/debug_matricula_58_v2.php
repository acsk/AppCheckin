<?php
/**
 * Debug Matrícula #58 - Investigação completa
 * 
 * Problema: No app a próxima cobrança está correta (22/04), 
 * mas na matrícula está errada (19/03).
 * 
 * Execução:
 * docker exec appcheckin_php php /var/www/html/debug_matricula_58_v2.php
 */

require_once __DIR__ . '/vendor/autoload.php';

$pdo = require __DIR__ . '/config/database.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$matriculaId = 58;

echo "=" . str_repeat("=", 79) . "\n";
echo " DEBUG MATRÍCULA #{$matriculaId}\n";
echo " " . date('Y-m-d H:i:s') . "\n";
echo "=" . str_repeat("=", 79) . "\n\n";

// =========================================================================
// 1. DADOS DA MATRÍCULA
// =========================================================================
echo "📋 1. DADOS DA MATRÍCULA\n";
echo str_repeat("-", 60) . "\n";

$stmt = $pdo->prepare("
    SELECT m.*, 
           sm.codigo as status_codigo, sm.nome as status_nome,
           p.nome as plano_nome, p.valor as plano_valor, p.duracao_dias,
           pc.meses as ciclo_meses, pc.valor as ciclo_valor,
           af.nome as freq_nome,
           u.nome as aluno_nome, u.email as aluno_email, a.usuario_id
    FROM matriculas m
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    INNER JOIN planos p ON p.id = m.plano_id
    LEFT JOIN plano_ciclos pc ON pc.id = m.plano_ciclo_id
    LEFT JOIN assinatura_frequencias af ON af.id = pc.assinatura_frequencia_id
    INNER JOIN alunos a ON a.id = m.aluno_id
    INNER JOIN usuarios u ON u.id = a.usuario_id
    WHERE m.id = ?
");
$stmt->execute([$matriculaId]);
$matricula = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$matricula) {
    echo "❌ Matrícula #{$matriculaId} não encontrada!\n";
    exit(1);
}

echo "  Aluno: {$matricula['aluno_nome']} (aluno_id={$matricula['aluno_id']}, usuario_id={$matricula['usuario_id']})\n";
echo "  Plano: {$matricula['plano_nome']} (plano_id={$matricula['plano_id']})\n";
echo "  Ciclo: {$matricula['ciclo_meses']} meses (plano_ciclo_id={$matricula['plano_ciclo_id']})\n";
echo "  Frequência: " . ($matricula['freq_nome'] ?? 'N/A') . "\n";
echo "  Valor: R\${$matricula['valor']}\n";
echo "  Tipo Cobrança: {$matricula['tipo_cobranca']}\n";
echo "  Status: {$matricula['status_nome']} (id={$matricula['status_id']}, codigo={$matricula['status_codigo']})\n";
echo "  Data Matrícula: {$matricula['data_matricula']}\n";
echo "  Data Início: {$matricula['data_inicio']}\n";
echo "  Data Vencimento: {$matricula['data_vencimento']}\n";
echo "  Dia Vencimento: {$matricula['dia_vencimento']}\n";
echo "  Período Teste: {$matricula['periodo_teste']}\n";
echo "  Data Início Cobrança: {$matricula['data_inicio_cobranca']}\n";
echo "  ⚠️  proxima_data_vencimento: {$matricula['proxima_data_vencimento']}\n";
echo "  Plano Anterior ID: {$matricula['plano_anterior_id']}\n";
echo "  Motivo ID: {$matricula['motivo_id']}\n";
echo "  Criado Por: {$matricula['criado_por']}\n";
echo "  Created At: {$matricula['created_at']}\n";
echo "  Updated At: {$matricula['updated_at']}\n";

// Campos de cancelamento
$cancelFields = ['data_cancelamento', 'cancelado_por', 'motivo_cancelamento'];
foreach ($cancelFields as $f) {
    if (!empty($matricula[$f])) {
        echo "  " . ucfirst(str_replace('_', ' ', $f)) . ": {$matricula[$f]}\n";
    }
}

// =========================================================================
// 2. PAGAMENTOS DA MATRÍCULA
// =========================================================================
echo "\n📋 2. PAGAMENTOS DA MATRÍCULA\n";
echo str_repeat("-", 60) . "\n";

$stmt = $pdo->prepare("
    SELECT pp.*, 
           sp.nome as status_nome,
           fp.nome as forma_pgto_nome,
           tb.nome as tipo_baixa_nome
    FROM pagamentos_plano pp
    LEFT JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
    LEFT JOIN formas_pagamento fp ON fp.id = pp.forma_pagamento_id
    LEFT JOIN tipos_baixa tb ON tb.id = pp.tipo_baixa_id
    WHERE pp.matricula_id = ?
    ORDER BY pp.data_vencimento ASC, pp.id ASC
");
$stmt->execute([$matriculaId]);
$pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($pagamentos)) {
    echo "  ❌ NENHUM pagamento encontrado para esta matrícula!\n";
} else {
    echo "  Total de pagamentos: " . count($pagamentos) . "\n\n";
    foreach ($pagamentos as $i => $pgto) {
        $num = $i + 1;
        echo "  [{$num}] Pagamento #{$pgto['id']}\n";
        echo "      Valor: R\${$pgto['valor']} | Desconto: R\${$pgto['desconto']}\n";
        echo "      Vencimento: {$pgto['data_vencimento']}\n";
        echo "      Pagamento: " . ($pgto['data_pagamento'] ?: 'NÃO PAGO') . "\n";
        echo "      Status: {$pgto['status_nome']} (id={$pgto['status_pagamento_id']})\n";
        echo "      Forma: " . ($pgto['forma_pgto_nome'] ?: 'N/A') . " (id=" . ($pgto['forma_pagamento_id'] ?: 'null') . ")\n";
        echo "      Tipo Baixa: " . ($pgto['tipo_baixa_nome'] ?: 'N/A') . " (id=" . ($pgto['tipo_baixa_id'] ?: 'null') . ")\n";
        echo "      Obs: " . ($pgto['observacoes'] ?: 'N/A') . "\n";
        echo "      Criado: {$pgto['created_at']} | Atualizado: {$pgto['updated_at']}\n";
        echo "      Baixado Por: " . ($pgto['baixado_por'] ?: 'N/A') . "\n";
        if ($pgto['credito_id']) {
            echo "      Crédito: id={$pgto['credito_id']} valor={$pgto['credito_aplicado']}\n";
        }
        echo "\n";
    }
}

// =========================================================================
// 3. ANÁLISE DO PRÓXIMO PAGAMENTO
// =========================================================================
echo "📋 3. ANÁLISE DO PRÓXIMO PAGAMENTO\n";
echo str_repeat("-", 60) . "\n";

$pendente = null;
$ultimoPago = null;
foreach ($pagamentos as $pgto) {
    if (in_array($pgto['status_pagamento_id'], [1, 3])) {
        if (!$pendente) $pendente = $pgto;
    }
    if ($pgto['status_pagamento_id'] == 2) {
        $ultimoPago = $pgto;
    }
}

if ($pendente) {
    echo "  ✅ Pagamento pendente encontrado: #{$pendente['id']} vencimento={$pendente['data_vencimento']}\n";
} else {
    echo "  ⚠️  NENHUM pagamento pendente/atrasado encontrado!\n";
    if ($ultimoPago) {
        echo "  Último pagamento pago: #{$ultimoPago['id']} vencimento={$ultimoPago['data_vencimento']} pago em {$ultimoPago['data_pagamento']}\n";
        echo "  ❌ O próximo pagamento DEVERIA ter sido gerado após a baixa, mas NÃO FOI!\n";
        
        $meses = (int)($matricula['ciclo_meses'] ?? 0);
        $diaVencimento = (int)($matricula['dia_vencimento'] ?? 0);
        
        if ($diaVencimento > 0 && $meses > 0) {
            $dataRef = new DateTime($ultimoPago['data_vencimento']);
            $dataRef->modify("+{$meses} month");
            $mesAno = $dataRef->format('Y-m');
            $ultimoDiaMes = (int)(new DateTime($mesAno . '-01'))->format('t');
            $diaReal = min($diaVencimento, $ultimoDiaMes);
            $proximoVenc = $mesAno . '-' . str_pad($diaReal, 2, '0', STR_PAD_LEFT);
            echo "  📌 Próximo vencimento CALCULADO: {$proximoVenc} (dia_vencimento={$diaVencimento}, +{$meses} meses)\n";
        } elseif ($meses > 0) {
            $dataRef = new DateTime($ultimoPago['data_vencimento']);
            $dataRef->modify("+{$meses} month");
            echo "  📌 Próximo vencimento CALCULADO: {$dataRef->format('Y-m-d')} (+{$meses} meses)\n";
        }
    } else {
        echo "  ❌ Nenhum pagamento pago encontrado!\n";
    }
}

// =========================================================================
// 4. ASSINATURA DA MATRÍCULA
// =========================================================================
echo "\n📋 4. ASSINATURA DA MATRÍCULA\n";
echo str_repeat("-", 60) . "\n";

$stmt = $pdo->prepare("
    SELECT a.*, ast.codigo as status_codigo, ast.nome as status_nome
    FROM assinaturas a
    LEFT JOIN assinatura_status ast ON ast.id = a.status_id
    WHERE a.matricula_id = ?
    ORDER BY a.id DESC
");
$stmt->execute([$matriculaId]);
$assinaturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($assinaturas)) {
    echo "  Nenhuma assinatura encontrada para esta matrícula.\n";
} else {
    foreach ($assinaturas as $ass) {
        echo "  Assinatura #{$ass['id']}\n";
        echo "    Status: " . ($ass['status_nome'] ?? 'N/A') . " (codigo=" . ($ass['status_codigo'] ?? 'N/A') . ")\n";
        echo "    Gateway Status: " . ($ass['status_gateway'] ?? 'N/A') . "\n";
        echo "    Gateway Assinatura ID: " . ($ass['gateway_assinatura_id'] ?? 'N/A') . "\n";
        echo "    External Reference: " . ($ass['external_reference'] ?? 'N/A') . "\n";
        echo "    Última Cobrança: " . ($ass['ultima_cobranca'] ?? 'N/A') . "\n";
        echo "    Criado em: " . ($ass['criado_em'] ?? 'N/A') . "\n";
        echo "    Atualizado em: " . ($ass['atualizado_em'] ?? 'N/A') . "\n\n";
    }
}

// =========================================================================
// 5. OUTRAS MATRÍCULAS DO MESMO ALUNO
// =========================================================================
echo "📋 5. TODAS AS MATRÍCULAS DO ALUNO (aluno_id={$matricula['aluno_id']})\n";
echo str_repeat("-", 60) . "\n";

$stmt = $pdo->prepare("
    SELECT m.id, m.plano_id, p.nome as plano_nome, m.status_id, sm.codigo as status_codigo, sm.nome as status_nome,
           m.data_inicio, m.data_vencimento, m.proxima_data_vencimento, m.tipo_cobranca,
           m.valor, m.created_at, m.updated_at
    FROM matriculas m
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    INNER JOIN planos p ON p.id = m.plano_id
    WHERE m.aluno_id = ? AND m.tenant_id = ?
    ORDER BY m.id ASC
");
$stmt->execute([$matricula['aluno_id'], $matricula['tenant_id']]);
$outrasMatriculas = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($outrasMatriculas as $om) {
    $marker = $om['id'] == $matriculaId ? ' ← ESTA' : '';
    echo "  Matrícula #{$om['id']}{$marker}\n";
    echo "    Plano: {$om['plano_nome']} (id={$om['plano_id']}) | Valor: R\${$om['valor']}\n";
    echo "    Status: {$om['status_nome']} ({$om['status_codigo']})\n";
    echo "    Data Início: {$om['data_inicio']}\n";
    echo "    Data Vencimento: {$om['data_vencimento']}\n";
    echo "    Próxima Data Vencimento: " . ($om['proxima_data_vencimento'] ?: 'NULL') . "\n";
    echo "    Tipo Cobrança: {$om['tipo_cobranca']}\n";
    echo "    Created: {$om['created_at']} | Updated: {$om['updated_at']}\n\n";
}

// =========================================================================
// 6. SIMULAÇÃO DO getPlanoUsuario (o que o Mobile App mostraria)
// =========================================================================
echo "📋 6. SIMULAÇÃO DO getPlanoUsuario (Mobile App)\n";
echo str_repeat("-", 60) . "\n";

$usuarioId = $matricula['usuario_id'];
$tenantId = $matricula['tenant_id'];

$stmt = $pdo->prepare("
    SELECT p.id as plano_id, p.nome as plano_nome,
           m.id as matricula_id, m.data_inicio, m.data_vencimento as data_fim, 
           m.proxima_data_vencimento, m.plano_ciclo_id,
           sm.id as status_id, sm.codigo as vinculo_status, sm.nome as status_nome
    FROM matriculas m
    INNER JOIN planos p ON m.plano_id = p.id
    INNER JOIN alunos a ON a.id = m.aluno_id
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    WHERE a.usuario_id = :user_id 
    AND m.tenant_id = :tenant_id
    AND sm.codigo IN ('ativa', 'pendente', 'vencida')
    ORDER BY FIELD(sm.codigo, 'ativa', 'pendente', 'vencida'), m.data_vencimento DESC
    LIMIT 1
");
$stmt->execute(['user_id' => $usuarioId, 'tenant_id' => $tenantId]);
$mobileResult = $stmt->fetch(PDO::FETCH_ASSOC);

if ($mobileResult) {
    $dataFim = $mobileResult['proxima_data_vencimento'] ?? $mobileResult['data_fim'] ?? null;
    echo "  App mostraria matrícula #{$mobileResult['matricula_id']}:\n";
    echo "    Plano: {$mobileResult['plano_nome']}\n";
    echo "    Status: {$mobileResult['status_nome']} ({$mobileResult['vinculo_status']})\n";
    echo "    proxima_data_vencimento: " . ($mobileResult['proxima_data_vencimento'] ?: 'NULL') . "\n";
    echo "    data_fim (data_vencimento): " . ($mobileResult['data_fim'] ?: 'NULL') . "\n";
    echo "    ➡️  Data mostrada no app (dataFim): {$dataFim}\n";
    
    if ($mobileResult['matricula_id'] != $matriculaId) {
        echo "\n  ⚠️  O APP MOSTRA OUTRA MATRÍCULA! (#{$mobileResult['matricula_id']} em vez de #58)\n";
        echo "  Matrícula #58 está '{$matricula['status_codigo']}' → NÃO aparece no filtro do app.\n";
    }
} else {
    echo "  ❌ Nenhuma matrícula ativa/pendente/vencida encontrada para o aluno!\n";
    echo "  O app não mostraria nenhum plano.\n";
}

// =========================================================================
// 7. PAGAMENTOS MERCADO PAGO
// =========================================================================
echo "\n📋 7. PAGAMENTOS MERCADO PAGO\n";
echo str_repeat("-", 60) . "\n";

try {
    $stmt = $pdo->prepare("
        SELECT * FROM pagamentos_mercadopago 
        WHERE matricula_id = ? 
        ORDER BY id DESC 
        LIMIT 10
    ");
    $stmt->execute([$matriculaId]);
    $pagamentosMP = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($pagamentosMP)) {
        echo "  Nenhum registro em pagamentos_mercadopago.\n";
    } else {
        foreach ($pagamentosMP as $pmp) {
            echo "  MP #{$pmp['id']}: payment_id=" . ($pmp['payment_id'] ?? 'N/A') . "\n";
            echo "    Status: " . ($pmp['status'] ?? 'N/A') . "\n";
            echo "    Valor: R\$" . ($pmp['transaction_amount'] ?? 'N/A') . "\n";
            echo "    Data Aprovação: " . ($pmp['date_approved'] ?? 'N/A') . "\n";
            echo "    Criado: " . ($pmp['created_at'] ?? 'N/A') . "\n\n";
        }
    }
} catch (\Exception $e) {
    echo "  (tabela não encontrada)\n";
}

// =========================================================================
// 8. WEBHOOK PAYLOADS RELACIONADOS
// =========================================================================
echo "📋 8. WEBHOOKS RELACIONADOS\n";
echo str_repeat("-", 60) . "\n";

try {
    $stmt = $pdo->prepare("
        SELECT id, tipo, external_reference, status, processado, created_at
        FROM webhook_payloads 
        WHERE external_reference LIKE ?
        ORDER BY id DESC 
        LIMIT 10
    ");
    $stmt->execute(["%mat_{$matriculaId}_%"]);
    $webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($webhooks)) {
        echo "  Nenhum webhook com referência à matrícula #{$matriculaId}.\n";
    } else {
        foreach ($webhooks as $wh) {
            echo "  Webhook #{$wh['id']}: tipo={$wh['tipo']} ref={$wh['external_reference']}\n";
            echo "    Status: {$wh['status']} | Processado: {$wh['processado']} | Data: {$wh['created_at']}\n";
        }
    }
} catch (\Exception $e) {
    echo "  (tabela não acessível)\n";
}

// =========================================================================
// 9. DIAGNÓSTICO FINAL
// =========================================================================
echo "\n" . str_repeat("=", 80) . "\n";
echo "🔍 DIAGNÓSTICO\n";
echo str_repeat("=", 80) . "\n\n";

$problemas = [];

$hoje = date('Y-m-d');
if ($matricula['proxima_data_vencimento'] && $matricula['proxima_data_vencimento'] < $hoje) {
    $problemas[] = "proxima_data_vencimento ({$matricula['proxima_data_vencimento']}) está no PASSADO (hoje={$hoje})";
}

if ($ultimoPago && !$pendente) {
    $problemas[] = "Último pagamento #{$ultimoPago['id']} está PAGO mas NENHUM próximo pagamento foi gerado";
    $problemas[] = "O fluxo de polling (AssinaturaController/atualizar_pagamentos_mp) NÃO gera próximo pagamento";
}

if ($matricula['status_codigo'] === 'cancelada') {
    $problemas[] = "Matrícula está CANCELADA (status_id=3) — NÃO aparece no app mobile!";
    $problemas[] = "Provável causa: job atualizar_status_matriculas.php marcou como cancelada por inadimplência (5+ dias)";
}

if ($mobileResult && $mobileResult['matricula_id'] != $matriculaId) {
    $problemas[] = "App mostra matrícula #{$mobileResult['matricula_id']} em vez da #58 (está cancelada, fora do filtro)";
}

if (empty($problemas)) {
    echo "  ✅ Nenhum problema evidente.\n";
} else {
    echo "  Problemas encontrados:\n\n";
    foreach ($problemas as $i => $p) {
        echo "  " . ($i + 1) . ". {$p}\n";
    }
}

echo "\n\n📌 AÇÃO SUGERIDA PARA CORREÇÃO:\n";
echo str_repeat("-", 60) . "\n";

if ($ultimoPago && !$pendente) {
    $meses = max(1, (int)($matricula['ciclo_meses'] ?? 1));
    $diaVenc = (int)($matricula['dia_vencimento'] ?? 0);
    
    $dataRef = new DateTime($ultimoPago['data_vencimento']);
    $dataRef->modify("+{$meses} month");
    
    if ($diaVenc > 0) {
        $mesAno = $dataRef->format('Y-m');
        $ultimoDiaMes = (int)(new DateTime($mesAno . '-01'))->format('t');
        $diaReal = min($diaVenc, $ultimoDiaMes);
        $proximoVenc = $mesAno . '-' . str_pad($diaReal, 2, '0', STR_PAD_LEFT);
    } else {
        $proximoVenc = $dataRef->format('Y-m-d');
    }
    
    echo "\n  1. Atualizar matrícula:\n";
    echo "  UPDATE matriculas SET\n";
    echo "    status_id = 1,\n";
    echo "    proxima_data_vencimento = '{$proximoVenc}',\n";
    echo "    updated_at = NOW()\n";
    echo "  WHERE id = {$matriculaId};\n\n";
    
    echo "  2. Gerar próximo pagamento:\n";
    echo "  INSERT INTO pagamentos_plano\n";
    echo "    (tenant_id, matricula_id, aluno_id, plano_id, valor, desconto, data_vencimento,\n";
    echo "     status_pagamento_id, observacoes, criado_por, created_at, updated_at)\n";
    echo "  VALUES\n";
    echo "    ({$matricula['tenant_id']}, {$matriculaId}, {$matricula['aluno_id']}, {$matricula['plano_id']},\n";
    echo "     {$matricula['valor']}, 0.00, '{$proximoVenc}', 1,\n";
    echo "     'Pagamento gerado automaticamente após confirmação', {$matricula['criado_por']}, NOW(), NOW());\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "Fim do debug.\n";
