<?php
/**
 * Script para gerar ciclos para todos os planos que ainda não têm
 * Execute em produção: curl https://seudominio.com/gerar_ciclos_planos.php
 */
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../config/database.php';
    
    // Buscar todos os planos ativos
    $stmtPlanos = $pdo->query("
        SELECT p.id, p.tenant_id, p.nome, p.valor, p.duracao_dias
        FROM planos p
        WHERE p.ativo = 1
    ");
    $planos = $stmtPlanos->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar tipos de ciclo
    $stmtTipos = $pdo->query("SELECT id, codigo, meses FROM assinatura_frequencias WHERE ativo = 1 ORDER BY ordem");
    $tiposCiclo = $stmtTipos->fetchAll(PDO::FETCH_ASSOC);
    
    $ciclosCriados = 0;
    $erros = [];
    $detalhes = [];
    
    foreach ($planos as $plano) {
        $valorMensal = $plano['valor']; // Valor base mensal do plano
        
        foreach ($tiposCiclo as $tipo) {
            // Verificar se já existe este ciclo para este plano
            $stmtCheck = $pdo->prepare("
                SELECT id FROM plano_ciclos 
                WHERE plano_id = ? AND assinatura_frequencia_id = ?
            ");
            $stmtCheck->execute([$plano['id'], $tipo['id']]);
            
            if ($stmtCheck->fetch()) {
                continue; // Já existe, pular
            }
            
            // Calcular valor do ciclo
            $meses = (int) $tipo['meses'];
            
            // Aplicar descontos progressivos para ciclos mais longos
            $descontoPercentual = 0;
            switch ($meses) {
                case 1: $descontoPercentual = 0; break;      // Mensal: sem desconto
                case 2: $descontoPercentual = 3; break;      // Bimestral: 3% desconto
                case 3: $descontoPercentual = 5; break;      // Trimestral: 5% desconto
                case 4: $descontoPercentual = 7; break;      // Quadrimestral: 7% desconto
                case 6: $descontoPercentual = 10; break;     // Semestral: 10% desconto
                case 12: $descontoPercentual = 15; break;    // Anual: 15% desconto
            }
            
            // Valor total = valor mensal × meses × (1 - desconto%)
            $valorTotal = $valorMensal * $meses * (1 - $descontoPercentual / 100);
            $valorTotal = round($valorTotal, 2);
            
            // Só permitir recorrência para mensal
            $permiteRecorrencia = ($meses == 1) ? 1 : 0;
            
            // Inserir ciclo
            try {
                $stmtInsert = $pdo->prepare("
                    INSERT INTO plano_ciclos 
                    (tenant_id, plano_id, assinatura_frequencia_id, meses, valor, desconto_percentual, permite_recorrencia, ativo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmtInsert->execute([
                    $plano['tenant_id'],
                    $plano['id'],
                    $tipo['id'],
                    $meses,
                    $valorTotal,
                    $descontoPercentual,
                    $permiteRecorrencia
                ]);
                
                $ciclosCriados++;
                $detalhes[] = "Plano {$plano['id']} ({$plano['nome']}): ciclo {$tipo['codigo']} criado - R$ " . number_format($valorTotal, 2, ',', '.');
                
            } catch (Exception $e) {
                $erros[] = "Erro ao criar ciclo {$tipo['codigo']} para plano {$plano['id']}: " . $e->getMessage();
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Ciclos gerados com sucesso",
        'total_planos' => count($planos),
        'assinatura_frequencias' => count($tiposCiclo),
        'ciclos_criados' => $ciclosCriados,
        'detalhes' => $detalhes,
        'erros' => $erros
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
