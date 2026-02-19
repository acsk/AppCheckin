<?php
/**
 * Migração: Adicionar coluna assinatura_id em pacote_contratos
 * 
 * Necessária para armazenar o ID da assinatura criada quando um contrato é pago
 */

require_once __DIR__ . '/../bootstrap.php';

try {
    $db = new \PDO(
        'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'],
        $_ENV['DB_USER'],
        $_ENV['DB_PASS'],
        [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
    );

    echo "═════════════════════════════════════════════════════════════\n";
    echo "🔄 Adicionando coluna assinatura_id em pacote_contratos\n";
    echo "═════════════════════════════════════════════════════════════\n\n";

    // Verificar se coluna já existe
    $stmt = $db->prepare("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'pacote_contratos'
        AND COLUMN_NAME = 'assinatura_id'
    ");
    $stmt->execute([$_ENV['DB_NAME']]);
    
    if ($stmt->fetch()) {
        echo "✅ Coluna 'assinatura_id' já existe em pacote_contratos\n";
    } else {
        echo "➕ Adicionando coluna 'assinatura_id'...\n";
        
        $sql = "ALTER TABLE pacote_contratos
                ADD COLUMN assinatura_id INT NULL DEFAULT NULL COMMENT 'ID da assinatura criada para este contrato'
                AFTER payment_preference_id";
        
        $db->exec($sql);
        
        echo "✅ Coluna adicionada com sucesso!\n";
        
        // Adicionar índice para melhor performance
        $db->exec("CREATE INDEX idx_pacote_contratos_assinatura_id ON pacote_contratos(assinatura_id)");
        echo "✅ Índice criado\n";
    }

    echo "\n📋 Resumo:\n";
    echo "   - Tabela: pacote_contratos\n";
    echo "   - Coluna: assinatura_id\n";
    echo "   - Tipo: INT\n";
    echo "   - Nulo: SIM\n";
    echo "   - Padrão: NULL\n";
    echo "\n✅ Migração concluída com sucesso!\n";
    echo "═════════════════════════════════════════════════════════════\n";

} catch (\PDOException $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
