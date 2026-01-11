<?php
$db = new PDO('mysql:host=mysql;dbname=appcheckin', 'root', 'root', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

echo "Colunas da tabela checkins:\n";
$result = $db->query('DESCRIBE checkins');
$columns = $result->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $col) {
    echo "- " . $col['Field'] . " (" . $col['Type'] . ")\n";
}

echo "\n\nExemplos de check-ins:\n";
$result = $db->query('SELECT * FROM checkins LIMIT 3');
$rows = $result->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    echo json_encode($row) . "\n";
}

echo "\n\nAlunos que fizeram check-in na turma 187:\n";
$sql = "SELECT DISTINCT usuario_id FROM checkins WHERE turma_id = 187 LIMIT 10";
try {
    $result = $db->query($sql);
    $rows = $result->fetchAll(PDO::FETCH_ASSOC);
    echo "Total: " . count($rows) . "\n";
    foreach ($rows as $row) {
        echo "- usuario_id: " . $row['usuario_id'] . "\n";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
?>
