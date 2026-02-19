<?php
$envFile = __DIR__ . '/../.env';
if (!file_exists($envFile)) {
    die("❌ Arquivo .env não encontrado\n");
}

$env = parse_ini_file($envFile);
if (!is_array($env) || empty($env['DB_HOST'])) {
    die("❌ Erro ao ler .env\n");
}

try {
    $db = new PDO(
        'mysql:host=' . $env['DB_HOST'] . ';dbname=' . $env['DB_NAME'],
        $env['DB_USER'],
        $env['DB_PASS'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'pacote_contratos'";
    $stmt = $db->prepare($sql);
    $stmt->execute([$env['DB_NAME']]);
    $result = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (in_array('assinatura_id', $result)) {
        echo "✅ Coluna 'assinatura_id' EXISTE\n";
    } else {
        echo "❌ Coluna 'assinatura_id' NÃO EXISTE\n";
        echo "Colunas encontradas: " . implode(', ', $result) . "\n";
    }
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
