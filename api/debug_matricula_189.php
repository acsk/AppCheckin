<?php
/**
 * Debug Matrícula #189
 * Investigação: Mobile reprocessou e gerou/atualizou assinatura não processada pelo MP.
 * Painel: vencida | Mobile: ativa | Assinatura: approved
 * Verificar divergência de status entre matrícula, assinatura e pagamentos.
 */

require_once __DIR__ . '/vendor/autoload.php';

$db = require __DIR__ . '/config/database.php';
$matriculaId = 189;

date_default_timezone_set('America/Sao_Paulo');

echo "====== DEBUG MATRÍCULA #189 ======\n";
echo "Data: " . date('Y-m-d H:i:s') . "\n\n";

// ============================================================
// 1. DADOS DA MATRÍCULA
// ============================================================
echo "1. DADOS DA MATRÍCULA\n";
echo str_repeat("-", 80) . "\n";
$sql = "
    SELECT 
        m.*,
        sm.codigo as status_codigo,
        sm.nome as status_nome,
        a.nome as aluno_nome,
        a.id as aluno_id_real,
        u.id as usuario_id,
        u.email as aluno_email,
        p.nome as plano_nome,
        p.modalidade_id,
        p.duracao_dias,
        p.valor as plano_valor,
        mod_t.nome as modalidade_nome,
        pc.meses as ciclo_meses,
        pc.valor as ciclo_valor,
        mm.codigo as motivo_codigo
    FROM matriculas m
    INNER JOIN alunos a ON m.aluno_id = a.id
    INNER JOIN usuarios u ON a.usuario_id = u.id
    INNER JOIN planos p ON m.plano_id = p.id
    INNER JOIN status_matricula sm ON m.status_id = sm.id
    LEFT JOIN modalidades mod_t ON p.modalidade_id = mod_t.id
    LEFT JOIN plano_ciclos pc ON m.plano_ciclo_id = pc.id
    LEFT JOIN motivo_matricula mm ON m.motivo_id = mm.id
    WHERE m.id = ?
";
$stmt = $db->prepare($sql);
$stmt->execute([$matriculaId]);
$matricula = $stmt->fetch(\PDO::FETCH_ASSOC);

if (!$matricula) {
    echo "Matricula nao encontrada!\n";
    exit(1);
}

$alunoId = $matricula['aluno_id'];
$tenantId = $matricula['tenant_id'];

echo "ID: {$matricula['id']}\n";
echo "Aluno: {$matricula['aluno_nome']} (aluno_id={$alunoId}, usuario_id={$matricula['usuario_id']}, email={$matricula['aluno_email']})\n";
echo "Tenant: {$tenantId}\n";
echo "Plano: {$matricula['plano_nome']} (ID: {$matricula['plano_id']})\n";
echo "Modalidade: " . ($matricula['modalidade_nome'] ?? '-') . " (ID: " . ($matricula['modalidade_id'] ?? '-') . ")\n";
echo "Plano Ciclo ID: " . ($matricula['plano_ciclo_id'] ?? 'null') . "\n";
if ($matricula['ciclo_meses']) {
    echo "Ciclo: {$matricula['ciclo_meses']} mes(es), R$ " . number_format((float)($matricula['ciclo_valor'] ?? 0), 2, ',', '.') . "\n";
}
echo "Tipo Cobranca: " . ($matricula['tipo_cobranca'] ?? '-') . "\n";
echo "Status: {$matricula['status_nome']} ({$matricula['status_codigo']}) - ID {$matricula['status_id']}\n";
echo "Motivo: " . ($matricula['motivo_codigo'] ?? '-') . " (ID: " . ($matricula['motivo_id'] ?? '-') . ")\n";
echo "Data Matricula: {$matricula['data_matricula']}\n";
echo "Data Inicio: {$matricula['data_inicio']}\n";
echo "Data Vencimento: {$matricula['data_vencimento']}\n";
echo "Proxima Data Vencimento: " . ($matricula['proxima_data_vencimento'] ?? '-') . "\n";
echo "Dia Vencimento: " . ($matricula['dia_vencimento'] ?? '-') . "\n";
echo "Valor: R$ " . number_format((float)$matricula['valor'], 2, ',', '.') . "\n";
echo "Duracao dias (plano): {$matricula['duracao_dias']}\n";
echo "Criada em: {$matricula['created_at']}\n";
echo "Atualizada em: {$matricula['updated_at']}\n";

