<?php
$db = new PDO('mysql:host=mysql;dbname=appcheckin', 'root', 'root', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

echo "Colunas da tabela dias:\n";
$result = $db->query('DESCRIBE dias');
$columns = $result->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $col) {
    echo "- " . $col['Field'] . " (" . $col['Type'] . ")\n";
}

echo "\nDados da tabela dias:\n";
$result = $db->query('SELECT * FROM dias LIMIT 10');
$dias = $result->fetchAll(PDO::FETCH_ASSOC);

foreach ($dias as $d) {
    echo json_encode($d) . "\n";
}
?>
