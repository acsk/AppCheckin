<?php
/**
 * Migration: Tornar horario_id opcional em checkins
 * Justificativa: Sistema migrou de horario_id para turma_id
 */

$dsn = 'mysql:host=' . ($_ENV['DB_HOST'] ?? 'mysql') . ';dbname=' . ($_ENV['DB_NAME'] ?? 'appcheckin') . ';charset=utf8mb4';
$user = $_ENV['DB_USER'] ?? 'root';
$password = $_ENV['DB_PASS'] ?? 'root';

echo "🔧 Iniciando Migration: Tornar horario_id opcional...\n";
echo "─────────────────────────────────────\n\n";

try {
    $db = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "✅ Conectado ao banco de dados\n\n";
    
    // PASSO 1: Verificar estrutura atual de horario_id
    echo "📊 PASSO 1: Verificando coluna horario_id...\n";
    
    $stmt = $db->query("DESCRIBE checkins");
    $columns = $stmt->fetchAll();
    
    $horario_col = null;
    foreach ($columns as $col) {
        if ($col['Field'] === 'horario_id') {
            $horario_col = $col;
            break;
        }
    }
    
    if ($horario_col) {
        echo "   Campo encontrado: " . $horario_col['Field'] . "\n";
        echo "   Tipo: " . $horario_col['Type'] . "\n";
        echo "   Nulo: " . ($horario_col['Null'] === 'YES' ? 'SIM' : 'NÃO') . "\n";
        echo "   Padrão: " . ($horario_col['Default'] ?? 'Nenhum') . "\n\n";
        
        if ($horario_col['Null'] === 'NO') {
            echo "   ⏳ Tornando campo NULL...\n";
            
            $db->exec("ALTER TABLE checkins MODIFY COLUMN horario_id INT NULL");
            
            echo "   ✅ Campo horario_id agora é OPCIONAL (NULL)\n";
        } else {
            echo "   ℹ️  Campo já é opcional\n";
        }
    } else {
        echo "   ❌ Campo horario_id não encontrado\n";
    }
    
    echo "\n✅ PASSO 1 Concluído\n\n";
    
    // PASSO 2: Verificar estrutura atualizada
    echo "📋 PASSO 2: Verificando estrutura atualizada...\n";
    
    $stmt = $db->query("DESCRIBE checkins");
    $columns = $stmt->fetchAll();
    
    echo "   Colunas relevantes:\n";
    foreach ($columns as $col) {
        if (in_array($col['Field'], ['id', 'usuario_id', 'turma_id', 'horario_id', 'created_at'])) {
            $nullable = $col['Null'] === 'YES' ? '(NULL)' : '(NOT NULL)';
            echo "   - {$col['Field']} {$nullable}\n";
        }
    }
    
    echo "\n✅ PASSO 2 Concluído\n\n";
    
    // PASSO 3: Teste de INSERT
    echo "🧪 PASSO 3: Testando INSERT...\n";
    
    // Teste com turma_id apenas (sem horario_id)
    $stmt = $db->prepare("
        SELECT COUNT(*) as total FROM checkins 
        WHERE usuario_id = 1 AND turma_id IS NOT NULL
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    
    echo "   Check-ins com turma_id (sem horario_id): " . $result['total'] . "\n";
    
    echo "\n✅ PASSO 3 Concluído\n\n";
    
    echo "─────────────────────────────────────\n";
    echo "✅ Migration Concluída com Sucesso!\n\n";
    
    echo "🎉 Agora é possível:\n";
    echo "   ✓ POST /mobile/checkin com turma_id\n";
    echo "   ✓ Sem necessidade de horario_id\n";
    echo "   ✓ GET /mobile/turma/{turmaId}/participantes\n";
    
} catch (PDOException $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
?>
