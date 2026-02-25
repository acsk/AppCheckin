<?php

declare(strict_types=1);

/**
 * Funções auxiliares globais
 */

/**
 * Retorna resposta JSON padronizada
 */
function jsonResponse(mixed $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

/**
 * Lê o body JSON da requisição
 */
function getJsonInput(): array
{
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    return is_array($data) ? $data : [];
}

/**
 * Gera um ID único para transação
 */
function generateTransactionId(string $prefix = 'pay'): string
{
    return $prefix . '_' . bin2hex(random_bytes(12));
}

/**
 * Lê dados de um arquivo JSON (simula banco de dados)
 */
function readJsonFile(string $filename): array
{
    $path = __DIR__ . '/../data/' . $filename;
    if (!file_exists($path)) {
        return [];
    }
    $content = file_get_contents($path);
    return json_decode($content, true) ?? [];
}

/**
 * Salva dados em um arquivo JSON
 */
function writeJsonFile(string $filename, array $data): void
{
    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($dir . '/' . $filename, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Registra log de atividade
 */
function logActivity(string $type, array $data): void
{
    $logs = readJsonFile('activity_log.json');
    $logs[] = [
        'id' => generateTransactionId('log'),
        'type' => $type,
        'data' => $data,
        'timestamp' => date('Y-m-d\TH:i:s.vP'),
    ];
    // Manter somente últimos 500 registros
    $logs = array_slice($logs, -500);
    writeJsonFile('activity_log.json', $logs);
}
