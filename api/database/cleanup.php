<?php
/**
 * Script de Limpeza do Banco de Dados
 * Uso: php database/cleanup.php
 * 
 * Apenas execute em desenvolvimento!
 * Em produção, execute manualmente com backup
 */

// Carregar configurações
require_once __DIR__ . '/../config/database.php';

// Cores para terminal
$colors = [
    'reset' => "\033[0m",
    'red' => "\033[91m",
    'green' => "\033[92m",
    'yellow' => "\033[93m",
    'blue' => "\033[94m",
];

echo "{$colors['blue']}==============================================\n";
echo "LIMPEZA DE BANCO DE DADOS\n";
echo "=============================================\n{$colors['reset']}";

// Verificar se está em produção
$appEnv = $_ENV['APP_ENV'] ?? 'development';
if ($appEnv === 'production') {
    echo "{$colors['red']}❌ ERRO: Não é possível executar este script em PRODUÇÃO!\n{$colors['reset']}";
    echo "Execute manualmente com backup.\n";
    exit(1);
}

echo "{$colors['yellow']}⚠️  AVISO: Este script APAGARÁ dados do banco!\n{$colors['reset']}";
echo "Ambiente: {$colors['yellow']}{$appEnv}{$colors['reset']}\n";
echo "Database: {$colors['yellow']}{$_ENV['DB_NAME']}{$colors['reset']}\n\n";

// Confirmar
echo "Digite 'SIM' para confirmar: ";
$confirm = trim(fgets(STDIN));

if ($confirm !== 'SIM') {
    echo "{$colors['red']}Operação cancelada.{$colors['reset']}\n";
    exit(0);
}

try {
    echo "\n{$colors['blue']}Iniciando limpeza...{$colors['reset']}\n";
    
    // Desabilitar constraints
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    echo "{$colors['green']}✓{$colors['reset']} Constraints desabilitadas\n";
    
    // Tabelas a limpar
    $tables = [
        'sessions',
        'checkins',
        'presenqas',
        'historico_planos',
        'matriculas',
        'contas_receber',
        'pagamentos',
        'planejamento_horarios',
        'planejamento_semanal',
        'horarios',
        'turmas',
        'professores',
        'modalidades',
        'dias',
        'feature_flags',
        'wod_resultados',
        'wod_variacoes',
        'wod_blocos',
        'wods',
        'auxiliar',
    ];
    
    foreach ($tables as $table) {
        try {
            $db->exec("DELETE FROM $table");
            echo "{$colors['green']}✓{$colors['reset']} Tabela '$table' limpa\n";
        } catch (\PDOException $e) {
            echo "{$colors['yellow']}⚠{$colors['reset']} Erro ao limpar '$table': {$e->getMessage()}\n";
        }
    }
    
    // Limpar usuários (manter SuperAdmin)
    $db->exec("DELETE FROM usuarios WHERE role_id != 3");
    echo "{$colors['green']}✓{$colors['reset']} Usuários limpos (SuperAdmin mantido)\n";
    
    // Limpar usuario_tenant (manter SuperAdmin)
    $db->exec("DELETE FROM usuario_tenant WHERE usuario_id NOT IN (SELECT id FROM usuarios WHERE role_id = 3)");
    echo "{$colors['green']}✓{$colors['reset']} Relações usuario_tenant limpas\n";
    
    // Limpar tenants > 1
    $db->exec("DELETE FROM tenant_planos WHERE tenant_id > 1");
    $db->exec("DELETE FROM tenant_formas_pagamento WHERE tenant_id > 1");
    $db->exec("DELETE FROM tenant_planos_sistema WHERE tenant_id > 1");
    $db->exec("DELETE FROM tenants WHERE id > 1");
    echo "{$colors['green']}✓{$colors['reset']} Tenants extras removidos\n";
    
    // Reabilitar constraints
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "{$colors['green']}✓{$colors['reset']} Constraints reabilitadas\n";
    
    echo "\n{$colors['green']}==============================================\n";
    echo "✅ LIMPEZA CONCLUÍDA COM SUCESSO!\n";
    echo "=============================================\n{$colors['reset']}";
    
    echo "\n{$colors['blue']}Dados mantidos:{$colors['reset']}\n";
    echo "  • SuperAdmin (role_id = 3)\n";
    echo "  • Planos do Sistema\n";
    echo "  • Formas de Pagamento\n";
    echo "  • Tenant padrão (ID = 1)\n";
    echo "  • Roles\n";
    echo "  • Status\n\n";
    
} catch (\PDOException $e) {
    echo "{$colors['red']}❌ ERRO: {$e->getMessage()}{$colors['reset']}\n";
    exit(1);
}
