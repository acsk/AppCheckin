<?php
/**
 * Script para recriar SuperAdmin após limpeza
 * 
 * Uso:
 * php database/create_superadmin.php
 * 
 * ⚠️ IMPORTANTE: Ajuste as credenciais abaixo conforme necessário
 */

// Configurar conexão
$db = require __DIR__ . '/../config/database.php';

// Credenciais do SuperAdmin a criar
$email = 'admin@appcheckin.com';
$senha = 'SuperAdmin@2024!'; // MUDE ISSO EM PRODUÇÃO
$nome = 'Super Admin';
$cpf = '00000000000'; // Usar algo genérico para SuperAdmin
$telefone = '0000000000';
$role_id = 3; // 3 = SuperAdmin
$tenant_id = 1; // Tenant padrão

try {
    // 1. Verificar se SuperAdmin já existe
    $checkStmt = $db->prepare("SELECT id FROM usuarios WHERE email = ? AND role_id = 3");
    $checkStmt->execute([$email]);
    $existing = $checkStmt->fetch();
    
    if ($existing) {
        echo "⚠️  SuperAdmin com email '{$email}' já existe!\n";
        echo "ID: {$existing['id']}\n";
        exit(1);
    }

    // 2. Validar entrada
    if (strlen($senha) < 8) {
        echo "❌ Erro: Senha muito curta (mínimo 8 caracteres)\n";
        exit(1);
    }

    // 3. Criar usuário
    $senhaHash = password_hash($senha, PASSWORD_BCRYPT);
    
    $sql = "INSERT INTO usuarios (email, senha, nome, cpf, telefone, role_id, status, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'ativo', NOW(), NOW())";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$email, $senhaHash, $nome, $cpf, $telefone, $role_id]);
    $usuarioId = $db->lastInsertId();
    
    echo "✅ Usuário criado com ID: {$usuarioId}\n";

    // 4. Associar ao tenant
    $sqlTenant = "INSERT INTO usuario_tenant (usuario_id, tenant_id, criado_em) 
                  VALUES (?, ?, NOW())
                  ON DUPLICATE KEY UPDATE criado_em = NOW()";
    
    $stmtTenant = $db->prepare($sqlTenant);
    $stmtTenant->execute([$usuarioId, $tenant_id]);
    
    echo "✅ Associado ao tenant: {$tenant_id}\n";

    // 5. Exibir informações
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "🎉 SuperAdmin criado com sucesso!\n";
    echo str_repeat("=", 50) . "\n\n";
    echo "📧 Email:    {$email}\n";
    echo "🔐 Senha:    {$senha}\n";
    echo "👤 Nome:     {$nome}\n";
    echo "🔑 Role ID:  {$role_id} (SuperAdmin)\n";
    echo "🏢 Tenant:   {$tenant_id}\n";
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "⚠️  SEGURANÇA: Mude a senha após primeiro login!\n";
    echo str_repeat("=", 50) . "\n";

    // 6. Testar login
    echo "\n🧪 Testando credenciais...\n";
    $testStmt = $db->prepare("SELECT id, email, nome, role_id FROM usuarios WHERE email = ? LIMIT 1");
    $testStmt->execute([$email]);
    $user = $testStmt->fetch(\PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "✅ Credenciais verificadas com sucesso!\n";
        echo "   Pronto para fazer login via endpoint /auth/login\n";
    } else {
        echo "❌ Erro ao verificar credenciais\n";
    }

} catch (\PDOException $e) {
    echo "❌ Erro de banco de dados: " . $e->getMessage() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✅ Script concluído com sucesso!\n";
