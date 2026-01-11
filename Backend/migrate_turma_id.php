<?php
/**
 * Migration: Adicionar coluna turma_id a tabela checkins
 */

// Carregar .env
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Configuração
$dsn = 'mysql:host=' . ($_ENV['DB_HOST'] ?? 'mysql') . ';dbname=' . ($_ENV['DB_NAME'] ?? 'appcheckin') . ';charset=utf8mb4';
$user = $_ENV['DB_USER'] ?? 'root';
$password = $_ENV['DB_PASS'] ?? 'root';

echo "🔧 Iniciando Migration...\n";
echo "─────────────────────────────────────\n";

try {
    // Conectar ao banco
    $db = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "✅ Conectado ao banco de dados\n\n";
    
    // PASSO 1: Verificar coluna turma_id
    echo "📊 PASSO 1: Verificando coluna turma_id...\n";
    
    $result = $db->query("SHOW COLUMNS FROM checkins LIKE 'turma_id'");
    
    if ($result->rowCount() === 0) {
        echo "   ⏳ Coluna NÃO encontrada. Adicionando...\n";
        
        // Adicionar coluna
        $db->exec("ALTER TABLE checkins ADD COLUMN turma_id INT NULL AFTER usuario_id");
        echo "   ✅ Coluna 'turma_id' adicionada\n";
        
        // Adicionar índice
        try {
            $db->exec("CREATE INDEX idx_checkins_turma ON checkins(turma_id)");
            echo "   ✅ Índice 'idx_checkins_turma' criado\n";
        } catch (PDOException $e) {
            echo "   ℹ️  Índice já existe (ignorado)\n";
        }
        
        // Adicionar foreign key
        try {
            $db->exec("ALTER TABLE checkins ADD CONSTRAINT fk_checkins_turma FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE");
            echo "   ✅ Foreign key 'fk_checkins_turma' adicionada\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate key') !== false) {
                echo "   ℹ️  Foreign key já existe (ignorado)\n";
            } else {
                throw $e;
            }
        }
    } else {
        echo "   ℹ️  Coluna 'turma_id' já existe\n";
    }
    
    echo "\n✅ PASSO 1 Concluído\n\n";
    
    // PASSO 2: Verificar estrutura
    echo "📋 PASSO 2: Verificando estrutura da tabela...\n";
    
    $stmt = $db->query("DESCRIBE checkins");
    $columns = $stmt->fetchAll();
    
    $column_names = array_column($columns, 'Field');
    
    echo "   Colunas encontradas:\n";
    foreach ($column_names as $col) {
        $status = in_array($col, ['turma_id', 'usuario_id', 'horario_id']) ? '✅' : '  ';
        echo "   $status $col\n";
    }
    
    echo "\n✅ PASSO 2 Concluído\n\n";
    
    // PASSO 3: Estatísticas
    echo "📈 PASSO 3: Estatísticas do banco...\n";
    
    // Total de check-ins
    $stmt = $db->query("SELECT COUNT(*) as total FROM checkins");
    $result = $stmt->fetch();
    echo "   Total de check-ins: " . $result['total'] . "\n";
    
    // Turmas ativas no tenant 4
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM turmas WHERE tenant_id = 4 AND ativo = 1");
    $stmt->execute();
    $result = $stmt->fetch();
    echo "   Turmas ativas (tenant 4): " . $result['total'] . "\n";
    
    // Usuários
    $stmt = $db->query("SELECT COUNT(*) as total FROM usuarios");
    $result = $stmt->fetch();
    echo "   Total de usuários: " . $result['total'] . "\n";
    
    echo "\n✅ PASSO 3 Concluído\n\n";
    
    // PASSO 4: Teste de conectividade
    echo "🧪 PASSO 4: Teste de Query...\n";
    
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            GROUP_CONCAT(DISTINCT turma_id) as turmas_com_checkin
        FROM checkins 
        WHERE turma_id IS NOT NULL
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    
    echo "   Check-ins com turma_id: " . ($result['total'] ?? 0) . "\n";
    echo "   Turmas com check-in: " . ($result['turmas_com_checkin'] ?? 'Nenhuma') . "\n";
    
    echo "\n✅ PASSO 4 Concluído\n\n";
    
    echo "─────────────────────────────────────\n";
    echo "✅ Migration Concluída com Sucesso!\n\n";
    
    echo "🎉 Sistema pronto para:\n";
    echo "   ✓ GET /mobile/turma/{turmaId}/participantes\n";
    echo "   ✓ POST /mobile/checkin\n";
    echo "   ✓ GET /mobile/horarios-disponiveis\n";
    
} catch (PDOException $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
?>
