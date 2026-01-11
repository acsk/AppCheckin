<?php
// Script para debugar validação de token

require __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$secret = 'seu_secret_aqui_mude_em_producao';

// Payload
$now = time();
$payload = [
    'user_id' => 11,
    'tenantId' => 4,
    'name' => 'Carolina Ferreira',
    'email' => 'carolina.ferreira@tenant4.com',
    'iat' => $now,
    'exp' => $now + 3600
];

echo "=== TESTE DE TOKEN ===\n\n";

// 1. Codificar
$token = JWT::encode($payload, $secret, 'HS256');
echo "Token gerado: $token\n\n";

// 2. Decodificar
try {
    $decoded = JWT::decode($token, new Key($secret, 'HS256'));
    echo "✅ Token decodificado com sucesso!\n";
    echo "Dados do token:\n";
    echo json_encode($decoded, JSON_PRETTY_PRINT) . "\n";
} catch (\Exception $e) {
    echo "❌ Erro ao decodificar: " . $e->getMessage() . "\n";
}
