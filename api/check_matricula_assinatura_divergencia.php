<?php
/**
 * Verificar divergência entre proxima_data_vencimento da matrícula
 * e data_fim da assinatura (quando feita por integração MP)
 * 
 * Uso:
 *   docker exec appcheckin_php php check_matricula_assinatura_divergencia.php
 *   ou em prod:
 *   php check_matricula_assinatura_divergencia.php
 */

$pdo = require __DIR__ . '/config/database.php';

echo "=== DIVERGÊNCIA: matriculas.proxima_data_vencimento vs assinaturas.data_fim ===\n";
echo "Data atual: " . date('Y-m-d H:i:s') . "\n\n";

// Query principal: matrículas com assinatura approved onde as datas divergem
$sql = "
    SELECT 
        m.id as matricula_id,
        m.aluno_id,
        u.nome as aluno_nome,
        m.plano_id,
        p.nome as plano_nome,
        sm.codigo as status_matricula,
        m.proxima_data_vencimento as mat_proximo_venc,
        m.data_vencimento as mat_data_venc,
        a.id as assinatura_id,
        a.data_inicio as ass_data_inicio,
        a.data_fim as ass_data_fim,
        a.proxima_cobranca as ass_proxima_cobranca,
        a.ultima_cobranca as ass_ultima_cobranca,
        a.status_gateway,
        a.tipo_cobranca,
        a.external_reference,
        DATEDIFF(
            COALESCE(a.data_fim, a.proxima_cobranca), 
            m.proxima_data_vencimento
        ) as diferenca_dias,
        (SELECT COUNT(*) FROM pagamentos_plano pp WHERE pp.matricula_id = m.id AND pp.status_pagamento_id = 2) as pagamentos_pagos,
        (SELECT COUNT(*) FROM pagamentos_plano pp WHERE pp.matricula_id = m.id AND pp.status_pagamento_id = 1) as pagamentos_pendentes
    FROM matriculas m
    INNER JOIN assinaturas a ON a.matricula_id = m.id AND a.tenant_id = m.tenant_id
    LEFT JOIN status_matricula sm ON sm.id = m.status_id
    LEFT JOIN alunos al ON al.id = m.aluno_id AND al.tenant_id = m.tenant_id
    LEFT JOIN usuarios u ON u.id = al.usuario_id
    LEFT JOIN planos p ON p.id = m.plano_id
    WHERE a.status_gateway = 'approved'
      AND COALESCE(a.data_fim, a.proxima_cobranca) IS NOT NULL
      AND m.proxima_data_vencimento != COALESCE(a.data_fim, a.proxima_cobranca)
    ORDER BY DATEDIFF(COALESCE(a.data_fim, a.proxima_cobranca), m.proxima_data_vencimento) DESC
";

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    echo "✅ Nenhuma divergência encontrada!\n";
    exit(0);
}

echo "⚠️  Encontradas " . count($rows) . " matrícula(s) com divergência:\n\n";
echo str_repeat('-', 120) . "\n";

foreach ($rows as $i => $row) {
    $num = $i + 1;
    echo "#{$num} Matrícula #{$row['matricula_id']} | Aluno #{$row['aluno_id']} ({$row['aluno_nome']})\n";
    echo "   Plano: {$row['plano_nome']} (id={$row['plano_id']})\n";
    echo "   Status matrícula: {$row['status_matricula']}\n";
    echo "   Tipo cobrança: {$row['tipo_cobranca']}\n";
    echo "   Ref: {$row['external_reference']}\n";
    echo "\n";
    echo "   MATRÍCULA:\n";
    echo "     proxima_data_vencimento = {$row['mat_proximo_venc']}\n";
    echo "     data_vencimento         = {$row['mat_data_venc']}\n";
    echo "\n";
    echo "   ASSINATURA (id={$row['assinatura_id']}, status_gateway={$row['status_gateway']}):\n";
    echo "     data_inicio      = {$row['ass_data_inicio']}\n";
    echo "     data_fim         = {$row['ass_data_fim']}\n";
    echo "     proxima_cobranca = " . ($row['ass_proxima_cobranca'] ?: 'NULL') . "\n";
    echo "     ultima_cobranca  = " . ($row['ass_ultima_cobranca'] ?: 'NULL') . "\n";
    echo "\n";
    
    $diff = (int) $row['diferenca_dias'];
    $direcao = $diff > 0 ? "ATRASADA {$diff} dia(s)" : "ADIANTADA " . abs($diff) . " dia(s)";
    echo "   ⚡ DIFERENÇA: {$direcao} (matrícula vs assinatura)\n";
    echo "   📊 Pagamentos: {$row['pagamentos_pagos']} pago(s), {$row['pagamentos_pendentes']} pendente(s)\n";
    
    // Sugerir correção
    $dataCorreta = $row['ass_data_fim'] ?: $row['ass_proxima_cobranca'];
    echo "\n   🔧 SQL de correção:\n";
    echo "   UPDATE matriculas SET proxima_data_vencimento = '{$dataCorreta}', data_vencimento = '{$dataCorreta}', status_id = (SELECT id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1), updated_at = NOW() WHERE id = {$row['matricula_id']};\n";
    
    echo str_repeat('-', 120) . "\n";
}

echo "\n=== RESUMO ===\n";
echo "Total com divergência: " . count($rows) . "\n";

// Contar por status
$porStatus = [];
foreach ($rows as $row) {
    $s = $row['status_matricula'];
    $porStatus[$s] = ($porStatus[$s] ?? 0) + 1;
}
foreach ($porStatus as $status => $count) {
    echo "  - {$status}: {$count}\n";
}

echo "\nDone.\n";
