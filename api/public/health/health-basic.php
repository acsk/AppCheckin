<?php
header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'php' => 'running',
    'timestamp' => date('Y-m-d H:i:s'),
]);
?>
