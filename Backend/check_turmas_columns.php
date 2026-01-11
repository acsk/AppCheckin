<?php
$db = new PDO('mysql:host=mysql;dbname=appcheckin', 'root', 'root', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$result = $db->query('DESCRIBE turmas');
$columns = $result->fetchAll(PDO::FETCH_ASSOC);

echo "Colunas da tabela turmas:\n";
foreach ($columns as $col) {
    echo "- " . $col['Field'] . " (" . $col['Type'] . ") " . ($col['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
}
?>
