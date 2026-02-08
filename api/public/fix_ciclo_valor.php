<?php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../config/database.php';
    
    // Atualizar o valor do ciclo 11 para R$ 0.50 (mínimo do MP)
    $stmt = $pdo->prepare("UPDATE plano_ciclos SET valor = 0.50 WHERE id = 11");
    $stmt->execute();
    
    // Verificar
    $stmt = $pdo->prepare("SELECT id, plano_id, assinatura_frequencia_id, valor, ativo FROM plano_ciclos WHERE id = 11");
    $stmt->execute();
    $ciclo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Ciclo atualizado para R$ 0.50',
        'ciclo' => $ciclo
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
