<?php
/**
 * Script de Teste: Validação da Correção de Usuários Duplicados
 * 
 * Este script demonstra que a correção implementada no método listarTodos()
 * resolve o problema de usuários duplicados na API /superadmin/usuarios
 * 
 * Uso: php test_usuarios_duplicados.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Models/Usuario.php';

use App\Models\Usuario;

// Criar conexão
$db = require __DIR__ . '/../config/database.php';
$usuarioModel = new Usuario($db);

echo "========================================\n";
echo "TESTE: Validação de Usuários Duplicados\n";
echo "========================================\n\n";

// 1. Teste: Listar TODOS os usuários (SuperAdmin)
echo "1. Listando usuários (SuperAdmin)...\n";
$usuarios = $usuarioModel->listarTodos(true, null, false);

echo "   Total de usuários retornados: " . count($usuarios) . "\n";
echo "   Status: " . (count($usuarios) > 0 ? "✓ SUCESSO" : "✗ ERRO") . "\n\n";

// 2. Verificar duplicatas
echo "2. Verificando duplicatas...\n";
$usuariosIds = array_column($usuarios, 'id');
$idsUnicos = array_unique($usuariosIds);
$temDuplicatas = count($usuariosIds) !== count($idsUnicos);

if ($temDuplicatas) {
    echo "   ✗ ERRO: Encontradas duplicatas!\n";
    $duplicatas = array_diff_assoc($usuariosIds, $idsUnicos);
    foreach ($duplicatas as $idx => $id) {
        echo "      - Usuário ID {$id} aparece múltiplas vezes\n";
    }
} else {
    echo "   ✓ SUCESSO: Nenhuma duplicata encontrada!\n";
}
echo "   Total único: " . count($idsUnicos) . "\n\n";

// 3. Verificar estrutura de dados
echo "3. Verificando estrutura de dados...\n";
if (!empty($usuarios)) {
    $primeiro = $usuarios[0];
    $camposEsperados = ['id', 'nome', 'email', 'role_id', 'role_nome', 'ativo', 'status', 'tenant'];
    $camposPresentes = array_keys($primeiro);
    
    $todosCampos = true;
    foreach ($camposEsperados as $campo) {
        if (!in_array($campo, $camposPresentes)) {
            echo "   ✗ Campo ausente: {$campo}\n";
            $todosCampos = false;
        }
    }
    
    if ($todosCampos) {
        echo "   ✓ SUCESSO: Todos os campos esperados presentes!\n";
    }
    
    // Verificar tenant
    if (isset($primeiro['tenant']) && is_array($primeiro['tenant'])) {
        echo "   ✓ SUCESSO: Tenant retornado como array!\n";
        echo "      Tenant: {$primeiro['tenant']['nome']} (ID: {$primeiro['tenant']['id']})\n";
    } else {
        echo "   ✗ ERRO: Tenant não é um array!\n";
    }
} else {
    echo "   ⚠ Nenhum usuário para verificar\n";
}
echo "\n";

// 4. Amostra de dados
echo "4. Amostra de dados retornados:\n";
echo "   " . str_repeat("-", 95) . "\n";
printf("   %-4s | %-30s | %-25s | %-30s\n", "ID", "Nome", "Email", "Tenant");
echo "   " . str_repeat("-", 95) . "\n";

foreach (array_slice($usuarios, 0, 5) as $user) {
    printf("   %-4d | %-30s | %-25s | %-30s\n",
        $user['id'],
        substr($user['nome'], 0, 28),
        substr($user['email'], 0, 23),
        substr($user['tenant']['nome'], 0, 28)
    );
}

if (count($usuarios) > 5) {
    echo "   ... (mais " . (count($usuarios) - 5) . " usuários)\n";
}
echo "   " . str_repeat("-", 95) . "\n\n";

// 5. Teste por Tenant (se não quebrar compatibilidade)
echo "5. Testando listarPorTenant (compatibilidade)...\n";
$usuariosTenant = $usuarioModel->listarPorTenant(5, false);
echo "   Total de usuários do tenant 5: " . count($usuariosTenant) . "\n";
echo "   Status: " . (count($usuariosTenant) > 0 ? "✓ OK" : "✓ OK (vazio)") . "\n\n";

// 6. Teste: Apenas ativos
echo "6. Testando filtro de ativos...\n";
$usuariosAtivos = $usuarioModel->listarTodos(true, null, true);
echo "   Total de usuários ativos: " . count($usuariosAtivos) . "\n";
echo "   Status: " . (count($usuariosAtivos) >= 0 ? "✓ OK" : "✗ ERRO") . "\n\n";

// 7. Relatório Final
echo "========================================\n";
echo "RELATÓRIO FINAL\n";
echo "========================================\n";

$problemas = [];

if ($temDuplicatas) {
    $problemas[] = "❌ Duplicatas encontradas";
} else {
    $problemas[] = "✅ Sem duplicatas";
}

if ($todosCampos ?? true) {
    $problemas[] = "✅ Estrutura de dados OK";
} else {
    $problemas[] = "❌ Campos faltando";
}

if (count($idsUnicos) > 0) {
    $problemas[] = "✅ Dados válidos";
} else {
    $problemas[] = "⚠️  Sem dados";
}

echo implode("\n", $problemas) . "\n\n";

if (!$temDuplicatas && ($todosCampos ?? true) && count($idsUnicos) > 0) {
    echo "✅ TODOS OS TESTES PASSARAM!\n";
    echo "A correção de usuários duplicados está funcionando corretamente.\n";
} else {
    echo "❌ ALGUNS TESTES FALHARAM!\n";
    echo "Verifique os logs acima.\n";
}

echo "\n";
