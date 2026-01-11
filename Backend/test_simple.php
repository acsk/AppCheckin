<?php
$db = new PDO('mysql:host=mysql;dbname=appcheckin', 'root', 'root', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$turmaId = 187;

// Query simples
$sql = "SELECT id, nome, horario_inicio, horario_fim FROM turmas WHERE id = ?";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute([$turmaId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "✅ Sucesso!\n";
        echo "Turma ID: " . $result['id'] . "\n";
        echo "Nome: " . $result['nome'] . "\n";
        echo "Horário: " . $result['horario_inicio'] . " - " . $result['horario_fim'] . "\n";
    } else {
        echo "❌ Turma não encontrada\n";
    }
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>
