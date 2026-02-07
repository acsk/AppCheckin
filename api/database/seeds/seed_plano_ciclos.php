<?php
/**
 * Seed: Criar ciclos padrão para planos existentes
 * 
 * Cria ciclos mensal, trimestral, semestral e anual para todos os planos ativos
 * que ainda não possuem ciclos cadastrados.
 * 
 * Execução:
 * php database/seeds/seed_plano_ciclos.php
 */

require_once __DIR__ . '/../../config/database.php';

try {
    echo "=== Criando ciclos padrão para planos ===\n\n";
    
    // Buscar planos ativos que não têm ciclos
    $stmt = $pdo->query("
        SELECT p.id, p.tenant_id, p.nome, p.valor, p.duracao_dias
        FROM planos p
        WHERE p.ativo = 1 
        AND p.valor > 0
        AND NOT EXISTS (
            SELECT 1 FROM plano_ciclos pc WHERE pc.plano_id = p.id
        )
        ORDER BY p.tenant_id, p.id
    ");
    
    $planos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($planos)) {
        echo "ℹ️  Todos os planos já possuem ciclos cadastrados.\n";
        exit(0);
    }
    
    echo "Encontrados " . count($planos) . " plano(s) sem ciclos.\n\n";
    
    // Ciclos padrão com descontos
    $ciclosPadrao = [
        [
            'codigo' => 'mensal',
            'nome' => 'Mensal',
            'meses' => 1,
            'desconto' => 0,
            'ordem' => 1
        ],
        [
            'codigo' => 'trimestral',
            'nome' => 'Trimestral',
            'meses' => 3,
            'desconto' => 10, // 10% de desconto
            'ordem' => 2
        ],
        [
            'codigo' => 'semestral',
            'nome' => 'Semestral',
            'meses' => 6,
            'desconto' => 15, // 15% de desconto
            'ordem' => 3
        ],
        [
            'codigo' => 'anual',
            'nome' => 'Anual',
            'meses' => 12,
            'desconto' => 20, // 20% de desconto
            'ordem' => 4
        ]
    ];
    
    $stmtInsert = $pdo->prepare("
        INSERT INTO plano_ciclos 
        (tenant_id, plano_id, nome, codigo, meses, valor, desconto_percentual, permite_recorrencia, ativo, ordem)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, ?)
    ");
    
    $totalCiclos = 0;
    
    foreach ($planos as $plano) {
        echo "📋 Plano: {$plano['nome']} (ID: {$plano['id']}) - Valor base: R$ " . number_format($plano['valor'], 2, ',', '.') . "\n";
        
        foreach ($ciclosPadrao as $ciclo) {
            // Calcular valor do ciclo com desconto
            $valorMensalBase = $plano['valor'];
            $valorTotal = $valorMensalBase * $ciclo['meses'];
            $valorComDesconto = $valorTotal * (1 - ($ciclo['desconto'] / 100));
            
            $stmtInsert->execute([
                $plano['tenant_id'],
                $plano['id'],
                $ciclo['nome'],
                $ciclo['codigo'],
                $ciclo['meses'],
                round($valorComDesconto, 2),
                $ciclo['desconto'],
                $ciclo['ordem']
            ]);
            
            $economia = $valorTotal - $valorComDesconto;
            echo "   ✅ {$ciclo['nome']}: R$ " . number_format($valorComDesconto, 2, ',', '.');
            if ($economia > 0) {
                echo " (economia de R$ " . number_format($economia, 2, ',', '.') . ")";
            }
            echo "\n";
            
            $totalCiclos++;
        }
        
        echo "\n";
    }
    
    echo "✅ {$totalCiclos} ciclos criados com sucesso!\n";
    
} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
