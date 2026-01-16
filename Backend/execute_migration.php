<?php
// Script para executar a migration de constraint da tabela wods

$host = 'localhost';
$port = 3307;
$user = 'root';
$password = 'root';
$database = 'check_in_dev';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$database";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);

    echo "✅ Conectado ao banco de dados\n";

    // SQL da migration
    $sql = [
        "ALTER TABLE wods DROP KEY uq_tenant_data;",
        "ALTER TABLE wods ADD UNIQUE KEY uq_tenant_data_modalidade (tenant_id, data, modalidade_id);"
    ];

    foreach ($sql as $statement) {
        echo "Executando: $statement\n";
        $pdo->exec($statement);
        echo "✅ OK\n";
    }

    echo "\n✅ Migration executada com sucesso!\n";

} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
