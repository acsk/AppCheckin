<?php
/**
 * AppCheckin API - Main Entry Point
 * Redireciona para public/index.php (Slim Framework)
 */

// Definir o arquivo principal
// Trace simples: registrar última requisição (para diagnóstico)
try {
	$trace = sprintf("[%s] %s %s\n", date('Y-m-d H:i:s'), $_SERVER['REQUEST_METHOD'] ?? '-', $_SERVER['REQUEST_URI'] ?? '-');
	@file_put_contents(__DIR__ . '/public/last_root_request.txt', $trace, FILE_APPEND);
} catch (\Throwable $e) {
	// silencioso
}

require_once __DIR__ . '/public/index.php';

