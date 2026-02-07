<?php
/**
 * Script para criar planos de teste gratuitos
 * Execute: docker exec appcheckin_php php /var/www/html/scripts/criar_planos_teste.php
 */

require __DIR__ . '/../vendor/autoload.php';

$db = require __DIR__ . '/../config/database.php';

$planosTeste = [
    [
        'nome' => '1x Semana - Teste Gratuito',
        'checkins_semanais' => 1,
        'descricao' => 'Plano teste - 1x por semana (gratuito até início de cobrança)'
    ],
    [
        'nome' => '2x Semana - Teste Gratuito',
        'checkins_semanais' => 2,
        'descricao' => 'Plano teste - 2x por semana (gratuito até início de cobrança)'
    ],
    [
        'nome' => '3x Semana - Teste Gratuito',
        'checkins_semanais' => 3,
        'descricao' => 'Plano teste - 3x por semana (gratuito até início de cobrança)'
    ],
    [
        'nome' => 'Livre - Teste Gratuito',
        'checkins_semanais' => 999,
        'descricao' => 'Plano teste - acesso livre (gratuito até início de cobrança)'
    ]
];

echo "🔍 Criando planos de teste para tenant_id = 1\n\n";

foreach ($planosTeste as $plano) {
    // Verificar se já existe
    $stmtCheck = $db->prepare("
        SELECT id FROM planos 
        WHERE tenant_id = 1 
        AND nome = :nome
    ");
    $stmtCheck->execute(['nome' => $plano['nome']]);
    
    if ($stmtCheck->fetch()) {
        echo "⚠️  Já existe: {$plano['nome']}\n";
        continue;
    }
    
    $stmt = $db->prepare("
        INSERT INTO planos (
            tenant_id, 
            modalidade_id, 
            nome, 
            valor, 
            checkins_semanais, 
            duracao_dias,
            descricao,
            ativo
        ) VALUES (
            1,
            1,
            :nome,
            0,
            :checkins_semanais,
            30,
            :descricao,
            1
        )
    ");
    
    $stmt->execute([
        'nome' => $plano['nome'],
        'checkins_semanais' => $plano['checkins_semanais'],
        'descricao' => $plano['descricao']
    ]);
    
    echo "✅ Criado: {$plano['nome']} (ID: {$db->lastInsertId()})\n";
}

echo "\n✅ Processo concluído!\n";
