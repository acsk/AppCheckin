<?php
/**
 * Script para testar cálculo proporcional de upgrade/downgrade de plano
 * 
 * Cenários de teste:
 * 1. UPGRADE: Plano 2x/semana (R$100) -> 4x/semana (R$180) = COBRAR diferença
 * 2. DOWNGRADE: Plano 4x/semana (R$180) -> 2x/semana (R$100) = CREDITAR diferença
 * 3. IGUAL: Plano 2x/semana (R$100) -> Plano 2x/semana (R$100) = SEM AJUSTE
 */

require_once __DIR__ . '/vendor/autoload.php';

$db = require __DIR__ . '/config/database.php';

echo "\n=== TESTE DE CÁLCULO PROPORCIONAL DE PLANO ===\n\n";

// Função de teste
function testarCalculo($db, $planoIdAnterior, $planoIdNovo, $dataVencimento) {
    // Buscar planos
    $stmtPlan1 = $db->prepare("SELECT id, nome, valor, duracao_dias FROM planos WHERE id = ?");
    $stmtPlan1->execute([$planoIdAnterior]);
    $planoAnt = $stmtPlan1->fetch();
    
    $stmtPlan2 = $db->prepare("SELECT id, nome, valor, duracao_dias FROM planos WHERE id = ?");
    $stmtPlan2->execute([$planoIdNovo]);
    $planoNov = $stmtPlan2->fetch();
    
    if (!$planoAnt || !$planoNov) {
        echo "❌ Planos não encontrados\n";
        return;
    }
    
    // Calcular
    $hoje = date('Y-m-d');
    $dataVencimentoObj = new \DateTime($dataVencimento);
    $dataHojeObj = new \DateTime($hoje);
    
    $intervalo = $dataHojeObj->diff($dataVencimentoObj);
    $diasRestantes = $intervalo->days;
    
    $valorDiarioAnt = $planoAnt['valor'] / $planoAnt['duracao_dias'];
    $valorDiarioNov = $planoNov['valor'] / $planoNov['duracao_dias'];
    
    $diferenca = ($valorDiarioNov - $valorDiarioAnt) * $diasRestantes;
    $diferenca = round($diferenca, 2);
    
    // Determinar tipo
    if ($diferenca > 0) {
        $tipo = 'UPGRADE ⬆️';
        $acao = "COBRAR R$ {$diferenca}";
    } elseif ($diferenca < 0) {
        $tipo = 'DOWNGRADE ⬇️';
        $acao = "CREDITAR R$ " . abs($diferenca);
    } else {
        $tipo = 'IGUAL ➡️';
        $acao = "SEM AJUSTE";
    }
    
    echo "📊 Teste: {$planoAnt['nome']} → {$planoNov['nome']}\n";
    echo "────────────────────────────────────────\n";
    echo "Plano Anterior: {$planoAnt['nome']} | R$ {$planoAnt['valor']}/mês\n";
    echo "Plano Novo:    {$planoNov['nome']} | R$ {$planoNov['valor']}/mês\n";
    echo "Data Vencimento: {$dataVencimento} (ainda {$diasRestantes} dias)\n\n";
    echo "Cálculo:\n";
    echo "  • Valor diário antigo: R$ " . number_format($valorDiarioAnt, 2, ',', '.') . "\n";
    echo "  • Valor diário novo:   R$ " . number_format($valorDiarioNov, 2, ',', '.') . "\n";
    echo "  • Diferença/dia:       R$ " . number_format($valorDiarioNov - $valorDiarioAnt, 2, ',', '.') . "\n";
    echo "  • Dias restantes:      {$diasRestantes}\n";
    echo "  • Total proporcional:  R$ " . number_format(abs($diferenca), 2, ',', '.') . "\n\n";
    echo "Resultado: {$tipo}\n";
    echo "Ação: {$acao}\n";
    echo "════════════════════════════════════════\n\n";
}

// Teste 1: UPGRADE
echo "🧪 TESTE 1: UPGRADE\n";
testarCalculo($db, 1, 2, date('Y-m-d', strtotime('+15 days')));

// Teste 2: DOWNGRADE  
echo "🧪 TESTE 2: DOWNGRADE\n";
testarCalculo($db, 2, 1, date('Y-m-d', strtotime('+20 days')));

// Teste 3: IGUAL
echo "🧪 TESTE 3: MESMO PLANO\n";
testarCalculo($db, 1, 1, date('Y-m-d', strtotime('+10 days')));

echo "\n✅ Testes concluídos!\n";
?>
