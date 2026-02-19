<?php
require_once __DIR__ . '/../bootstrap.php';

try {
    $db = new \PDO(
        'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'],
        $_ENV['DB_USER'],
        $_ENV['DB_PASS'],
        [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
    );

    echo "═════════════════════════════════════════════════\n";
    echo "Verificando estrutura de pacote_contratos\n";
    echo "═════════════════════════════════════════════════\n\n";

    // Verificar se coluna existe
    $stmt = $db->prepare("
        SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_COMMENT
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'pacote_contratos'
        ORDER BY ORDINAL_POSITION
    ");
    $stmt->execute([$_ENV['DB_NAME']]);
    $colunas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    if (count($colunas) === 0) {
        echo "❌ Tabela pacote_contratos não encontrada\n";
    } else {
        echo "Colunas da tabela pacote_contratos:\n\n";
        foreach ($colunas as $col) {
            $marca = ($col['COLUMN_NAME'] === 'assinatura_id') ? '⭐ ' : '';
            echo "{$marca}{$col['COLUMN_NAME']}\n";
            echo "   Tipo: {$col['DATA_TYPE']}\n";
            echo "   Nulo: {$col['IS_NULLABLE']}\n";
            echo "   Padrão: " . ($col['COLUMN_DEFAULT'] ?? 'NULL') . "\n";
            if ($col['COLUMN_COMMENT']) {
                echo "   Comentário: {$col['COLUMN_COMMENT']}\n";
            }
            echo "\n";
        }
    }

    // Se a coluna não existir, mostrar como criá-la
    if (!in_array('assinatura_id', array_column($colunas, 'COLUMN_NAME'))) {
        echo "\n⚠️  COLUNA 'assinatura_id' NÃO ENCONTRADA!\n";
        echo "\n Para adicionar, execute:\n\n";
        echo "ALTER TABLE pacote_contratos ADD COLUMN assinatura_id INT NULL DEFAULT NULL AFTER payment_preference_id;\n\n";
        
        // Tentar criar automaticamente
        echo "Tentando criar coluna automaticamente...\n";
        $db->exec("ALTER TABLE pacote_contratos ADD COLUMN assinatura_id INT NULL DEFAULT NULL AFTER payment_preference_id");
        echo "✅ Coluna criada com sucesso!\n";
    } else {
        echo "\n✅ Coluna 'assinatura_id' EXISTE\n";
    }

} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
?>
