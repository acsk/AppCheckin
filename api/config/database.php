<?php

// Helper para obter variável de ambiente com fallback de nomes
if (!function_exists('env_or_server')) {
    function env_or_server(string $key, array $fallbacks = []): ?string {
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return $_SERVER[$key];
        foreach ($fallbacks as $alt) {
            if (isset($_ENV[$alt]) && $_ENV[$alt] !== '') return $_ENV[$alt];
            if (isset($_SERVER[$alt]) && $_SERVER[$alt] !== '') return $_SERVER[$alt];
            $val = getenv($alt);
            if ($val !== false && $val !== '') return $val;
        }
        $val = getenv($key);
        return ($val !== false && $val !== '') ? $val : null;
    }
}

$host = env_or_server('DB_HOST', ['MYSQL_HOST', 'DBHOST']);
$dbname = env_or_server('DB_NAME', ['MYSQL_DATABASE', 'DBNAME']);
$user = env_or_server('DB_USER', ['MYSQL_USER', 'DBUSER', 'DB_USERNAME']);
$pass = env_or_server('DB_PASS', ['MYSQL_PASSWORD', 'DBPASS', 'DB_PASSWORD']);

// Em jobs/CLI o ambiente pode não carregar .env automaticamente
if (!$host || !$dbname || !$user) {
    $rootPath = realpath(__DIR__ . '/..');
    $envFile = $rootPath ? $rootPath . '/.env' : null;
    if ($envFile && file_exists($envFile)) {
        $autoload = $rootPath . '/vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }

        if (class_exists('Dotenv\\Dotenv')) {
            try {
                $dotenv = Dotenv\Dotenv::createImmutable($rootPath);
                $dotenv->safeLoad();
            } catch (Throwable $e) {
                error_log("[database.php] Falha ao carregar .env: " . $e->getMessage());
            }
        }

        // Recarregar variáveis após tentar carregar .env
        $host = env_or_server('DB_HOST', ['MYSQL_HOST', 'DBHOST']);
        $dbname = env_or_server('DB_NAME', ['MYSQL_DATABASE', 'DBNAME']);
        $user = env_or_server('DB_USER', ['MYSQL_USER', 'DBUSER', 'DB_USERNAME']);
        $pass = env_or_server('DB_PASS', ['MYSQL_PASSWORD', 'DBPASS', 'DB_PASSWORD']);
    }
}

if (!$host || !$dbname || !$user) {
    error_log("[database.php] Variáveis de ambiente de DB ausentes: host=" . ($host ?? 'null') . ", dbname=" . ($dbname ?? 'null') . ", user=" . ($user ?? 'null'));
}

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '-03:00'"
        ]
    );
    
    // Garantir UTF-8 na conexão
    $pdo->exec("SET CHARACTER SET utf8mb4");
    
    return $pdo;
} catch (PDOException $e) {
    error_log("[database.php] Erro ao conectar ao banco de dados: " . $e->getMessage());
    throw $e;
}
