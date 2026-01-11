<?php
$db = new PDO('mysql:host=mysql;dbname=appcheckin', 'root', 'root', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

echo "Colunas da tabela planos:\n";
$result = $db->query('DESCRIBE planos');
$columns = $result->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $col) {
    echo "- " . $col['Field'] . " (" . $col['Type'] . ")\n";
}

echo "\n\nColunas da tabela turmas:\n";
$result = $db->query('DESCRIBE turmas');
$columns = $result->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $col) {
    echo "- " . $col['Field'] . " (" . $col['Type'] . ")\n";
}

echo "\n\nQuery para traçar relação turmas -> planos -> matriculas:\n";
$sql = "SELECT t.id as turma_id, p.id as plano_id, m.id as matricula_id, u.nome 
        FROM turmas t 
        LEFT JOIN planos p ON p.turma_id = t.id 
        LEFT JOIN matriculas m ON m.plano_id = p.id 
        LEFT JOIN usuarios u ON m.usuario_id = u.id 
        WHERE t.id = 187 LIMIT 5";

try {
    $result = $db->query($sql);
    $data = $result->fetchAll(PDO::FETCH_ASSOC);
    foreach ($data as $row) {
        echo json_encode($row) . "\n";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
?>
