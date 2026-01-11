<?php
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$pdo = new PDO(
    "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
    $_ENV['DB_USER'],
    $_ENV['DB_PASS'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "=== Verificando tipos_baixa ===\n";
$stmt = $pdo->query("SELECT * FROM tipos_baixa");
$tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Total de tipos: " . count($tipos) . "\n";
print_r($tipos);

echo "\n=== Testando query completa ===\n";
$sql = "SELECT 
            p.id,
            p.tipo_baixa_id,
            tb.nome as tipo_baixa_nome
        FROM pagamentos_plano p
        LEFT JOIN tipos_baixa tb ON p.tipo_baixa_id = tb.id
        WHERE p.id IN (1, 2, 3, 4, 5)
        ORDER BY p.id";
$stmt = $pdo->query($sql);
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($result);
