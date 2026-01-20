<?php
/**
 * Job: Popular tabela 'dias' com todos os dias de 2026
 * 
 * Execução:
 *   php jobs/PopularDias2026.php
 */

try {
    $db = require __DIR__ . '/../config/database.php';
    
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "🔄 INICIANDO JOB: Popular Dias 2026\n";
    echo "Data/Hora: " . date('Y-m-d H:i:s') . "\n";
    echo str_repeat('=', 60) . "\n\n";
    
    // 1. Gerar todas as datas de 2026
    $dataInicio = new DateTime('2026-01-01');
    $dataFim = new DateTime('2026-12-31');
    $intervalo = new DateInterval('P1D');
    $periodo = new DatePeriod($dataInicio, $intervalo, $dataFim->modify('+1 day'));
    
    $datas = [];
    foreach ($periodo as $data) {
        $datas[] = $data->format('Y-m-d');
    }
    
    echo "📅 Total de dias a inserir: " . count($datas) . "\n\n";
    
    // 2. Verificar quantos dias já existem
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM dias WHERE YEAR(data) = 2026");
    $stmt->execute();
    $resultado = $stmt->fetch();
    $diasExistentes = $resultado['total'] ?? 0;
    
    echo "📊 Dias já existentes em 2026: $diasExistentes\n";
    
    if ($diasExistentes > 0) {
        echo "⚠️  Removendo dias existentes de 2026...\n";
        $stmtDelete = $db->prepare("DELETE FROM dias WHERE YEAR(data) = 2026");
        $stmtDelete->execute();
        echo "✅ Dias antigos removidos\n\n";
    }
    
    // 3. Inserir os dias
    echo "💾 INSERINDO DIAS:\n";
    echo str_repeat('-', 60) . "\n";
    
    $sql = "INSERT INTO dias (data, ativo, created_at) VALUES (:data, 1, NOW())";
    $stmt = $db->prepare($sql);
    
    $inseridos = 0;
    $erros = 0;
    
    try {
        $db->beginTransaction();
        
        foreach ($datas as $data) {
            try {
                $stmt->execute(['data' => $data]);
                $inseridos++;
            } catch (Exception $e) {
                $erros++;
                error_log("Erro ao inserir dia $data: " . $e->getMessage());
            }
        }
        
        $db->commit();
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
    echo "✅ Dias inseridos com sucesso: $inseridos\n";
    if ($erros > 0) {
        echo "❌ Erros ao inserir: $erros\n";
    }
    echo "\n";
    
    // 4. Verificar resultado final
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM dias WHERE YEAR(data) = 2026");
    $stmt->execute();
    $resultado = $stmt->fetch();
    $totalFinal = $resultado['total'] ?? 0;
    
    echo "📈 RESULTADO FINAL:\n";
    echo str_repeat('-', 60) . "\n";
    echo "Total de dias em 2026 na tabela: $totalFinal\n";
    
    // 5. Mostrar alguns exemplos
    echo "\n📝 EXEMPLOS DE DIAS INSERIDOS:\n";
    echo str_repeat('-', 60) . "\n";
    
    $stmt = $db->prepare("
        SELECT data, ativo, created_at 
        FROM dias 
        WHERE YEAR(data) = 2026 
        ORDER BY data 
        LIMIT 10
    ");
    $stmt->execute();
    $exemplos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($exemplos as $dia) {
        $diaDaSemana = date('l', strtotime($dia['data'])); // Dia da semana em inglês
        $nomesDia = [
            'Monday' => 'Segunda',
            'Tuesday' => 'Terça',
            'Wednesday' => 'Quarta',
            'Thursday' => 'Quinta',
            'Friday' => 'Sexta',
            'Saturday' => 'Sábado',
            'Sunday' => 'Domingo'
        ];
        $nomePT = $nomesDia[$diaDaSemana] ?? $diaDaSemana;
        
        echo sprintf("  • %s (%s) - Ativo: %s\n", 
            $dia['data'],
            $nomePT,
            $dia['ativo'] ? 'Sim' : 'Não'
        );
    }
    
    // 6. Conclusão
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "✅ JOB FINALIZADO COM SUCESSO!\n";
    echo "Fim: " . date('Y-m-d H:i:s') . "\n";
    echo str_repeat('=', 60) . "\n\n";
    
    exit(0);
    
} catch (Exception $e) {
    echo "\n❌ ERRO ao processar:\n";
    echo "   Mensagem: " . $e->getMessage() . "\n";
    echo "   Arquivo: " . $e->getFile() . "\n";
    echo "   Linha: " . $e->getLine() . "\n";
    exit(1);
}
?>
