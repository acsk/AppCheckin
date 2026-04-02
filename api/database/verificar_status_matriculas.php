<?php
/**
 * Verificação: matrículas canceladas/vencidas estão corretas?
 * Compara o status atual com o status calculado pelas parcelas.
 *
 * Uso: php database/verificar_status_matriculas.php
 */

$db = require __DIR__ . '/../config/database.php';

echo "=== Verificação de status de matrículas ===\n";
echo "Data atual: " . date('Y-m-d') . "\n\n";

$mats = $db->query("
    SELECT m.id, m.tenant_id, m.data_inicio, m.data_vencimento, m.proxima_data_vencimento,
           m.status_id, sm.codigo as status_codigo, sm.nome as status_nome,
           a.nome as aluno_nome
    FROM matriculas m
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    LEFT JOIN alunos a ON a.id = m.aluno_id
    WHERE sm.codigo IN ('cancelada', 'vencida')
    ORDER BY m.id
")->fetchAll(PDO::FETCH_ASSOC);

echo "Matrículas canceladas/vencidas: " . count($mats) . "\n\n";

$incorretas = 0;

foreach ($mats as $m) {
    $mId = (int) $m['id'];
    $tId = (int) $m['tenant_id'];

    // Buscar parcelas
    $stmtPags = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status_pagamento_id = 2 THEN 1 ELSE 0 END) as pagas,
            SUM(CASE WHEN status_pagamento_id = 1 THEN 1 ELSE 0 END) as pendentes,
            SUM(CASE WHEN status_pagamento_id = 3 THEN 1 ELSE 0 END) as atrasadas,
            SUM(CASE WHEN status_pagamento_id = 4 THEN 1 ELSE 0 END) as canceladas,
            MAX(CASE WHEN status_pagamento_id IN (1, 3) AND data_vencimento < CURDATE() 
                THEN DATEDIFF(CURDATE(), data_vencimento) ELSE 0 END) as dias_atraso,
            MIN(CASE WHEN status_pagamento_id IN (1, 3) THEN data_vencimento END) as prox_pendente,
            MAX(CASE WHEN status_pagamento_id = 2 THEN data_vencimento END) as ultimo_pago
        FROM pagamentos_plano
        WHERE tenant_id = ? AND matricula_id = ?
    ");
    $stmtPags->execute([$tId, $mId]);
    $p = $stmtPags->fetch(PDO::FETCH_ASSOC);

    // Verificar assinatura
    $stmtAss = $db->prepare("SELECT id, status_id, data_fim FROM assinaturas WHERE matricula_id = ? LIMIT 1");
    $stmtAss->execute([$mId]);
    $ass = $stmtAss->fetch(PDO::FETCH_ASSOC);

    // Calcular status correto (mesma lógica de atualizarStatusMatricula)
    $statusCorreto = 'ativa';
    $pendentes = (int) $p['pendentes'] + (int) $p['atrasadas'];
    $diasAtraso = (int) $p['dias_atraso'];

    if ($pendentes > 0) {
        if ($diasAtraso >= 5) {
            $statusCorreto = 'cancelada';
        } elseif ($diasAtraso >= 1) {
            $statusCorreto = 'vencida';
        }
    }

    $statusAtual = $m['status_codigo'];
    $correto = ($statusAtual === $statusCorreto);

    if (!$correto) {
        $incorretas++;
        $tag = "❌ INCORRETO";
    } else {
        $tag = "✅ OK";
    }

    echo "#{$mId} {$m['aluno_nome']}\n";
    echo "  Status: {$m['status_nome']} ({$statusAtual}) → Deveria ser: {$statusCorreto} {$tag}\n";
    echo "  Parcelas: {$p['total']} total | {$p['pagas']} pagas | {$p['pendentes']} pend | {$p['atrasadas']} atras | {$p['canceladas']} canc\n";
    echo "  Dias atraso: {$diasAtraso} | Último pago: " . ($p['ultimo_pago'] ?: '-') . " | Próx pendente: " . ($p['prox_pendente'] ?: '-') . "\n";
    echo "  Datas mat: inicio={$m['data_inicio']} | acesso_ate={$m['data_vencimento']} | prox_venc={$m['proxima_data_vencimento']}\n";
    if ($ass) {
        echo "  Assinatura: #{$ass['id']} status={$ass['status_id']} data_fim=" . ($ass['data_fim'] ?: 'NULL') . "\n";
    }
    echo "\n";
}

echo "=== Resumo ===\n";
echo "Total verificadas: " . count($mats) . "\n";
echo "Status incorreto: {$incorretas}\n";
echo "Status correto: " . (count($mats) - $incorretas) . "\n";
