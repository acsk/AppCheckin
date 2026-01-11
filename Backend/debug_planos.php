<?php

$conn = new PDO('mysql:host=appcheckin_mysql;dbname=app_checkin', 'root', 'root');

echo "=== TODOS OS PLANOS DO TENANT 5 ===\n";
$sql = "SELECT id, nome, ativo, atual FROM planos WHERE tenant_id = 5 ORDER BY valor ASC";
$result = $conn->query($sql);
$todos = $result->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($todos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== APENAS PLANOS COM atual = 1 ===\n";
$sql = "SELECT id, nome, ativo, atual FROM planos WHERE tenant_id = 5 AND atual = 1 ORDER BY valor ASC";
$result = $conn->query($sql);
$atuais = $result->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($atuais, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "Total com atual=1: " . count($atuais) . "\n";
