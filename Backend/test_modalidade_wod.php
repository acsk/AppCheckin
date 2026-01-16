<?php
// Test WOD creation with modalidade_id

require_once __DIR__ . '/vendor/autoload.php';

use App\Database\Connection;
use App\Models\Wod;

try {
    $pdo = Connection::getInstance()->getConnection();
    
    // Verificar se a coluna existe
    echo "=== Verificando estrutura da tabela ===\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM wods WHERE Field = 'modalidade_id'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($column) {
        echo "✓ Coluna modalidade_id existe:\n";
        print_r($column);
        echo "\n";
    } else {
        echo "✗ Coluna modalidade_id NÃO existe!\n";
        echo "Criando coluna...\n";
        $pdo->exec("ALTER TABLE wods ADD COLUMN modalidade_id INT NULL AFTER tenant_id");
        $pdo->exec("ALTER TABLE wods ADD INDEX idx_wods_modalidade (modalidade_id)");
        echo "✓ Coluna criada!\n\n";
    }
    
    // Testar inserção
    echo "=== Testando inserção de WOD ===\n";
    $wodModel = new Wod($pdo);
    
    $testData = [
        'modalidade_id' => 5,
        'data' => '2026-01-16',
        'titulo' => 'Teste Modalidade',
        'descricao' => 'Teste de gravação de modalidade',
        'status' => 'draft',
        'criado_por' => 1,
    ];
    
    $wodId = $wodModel->create($testData, 4); // tenant 4
    
    if ($wodId) {
        echo "✓ WOD criado com ID: $wodId\n";
        
        // Verificar se modalidade_id foi salva
        $stmt = $pdo->prepare("SELECT id, modalidade_id, titulo FROM wods WHERE id = ?");
        $stmt->execute([$wodId]);
        $savedWod = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "\nDados salvos:\n";
        print_r($savedWod);
        
        if ($savedWod['modalidade_id'] == 5) {
            echo "\n✓ modalidade_id foi salva corretamente!\n";
        } else {
            echo "\n✗ modalidade_id NÃO foi salva! Valor: " . var_export($savedWod['modalidade_id'], true) . "\n";
        }
        
        // Limpar teste
        $pdo->exec("DELETE FROM wods WHERE id = $wodId");
        echo "\n✓ WOD de teste removido\n";
    } else {
        echo "✗ Erro ao criar WOD\n";
    }
    
} catch (Exception $e) {
    echo "\n✗ ERRO: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
