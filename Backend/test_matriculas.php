<?php
$db = new PDO('mysql:host=mysql;dbname=appcheckin', 'root', 'root', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$turmaId = 187;

// Testar a query de matriculas
try {
    echo "Testando query de matriculas...\n";
    $sql = "SELECT COUNT(DISTINCT usuario_id) as total FROM matriculas WHERE turma_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$turmaId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Sucesso! Total: " . $result['total'] . "\n";
} catch (Exception $e) {
    echo "❌ Erro em matriculas: " . $e->getMessage() . "\n";
}
?>
