<?php
/**
 * Conexão PDO com o MySQL remoto (Hostinger).
 *
 * Copie para database.remote.php e preencha as credenciais (não commitar senha).
 * Ou defina variáveis de ambiente: PROD_DB_HOST, PROD_DB_NAME, PROD_DB_USER, PROD_DB_PASS.
 *
 * Host: srv1314.hstgr.io  (ou IP 193.203.175.71)
 * Banco API produção: u304177849_api
 * Banco curso (se aplicável): u304177849_curso
 *
 * Uso em scripts de debug:
 *   $pdo = require __DIR__ . '/config/database.remote.php';
 */

declare(strict_types=1);

$host = getenv('PROD_DB_HOST') ?: 'srv1314.hstgr.io';
$port = (int) (getenv('PROD_DB_PORT') ?: 3306);
$dbname = getenv('PROD_DB_NAME') ?: 'u304177849_api';
$user = getenv('PROD_DB_USER') ?: 'u304177849_api';
$pass = getenv('PROD_DB_PASS') ?: '';

if ($pass === '' && file_exists(__DIR__ . '/database.remote.local.php')) {
  return require __DIR__ . '/database.remote.local.php';
}

if ($pass === '') {
    throw new RuntimeException(
        'Defina PROD_DB_PASS ou crie config/database.remote.local.php com a conexão PDO.'
    );
}

return new PDO(
    "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
    $user,
    $pass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);
