<?php
/**
 * Concede papel Super Admin (4) a um usuário existente.
 *
 * Uso:
 *   php database/grant_superadmin.php --email=andrecabrall@gmail.com
 *   php database/grant_superadmin.php --usuario-id=3
 *   php database/grant_superadmin.php --email=andrecabrall@gmail.com --tenant-id=2
 */

$db = require __DIR__ . '/../config/database.php';

$opts = getopt('', ['email:', 'usuario-id:', 'tenant-id:']);
$email = isset($opts['email']) ? mb_strtolower(trim((string) $opts['email']), 'UTF-8') : '';
$usuarioId = isset($opts['usuario-id']) ? (int) $opts['usuario-id'] : 0;
$tenantId = isset($opts['tenant-id']) ? (int) $opts['tenant-id'] : 0;

if ($email === '' && $usuarioId <= 0) {
    echo "Informe --email= ou --usuario-id=\n";
    exit(1);
}

try {
    if ($usuarioId <= 0) {
        $stmt = $db->prepare('SELECT id, nome, email FROM usuarios WHERE email = ? OR email_global = ? LIMIT 1');
        $stmt->execute([$email, $email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$usuario) {
            echo "Usuário não encontrado: {$email}\n";
            exit(1);
        }
        $usuarioId = (int) $usuario['id'];
    } else {
        $stmt = $db->prepare('SELECT id, nome, email FROM usuarios WHERE id = ? LIMIT 1');
        $stmt->execute([$usuarioId]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$usuario) {
            echo "Usuário não encontrado: id {$usuarioId}\n";
            exit(1);
        }
    }

    if ($tenantId <= 0) {
        $stmtTenant = $db->prepare('
            SELECT tenant_id
            FROM tenant_usuario_papel
            WHERE usuario_id = ? AND ativo = 1
            ORDER BY papel_id DESC, tenant_id ASC
            LIMIT 1
        ');
        $stmtTenant->execute([$usuarioId]);
        $row = $stmtTenant->fetch(PDO::FETCH_ASSOC);
        $tenantId = $row ? (int) $row['tenant_id'] : 1;
    }

    $check = $db->prepare('
        SELECT id FROM tenant_usuario_papel
        WHERE usuario_id = ? AND tenant_id = ? AND papel_id = 4 AND ativo = 1
        LIMIT 1
    ');
    $check->execute([$usuarioId, $tenantId]);
    if ($check->fetch()) {
        echo "Usuário #{$usuarioId} ({$usuario['email']}) já possui Super Admin no tenant {$tenantId}.\n";
        exit(0);
    }

    $insert = $db->prepare('
        INSERT INTO tenant_usuario_papel (tenant_id, usuario_id, papel_id, ativo, created_at, updated_at)
        VALUES (?, ?, 4, 1, NOW(), NOW())
    ');
    $insert->execute([$tenantId, $usuarioId]);

    echo "Super Admin concedido.\n";
    echo "  Usuário: #{$usuarioId} — {$usuario['nome']} ({$usuario['email']})\n";
    echo "  Tenant:  {$tenantId}\n";
    echo "Faça logout e login novamente no painel.\n";
} catch (PDOException $e) {
    echo 'Erro: ' . $e->getMessage() . "\n";
    exit(1);
}
