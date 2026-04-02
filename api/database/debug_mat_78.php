<?php
$db = require __DIR__ . '/../config/database.php';

echo "=== Debug Matrícula #78 ===\n\n";

// Matrícula
$mat = $db->query("
    SELECT m.*, p.nome as plano_nome, p.duracao_meses
    FROM matriculas m
    LEFT JOIN planos p ON p.id = m.plano_id
    WHERE m.id = 78
")->fetch(PDO::FETCH_ASSOC);
echo "--- Matrícula ---\n";
echo "ID: {$mat['id']}\n";
echo "Plano: {$mat['plano_nome']} (duração: {$mat['duracao_meses']} meses)\n";
echo "data_inicio: {$mat['data_inicio']}\n";
echo "data_vencimento (acesso até): {$mat['data_vencimento']}\n";
echo "proxima_data_vencimento: {$mat['proxima_data_vencimento']}\n";
echo "status_id: {$mat['status_id']}\n";
echo "tenant_id: {$mat['tenant_id']}\n\n";

// Assinatura
$ass = $db->query("
    SELECT * FROM assinaturas WHERE matricula_id = 78
")->fetchAll(PDO::FETCH_ASSOC);
echo "--- Assinatura ---\n";
if (empty($ass)) {
    echo "NENHUMA assinatura encontrada!\n";
} else {
    foreach ($ass as $a) {
        echo "ID: {$a['id']}\n";
        echo "data_inicio: {$a['data_inicio']}\n";
        echo "data_fim: {$a['data_fim']}\n";
        echo "dia_cobranca: {$a['dia_cobranca']}\n";
        echo "proxima_cobranca: {$a['proxima_cobranca']}\n";
        echo "status_id: {$a['status_id']}\n";
        echo "valor: " . ($a['valor'] ?? 'NULL') . "\n\n";
    }
}

// Pagamentos
$pags = $db->query("
    SELECT pp.*, sp.nome as status_nome, tb.nome as tipo_baixa_nome
    FROM pagamentos_plano pp
    LEFT JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
    LEFT JOIN tipos_baixa tb ON tb.id = pp.tipo_baixa_id
    WHERE pp.matricula_id = 78
    ORDER BY pp.data_vencimento
")->fetchAll(PDO::FETCH_ASSOC);
echo "--- Pagamentos ({$mat['id']}) ---\n";
foreach ($pags as $p) {
    echo "#{$p['id']} | Venc: {$p['data_vencimento']} | Pag: {$p['data_pagamento']} | Status: {$p['status_nome']} | Baixa: {$p['tipo_baixa_nome']} | Valor: {$p['valor']}\n";
}

// Cálculo esperado
echo "\n--- Cálculo esperado ---\n";
if (!empty($ass)) {
    $a = $ass[0];
    echo "Assinatura data_inicio: {$a['data_inicio']}\n";
    echo "Assinatura data_fim: {$a['data_fim']}\n";
    echo "Status assinatura: {$a['status_id']}\n";
    
    if ((int) $a['status_id'] === 1) {
        echo "ATIVA: acesso_ate deveria ser = próxima cobrança\n";
    } else {
        echo "CANCELADA (status={$a['status_id']}): acesso_ate deveria ser = data_fim = {$a['data_fim']}\n";
    }
} else {
    echo "Sem assinatura - deveria usar dados das parcelas\n";
    if (!empty($pags)) {
        $duracaoMeses = (int) ($mat['duracao_meses'] ?? 1);
        $ultimoPago = null;
        foreach ($pags as $p) {
            if ((int) $p['status_pagamento_id'] === 2) {
                $ultimoPago = $p;
            }
        }
        if ($ultimoPago) {
            $dataBase = new DateTime($ultimoPago['data_vencimento']);
            $acessoAte = clone $dataBase;
            $acessoAte->modify("+{$duracaoMeses} months");
            echo "Último pagamento pago: #{$ultimoPago['id']} em {$ultimoPago['data_vencimento']}\n";
            echo "Duração ciclo: {$duracaoMeses} meses\n";
            echo "Acesso até deveria ser: " . $acessoAte->format('Y-m-d') . "\n";
        }
    }
}
