<?php
// Este arquivo deve ser acessível sem passar pelo .htaccess
// Testa se PHP está funcionando

http_response_code(200);
header('Content-Type: application/json; charset=utf-8');

$response = [
    'status' => 'ok',
    'test' => 'PHP is working',
    'php_version' => phpversion(),
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'request_uri' => $_SERVER['REQUEST_URI']
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
