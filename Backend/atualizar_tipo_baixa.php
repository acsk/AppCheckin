<?php
/**
 * Script para atualizar pagamentos antigos com tipo_baixa_id
 * Define tipo_baixa_id = 1 (Manual) para pagamentos que foram baixados mas não têm tipo definido
 */

require_once __DIR__ . '/vendor/autoload.php';

// Carregar variáveis de ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Conectar ao banco
$host = $_ENV['DB_HOST'];
$dbname = $_ENV['DB_NAME'];
$user = $_ENV['DB_USER'];
$pass = $_ENV['DB_PASS'];

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    
    echo "================================================\n";
    echo "  Atualizando pagamentos antigos com tipo_baixa\n";
    echo "================================================\n\n";
    
    // Atualizar pagamentos que foram baixados (status = 2 - Pago) mas não têm tipo_baixa_id
    echo "Atualizando pagamentos pagos sem tipo de baixa...\n";
    $sql = "UPDATE pagamentos_plano 
            SET tipo_baixa_id = 1 
            WHERE status_pagamento_id = 2 
            AND baixado_por IS NOT NULL 
            AND tipo_baixa_id IS NULL";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $updated = $stmt->rowCount();
    
    echo "✅ {$updated} pagamento(s) atualizado(s) com tipo_baixa_id = 1 (Manual)\n\n";
    
    // Mostrar resumo
    echo "Resumo dos pagamentos por tipo de baixa:\n";
    $sql = "SELECT 
                tb.nome as tipo_baixa,
                COUNT(*) as total
            FROM pagamentos_plano p
            LEFT JOIN tipos_baixa tb ON p.tipo_baixa_id = tb.id
            WHERE p.status_pagamento_id = 2
            GROUP BY p.tipo_baixa_id, tb.nome
            ORDER BY p.tipo_baixa_id";
    
    $stmt = $pdo->query($sql);
    $resumo = $stmt->fetchAll();
    
    foreach ($resumo as $row) {
        $tipo = $row['tipo_baixa'] ?? 'Sem tipo definido';
        echo "  {$tipo}: {$row['total']} pagamento(s)\n";
    }
    
    echo "\n✅ Atualização concluída!\n";
    
} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
