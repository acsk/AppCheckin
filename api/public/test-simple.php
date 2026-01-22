<?php
// Teste SIMPLES sem Slim
header('Content-Type: application/json');

echo json_encode([
    'test' => 'OK',
    'php_version' => phpversion(),
    'file' => __FILE__,
    'method' => $_SERVER['REQUEST_METHOD'],
    'timestamp' => date('Y-m-d H:i:s')
]);
?>
