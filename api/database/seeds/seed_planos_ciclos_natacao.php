<?php
/**
 * Seed: Criar planos e ciclos de Natação
 * 
 * Baseado na tabela de preços:
 * - MENSAL: 3 aulas R$150, 2 aulas R$120, 1 aula R$70
 * - BIMESTRAL: 3 aulas R$240, 2 aulas R$200, 1 aula R$120
 * - QUADRIMESTRAL: 3 aulas R$400, 2 aulas R$360, 1 aula R$200
 * 
 * Execução:
 * php database/seeds/seed_planos_ciclos_natacao.php
 */

require_once __DIR__ . '/../../config/database.php';

// =====================================================================
// CONFIGURAÇÃO - ALTERE CONFORME NECESSÁRIO
// =====================================================================

$TENANT_ID = 1; // ID do tenant onde criar os planos

// Planos base (valor mensal de referência)
$planosBase = [
    [
        'nome' => '3 aulas por semana',
        'descricao' => 'Plano com 3 aulas semanais de natação',
        'checkins_semanais' => 3,
        'valor_mensal' => 150.00,
        'ciclos' => [
            'mensal' => 150.00,
            'bimestral' => 240.00,      // ~R$120/mês
            'quadrimestral' => 400.00,   // R$100/mês
        ]
    ],
    [
        'nome' => '2 aulas por semana',
        'descricao' => 'Plano com 2 aulas semanais de natação',
        'checkins_semanais' => 2,
        'valor_mensal' => 120.00,
        'ciclos' => [
            'mensal' => 120.00,
            'bimestral' => 200.00,      // R$100/mês
            'quadrimestral' => 360.00,   // R$90/mês
        ]
    ],
    [
        'nome' => '1 aula por semana',
        'descricao' => 'Plano com 1 aula semanal de natação',
        'checkins_semanais' => 1,
        'valor_mensal' => 70.00,
        'ciclos' => [
            'mensal' => 70.00,
            'bimestral' => 120.00,      // R$60/mês
            'quadrimestral' => 200.00,   // R$50/mês
        ]
    ]
];

// =====================================================================
// EXECUÇÃO
// =====================================================================

