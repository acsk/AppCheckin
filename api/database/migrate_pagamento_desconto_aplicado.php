<?php
/**
 * Migration: Melhoria no sistema de descontos
 * - Adiciona valor_original em pagamentos_plano
 * - Cria tabela pivot pagamento_desconto_aplicado
 */

require_once __DIR__ . '/../vendor/autoload.php';

$db = require __DIR__ . '/../config/database.php';

try {
    echo "=== Migration: Melhoria Descontos ===\n\n";

    // 1. Adicionar valor_original se não existe
    $cols = $db->query("SHOW COLUMNS FROM pagamentos_plano LIKE 'valor_original'")->fetchAll();
    if (empty($cols)) {
        $db->exec("ALTER TABLE pagamentos_plano ADD COLUMN valor_original DECIMAL(10,2) NULL AFTER valor");
        echo "✅ Coluna valor_original adicionada em pagamentos_plano\n";

        // Backfill
        $affected = $db->exec("UPDATE pagamentos_plano SET valor_original = valor + COALESCE(desconto, 0) WHERE valor_original IS NULL");
        echo "✅ Backfill: {$affected} registros atualizados (valor_original = valor + desconto)\n";
    } else {
        echo "⏩ Coluna valor_original já existe\n";
    }

    // 2. Criar tabela pivot
    $tables = $db->query("SHOW TABLES LIKE 'pagamento_desconto_aplicado'")->fetchAll();
    if (empty($tables)) {
        $db->exec("
            CREATE TABLE pagamento_desconto_aplicado (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pagamento_plano_id INT NOT NULL,
                matricula_desconto_id INT NOT NULL,
                valor_desconto DECIMAL(10,2) NOT NULL COMMENT 'Quanto este desconto abateu nesta parcela',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (pagamento_plano_id) REFERENCES pagamentos_plano(id) ON DELETE CASCADE,
                FOREIGN KEY (matricula_desconto_id) REFERENCES matricula_descontos(id) ON DELETE CASCADE,
                INDEX idx_pagamento (pagamento_plano_id),
                INDEX idx_desconto (matricula_desconto_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✅ Tabela pagamento_desconto_aplicado criada\n";
    } else {
        echo "⏩ Tabela pagamento_desconto_aplicado já existe\n";
    }

    echo "\n=== Migration concluída ===\n";
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
