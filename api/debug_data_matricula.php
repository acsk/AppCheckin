<?php
/**
 * Debug: Verificar problema de datas em matrículas
 * 
 * Problema: Quando um plano é comprado hoje, a matrícula fica com a data de amanhã
 * 
 * Uso: php debug_data_matricula.php [--dias=7] [--tenant=3] [--verbose]
 */

$root = __DIR__;
require_once $root . '/config/database.php';

if (!isset($pdo)) {
    $pdo = require $root . '/config/database.php';
}

$options = getopt('', ['dias:', 'tenant:', 'verbose']);
$tenantId = isset($options['tenant']) ? (int)$options['tenant'] : 3;
$diasAtrás = isset($options['dias']) ? (int)$options['dias'] : 7;
$verbose = isset($options['verbose']);

echo "\n" . str_repeat("=", 100) . "\n";
echo "DEBUG: VERIFICAR PROBLEMA DE DATAS EM MATRÍCULAS\n";
echo "Período: Últimos $diasAtrás dias | Tenant: $tenantId\n";
echo str_repeat("=", 100) . "\n\n";

// Configurar timezone
date_default_timezone_set('America/Sao_Paulo');

// 1. Buscar matrículas criadas recentemente
echo "📋 MATRÍCULAS CRIADAS (últimos $diasAtrás dias):\n";
echo str_repeat("-", 100) . "\n";

$sql = "
    SELECT 
        m.id,
        m.aluno_id,
        m.plano_id,
        p.nome as plano_nome,
        m.data_matricula,
        m.data_inicio,
        m.data_vencimento,
        m.created_at,
        m.updated_at,
        DATEDIFF(m.data_inicio, DATE(m.created_at)) as dias_diferenca,
        DATEDIFF(m.data_matricula, DATE(m.created_at)) as dias_diferenca_matricula,
        CURDATE() as hoje
    FROM matriculas m
    INNER JOIN planos p ON p.id = m.plano_id
    WHERE m.tenant_id = ? 
      AND DATE(m.created_at) >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
    ORDER BY m.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$tenantId, $diasAtrás]);
$matriculas = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($matriculas)) {
    echo "❌ Nenhuma matrícula encontrada nos últimos $diasAtrás dias.\n";
} else {
    echo sprintf(
        "%-4s | %-10s | %-30s | %-12s | %-12s | %-12s | %-20s | %s\n",
        "ID", "Aluno", "Plano", "Cria em", "Mat Data", "Início", "Criado em", "Diferença"
    );
    echo str_repeat("-", 130) . "\n";
    
    $problemas = [];
    
    foreach ($matriculas as $m) {
        $dataCriacao = date('Y-m-d', strtotime($m['created_at']));
        $diferenca = (int)$m['dias_diferenca'];
        $diferenciaMatricula = (int)$m['dias_diferenca_matricula'];
        
        $status = "✅";
        
        // Se data_inicio é diferente de data_matricula, há problema
        if ($m['data_inicio'] !== $m['data_matricula']) {
            $status = "⚠️ ";
            $problemas[] = [
                'id' => $m['id'],
                'aluno_id' => $m['aluno_id'],
                'plano' => $m['plano_nome'],
                'data_criacao' => $dataCriacao,
                'data_matricula' => $m['data_matricula'],
                'data_inicio' => $m['data_inicio'],
                'diferenca_dias' => $diferenca
            ];
        }
        
        // Se data_matricula ou data_inicio é amanhã (ou mais dias no futuro), há problema
        if ($diferenciaMatricula > 0 || $diferenca > 0) {
            $status = "⚠️ ";
        }
        
        echo sprintf(
            "%s%-3d | %-10d | %-30s | %-12s | %-12s | %-12s | %-20s | %+d dias\n",
            $status,
            $m['id'],
            $m['aluno_id'],
            substr($m['plano_nome'], 0, 28),
            $dataCriacao,
            $m['data_matricula'],
            $m['data_inicio'],
            $m['created_at'],
            $diferenca
        );
    }
    
    // Relatório de problemas
    if (!empty($problemas)) {
        echo "\n\n🔴 PROBLEMAS ENCONTRADOS:\n";
        echo str_repeat("-", 100) . "\n";
        echo "Total de matrículas com discrepância: " . count($problemas) . "\n\n";
        
        foreach ($problemas as $p) {
            echo "  📌 Matrícula #{$p['id']} (Aluno #{$p['aluno_id']})\n";
            echo "     Plano: {$p['plano']}\n";
            echo "     Criada em: {$p['data_criacao']}\n";
            echo sprintf(
                "     Data Matrícula: %s (esperado: %s) %s\n",
                $p['data_matricula'],
                $p['data_criacao'],
                $p['data_matricula'] === $p['data_criacao'] ? "✅" : "❌ DIFERENTE"
            );
            echo sprintf(
                "     Data Início: %s (esperado: %s) %s\n",
                $p['data_inicio'],
                $p['data_criacao'],
                $p['data_inicio'] === $p['data_criacao'] ? "✅" : "❌ DIFERENTE"
            );
            echo sprintf(
                "     Diferença: %+d dias (data_inicio está %s)\n\n",
                $p['diferenca_dias'],
                $p['diferenca_dias'] > 0 ? "MAS CEDO (FUTURA)" : "CORRETO"
            );
        }
    }
}

