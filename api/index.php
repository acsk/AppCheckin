<?php
/**
 * AppCheckin API - Main Index
 */

// Debug: Mostrar estrutura
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar se público existe
$publicIndexPath = __DIR__ . '/public/index.php';

if (!file_exists($publicIndexPath)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Arquivo public/index.php não encontrado',
        'path' => $publicIndexPath,
        'dir' => __DIR__,
        'files' => scandir(__DIR__)
    ]);
    exit;
}

// Redirecionar para public/index.php
require $publicIndexPath;
