<?php
/**
 * Migration: Adicionar coluna pacote_contrato_id à tabela assinaturas
 * 
 * Esta coluna armazena o ID do pacote (pacote_contratos.id) para webhooks de assinatura
 * recorrente de pacotes. Permite recuperar o pacote quando o webhook de pagamento
 * chega com metadados vazios.
 */

$db = require 'config/database.php';

try {
    echo "[Migration] Verificando coluna 'pacote_contrato_id' em 'assinaturas'...\n";
    
    // Verificar se coluna já existe
    $stmtCheck = $db->query("DESC assinaturas");
    $colunas = $stmtCheck->fetchAll(PDO::FETCH_COLUMN, 0);
    
    if (in_array('pacote_contrato_id', $colunas)) {
        echo "✅ Coluna 'pacote_contrato_id' já existe em 'assinaturas'\n";
        exit(0);
    }
    
    echo "➕ Adicionando coluna 'pacote_contrato_id'...\n";
    
    // Adicionar coluna
    $sql = "ALTER TABLE assinaturas ADD COLUMN pacote_contrato_id INT NULL DEFAULT NULL COMMENT 'ID do pacote para assinaturas recorrentes de pacotes' AFTER gateway_assinatura_id";
    
    $db->exec($sql);
    
    echo "✅ Coluna 'pacote_contrato_id' adicionada com sucesso!\n";
    echo "\n📋 Detalhes:\n";
    echo "   - Tabela: assinaturas\n";
    echo "   - Coluna: pacote_contrato_id\n";
    echo "   - Tipo: INT\n";
    echo "   - Nulo: SIM\n";
    echo "   - Padrão: NULL\n";
    
} catch (\PDOException $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