// 2. Análise geral de padrões
echo "\n\n📊 ANÁLISE ESTATÍSTICA:\n";
echo str_repeat("-", 100) . "\n";

$sqlStats = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN data_inicio = data_matricula THEN 1 ELSE 0 END) as corretas,
        SUM(CASE WHEN data_inicio <> data_matricula THEN 1 ELSE 0 END) as incorretas,
        SUM(CASE WHEN DATEDIFF(data_inicio, data_matricula) > 0 THEN 1 ELSE 0 END) as futuras,
        MIN(DATEDIFF(data_inicio, data_matricula)) as min_diferenca,
        MAX(DATEDIFF(data_inicio, data_matricula)) as max_diferenca,
        AVG(DATEDIFF(data_inicio, data_matricula)) as media_diferenca
    FROM matriculas
    WHERE tenant_id = ?
      AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
";

$stmt = $pdo->prepare($sqlStats);
$stmt->execute([$tenantId, $diasAtrás]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Total de matrículas: {$stats['total']}\n";
echo "✅ Com data_inicio = data_matricula: {$stats['corretas']}\n";
echo "❌ Com data_inicio ≠ data_matricula: {$stats['incorretas']}\n";
echo "🚀 Futuras (data_inicio > data_matricula): {$stats['futuras']}\n";
echo "   Diferença mínima: {$stats['min_diferenca']} dias\n";
echo "   Diferença máxima: {$stats['max_diferenca']} dias\n";
echo "   Média: " . round((float)$stats['media_diferenca'], 1) . " dias\n";

// 3. Verificar timezone atual
echo "\n\n🕐 INFORMAÇÕES DE TIMEZONE:\n";
echo str_repeat("-", 100) . "\n";
echo "Timezone PHP atual: " . date_default_timezone_get() . "\n";
echo "Data/Hora atual (PHP): " . date('Y-m-d H:i:s') . "\n";
echo "Data hoje (PHP): " . date('Y-m-d') . "\n";

// Consultar timezone do MySQL
$tzResult = $pdo->query("SELECT @@session.time_zone, @@global.time_zone;")->fetch(PDO::FETCH_ASSOC);
echo "Timezone MySQL session: {$tzResult['@@session.time_zone']}\n";
echo "Timezone MySQL global: {$tzResult['@@global.time_zone']}\n";

// Valores NOW() no MySQL
$nowResult = $pdo->query("SELECT NOW(), CURDATE(), CURTIME();")->fetch(PDO::FETCH_ASSOC);
echo "NOW() MySQL: {$nowResult['NOW()']}\n";
echo "CURDATE() MySQL: {$nowResult['CURDATE()']}\n";

// 4. Verificar se há filtro por timezone em outras tabelas
echo "\n\n⚙️  RECOMENDAÇÕES:\n";
echo str_repeat("-", 100) . "\n";

if ($stats['incorretas'] > 0) {
    echo "🔴 PROBLEMA CONFIRMADO - Há matrículas com data_inicio diferente de data_matricula\n\n";
    
    if ($stats['futuras'] > 0) {
        echo "📌 As datas_inicio estão adiantadas (no futuro) em relação à criação\n";
        echo "    Causa provável: Erro no cálculo de datas ou timezone mal configurado\n";
        echo "    Localização suspeita: MobileController::comprarPlano() ou webhook\n";
    }
    
    echo "\n✅ AÇÕES RECOMENDADAS:\n";
    echo "   1. Verificar o código de compra de plano em MobileController::comprarPlano()\n";
    echo "   2. Verificar se há modulos com strtotime() que podem estar adicionando dias\n";
    echo "   3. Confirmar timezone configurado em config/database.php e .env\n";
    echo "   4. Testar novo purchase-plano após correção com --verbose\n";
} else {
    echo "✅ Nenhum problema encontrado - Todas as datas estão corretas!\n";
}

echo "\n" . str_repeat("=", 100) . "\n\n";
