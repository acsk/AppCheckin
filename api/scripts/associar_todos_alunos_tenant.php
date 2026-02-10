<?php

/**
 * Script para associar todos os alunos ao tenant
 * Uso: php scripts/associar_todos_alunos_tenant.php
 */

require_once __DIR__ . '/../config/database.php';

// Configurações
$tenantId = 3; // Cia da Natação
$papelAlunoId = 1;

try {
    $db = require __DIR__ . '/../config/database.php';
    
    echo "========================================\n";
    echo "ASSOCIAÇÃO DE ALUNOS AO TENANT\n";
    echo "Tenant ID: {$tenantId}\n";
    echo "========================================\n\n";
    
    // Buscar todos os alunos
    $stmtAlunos = $db->prepare("
        SELECT a.id, a.usuario_id, u.nome, u.email
        FROM alunos a
        INNER JOIN usuarios u ON u.id = a.usuario_id
        WHERE a.ativo = 1
        ORDER BY a.id
    ");
    $stmtAlunos->execute();
    $alunos = $stmtAlunos->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total de alunos encontrados: " . count($alunos) . "\n\n";
    
    if (empty($alunos)) {
        echo "Nenhum aluno encontrado.\n";
        exit(0);
    }
    
    // Preparar statement de inserção
    $stmtInsert = $db->prepare("
        INSERT INTO tenant_usuario_papel (usuario_id, tenant_id, papel_id, ativo, created_at, updated_at)
        VALUES (?, ?, ?, 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE ativo = 1, updated_at = NOW()
    ");
    
    $sucessos = 0;
    $erros = 0;
    $jaExistiam = 0;
    
    // Iniciar transação
    $db->beginTransaction();
    
    foreach ($alunos as $aluno) {
        try {
            // Verificar se já existe
            $stmtCheck = $db->prepare("
                SELECT id FROM tenant_usuario_papel
                WHERE usuario_id = ? AND tenant_id = ? AND papel_id = ?
            ");
            $stmtCheck->execute([$aluno['usuario_id'], $tenantId, $papelAlunoId]);
            
            if ($stmtCheck->fetch()) {
                echo "✓ Aluno ID {$aluno['id']} ({$aluno['nome']}) - JÁ ASSOCIADO\n";
                $jaExistiam++;
            } else {
                // Inserir associação
                $stmtInsert->execute([
                    $aluno['usuario_id'],
                    $tenantId,
                    $papelAlunoId
                ]);
                
                echo "✓ Aluno ID {$aluno['id']} ({$aluno['nome']}) - ASSOCIADO\n";
                $sucessos++;
            }
            
        } catch (Exception $e) {
            echo "✗ Erro ao associar aluno ID {$aluno['id']}: {$e->getMessage()}\n";
            $erros++;
        }
    }
    
    // Commit da transação
    $db->commit();
    
    echo "\n========================================\n";
    echo "RESUMO\n";
    echo "========================================\n";
    echo "Total processados: " . count($alunos) . "\n";
    echo "Novos associados: {$sucessos}\n";
    echo "Já existiam: {$jaExistiam}\n";
    echo "Erros: {$erros}\n";
    echo "========================================\n\n";
    
    // Verificação final
    echo "Verificando associações...\n\n";
    
    $stmtVerifica = $db->prepare("
        SELECT 
            COUNT(*) as total
        FROM tenant_usuario_papel tup
        INNER JOIN usuarios u ON u.id = tup.usuario_id
        INNER JOIN alunos a ON a.usuario_id = u.id
        WHERE tup.tenant_id = ?
        AND tup.papel_id = ?
        AND a.ativo = 1
    ");
    $stmtVerifica->execute([$tenantId, $papelAlunoId]);
    $resultado = $stmtVerifica->fetch(PDO::FETCH_ASSOC);
    
    echo "Total de alunos associados ao tenant: {$resultado['total']}\n";
    
    // Listar alguns exemplos
    echo "\nExemplos de alunos associados:\n";
    $stmtExemplos = $db->prepare("
        SELECT 
            a.id as aluno_id,
            u.nome,
            u.email
        FROM tenant_usuario_papel tup
        INNER JOIN usuarios u ON u.id = tup.usuario_id
        INNER JOIN alunos a ON a.usuario_id = u.id
        WHERE tup.tenant_id = ?
        AND tup.papel_id = ?
        AND a.ativo = 1
        ORDER BY a.id
        LIMIT 10
    ");
    $stmtExemplos->execute([$tenantId, $papelAlunoId]);
    $exemplos = $stmtExemplos->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($exemplos as $ex) {
        echo "  - ID {$ex['aluno_id']}: {$ex['nome']} ({$ex['email']})\n";
    }
    
    if ($resultado['total'] > 10) {
        echo "  ... e mais " . ($resultado['total'] - 10) . " alunos\n";
    }
    
    echo "\n✅ Processo concluído com sucesso!\n";
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo "\n❌ ERRO: {$e->getMessage()}\n";
    echo "Stack trace:\n{$e->getTraceAsString()}\n";
    exit(1);
}