<?php
header('Content-Type: application/json');
echo json_encode(['hello' => 'world', 'time' => date('Y-m-d H:i:s')]);
