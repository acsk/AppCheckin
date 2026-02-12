<?php
// Arquivo de diagnóstico simples
echo "Diagnóstico da API AppCheckin\n";
echo "==============================\n\n";

// 1. Verificar PHP
echo "PHP Version: " . phpversion() . "\n";

// 2. Verificar Autoload
echo "Autoload exists: " . (file_exists(__DIR__ . '/../vendor/autoload.php') ? 'SIM' : 'NÃO') . "\n";

// 3. Tentar carregar autoload
try {
    require __DIR__ . '/../vendor/autoload.php';
    echo "Autoload carregado: SIM\n";
} catch (\Exception $e) {
    echo "Erro ao carregar autoload: " . $e->getMessage() . "\n";
}

// 4. Verificar .env
echo ".env exists: " . (file_exists(__DIR__ . '/../.env') ? 'SIM' : 'NÃO') . "\n";

// 5. Tentar carregar .env
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
    echo ".env carregado: SIM\n";
} catch (\Exception $e) {
    echo "Erro ao carregar .env: " . $e->getMessage() . "\n";
}

// 6. Tentar criar Slim app
try {
    $app = \Slim\Factory\AppFactory::create();
    echo "Slim app criado: SIM\n";
} catch (\Exception $e) {
    echo "Erro ao criar Slim app: " . $e->getMessage() . "\n";
}

echo "\nRequisição atual:\n";
echo "Method: " . $_SERVER['REQUEST_METHOD'] . "\n";
echo "Path: " . $_SERVER['REQUEST_URI'] . "\n";
?>
