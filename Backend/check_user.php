<?php
// Verificar se o usuário existe no banco

$db = require __DIR__ . '/config/database.php';

$userId = 11;

$stmt = $db->prepare("
    SELECT 
        u.id, 
        u.nome, 
        u.email,
        ut.tenant_id,
        ut.status as tenant_status
    FROM usuarios u
    LEFT JOIN usuario_tenant ut ON ut.usuario_id = u.id AND ut.status = 'ativo'
    WHERE u.id = :user_id
");

$stmt->execute(['user_id' => $userId]);
$usuario = $stmt->fetch(\PDO::FETCH_ASSOC);

if ($usuario) {
    echo "✅ Usuário encontrado:\n";
    echo json_encode($usuario, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo "❌ Usuário NÃO encontrado\n";
}
