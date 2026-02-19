<?php
/**
 * Script para executar migração de adição de colunas de pacote
 * Uso: php database/2026_02_19_add_pacote_columns.php
 */

require_once __DIR__ . '/../config/database.php';

try {
    echo "═════════════════════════════════════════════════════════════\n";
    echo "🔄 Executando migração: Adicionar colunas de pacote\n";
    echo "═════════════════════════════════════════════════════════════\n\n";

    // Ler o arquivo SQL
    $sqlFile = __DIR__ . '/migrations/2026_02_19_add_pacote_columns.sql';
    
    if (!file_exists($sqlFile)) {
        throw new Exception("Arquivo de migração não encontrado: {$sqlFile}");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Executar cada statement
    $statements = array_filter(array_map('trim', explode(';', $sql)), function($s) {
        return !empty($s) && !str_starts_with($s, '--');
    });
    
    foreach ($statements as $statement) {
        if (empty(trim($statement))) {
            continue;
        }
        
        try {
            $db->exec($statement);
            echo "✅ Statement executado\n";
        } catch (Exception $e) {
            error_log("Erro ao executar statement: " . $e->getMessage());
            // Continuar mesmo com erro para tratamento de "já existe"
            echo "⚠️  " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n═════════════════════════════════════════════════════════════\n";
    echo "✅ Migração concluída com sucesso!\n";
    echo "═════════════════════════════════════════════════════════════\n\n";
    
    // Verificar colunas criadas
    echo "📊 Verificando colunas:\n\n";
    
    $verificacoes = [
        'matriculas' => ['pacote_contrato_id', 'valor_rateado'],
        'pagamentos_plano' => ['pacote_contrato_id'],
        'pacote_beneficiarios' => ['matricula_id', 'status', 'valor_rateado']
    ];
    
    foreach ($verificacoes as $tabela => $colunas) {
        echo "📋 Tabela: {$tabela}\n";
        
        foreach ($colunas as $coluna) {
            $stmt = $db->prepare("
                SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
            ");
            $stmt->execute([$_ENV['DB_NAME'], $tabela, $coluna]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($result) {
                echo "   ✅ {$coluna} ({$result['COLUMN_TYPE']}) - NULL: {$result['IS_NULLABLE']}\n";
            } else {
                echo "   ❌ {$coluna} - NÃO ENCONTRADA\n";
            }
        }
        echo "\n";
    }
    
    echo "✨ Tudo pronto para usar as novas colunas!\n";
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack: " . $e->getTraceAsString() . "\n";
    exit(1);
}
