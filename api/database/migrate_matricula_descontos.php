<?php
/**
 * Migration: Criar tabela matricula_descontos
 * Execução: php database/migrate_matricula_descontos.php
 */

require __DIR__ . '/../vendor/autoload.php';

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$db = require __DIR__ . '/../config/database.php';

echo "=== Migração: matricula_descontos ===\n\n";

try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS matricula_descontos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL,
            matricula_id INT NOT NULL,
            tipo ENUM('primeira_mensalidade', 'recorrente') NOT NULL,
            valor DECIMAL(10,2) NULL,
            percentual DECIMAL(5,2) NULL,
            vigencia_inicio DATE NOT NULL,
            vigencia_fim DATE NULL,
            parcelas_restantes INT NULL,
            motivo VARCHAR(255) NOT NULL,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            criado_por INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (matricula_id) REFERENCES matriculas(id) ON DELETE CASCADE,
            INDEX idx_tenant_matricula (tenant_id, matricula_id, ativo),
            INDEX idx_vigencia (tenant_id, ativo, vigencia_inicio, vigencia_fim)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Tabela matricula_descontos criada\n";

} catch (\PDOException $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== Migração concluída ===\n";