if (!empty($matricula['cancelado_por'])) {
    echo "CANCELADA por: {$matricula['cancelado_por']}\n";
    echo "  Data cancelamento: " . ($matricula['data_cancelamento'] ?? '-') . "\n";
    echo "  Motivo cancelamento: " . ($matricula['motivo_cancelamento'] ?? '-') . "\n";
}

// Verificar se vencimento já passou
$hoje = new \DateTime();
$venc = new \DateTime($matricula['data_vencimento']);
$diff = $hoje->diff($venc);
$diasParaVencer = $hoje > $venc ? -$diff->days : $diff->days;
echo "\n>>> HOJE: " . $hoje->format('Y-m-d') . " | Vencimento: {$matricula['data_vencimento']} | ";
if ($diasParaVencer < 0) {
    echo "VENCIDO há " . abs($diasParaVencer) . " dia(s)\n";
} else {
    echo "Faltam {$diasParaVencer} dia(s) para vencer\n";
}
echo "\n";

// ============================================================
// 2. ASSINATURAS DA MATRÍCULA (FOCO PRINCIPAL)
// ============================================================
echo "2. ASSINATURAS DA MATRÍCULA\n";
echo str_repeat("-", 80) . "\n";
try {
    $stmt = $db->prepare("
        SELECT a.*, 
               ast.codigo as status_nome_local, 
               ast.nome as status_label
        FROM assinaturas a
        LEFT JOIN assinatura_status ast ON ast.id = a.status_id
        WHERE a.matricula_id = ?
        ORDER BY a.id DESC
    ");
    $stmt->execute([$matriculaId]);
    $assinaturas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    echo "Total de assinaturas vinculadas: " . count($assinaturas) . "\n\n";
    foreach ($assinaturas as $ass) {
        echo "  Assinatura ID: {$ass['id']}\n";
        echo "  Status Local: " . ($ass['status_label'] ?? '?') . " ({$ass['status_nome_local']}) - status_id={$ass['status_id']}\n";
        echo "  Status Gateway (MP): " . ($ass['status_gateway'] ?? 'NULL') . "\n";
        echo "  Gateway Assinatura ID: " . ($ass['gateway_assinatura_id'] ?? 'NULL') . "\n";
        echo "  Gateway Preference ID: " . ($ass['gateway_preference_id'] ?? 'NULL') . "\n";
        echo "  External Reference: " . ($ass['external_reference'] ?? 'NULL') . "\n";
        echo "  Payment URL: " . ($ass['payment_url'] ?? 'NULL') . "\n";
        echo "  Gateway Cliente ID: " . ($ass['gateway_cliente_id'] ?? 'NULL') . "\n";
        echo "  Valor: R$ " . number_format((float)$ass['valor'], 2, ',', '.') . "\n";
        echo "  Tipo Cobranca: " . ($ass['tipo_cobranca'] ?? '-') . "\n";
        echo "  Frequencia ID: " . ($ass['frequencia_id'] ?? '-') . "\n";
        echo "  Data Inicio: " . ($ass['data_inicio'] ?? '-') . "\n";
        echo "  Data Fim: " . ($ass['data_fim'] ?? 'NULL') . "\n";
        echo "  Proxima Cobranca: " . ($ass['proxima_cobranca'] ?? 'NULL') . "\n";
        echo "  Ultima Cobranca: " . ($ass['ultima_cobranca'] ?? 'NULL') . "\n";
        echo "  Dia Cobranca: " . ($ass['dia_cobranca'] ?? 'NULL') . "\n";
        echo "  Tentativas Cobranca: " . ($ass['tentativas_cobranca'] ?? '0') . "\n";
        echo "  Criada em: {$ass['criado_em']}\n";
        echo "  Atualizada em: {$ass['atualizado_em']}\n";

        // FLAG: divergência entre status_gateway e status local
        $flags = [];
        if ($ass['status_gateway'] === 'authorized' && $ass['status_id'] != 2) {
            $flags[] = "DIVERGÊNCIA: Gateway=authorized mas Status Local={$ass['status_nome_local']} (esperado: ativa/2)";
        }
        if ($ass['status_gateway'] === 'cancelled' && $ass['status_id'] != 4) {
            $flags[] = "DIVERGÊNCIA: Gateway=cancelled mas Status Local={$ass['status_nome_local']} (esperado: cancelada/4)";
        }
        if (empty($ass['gateway_assinatura_id'])) {
            $flags[] = "SEM GATEWAY_ASSINATURA_ID: MP pode não ter processado esta assinatura";
        }
        if (count($flags) > 0) {
            echo "  *** ANOMALIAS ***\n";
            foreach ($flags as $f) echo "    - {$f}\n";
        }
        echo "\n";
    }
} catch (\Throwable $e) {
    echo "Erro ao buscar assinaturas: " . $e->getMessage() . "\n\n";
}

// 2b. Assinaturas do ALUNO (outras matrículas)
echo "2b. TODAS ASSINATURAS DO ALUNO (aluno_id={$alunoId})\n";
try {
    $stmt = $db->prepare("
        SELECT a.*, 
               ast.codigo as status_nome_local, 
               ast.nome as status_label
        FROM assinaturas a
        LEFT JOIN assinatura_status ast ON ast.id = a.status_id
        WHERE a.aluno_id = ?
        ORDER BY a.id DESC
    ");
    $stmt->execute([$alunoId]);
    $todasAss = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    echo "Total: " . count($todasAss) . "\n\n";
    foreach ($todasAss as $ass) {
        $destaque = ($ass['matricula_id'] == $matriculaId) ? " <<< MAT #189" : "";
        echo "  Ass ID: {$ass['id']} | Mat ID: " . ($ass['matricula_id'] ?? 'NULL') . "{$destaque}\n";
        echo "    Status Local: " . ($ass['status_label'] ?? '?') . " | Gateway: " . ($ass['status_gateway'] ?? 'NULL') . "\n";
        echo "    Gateway Ass ID: " . ($ass['gateway_assinatura_id'] ?? 'NULL') . "\n";
        echo "    Valor: R$ " . number_format((float)$ass['valor'], 2, ',', '.') . "\n";
        echo "    Criada: {$ass['criado_em']} | Atualizada: {$ass['atualizado_em']}\n\n";
    }
} catch (\Throwable $e) {
    echo "Erro: " . $e->getMessage() . "\n\n";
}

// ============================================================
// 3. PARCELAS (PAGAMENTOS_PLANO)
// ============================================================
echo "3. PARCELAS (pagamentos_plano)\n";
echo str_repeat("-", 80) . "\n";
$sql = "
    SELECT 
        pp.*,
        sp.nome as status_pagamento,
        fp.nome as forma_pagamento,
        u_baixa.nome as baixado_por_nome,
        tb.nome as tipo_baixa_nome,
        u_criador.nome as criado_por_nome
    FROM pagamentos_plano pp
    LEFT JOIN status_pagamento sp ON pp.status_pagamento_id = sp.id
    LEFT JOIN formas_pagamento fp ON pp.forma_pagamento_id = fp.id
    LEFT JOIN usuarios u_baixa ON pp.baixado_por = u_baixa.id
    LEFT JOIN tipos_baixa tb ON pp.tipo_baixa_id = tb.id
    LEFT JOIN usuarios u_criador ON pp.criado_por = u_criador.id
    WHERE pp.matricula_id = ?
    ORDER BY pp.data_vencimento ASC, pp.id ASC
";
try {
    $stmt = $db->prepare($sql);
    $stmt->execute([$matriculaId]);
    $parcelas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    echo "Aviso: fallback simples: " . $e->getMessage() . "\n";
    $stmt = $db->prepare("SELECT * FROM pagamentos_plano WHERE matricula_id = ? ORDER BY data_vencimento ASC, id ASC");
    $stmt->execute([$matriculaId]);
    $parcelas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

echo "Total de parcelas: " . count($parcelas) . "\n\n";

$totalPago = 0;
$totalPendente = 0;
foreach ($parcelas as $idx => $p) {
    $num = $idx + 1;
    $statusPag = $p['status_pagamento'] ?? ('status_id=' . $p['status_pagamento_id']);
    $dataPag = $p['data_pagamento'] ?: 'Nao paga';

    echo "Parcela {$num} (ID: {$p['id']})\n";
    echo "  Valor: R$ " . number_format((float)$p['valor'], 2, ',', '.') . "\n";
    echo "  Vencimento: {$p['data_vencimento']}\n";
    echo "  Status: {$statusPag} (id={$p['status_pagamento_id']})\n";
    echo "  Data Pagamento: {$dataPag}\n";
    echo "  Forma Pagamento: " . ($p['forma_pagamento'] ?? ($p['forma_pagamento_id'] ?? '-')) . "\n";
    echo "  Tipo Baixa: " . ($p['tipo_baixa_nome'] ?? ($p['tipo_baixa_id'] ?? '-')) . "\n";
    echo "  Baixado por: " . ($p['baixado_por_nome'] ?? ($p['baixado_por'] ?? '-')) . "\n";
    echo "  Criado por: " . ($p['criado_por_nome'] ?? ($p['criado_por'] ?? '-')) . "\n";
    if ($p['observacoes']) echo "  Obs: {$p['observacoes']}\n";
    echo "  Criada em: {$p['created_at']} | Atualizada em: {$p['updated_at']}\n";

    if ($p['status_pagamento_id'] == 2) $totalPago += (float)$p['valor'];
    if (in_array($p['status_pagamento_id'], [1, 3])) $totalPendente += (float)$p['valor'];

    // Anomalias
    $flags = [];
    if ($p['data_pagamento'] && $p['status_pagamento_id'] != 2) {
        $flags[] = "data_pagamento preenchida mas status NÃO é Pago (id={$p['status_pagamento_id']})";
    }
    if (!$p['data_pagamento'] && $p['status_pagamento_id'] == 2) {
        $flags[] = "Status=Pago mas data_pagamento é NULL";
    }
    if ($p['data_vencimento'] < date('Y-m-d') && in_array($p['status_pagamento_id'], [1])) {
        $flags[] = "VENCIDA e ainda com status Aguardando (deveria ser Atrasado)";
    }
    if (count($flags) > 0) {
        echo "  *** ANOMALIAS ***\n";
        foreach ($flags as $f) echo "    - {$f}\n";
    }
    echo "\n";
}

echo "RESUMO PARCELAS: Pago=R$ " . number_format($totalPago, 2, ',', '.') . " | Pendente=R$ " . number_format($totalPendente, 2, ',', '.') . "\n\n";

// ============================================================
// 4. PAGAMENTOS MERCADOPAGO
// ============================================================
echo "4. PAGAMENTOS MERCADOPAGO (matricula_id={$matriculaId})\n";
echo str_repeat("-", 80) . "\n";
try {
    $stmt = $db->prepare("SELECT * FROM pagamentos_mercadopago WHERE matricula_id = ? ORDER BY id DESC");
    $stmt->execute([$matriculaId]);
    $pagsMp = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    echo "Total: " . count($pagsMp) . "\n\n";
    foreach ($pagsMp as $pm) {
        echo "  PM ID: {$pm['id']} | Payment ID: " . ($pm['payment_id'] ?? '-') . "\n";
        echo "    Status: " . ($pm['status'] ?? '-') . " | Status ID: " . ($pm['status_id'] ?? '-') . "\n";
        echo "    Valor: R$ " . number_format((float)($pm['valor'] ?? 0), 2, ',', '.') . "\n";
        echo "    External Ref: " . ($pm['external_reference'] ?? '-') . "\n";
        echo "    Data Criação: " . ($pm['date_created'] ?? '-') . "\n";
        echo "    Método: " . ($pm['metodo_pagamento'] ?? '-') . "\n\n";
    }
} catch (\Throwable $e) {
    echo "Erro: " . $e->getMessage() . "\n\n";
}

// 4b. MP por external_reference MAT-189-*
echo "4b. PAGAMENTOS MP por external_reference LIKE 'MAT-189%'\n";
try {
    $stmt = $db->prepare("SELECT * FROM pagamentos_mercadopago WHERE external_reference LIKE ? ORDER BY id DESC");
    $stmt->execute(["MAT-{$matriculaId}%"]);
    $pagsMpRef = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    echo "Total: " . count($pagsMpRef) . "\n\n";
    foreach ($pagsMpRef as $pm) {
        echo "  PM ID: {$pm['id']} | Payment ID: " . ($pm['payment_id'] ?? '-') . "\n";
        echo "    Status: " . ($pm['status'] ?? '-') . " | External Ref: " . ($pm['external_reference'] ?? '-') . "\n";
        echo "    Valor: R$ " . number_format((float)($pm['valor'] ?? 0), 2, ',', '.') . "\n";
        echo "    Data: " . ($pm['date_created'] ?? '-') . "\n\n";
    }
} catch (\Throwable $e) {
    echo "Erro: " . $e->getMessage() . "\n\n";
}

// 4c. Todos pagamentos MP do aluno
echo "4c. TODOS PAGAMENTOS MP DO ALUNO (aluno_id={$alunoId})\n";
try {
    $stmt = $db->prepare("SELECT * FROM pagamentos_mercadopago WHERE aluno_id = ? ORDER BY id DESC LIMIT 20");
    $stmt->execute([$alunoId]);
    $pagsMpAluno = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    echo "Total (limite 20): " . count($pagsMpAluno) . "\n\n";
    foreach ($pagsMpAluno as $pm) {
        echo "  PM ID: {$pm['id']} | Payment: " . ($pm['payment_id'] ?? '-') . " | Mat: " . ($pm['matricula_id'] ?? '-') . "\n";
        echo "    Status: " . ($pm['status'] ?? '-') . " | Valor: R$ " . number_format((float)($pm['valor'] ?? 0), 2, ',', '.') . "\n";
        echo "    Ref: " . ($pm['external_reference'] ?? '-') . " | Data: " . ($pm['date_created'] ?? '-') . "\n\n";
    }
} catch (\Throwable $e) {
    echo "Erro: " . $e->getMessage() . "\n\n";
}

// ============================================================
// 5. WEBHOOKS RELACIONADOS
// ============================================================
echo "5. WEBHOOKS\n";
echo str_repeat("-", 80) . "\n";
echo "5a. Webhooks por external_reference LIKE 'MAT-189%':\n";
try {
    $stmt = $db->prepare("SELECT * FROM webhook_payloads_mercadopago WHERE external_reference LIKE ? ORDER BY id DESC LIMIT 20");
    $stmt->execute(["MAT-{$matriculaId}%"]);
    $webhooks = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    echo "Total: " . count($webhooks) . "\n\n";
    foreach ($webhooks as $wh) {
        echo "  WH ID: {$wh['id']} | Payment ID: " . ($wh['payment_id'] ?? '-') . "\n";
        echo "    Status: " . ($wh['status'] ?? '-') . " | Tipo: " . ($wh['tipo'] ?? '-') . "\n";
        echo "    External Ref: " . ($wh['external_reference'] ?? '-') . "\n";
        echo "    Processado: " . ($wh['processado'] ?? '-') . " | Erro: " . ($wh['erro'] ?? '-') . "\n";
        echo "    Criado em: " . ($wh['created_at'] ?? '-') . "\n\n";
    }
} catch (\Throwable $e) {
    echo "Erro: " . $e->getMessage() . "\n\n";
}

// ============================================================
// 6. TODAS MATRÍCULAS DO ALUNO
// ============================================================
echo "6. TODAS MATRICULAS DO ALUNO (aluno_id={$alunoId})\n";
echo str_repeat("-", 80) . "\n";
$sql = "
    SELECT 
        m.id, m.plano_id, m.plano_ciclo_id, m.tipo_cobranca,
        m.data_matricula, m.data_inicio, m.data_vencimento, m.proxima_data_vencimento,
        m.valor, m.status_id, m.matricula_anterior_id,
        m.created_at, m.updated_at, m.cancelado_por, m.data_cancelamento, m.motivo_cancelamento,
        p.nome as plano_nome,
        sm.codigo as status_codigo, sm.nome as status_nome,
        mod_t.nome as modalidade_nome
    FROM matriculas m
    INNER JOIN planos p ON m.plano_id = p.id
    INNER JOIN status_matricula sm ON m.status_id = sm.id
    LEFT JOIN modalidades mod_t ON p.modalidade_id = mod_t.id
    WHERE m.aluno_id = ? AND m.tenant_id = ?
    ORDER BY m.id ASC
";
$stmt = $db->prepare($sql);
$stmt->execute([$alunoId, $tenantId]);
$todasMatriculas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

echo "Total: " . count($todasMatriculas) . "\n\n";
foreach ($todasMatriculas as $m) {
    $destaque = ($m['id'] == $matriculaId) ? " <<< INVESTIGADA" : "";
    echo "Matricula #{$m['id']}{$destaque}\n";
    echo "  Plano: {$m['plano_nome']} (ID: {$m['plano_id']})\n";
    echo "  Modalidade: " . ($m['modalidade_nome'] ?? '-') . "\n";
    echo "  Status: {$m['status_nome']} ({$m['status_codigo']})\n";
    echo "  Data Inicio: {$m['data_inicio']} | Vencimento: {$m['data_vencimento']}\n";
    echo "  Prox. Vencimento: " . ($m['proxima_data_vencimento'] ?? '-') . "\n";
    echo "  Valor: R$ " . number_format((float)$m['valor'], 2, ',', '.') . "\n";
    echo "  Criada: {$m['created_at']} | Atualizada: {$m['updated_at']}\n";
    if (!empty($m['cancelado_por'])) {
        echo "  CANCELADA: {$m['data_cancelamento']} - {$m['motivo_cancelamento']}\n";
    }

    // Contar parcelas
    $stmtCount = $db->prepare("
        SELECT sp.nome, COUNT(*) as total
        FROM pagamentos_plano pp
        LEFT JOIN status_pagamento sp ON pp.status_pagamento_id = sp.id
        WHERE pp.matricula_id = ?
        GROUP BY pp.status_pagamento_id, sp.nome
    ");
    $stmtCount->execute([$m['id']]);
    $parcelaCounts = $stmtCount->fetchAll(\PDO::FETCH_ASSOC);
    $countStr = implode(', ', array_map(fn($c) => ($c['nome'] ?? '?') . ': ' . $c['total'], $parcelaCounts));
    echo "  Parcelas: " . ($countStr ?: 'nenhuma') . "\n";

    // Assinatura vinculada
    try {
        $stmtAss = $db->prepare("SELECT id, status_id, status_gateway, gateway_assinatura_id FROM assinaturas WHERE matricula_id = ?");
        $stmtAss->execute([$m['id']]);
        $assVinc = $stmtAss->fetch(\PDO::FETCH_ASSOC);
        if ($assVinc) {
            echo "  Assinatura: ID={$assVinc['id']} | Status Local={$assVinc['status_id']} | Gateway={$assVinc['status_gateway']} | GW ID={$assVinc['gateway_assinatura_id']}\n";
        } else {
            echo "  Assinatura: nenhuma\n";
        }
    } catch (\Throwable $e) {}
    echo "\n";
}

// ============================================================
// 7. ANÁLISE CRUZADA: STATUS MATRÍCULA vs ASSINATURA vs PARCELAS
// ============================================================
echo "7. ANÁLISE CRUZADA DE STATUS\n";
echo str_repeat("=", 80) . "\n";

$anomalias = [];

// 7a. Matrícula vencida mas assinatura approved
if (!empty($assinaturas)) {
    foreach ($assinaturas as $ass) {
        if ($matricula['status_codigo'] === 'vencida' && in_array($ass['status_gateway'], ['authorized', 'approved'])) {
            $anomalias[] = "CRÍTICO: Matrícula está VENCIDA mas assinatura ID={$ass['id']} tem status_gateway='{$ass['status_gateway']}'";
        }
        if ($matricula['status_codigo'] === 'ativa' && in_array($ass['status_gateway'], ['cancelled', 'paused'])) {
            $anomalias[] = "DIVERGÊNCIA: Matrícula está ATIVA mas assinatura ID={$ass['id']} tem status_gateway='{$ass['status_gateway']}'";
        }
        if (empty($ass['gateway_assinatura_id']) && $ass['status_gateway']) {
            $anomalias[] = "SUSPEITO: Assinatura ID={$ass['id']} tem status_gateway='{$ass['status_gateway']}' mas gateway_assinatura_id está VAZIO (MP pode não ter realmente processado)";
        }
    }
}

// 7b. Parcelas que deveriam estar atrasadas
foreach ($parcelas as $p) {
    if ($p['data_vencimento'] < date('Y-m-d') && $p['status_pagamento_id'] == 1) {
        $anomalias[] = "PARCELA ID={$p['id']}: Venceu em {$p['data_vencimento']} mas ainda está Aguardando (deveria ser Atrasado)";
    }
}

// 7c. Nenhum pagamento pago mas matrícula não é pendente
$temPagoPago = false;
foreach ($parcelas as $p) {
    if ($p['status_pagamento_id'] == 2) $temPagoPago = true;
}
if (!$temPagoPago && !in_array($matricula['status_codigo'], ['pendente', 'cancelada'])) {
    $anomalias[] = "NENHUM PAGAMENTO CONFIRMADO: Matrícula está '{$matricula['status_codigo']}' mas não tem nenhuma parcela com status Pago";
}

// 7d. Múltiplas assinaturas para mesma matrícula
if (count($assinaturas) > 1) {
    $anomalias[] = "MÚLTIPLAS ASSINATURAS: Matrícula #189 tem " . count($assinaturas) . " assinaturas vinculadas";
}

// 7e. Assinatura sem payment_url (nunca foi pra pagar no MP)
foreach ($assinaturas as $ass) {
    if (empty($ass['payment_url']) && empty($ass['gateway_assinatura_id'])) {
        $anomalias[] = "Assinatura ID={$ass['id']} sem payment_url E sem gateway_assinatura_id: nunca chegou ao MP";
    }
}

echo "\n";
if (count($anomalias) > 0) {
    echo "ANOMALIAS ENCONTRADAS (" . count($anomalias) . "):\n\n";
    foreach ($anomalias as $i => $a) {
        echo "  " . ($i + 1) . ". {$a}\n";
    }
} else {
    echo "Nenhuma anomalia encontrada.\n";
}

// ============================================================
// 8. ENDPOINT MOBILE vs PAINEL - O QUE CADA UM CONSULTA
// ============================================================
echo "\n\n8. DIAGNÓSTICO: MOBILE vs PAINEL\n";
echo str_repeat("-", 80) . "\n";
echo "Mobile geralmente consulta: assinaturas.status_gateway para definir se 'ativa'\n";
echo "Painel geralmente consulta: matriculas.status_id para definir se 'vencida'\n\n";

if ($matricula['status_codigo'] === 'vencida' && !empty($assinaturas)) {
    $assAtiva = false;
    foreach ($assinaturas as $ass) {
        if (in_array($ass['status_gateway'], ['authorized', 'approved'])) {
            $assAtiva = true;
        }
    }
    if ($assAtiva) {
        echo ">>> CAUSA PROVÁVEL: Mobile mostra 'ativa' porque ASSINATURA.status_gateway='authorized/approved'\n";
        echo ">>> Painel mostra 'vencida' porque MATRICULA.status_codigo='vencida' (parcela venceu)\n";
        echo ">>> SOLUÇÃO: Sincronizar - ou a assinatura precisa ser cancelada no MP,\n";
        echo ">>>          ou a matrícula precisa ser atualizada para 'ativa' se o pagamento foi feito.\n";
    }
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "FIM DO DEBUG MATRÍCULA #189\n";
