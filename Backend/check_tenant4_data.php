<?php
$db = new PDO('mysql:host=mysql;dbname=appcheckin', 'root', 'root', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

echo "Turmas do tenant 4:\n";
$sql = "SELECT id, nome FROM turmas WHERE tenant_id = 4 LIMIT 10";
$result = $db->query($sql);
$turmas = $result->fetchAll(PDO::FETCH_ASSOC);

foreach ($turmas as $t) {
    echo "- ID: " . $t['id'] . " | " . $t['nome'] . "\n";
}

echo "\n\nCheck-ins do tenant 4:\n";
$sql = "SELECT DISTINCT turma_id FROM checkins WHERE tenant_id = 4 LIMIT 10";
$result = $db->query($sql);
$checkins = $result->fetchAll(PDO::FETCH_ASSOC);

foreach ($checkins as $c) {
    echo "- turma_id: " . $c['turma_id'] . "\n";
}

echo "\nTotal de check-ins tenant 4: ";
$sql = "SELECT COUNT(*) as total FROM checkins WHERE tenant_id = 4";
$result = $db->query($sql);
$count = $result->fetch(PDO::FETCH_ASSOC);
echo $count['total'] . "\n";
?>
