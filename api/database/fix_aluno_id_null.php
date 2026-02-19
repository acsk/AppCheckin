<?php

// Conectar ao banco
$host = 'localhost';
$user = 'u304177849_api';
$password = 'U3043177849xyzP@ss';
$db = 'u304177849_api';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Executar ALTER
    $pdo->exec("ALTER TABLE assinaturas MODIFY COLUMN aluno_id INT NULL");
    
    echo "✅ Sucesso! Coluna aluno_id agora permite NULL\n";
    
    // Verificar
    $result = $pdo->query("DESCRIBE assinaturas")->fetchAll(PDO::FETCH_ASSOC);
    $alunoIdField = array_filter($result, fn($r) => $r['Field'] === 'aluno_id')[0] ?? null;
    
    if ($alunoIdField) {
        echo "✓ Status: " . $alunoIdField['Null'] . " (permitindo NULL)\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
