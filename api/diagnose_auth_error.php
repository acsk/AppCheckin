<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== DIAGNÓSTICO AuthController ===\n\n";

// Teste 1: Carregar variáveis de ambiente
echo "1. Testando .env...\n";
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value);
        }
    }
    echo "   ✓ .env carregado\n";
} else {
    echo "   ✗ .env não encontrado\n";
}

// Teste 2: Verificar conexão com banco
echo "\n2. Testando conexão com banco...\n";
try {
    $db = require __DIR__ . '/config/database.php';
    echo "   ✓ Conexão estabelecida\n";
    
    // Teste 3: Verificar se tabela existe
    echo "\n3. Verificando tabelas...\n";
    $tables = ['usuarios', 'tenant_usuario_papel', 'tenants'];
    foreach ($tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "   ✓ Tabela $table existe\n";
        } else {
            echo "   ✗ Tabela $table NÃO existe\n";
        }
    }
    
    // Teste 4: Carregar Usuario Model
    echo "\n4. Testando Usuario Model...\n";
    require_once __DIR__ . '/app/Models/Usuario.php';
    $usuarioModel = new \App\Models\Usuario($db);
    echo "   ✓ Usuario Model instanciado\n";
    
    // Teste 5: Carregar JWTService
    echo "\n5. Testando JWTService...\n";
    require_once __DIR__ . '/app/Services/JWTService.php';
    
    if (empty($_ENV['JWT_SECRET'])) {
        echo "   ✗ JWT_SECRET não definido\n";
    } else {
        $jwtService = new \App\Services\JWTService($_ENV['JWT_SECRET']);
        echo "   ✓ JWTService instanciado\n";
    }
    
    // Teste 6: Simular construtor do AuthController
    echo "\n6. Simulando construtor do AuthController...\n";
    try {
        $db2 = require __DIR__ . '/config/database.php';
        $usuarioModel2 = new \App\Models\Usuario($db2);
        $jwtService2 = new \App\Services\JWTService($_ENV['JWT_SECRET']);
        echo "   ✓ Construtor simulado com sucesso\n";
    } catch (\Throwable $e) {
        echo "   ✗ ERRO no construtor: " . $e->getMessage() . "\n";
        echo "   Stack trace:\n";
        echo "   " . $e->getTraceAsString() . "\n";
    }
    
    echo "\n=== DIAGNÓSTICO CONCLUÍDO ===\n";
    
} catch (\Throwable $e) {
    echo "   ✗ ERRO: " . $e->getMessage() . "\n";
    echo "   Arquivo: " . $e->getFile() . "\n";
    echo "   Linha: " . $e->getLine() . "\n";
    echo "\n   Stack trace:\n";
    echo "   " . $e->getTraceAsString() . "\n";
}
