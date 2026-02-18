<?php
/**
 * Script para criar tabela de webhook payloads do Mercado Pago
 * 
 * Uso: php database/create_webhook_payloads_table.php
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = require __DIR__ . '/../config/database.php';
    
    $sql = "CREATE TABLE IF NOT EXISTS webhook_payloads_mercadopago (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT UNSIGNED,
        tipo VARCHAR(50),
        data_id BIGINT UNSIGNED,
        external_reference VARCHAR(255),
        payment_id BIGINT UNSIGNED NULL,
        preapproval_id VARCHAR(255) NULL,
        status VARCHAR(50),
        erro_processamento VARCHAR(500) NULL,
        payload LONGTEXT NOT NULL COMMENT 'Payload completo em JSON',
        resultado_processamento LONGTEXT NULL COMMENT 'Resultado do processamento em JSON',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_tenant_id (tenant_id),
        INDEX idx_tipo (tipo),
        INDEX idx_data_id (data_id),
        INDEX idx_external_reference (external_reference),
        INDEX idx_payment_id (payment_id),
        INDEX idx_preapproval_id (preapproval_id),
        INDEX idx_status (status),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Armazena payloads completos dos webhooks do Mercado Pago para auditoria e debug'";
    
    $db->exec($sql);
    
    echo "✅ Tabela webhook_payloads_mercadopago criada com sucesso!\n";
    echo "📋 A tabela foi criada com os seguintes campos:\n";
    echo "   - id: ID único\n";
    echo "   - tenant_id: ID do tenant\n";
    echo "   - tipo: Tipo de notificação (payment, subscription_preapproval)\n";
    echo "   - data_id: ID do objeto (payment_id ou preapproval_id)\n";
    echo "   - external_reference: Referência externa\n";
    echo "   - payment_id: ID do pagamento\n";
    echo "   - preapproval_id: ID da assinatura\n";
    echo "   - status: Status do processamento (sucesso, erro)\n";
    echo "   - erro_processamento: Mensagem de erro se houver\n";
    echo "   - payload: Payload completo em JSON\n";
    echo "   - resultado_processamento: Resultado do processamento em JSON\n";
    echo "   - created_at: Data de criação\n";
    echo "   - updated_at: Data de atualização\n";
    echo "\n";
    
    // Verificar se tabela foi criada
    $result = $db->query("SHOW TABLES LIKE 'webhook_payloads_mercadopago'");
    if ($result->rowCount() > 0) {
        echo "✅ Verificação: Tabela foi criada com sucesso!\n";
    }
    
} catch (\PDOException $e) {
    echo "❌ Erro ao criar tabela: " . $e->getMessage() . "\n";
    exit(1);
}