try {
    echo "=== Seed de Planos e Ciclos de Natação ===\n\n";
    
    // Verificar se tenant existe
    $stmtTenant = $pdo->prepare("SELECT id, nome FROM tenants WHERE id = ?");
    $stmtTenant->execute([$TENANT_ID]);
    $tenant = $stmtTenant->fetch(PDO::FETCH_ASSOC);
    
    if (!$tenant) {
        echo "❌ Tenant ID {$TENANT_ID} não encontrado!\n";
        exit(1);
    }
    
    echo "📍 Tenant: {$tenant['nome']} (ID: {$tenant['id']})\n\n";
    
    // Buscar tipos de ciclo
    $stmtTipos = $pdo->query("SELECT id, nome, codigo, meses FROM tipos_ciclo WHERE ativo = 1 ORDER BY ordem");
    $tiposCiclo = [];
    foreach ($stmtTipos->fetchAll(PDO::FETCH_ASSOC) as $tipo) {
        $tiposCiclo[$tipo['codigo']] = $tipo;
    }
    
    echo "Tipos de ciclo disponíveis:\n";
    foreach ($tiposCiclo as $codigo => $tipo) {
        echo "   - {$tipo['nome']} ({$tipo['meses']} meses)\n";
    }
    echo "\n";
    
    // Buscar ou criar modalidade Natação
    $stmtModalidade = $pdo->prepare("SELECT id FROM modalidades WHERE tenant_id = ? AND nome LIKE '%Nata%' LIMIT 1");
    $stmtModalidade->execute([$TENANT_ID]);
    $modalidade = $stmtModalidade->fetch(PDO::FETCH_ASSOC);
    
    $modalidadeId = null;
    if ($modalidade) {
        $modalidadeId = $modalidade['id'];
        echo "📋 Modalidade existente ID: {$modalidadeId}\n\n";
    } else {
        echo "ℹ️  Nenhuma modalidade de Natação encontrada, planos serão criados sem modalidade.\n\n";
    }
    
    // Preparar statements
    $stmtCheckPlano = $pdo->prepare("SELECT id FROM planos WHERE tenant_id = ? AND nome = ?");
    $stmtInsertPlano = $pdo->prepare("
        INSERT INTO planos (tenant_id, modalidade_id, nome, descricao, valor, duracao_dias, checkins_semanais, ativo)
        VALUES (?, ?, ?, ?, ?, 30, ?, 1)
    ");
    $stmtInsertCiclo = $pdo->prepare("
        INSERT INTO plano_ciclos (tenant_id, plano_id, tipo_ciclo_id, meses, valor, desconto_percentual, permite_recorrencia, ativo)
        VALUES (?, ?, ?, ?, ?, ?, 1, 1)
        ON DUPLICATE KEY UPDATE valor = VALUES(valor), desconto_percentual = VALUES(desconto_percentual)
    ");
    
    $planosProcessados = 0;
    $ciclosProcessados = 0;
    
    foreach ($planosBase as $planoData) {
        echo "📋 Processando: {$planoData['nome']}\n";
        
        // Verificar se plano já existe
        $stmtCheckPlano->execute([$TENANT_ID, $planoData['nome']]);
        $planoExistente = $stmtCheckPlano->fetch(PDO::FETCH_ASSOC);
        
        if ($planoExistente) {
            $planoId = $planoExistente['id'];
            echo "   ✓ Plano já existe (ID: {$planoId})\n";
        } else {
            // Criar plano
            $stmtInsertPlano->execute([
                $TENANT_ID,
                $modalidadeId,
                $planoData['nome'],
                $planoData['descricao'],
                $planoData['valor_mensal'],
                $planoData['checkins_semanais']
            ]);
            $planoId = $pdo->lastInsertId();
            echo "   ✅ Plano criado (ID: {$planoId})\n";
            $planosProcessados++;
        }
        
        // Criar ciclos
        foreach ($planoData['ciclos'] as $codigoCiclo => $valorCiclo) {
            if (!isset($tiposCiclo[$codigoCiclo])) {
                echo "   ⚠️  Tipo de ciclo '{$codigoCiclo}' não encontrado, pulando...\n";
                continue;
            }
            
            $tipo = $tiposCiclo[$codigoCiclo];
            
            // Calcular desconto em relação ao valor mensal
            $valorMensalSemDesconto = $planoData['valor_mensal'] * $tipo['meses'];
            $desconto = $valorMensalSemDesconto > 0 
                ? round((($valorMensalSemDesconto - $valorCiclo) / $valorMensalSemDesconto) * 100, 2)
                : 0;
            
            $stmtInsertCiclo->execute([
                $TENANT_ID,
                $planoId,
                $tipo['id'],
                $tipo['meses'],
                $valorCiclo,
                $desconto
            ]);
            
            $valorMensalEquiv = $valorCiclo / $tipo['meses'];
            $economiaTotal = $valorMensalSemDesconto - $valorCiclo;
            
            echo "   ✅ {$tipo['nome']}: R$ " . number_format($valorCiclo, 2, ',', '.');
            echo " (R$ " . number_format($valorMensalEquiv, 2, ',', '.') . "/mês";
            if ($economiaTotal > 0) {
                echo ", economia R$ " . number_format($economiaTotal, 2, ',', '.');
            }
            echo ")\n";
            
            $ciclosProcessados++;
        }
        
        echo "\n";
    }
    
    echo "=" . str_repeat("=", 50) . "\n";
    echo "✅ Resumo:\n";
    echo "   - Planos novos: {$planosProcessados}\n";
    echo "   - Ciclos criados/atualizados: {$ciclosProcessados}\n";
    echo "=" . str_repeat("=", 50) . "\n";
    
} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
