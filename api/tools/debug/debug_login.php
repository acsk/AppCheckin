<?php
require_once 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$db = require 'config/database.php';

$email = 'carlos.mendes@temp.local';
$senha = '654321';

echo "=== DEBUG LOGIN ===\n\n";

// Buscar usuário
$stmt = $db->prepare('SELECT id, nome, email, email_global, senha_hash, ativo FROM usuarios WHERE email = :email OR email_global = :email2 LIMIT 1');
$stmt->execute(['email' => $email, 'email2' => $email]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if ($usuario) {
    echo "Usuario encontrado:\n";
    echo "ID: {$usuario['id']}\n";
    echo "Nome: {$usuario['nome']}\n";
    echo "Email: {$usuario['email']}\n";
    echo "Email Global: " . ($usuario['email_global'] ?? 'NULL') . "\n";
    echo "Ativo: {$usuario['ativo']}\n";
    echo "Senha Hash: " . $usuario['senha_hash'] . "\n";
    echo "\nVerificacao de senha:\n";
    echo "password_verify('654321'): " . (password_verify($senha, $usuario['senha_hash']) ? 'TRUE' : 'FALSE') . "\n";
    
    // Testar com hash manual
    echo "\nTeste hash:\n";
    $testHash = password_hash($senha, PASSWORD_BCRYPT);
    echo "Novo hash para '654321': $testHash\n";
} else {
    echo "Usuario NAO encontrado com email: $email\n";
    
    // Listar usuarios para verificar
    echo "\nListando ultimos 5 usuarios:\n";
    $stmt2 = $db->query('SELECT id, nome, email, email_global FROM usuarios ORDER BY id DESC LIMIT 5');
    while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
        echo "- ID: {$row['id']}, Email: {$row['email']}, Global: " . ($row['email_global'] ?? 'NULL') . "\n";
    }
}
