<?php
/**
 * Diagnostica login e (opcional) redefine a senha do usuário para o CPF (só dígitos).
 *
 * Uso:
 *   php scripts/reset_senha_cpf_usuario.php --user=316
 *   php scripts/reset_senha_cpf_usuario.php --user=316 --apply
 *   php scripts/reset_senha_cpf_usuario.php --email=aluna@email.com --apply
 *
 * Banco: usa config/database.php (local) ou, se existir, config/database.remote.php
 * com --prod
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$args = array_slice($argv ?? [], 1);
$userId = null;
$email = null;
$apply = false;
$useProd = false;

foreach ($args as $arg) {
    if (preg_match('/^--user=(\d+)$/', $arg, $m)) {
        $userId = (int) $m[1];
    } elseif (preg_match('/^--email=(.+)$/', $arg, $m)) {
        $email = mb_strtolower(trim($m[1]), 'UTF-8');
    } elseif ($arg === '--apply') {
        $apply = true;
    } elseif ($arg === '--prod') {
        $useProd = true;
    }
}

if ($userId === null && $email === null) {
    fwrite(STDERR, "Informe --user=ID ou --email=...\n");
    exit(1);
}

require_once __DIR__ . '/../vendor/autoload.php';

if ($useProd) {
    $remote = __DIR__ . '/../config/database.remote.php';
    $remoteLocal = __DIR__ . '/../config/database.remote.local.php';
    if (file_exists($remoteLocal)) {
        $db = require $remoteLocal;
    } elseif (file_exists($remote)) {
        $db = require $remote;
    } else {
        fwrite(STDERR, "Sem config remota. Crie config/database.remote.local.php ou use o banco local.\n");
        exit(1);
    }
} else {
    $db = require __DIR__ . '/../config/database.php';
}

if ($userId !== null) {
    $stmt = $db->prepare('SELECT id, nome, email, email_global, cpf, ativo, senha_hash, created_at, updated_at FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
} else {
    $stmt = $db->prepare("SELECT id, nome, email, email_global, cpf, ativo, senha_hash, created_at, updated_at FROM usuarios WHERE LOWER(TRIM(email)) = ? OR LOWER(TRIM(COALESCE(email_global, ''))) = ? LIMIT 1");
    $stmt->execute([$email, $email]);
}

$u = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$u) {
    fwrite(STDERR, "Usuário não encontrado.\n");
    exit(1);
}

$cpf = preg_replace('/[^0-9]/', '', (string) ($u['cpf'] ?? '')) ?: '';
$hash = (string) ($u['senha_hash'] ?? '');

echo "=== Usuário #{$u['id']} ===\n";
echo "Nome: {$u['nome']}\n";
echo "Email: {$u['email']}\n";
echo "Email global: " . ($u['email_global'] ?? 'NULL') . "\n";
echo "CPF: " . ($u['cpf'] ?? 'NULL') . " (digitos={$cpf})\n";
echo "Ativo: {$u['ativo']}\n";
echo "Hash len: " . strlen($hash) . "\n";
echo "Senha=CPF digitos? " . ($cpf !== '' && password_verify($cpf, $hash) ? 'SIM' : 'NAO') . "\n";
if ($cpf !== '' && strlen($cpf) === 11) {
    $fmt = substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    echo "Senha=CPF mascarado? " . (password_verify($fmt, $hash) ? 'SIM' : 'NAO') . "\n";
}

$stmtTup = $db->prepare('SELECT tup.tenant_id, t.nome, tup.papel_id, tup.ativo FROM tenant_usuario_papel tup LEFT JOIN tenants t ON t.id = tup.tenant_id WHERE tup.usuario_id = ?');
$stmtTup->execute([(int) $u['id']]);
$vinculos = $stmtTup->fetchAll(PDO::FETCH_ASSOC);
echo "Vínculos tenant: " . json_encode($vinculos, JSON_UNESCAPED_UNICODE) . "\n";

if ($cpf === '' || strlen($cpf) !== 11) {
    fwrite(STDERR, "CPF inválido/ausente — não é possível redefinir senha para CPF.\n");
    exit(1);
}

if (!$apply) {
    echo "\nDry-run. Para aplicar: acrescente --apply\n";
    exit(0);
}

$novoHash = password_hash($cpf, PASSWORD_BCRYPT);
$emailNorm = mb_strtolower(trim((string) $u['email']), 'UTF-8');
$upd = $db->prepare('UPDATE usuarios SET senha_hash = ?, email = ?, email_global = ?, updated_at = NOW() WHERE id = ?');
$upd->execute([$novoHash, $emailNorm, $emailNorm, (int) $u['id']]);

echo "\nSenha redefinida para o CPF (somente dígitos).\n";
echo "Verificação: " . (password_verify($cpf, $novoHash) ? 'OK' : 'FALHOU') . "\n";
echo "Login: email={$emailNorm} | senha={$cpf}\n";
