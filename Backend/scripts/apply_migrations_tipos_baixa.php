<?php
/**
 * Script para aplicar as migrations de tipos de baixa
 */

require_once __DIR__ . '/vendor/autoload.php';

// Carregar variáveis de ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Conectar ao banco
$host = $_ENV['DB_HOST'];
$dbname = $_ENV['DB_NAME'];
$user = $_ENV['DB_USER'];
$pass = $_ENV['DB_PASS'];

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    
    echo "========================================\n";
    echo "  Aplicando Migrations de Tipos de Baixa\n";
    echo "========================================\n\n";
    
    // Migration 051 - Criar tabela tipos_baixa
    echo "Aplicando migration 051_create_tipos_baixa_table.sql...\n";
    $sql051 = file_get_contents(__DIR__ . '/database/migrations/051_create_tipos_baixa_table.sql');
    $pdo->exec($sql051);
    echo "✅ Migration 051 aplicada com sucesso!\n\n";
    
    // Migration 052 - Adicionar campo tipo_baixa_id
    echo "Aplicando migration 052_add_tipo_baixa_to_pagamentos_plano.sql...\n";
    $sql052 = file_get_contents(__DIR__ . '/database/migrations/052_add_tipo_baixa_to_pagamentos_plano.sql');
    $pdo->exec($sql052);
    echo "✅ Migration 052 aplicada com sucesso!\n\n";
    
    echo "========================================\n";
    echo "  Migrations aplicadas com sucesso! ✅\n";
    echo "========================================\n\n";
    
    echo "Tabela 'tipos_baixa' criada com os seguintes registros:\n";
    echo "  1 - Manual\n";
    echo "  2 - Automática\n";
    echo "  3 - Importação\n";
    echo "  4 - Integração\n\n";
    
    echo "Campo 'tipo_baixa_id' adicionado à tabela 'pagamentos_plano'\n\n";
    
    // Verificar os tipos de baixa inseridos
    $stmt = $pdo->query("SELECT id, nome, descricao FROM tipos_baixa ORDER BY id");
    $tipos = $stmt->fetchAll();
    
    echo "Tipos de baixa cadastrados no banco:\n";
    foreach ($tipos as $tipo) {
        echo "  [{$tipo['id']}] {$tipo['nome']} - {$tipo['descricao']}\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
