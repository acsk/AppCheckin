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
    
    // Buscar tipos de ciclo disponíveis
    $stmtTipos = $pdo->query("SELECT id, nome, codigo, meses, ordem FROM tipos_ciclo WHERE ativo = 1 ORDER BY ordem ASC");
    $tiposCiclo = $stmtTipos->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($tiposCiclo)) {
        echo "❌ Nenhum tipo de ciclo encontrado. Execute a migration primeiro.\n";
        exit(1);
    }
    
    echo "Tipos de ciclo disponíveis: " . count($tiposCiclo) . "\n";
    foreach ($tiposCiclo as $tipo) {
        echo "   - {$tipo['nome']} ({$tipo['meses']} meses)\n";
    }
    echo "\n";
    
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
    
    // Descontos padrão por código de ciclo
    $descontosPadrao = [
        'mensal' => 0,
        'trimestral' => 10,  // 10% de desconto
        'semestral' => 15,   // 15% de desconto
        'anual' => 20        // 20% de desconto
    ];
    
    $stmtInsert = $pdo->prepare("
        INSERT INTO plano_ciclos 
        (tenant_id, plano_id, tipo_ciclo_id, meses, valor, desconto_percentual, permite_recorrencia, ativo)
        VALUES (?, ?, ?, ?, ?, ?, 1, 1)
    ");
    
    $totalCiclos = 0;
    
    foreach ($planos as $plano) {
        echo "📋 Plano: {$plano['nome']} (ID: {$plano['id']}) - Valor base: R$ " . number_format($plano['valor'], 2, ',', '.') . "\n";
        
        foreach ($tiposCiclo as $tipo) {
            // Calcular valor do ciclo com desconto
            $desconto = $descontosPadrao[$tipo['codigo']] ?? 0;
            $valorMensalBase = $plano['valor'];
            $valorTotal = $valorMensalBase * $tipo['meses'];
            $valorComDesconto = $valorTotal * (1 - ($desconto / 100));
            
            $stmtInsert->execute([
                $plano['tenant_id'],
                $plano['id'],
                $tipo['id'],
                $tipo['meses'],
                round($valorComDesconto, 2),
                $desconto
            ]);
            
            $economia = $valorTotal - $valorComDesconto;
            echo "   ✅ {$tipo['nome']}: R$ " . number_format($valorComDesconto, 2, ',', '.');
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
