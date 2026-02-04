<?php

$novaSenha = '654321';
$hash = password_hash($novaSenha, PASSWORD_BCRYPT);

echo "Nova senha: $novaSenha\n";
echo "Novo hash: $hash\n\n";

// Conectar ao banco
$db = new PDO('mysql:host=appcheckin_mysql;dbname=appcheckin', 'root', 'root');

// Atualizar senha do usuário
$stmt = $db->prepare("UPDATE usuarios SET senha_hash = :hash WHERE id = 27");
$stmt->execute([':hash' => $hash]);

echo "Senha atualizada com sucesso!\n";
echo "Testando nova senha...\n";

// Buscar usuário atualizado
$stmt = $db->prepare("SELECT id, email, senha_hash FROM usuarios WHERE id = 27");
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Verificação: " . (password_verify($novaSenha, $user['senha_hash']) ? '✓ VÁLIDA' : '✗ INVÁLIDA') . "\n";
