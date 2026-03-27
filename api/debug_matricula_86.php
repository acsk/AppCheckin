<?php
/**
 * Debug Matrícula #86 - PEDRO JUNIOR
 * Investigação: parcelas integração (1 e 4), payment ID 148111325145,
 * bagunça de matrículas criadas pelo usuário
 */

require_once __DIR__ . '/vendor/autoload.php';

$db = require __DIR__ . '/config/database.php';
$matriculaId = 86;
$paymentIdInvestigar = '148111325145';
$externalRef = 'MAT-86-1772645160';

echo "====== DEBUG MATRÍCULA #86 - PEDRO JUNIOR ======\n";
echo "Data: " . date('Y-m-d H:i:s') . "\n";
echo "Payment ID investigado: {$paymentIdInvestigar}\n";
echo "External Ref: {$externalRef}\n\n";

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
echo "Proxima Data Vencimento: {$matricula['proxima_data_vencimento']}\n";
echo "Valor: R$ " . number_format((float)$matricula['valor'], 2, ',', '.') . "\n";
echo "Duracao dias (plano): {$matricula['duracao_dias']}\n";
echo "Matricula Anterior ID: " . ($matricula['matricula_anterior_id'] ?? 'null') . "\n";
echo "Pacote Contrato ID: " . ($matricula['pacote_contrato_id'] ?? 'null') . "\n";
echo "Criada em: {$matricula['created_at']}\n";
echo "Atualizada em: {$matricula['updated_at']}\n";

if (!empty($matricula['cancelado_por'])) {
    echo "CANCELADA por: {$matricula['cancelado_por']}\n";
    echo "  Data cancelamento: " . ($matricula['data_cancelamento'] ?? '-') . "\n";
    echo "  Motivo cancelamento: " . ($matricula['motivo_cancelamento'] ?? '-') . "\n";
}

echo "\n";

// ============================================================
// 2. PARCELAS DETALHADAS (com baixador e tipo)
// ============================================================
echo "2. PARCELAS (pagamentos_plano) - DETALHADO\n";
echo str_repeat("-", 80) . "\n";
$sql = "
    SELECT 
        pp.id,
        pp.matricula_id,
        pp.aluno_id,
        pp.plano_id,
        pp.valor,
        pp.data_vencimento,
        pp.data_pagamento,
        pp.status_pagamento_id,
        sp.nome as status_pagamento,
        pp.forma_pagamento_id,
        fp.nome as forma_pagamento,
        pp.baixado_por,
        baixador.nome as baixado_por_nome,
        pp.tipo_baixa_id,
        tb.nome as tipo_baixa_nome,
        pp.criado_por,
        criador.nome as criado_por_nome,
        pp.observacoes,
        pp.pacote_contrato_id,
        pp.created_at,
        pp.updated_at
    FROM pagamentos_plano pp
    LEFT JOIN status_pagamento sp ON pp.status_pagamento_id = sp.id
    LEFT JOIN formas_pagamento fp ON pp.forma_pagamento_id = fp.id
    LEFT JOIN usuarios baixador ON pp.baixado_por = baixador.id
    LEFT JOIN tipos_baixa tb ON pp.tipo_baixa_id = tb.id
    LEFT JOIN usuarios criador ON pp.criado_por = criador.id
    WHERE pp.matricula_id = ?
    ORDER BY pp.data_vencimento ASC, pp.id ASC
