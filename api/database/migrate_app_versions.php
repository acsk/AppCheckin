<?php
/**
 * Cria a tabela app_versions e o registro inicial do app mobile.
 *
 * Execução (Hostinger / Slim):
 *   php database/migrate_app_versions.php
 */

require __DIR__.'/../vendor/autoload.php';

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__.'/..');
$dotenv->safeLoad();

/** @var PDO $db */
$db = require __DIR__.'/../config/database.php';

echo "=== Tabela app_versions ===\n\n";

try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS app_versions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            app VARCHAR(32) NOT NULL,
            version INT UNSIGNED NOT NULL DEFAULT 1,
            `release` INT UNSIGNED NOT NULL DEFAULT 0,
            release_date DATE NOT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_app_versions_app (app)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Tabela app_versions ok.\n";

    $exists = $db->query("SELECT id FROM app_versions WHERE app = 'mobile' LIMIT 1")->fetchColumn();
    if ($exists) {
        echo "ℹ️  Registro mobile já existe (id {$exists}).\n";
    } else {
        $db->exec("
            INSERT INTO app_versions (app, version, `release`, release_date, created_at, updated_at)
            VALUES ('mobile', 1, 0, CURDATE(), NOW(), NOW())
        ");
        echo "✅ Registro mobile v1.0 inserido.\n";
    }
} catch (PDOException $e) {
    echo '❌ Erro: '.$e->getMessage()."\n";
    exit(1);
}

echo "\n=== Concluído ===\n";
