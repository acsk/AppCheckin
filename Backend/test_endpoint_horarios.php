<?php
// Script para testar o endpoint com novo token

require __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;

$db = require __DIR__ . '/config/database.php';

// 1. Gerar JWT usando a mesma biblioteca do projeto
$userId = 11;
$tenantId = 4;
$nome = 'Carolina Ferreira';
$email = 'carolina.ferreira@tenant4.com';

$secret = 'seu_secret_aqui_mude_em_producao';
$now = time();
$expiry = $now + 3600;

// Payload
$payload = [
    'user_id' => $userId,
    'tenantId' => $tenantId,
    'name' => $nome,
    'email' => $email,
    'iat' => $now,
    'exp' => $expiry
];

$token = JWT::encode($payload, $secret, 'HS256');

echo "JWT Token gerado: $token\n\n";

// 2. Testar o endpoint
$url = "http://host.docker.internal:8080/mobile/horarios-disponiveis";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ Erro de conexão: $error\n";
    exit;
}

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n\n";

$data = json_decode($response, true);

echo "=== RESPOSTA DO ENDPOINT ===\n\n";

if (!isset($data['data']['turmas'])) {
    echo "❌ Erro: " . ($data['error'] ?? 'Erro desconhecido') . "\n";
    echo "Resposta completa: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit;
}

echo "✅ Turmas carregadas com sucesso\n";
echo "Total: " . count($data['data']['turmas']) . "\n\n";

foreach ($data['data']['turmas'] as $i => $turma) {
    echo "[" . ($i + 1) . "] " . $turma['nome'] . "\n";
    echo "    Alunos com check-in: " . $turma['alunos_inscritos'] . "\n";
    echo "    Vagas: " . $turma['alunos_inscritos'] . "/" . $turma['limite_alunos'] . "\n";
    echo "    Disponíveis: " . $turma['vagas_disponiveis'] . "\n\n";
}

echo "\n✅ Teste completo!\n";