";
try {
    $stmt = $db->prepare($sql);
    $stmt->execute([$matriculaId]);
    $parcelas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    echo "Aviso: falha na consulta detalhada, tentando fallback: " . $e->getMessage() . "\n";
    $stmt = $db->prepare("SELECT * FROM pagamentos_plano WHERE matricula_id = ? ORDER BY data_vencimento ASC, id ASC");
    $stmt->execute([$matriculaId]);
    $parcelas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

echo "Total de parcelas nesta matricula: " . count($parcelas) . "\n\n";
foreach ($parcelas as $idx => $p) {
    $num = $idx + 1;
    $dataPag = $p['data_pagamento'] ?: 'Nao paga';
    $formaPag = $p['forma_pagamento'] ?? ($p['forma_pagamento_id'] ?? '-');
    $baixadorNome = $p['baixado_por_nome'] ?? ($p['baixado_por'] ?? '-');
    $tipoBaixa = $p['tipo_baixa_nome'] ?? ($p['tipo_baixa_id'] ?? '-');
    $criadorNome = $p['criado_por_nome'] ?? ($p['criado_por'] ?? '-');
    $statusPag = $p['status_pagamento'] ?? ('status_id=' . $p['status_pagamento_id']);

    echo "Parcela {$num} (ID: {$p['id']})\n";
    echo "  Matricula ID: {$p['matricula_id']}\n";
    echo "  Aluno ID: " . ($p['aluno_id'] ?? '-') . "\n";
    echo "  Plano ID: " . ($p['plano_id'] ?? '-') . "\n";
    echo "  Valor: R$ " . number_format((float)$p['valor'], 2, ',', '.') . "\n";
    echo "  Vencimento: {$p['data_vencimento']}\n";
    echo "  Status: {$statusPag} (id={$p['status_pagamento_id']})\n";
    echo "  Data Pagamento: {$dataPag}\n";
    echo "  Forma Pagamento: {$formaPag}\n";
    echo "  Tipo Baixa: {$tipoBaixa}\n";
    echo "  Baixado por: {$baixadorNome}\n";
    echo "  Criado por: {$criadorNome}\n";
    echo "  Pacote contrato: " . ($p['pacote_contrato_id'] ?? '-') . "\n";
    if ($p['observacoes']) {
        echo "  Observacoes: {$p['observacoes']}\n";
    }
    echo "  Criada em: {$p['created_at']}\n";
    echo "  Atualizada em: {$p['updated_at']}\n";

    // Flags de anomalia por parcela
    $flags = [];
    if ($p['data_pagamento'] && $p['status_pagamento_id'] != 2) {
        $flags[] = "tem data_pagamento mas status NAO e Pago";
    }
    if (!$p['data_pagamento'] && $p['status_pagamento_id'] == 2) {
        $flags[] = "status Pago mas sem data_pagamento";
    }
    if ($p['data_vencimento'] < ($matricula['data_inicio'] ?? '9999-12-31')) {
        $flags[] = "vencimento ANTES da data_inicio da matricula ({$matricula['data_inicio']})";
    }
    if ($p['data_pagamento'] && $p['data_vencimento'] && $p['data_pagamento'] < $p['data_vencimento']) {
        $diffDias = (new \DateTime($p['data_vencimento']))->diff(new \DateTime($p['data_pagamento']))->days;
        $flags[] = "pagamento {$diffDias} dia(s) ANTES do vencimento";
    }
    if (count($flags) > 0) {
        echo "  *** FLAGS: " . implode(' | ', $flags) . "\n";
    }
    echo "\n";
}

// ============================================================
// 3. BUSCA ESPECÍFICA DO PAYMENT ID 148111325145
// ============================================================
echo "3. BUSCA PAYMENT ID {$paymentIdInvestigar}\n";
echo str_repeat("-", 80) . "\n";

// 3a. Em pagamentos_mercadopago
echo "3a. pagamentos_mercadopago:\n";
try {
    $stmt = $db->prepare("SELECT * FROM pagamentos_mercadopago WHERE payment_id = ?");
    $stmt->execute([$paymentIdInvestigar]);
    $pmPorPaymentId = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    if (count($pmPorPaymentId) > 0) {
        foreach ($pmPorPaymentId as $pm) {
            echo "  Encontrado! Registro ID: " . ($pm['id'] ?? '-') . "\n";
            foreach ($pm as $col => $val) {
                if ($val !== null && $val !== '') {
                    echo "    {$col}: {$val}\n";
                }
            }
            echo "\n";
        }
    } else {
        echo "  Nenhum registro com payment_id={$paymentIdInvestigar}\n\n";
    }
} catch (\Throwable $e) {
    echo "  Erro: " . $e->getMessage() . "\n\n";
}

// 3b. Em webhook_payloads_mercadopago
echo "3b. webhook_payloads_mercadopago com payment_id {$paymentIdInvestigar}:\n";
try {
    $stmt = $db->prepare("SELECT * FROM webhook_payloads_mercadopago WHERE payment_id = ? ORDER BY id DESC");
    $stmt->execute([$paymentIdInvestigar]);
    $whPayment = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    if (count($whPayment) > 0) {
        foreach ($whPayment as $wh) {
            echo "  Webhook ID: " . ($wh['id'] ?? '-') . "\n";
            foreach ($wh as $col => $val) {
                if ($val !== null && $val !== '' && strlen($val) < 500) {
                    echo "    {$col}: {$val}\n";
                }
            }
            echo "\n";
        }
    } else {
        echo "  Nenhum webhook com payment_id={$paymentIdInvestigar}\n\n";
    }
} catch (\Throwable $e) {
    echo "  Erro: " . $e->getMessage() . "\n\n";
}

// 3c. Em pagamentos_simples (pode ter sido processado como pagamento simples)
echo "3c. pagamentos_simples com payment_id {$paymentIdInvestigar}:\n";
try {
    $stmt = $db->prepare("SELECT * FROM pagamentos_simples WHERE payment_id = ? ORDER BY id DESC");
    $stmt->execute([$paymentIdInvestigar]);
    $psPayment = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    if (count($psPayment) > 0) {
        foreach ($psPayment as $ps) {
            echo "  Encontrado! Registro ID: " . ($ps['id'] ?? '-') . "\n";
            foreach ($ps as $col => $val) {
                if ($val !== null && $val !== '') {
                    echo "    {$col}: {$val}\n";
                }
            }
            echo "\n";
        }
    } else {
        echo "  Nenhum registro\n\n";
    }
} catch (\Throwable $e) {
    echo "  Erro/tabela nao existe: " . $e->getMessage() . "\n\n";
}

// ============================================================
// 4. TODOS OS PAGAMENTOS MP DESTA MATRICULA
// ============================================================
echo "4. PAGAMENTOS MERCADOPAGO (matricula_id={$matriculaId})\n";
echo str_repeat("-", 80) . "\n";
try {
    $stmt = $db->prepare("SELECT * FROM pagamentos_mercadopago WHERE matricula_id = ? ORDER BY id DESC");
    $stmt->execute([$matriculaId]);
    $pagsMp = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    echo "Total: " . count($pagsMp) . "\n\n";
    foreach ($pagsMp as $pm) {
        echo "  ID: " . ($pm['id'] ?? '-') . " | payment_id: " . ($pm['payment_id'] ?? '-') . "\n";
        echo "  status: " . ($pm['status'] ?? '-') . " | amount: " . ($pm['amount'] ?? ($pm['transaction_amount'] ?? '-')) . "\n";
        echo "  external_reference: " . ($pm['external_reference'] ?? '-') . "\n";
        echo "  date_approved: " . ($pm['date_approved'] ?? '-') . "\n";
        echo "  created_at: " . ($pm['created_at'] ?? '-') . "\n";
        echo "\n";
    }
} catch (\Throwable $e) {
    echo "Erro: " . $e->getMessage() . "\n\n";
}

// 4b. MP por external_reference MAT-86-*
echo "4b. PAGAMENTOS MP por external_reference LIKE 'MAT-86%'\n";
try {
    $stmt = $db->prepare("SELECT * FROM pagamentos_mercadopago WHERE external_reference LIKE ? ORDER BY id DESC");
    $stmt->execute(['MAT-86%']);
    $pagsMpRef = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    echo "Total: " . count($pagsMpRef) . "\n\n";
    foreach ($pagsMpRef as $pm) {
        echo "  ID: " . ($pm['id'] ?? '-') . " | payment_id: " . ($pm['payment_id'] ?? '-') . "\n";
        echo "  status: " . ($pm['status'] ?? '-') . " | amount: " . ($pm['amount'] ?? ($pm['transaction_amount'] ?? '-')) . "\n";
        echo "  external_reference: " . ($pm['external_reference'] ?? '-') . "\n";
        echo "  date_approved: " . ($pm['date_approved'] ?? '-') . "\n";
        echo "  matricula_id: " . ($pm['matricula_id'] ?? '-') . "\n";
        echo "  aluno_id: " . ($pm['aluno_id'] ?? '-') . "\n";
        echo "  created_at: " . ($pm['created_at'] ?? '-') . "\n";
        echo "\n";
    }
} catch (\Throwable $e) {
    echo "Erro: " . $e->getMessage() . "\n\n";
}

// 4c. MP payments do ALUNO (independente da matricula)
echo "4c. TODOS PAGAMENTOS MP DO ALUNO (aluno_id={$alunoId})\n";
try {
    $stmt = $db->prepare("SELECT * FROM pagamentos_mercadopago WHERE aluno_id = ? ORDER BY id DESC");
    $stmt->execute([$alunoId]);
    $pagsMpAluno = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    echo "Total: " . count($pagsMpAluno) . "\n\n";
    foreach ($pagsMpAluno as $pm) {
        $isOurMat = ($pm['matricula_id'] ?? '') == $matriculaId ? ' ← MAT-86' : '';
        echo "  ID: " . ($pm['id'] ?? '-') . " | payment_id: " . ($pm['payment_id'] ?? '-') . "{$isOurMat}\n";
        echo "  status: " . ($pm['status'] ?? '-') . " | amount: " . ($pm['amount'] ?? ($pm['transaction_amount'] ?? '-')) . "\n";
        echo "  external_reference: " . ($pm['external_reference'] ?? '-') . "\n";
        echo "  matricula_id: " . ($pm['matricula_id'] ?? '-') . "\n";
        echo "  date_approved: " . ($pm['date_approved'] ?? '-') . "\n";
        echo "  created_at: " . ($pm['created_at'] ?? '-') . "\n";
        echo "\n";
    }
} catch (\Throwable $e) {
    echo "Erro: " . $e->getMessage() . "\n\n";
}

// ============================================================
// 5. WEBHOOKS RELACIONADOS
// ============================================================
echo "5. WEBHOOKS\n";
echo str_repeat("-", 80) . "\n";

// 5a. Por external_reference MAT-86
echo "5a. Webhooks por external_reference LIKE 'MAT-86%':\n";
try {
    $stmt = $db->prepare("SELECT * FROM webhook_payloads_mercadopago WHERE external_reference LIKE ? ORDER BY id DESC");
    $stmt->execute(['MAT-86%']);
    $webhooks = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    echo "Total: " . count($webhooks) . "\n\n";
    foreach ($webhooks as $wh) {
        echo "  WH ID: " . ($wh['id'] ?? '-') . " | payment_id: " . ($wh['payment_id'] ?? '-') . "\n";
        echo "  status: " . ($wh['status'] ?? '-') . " | action: " . ($wh['action'] ?? '-') . "\n";
        echo "  external_reference: " . ($wh['external_reference'] ?? '-') . "\n";
        echo "  amount: " . ($wh['amount'] ?? '-') . "\n";
        echo "  processed_at: " . ($wh['processed_at'] ?? '-') . "\n";
        $result = $wh['processed_result'] ?? '-';
        if (strlen($result) > 200) $result = substr($result, 0, 200) . '...';
        echo "  processed_result: {$result}\n";
        echo "  created_at: " . ($wh['created_at'] ?? '-') . "\n";
        echo "\n";
    }
} catch (\Throwable $e) {
    echo "Erro: " . $e->getMessage() . "\n\n";
}

// 5b. Webhooks com o payment_id específico
echo "5b. Webhooks com payment_id={$paymentIdInvestigar}:\n";
try {
    $stmt = $db->prepare("SELECT * FROM webhook_payloads_mercadopago WHERE payment_id = ? ORDER BY id DESC");
    $stmt->execute([$paymentIdInvestigar]);
    $whSpecific = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    echo "Total: " . count($whSpecific) . "\n\n";
    foreach ($whSpecific as $wh) {
        echo "  WH ID: " . ($wh['id'] ?? '-') . "\n";
        foreach ($wh as $col => $val) {
            if ($val !== null && $val !== '' && strlen((string)$val) < 500) {
                echo "    {$col}: {$val}\n";
            }
        }
        echo "\n";
    }
} catch (\Throwable $e) {
    echo "Erro: " . $e->getMessage() . "\n\n";
}

// ============================================================
// 6. TODAS AS MATRÍCULAS DO ALUNO (investigar "bagunça")
// ============================================================
echo "6. TODAS MATRICULAS DO ALUNO (aluno_id={$alunoId})\n";
echo str_repeat("-", 80) . "\n";
$sql = "
    SELECT 
        m.id,
        m.plano_id,
        p.nome as plano_nome,
        p.modalidade_id,
        mod_t.nome as modalidade_nome,
        m.plano_ciclo_id,
        m.tipo_cobranca,
        m.data_matricula,
        m.data_inicio,
        m.data_vencimento,
        m.proxima_data_vencimento,
        m.status_id,
        sm.nome as status_nome,
        sm.codigo as status_codigo,
        m.motivo_id,
        m.matricula_anterior_id,
        m.valor,
        m.pacote_contrato_id,
        m.created_at,
        m.updated_at,
        m.cancelado_por,
        m.data_cancelamento,
        m.motivo_cancelamento
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

echo "Total de matriculas do aluno: " . count($todasMatriculas) . "\n\n";
foreach ($todasMatriculas as $m) {
    $destaque = ($m['id'] == $matriculaId) ? " <<< INVESTIGADA" : "";
    echo "Matricula #{$m['id']}{$destaque}\n";
    echo "  Plano: {$m['plano_nome']} (ID: {$m['plano_id']})\n";
    echo "  Modalidade: " . ($m['modalidade_nome'] ?? '-') . " (ID: " . ($m['modalidade_id'] ?? '-') . ")\n";
    echo "  Ciclo ID: " . ($m['plano_ciclo_id'] ?? '-') . "\n";
    echo "  Tipo Cobranca: " . ($m['tipo_cobranca'] ?? '-') . "\n";
    echo "  Status: {$m['status_nome']} ({$m['status_codigo']})\n";
    echo "  Data Matricula: {$m['data_matricula']}\n";
    echo "  Data Inicio: {$m['data_inicio']}\n";
    echo "  Data Vencimento: {$m['data_vencimento']}\n";
    echo "  Prox. Vencimento: " . ($m['proxima_data_vencimento'] ?? '-') . "\n";
    echo "  Valor: R$ " . number_format((float)$m['valor'], 2, ',', '.') . "\n";
    echo "  Matricula Anterior: " . ($m['matricula_anterior_id'] ?? '-') . "\n";
    echo "  Pacote contrato: " . ($m['pacote_contrato_id'] ?? '-') . "\n";
    echo "  Criada: {$m['created_at']}\n";
    echo "  Atualizada: {$m['updated_at']}\n";
    if (!empty($m['cancelado_por'])) {
        echo "  CANCELADA por: {$m['cancelado_por']} em {$m['data_cancelamento']}\n";
        echo "  Motivo: " . ($m['motivo_cancelamento'] ?? '-') . "\n";
    }
    
    // Contar parcelas por status
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
    echo "\n";
}

// ============================================================
// 7. PARCELAS DE OUTRAS MATRÍCULAS DO ALUNO (cruzamento)
// ============================================================
echo "7. TODAS PARCELAS DO ALUNO (todas matriculas)\n";
echo str_repeat("-", 80) . "\n";
$sql = "
    SELECT 
        pp.id,
        pp.matricula_id,
        pp.plano_id,
        pp.valor,
        pp.data_vencimento,
        pp.data_pagamento,
        pp.status_pagamento_id,
        sp.nome as status_pagamento,
        pp.tipo_baixa_id,
        tb.nome as tipo_baixa_nome,
        pp.baixado_por,
        baixador.nome as baixado_por_nome,
        pp.observacoes,
        pp.created_at,
        pp.updated_at
    FROM pagamentos_plano pp
    LEFT JOIN status_pagamento sp ON pp.status_pagamento_id = sp.id
    LEFT JOIN tipos_baixa tb ON pp.tipo_baixa_id = tb.id
    LEFT JOIN usuarios baixador ON pp.baixado_por = baixador.id
    WHERE pp.aluno_id = ? AND pp.tenant_id = ?
    ORDER BY pp.matricula_id ASC, pp.data_vencimento ASC, pp.id ASC
";
try {
    $stmt = $db->prepare($sql);
    $stmt->execute([$alunoId, $tenantId]);
    $todasParcelas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    echo "Aviso fallback: " . $e->getMessage() . "\n";
    $stmt = $db->prepare("SELECT * FROM pagamentos_plano WHERE aluno_id = ? AND tenant_id = ? ORDER BY matricula_id ASC, data_vencimento ASC");
    $stmt->execute([$alunoId, $tenantId]);
    $todasParcelas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

echo "Total parcelas do aluno (todas matriculas): " . count($todasParcelas) . "\n\n";
$currentMat = null;
foreach ($todasParcelas as $p) {
    if ($p['matricula_id'] !== $currentMat) {
        $currentMat = $p['matricula_id'];
        $label = ($currentMat == $matriculaId) ? " <<< INVESTIGADA" : "";
        echo "  --- Matricula #{$currentMat}{$label} ---\n";
    }
    $tipoBaixa = $p['tipo_baixa_nome'] ?? ($p['tipo_baixa_id'] ?? '-');
    $baixador = $p['baixado_por_nome'] ?? ($p['baixado_por'] ?? '-');
    $statusPag = $p['status_pagamento'] ?? ('sid=' . $p['status_pagamento_id']);
    $obs = $p['observacoes'] ? ' obs=' . substr($p['observacoes'], 0, 80) : '';
    echo "  PP#{$p['id']} | venc={$p['data_vencimento']} | pag=" . ($p['data_pagamento'] ?: '-') . " | {$statusPag} | baixa={$tipoBaixa} por={$baixador}{$obs}\n";
    echo "           created={$p['created_at']} | updated={$p['updated_at']}\n";
}
echo "\n";

// ============================================================
// 8. HISTÓRICO DE PLANOS DO ALUNO
// ============================================================
echo "8. HISTORICO DE PLANOS\n";
echo str_repeat("-", 80) . "\n";
try {
    $stmt = $db->prepare("
        SELECT hp.*, p.nome as plano_nome, u.nome as admin_nome
        FROM historico_planos hp
        LEFT JOIN planos p ON hp.plano_id = p.id
        LEFT JOIN usuarios u ON hp.admin_id = u.id
        WHERE hp.aluno_id = ? AND hp.tenant_id = ?
        ORDER BY hp.created_at ASC
    ");
    $stmt->execute([$alunoId, $tenantId]);
    $historicos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    echo "Total: " . count($historicos) . "\n\n";
    foreach ($historicos as $h) {
        echo "  ID: {$h['id']} | Matricula: " . ($h['matricula_id'] ?? '-') . " | Plano: " . ($h['plano_nome'] ?? ($h['plano_id'] ?? '-')) . "\n";
        echo "  Motivo: " . ($h['motivo'] ?? ($h['motivo_id'] ?? '-')) . " | Admin: " . ($h['admin_nome'] ?? '-') . "\n";
        echo "  Inicio: " . ($h['data_inicio'] ?? '-') . " | Fim: " . ($h['data_fim'] ?? '-') . "\n";
        echo "  Criado: {$h['created_at']}\n\n";
    }
} catch (\Throwable $e) {
    echo "Erro/tabela nao existe: " . $e->getMessage() . "\n\n";
}

// ============================================================
// 9. ASSINATURAS DO ALUNO
// ============================================================
echo "9. ASSINATURAS DO ALUNO\n";
echo str_repeat("-", 80) . "\n";
try {
    $stmt = $db->prepare("SELECT * FROM assinaturas WHERE aluno_id = ? ORDER BY id DESC");
    $stmt->execute([$alunoId]);
    $assinaturas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    echo "Total: " . count($assinaturas) . "\n\n";
    foreach ($assinaturas as $a) {
        echo "  Assinatura ID: {$a['id']}\n";
        foreach (['status', 'preapproval_id', 'matricula_id', 'plano_id', 'plano_ciclo_id', 'data_inicio', 'data_vencimento', 'valor_mensal', 'created_at', 'updated_at'] as $col) {
            if (isset($a[$col])) {
                echo "    {$col}: {$a[$col]}\n";
            }
        }
        echo "\n";
    }
} catch (\Throwable $e) {
    echo "Erro/ignorado: " . $e->getMessage() . "\n\n";
}

// ============================================================
// 10. ANALISE E ANOMALIAS
// ============================================================
echo "10. ANALISE E ANOMALIAS\n";
echo str_repeat("=", 80) . "\n";

$anomalias = [];

// Parcelas com tipo_baixa Integração
$parcelasIntegracao = array_filter($parcelas, fn($p) => ($p['tipo_baixa_id'] ?? null) == 4);
if (count($parcelasIntegracao) > 0) {
    $ids = implode(', ', array_map(fn($p) => '#' . $p['id'], $parcelasIntegracao));
    $anomalias[] = "Parcelas com tipo_baixa=Integracao: {$ids}";
}

// Parcelas com data_pagamento mas status Aguardando
$parcelasInconsistentes = array_filter($parcelas, fn($p) => !empty($p['data_pagamento']) && $p['status_pagamento_id'] == 1);
if (count($parcelasInconsistentes) > 0) {
    foreach ($parcelasInconsistentes as $pi) {
        $anomalias[] = "PP#{$pi['id']}: tem data_pagamento ({$pi['data_pagamento']}) mas status Aguardando";
    }
}

// Parcelas com data_pagamento ANTES do created_at da matrícula
foreach ($parcelas as $p) {
    if ($p['data_pagamento'] && $p['created_at']) {
        $createdDate = substr($p['created_at'], 0, 10);
        if ($p['data_pagamento'] < $createdDate) {
            $anomalias[] = "PP#{$p['id']}: data_pagamento ({$p['data_pagamento']}) ANTES do created_at ({$p['created_at']})";
        }
    }
}

// Múltiplas matrículas na mesma modalidade
$matPorModalidade = [];
foreach ($todasMatriculas as $m) {
    $modId = $m['modalidade_id'] ?? 'null';
    $matPorModalidade[$modId][] = $m;
}
foreach ($matPorModalidade as $modId => $mats) {
    if (count($mats) > 1) {
        $ids = implode(', ', array_map(fn($m) => "#{$m['id']}({$m['status_codigo']})", $mats));
        $modNome = $mats[0]['modalidade_nome'] ?? $modId;
        $anomalias[] = "Multiplas matriculas na modalidade '{$modNome}': {$ids}";
    }
}

// Parcelas órfãs (ligadas a matrícula finalizada/cancelada)
foreach ($todasMatriculas as $m) {
    if (in_array($m['status_codigo'], ['finalizada', 'cancelada'])) {
        $stmtOrfa = $db->prepare("SELECT COUNT(*) FROM pagamentos_plano WHERE matricula_id = ? AND status_pagamento_id = 1");
        $stmtOrfa->execute([$m['id']]);
        $qtdPendentes = (int)$stmtOrfa->fetchColumn();
        if ($qtdPendentes > 0) {
            $anomalias[] = "Matricula #{$m['id']} ({$m['status_codigo']}) ainda tem {$qtdPendentes} parcela(s) Aguardando";
        }
    }
}

// Pagamentos MP sem vínculo na parcela
if (isset($pagsMpAluno)) {
    foreach ($pagsMpAluno as $pm) {
        $pmPayId = $pm['payment_id'] ?? null;
        if ($pmPayId && ($pm['status'] ?? '') === 'approved') {
            // Verificar se existe referência na observação de alguma parcela
            $found = false;
            foreach ($todasParcelas as $tp) {
                if ($tp['observacoes'] && strpos($tp['observacoes'], (string)$pmPayId) !== false) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $anomalias[] = "Pagamento MP payment_id={$pmPayId} (approved) nao referenciado em nenhuma parcela";
            }
        }
    }
}

// Parcela #48 específica (mencionada pelo usuário)
$parcela48 = array_filter($parcelas, fn($p) => $p['id'] == 48);
if (count($parcela48) > 0) {
    $p48 = array_values($parcela48)[0];
    $anomalias[] = "FOCO: PP#48 - venc={$p48['data_vencimento']} pag=" . ($p48['data_pagamento'] ?: '-') . " status_id={$p48['status_pagamento_id']} tipo_baixa=" . ($p48['tipo_baixa_id'] ?? '-') . " created={$p48['created_at']}";
}

if (count($anomalias) > 0) {
    echo "ANOMALIAS DETECTADAS:\n\n";
    foreach ($anomalias as $i => $a) {
        echo "  " . ($i + 1) . ". {$a}\n";
    }
} else {
    echo "Nenhuma anomalia detectada\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "FIM DO DEBUG MATRICULA #86\n";
