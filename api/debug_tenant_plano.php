<?php

// Carregar configuração de banco de dados
$db = require __DIR__ . '/config/database.php';

try {
    echo "🔍 DEBUG: Testando queries de TenantPlano\n";
    echo "=" . str_repeat("=", 80) . "\n\n";
    
    // Teste 1: Verificar estrutura da tabela tenant_planos_sistema
    echo "📋 TESTE 1: Estrutura da tabela tenant_planos_sistema\n";
    echo "-" . str_repeat("-", 80) . "\n";
    $stmt = $db->query("DESC tenant_planos_sistema");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Colunas encontradas:\n";
    foreach ($columns as $col) {
        echo "  - {$col['Field']}: {$col['Type']} (Null: {$col['Null']}, Key: {$col['Key']})\n";
    }
    echo "\n";
    
    // Teste 2: Verificar dados em tenant_planos_sistema
    echo "📋 TESTE 2: Dados em tenant_planos_sistema\n";
    echo "-" . str_repeat("-", 80) . "\n";
    $stmt = $db->query("SELECT COUNT(*) as total FROM tenant_planos_sistema");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total de registros: {$result['total']}\n";
    
    $stmt = $db->query("SELECT id, tenant_id, plano_sistema_id, status_id FROM tenant_planos_sistema LIMIT 5");
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($records) {
        echo "Primeiros 5 registros:\n";
        foreach ($records as $rec) {
            echo "  - ID: {$rec['id']}, Tenant: {$rec['tenant_id']}, Plano: {$rec['plano_sistema_id']}, Status: {$rec['status_id']}\n";
        }
    } else {
        echo "Nenhum registro encontrado\n";
    }
    echo "\n";
    
    // Teste 3: Query simples sem JOINs
    echo "📋 TESTE 3: Query simples sem JOINs\n";
    echo "-" . str_repeat("-", 80) . "\n";
    $sql = "SELECT tp.id, tp.tenant_id, tp.status_id FROM tenant_planos_sistema tp WHERE tp.tenant_id = :tenant_id";
    echo "SQL: $sql\n";
    $stmt = $db->prepare($sql);
    $stmt->execute(['tenant_id' => 2]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Resultado: " . ($result ? json_encode($result) : "Nenhum registro") . "\n\n";
    
    // Teste 4: Query com INNER JOIN simples
    echo "📋 TESTE 4: Query com INNER JOIN (planos_sistema)\n";
    echo "-" . str_repeat("-", 80) . "\n";
    $sql = "SELECT tp.id, tp.tenant_id, ps.id as plano_id, ps.nome as plano_nome
            FROM tenant_planos_sistema tp
            INNER JOIN planos_sistema ps ON tp.plano_sistema_id = ps.id
            WHERE tp.tenant_id = :tenant_id";
    echo "SQL: $sql\n";
    $stmt = $db->prepare($sql);
    $stmt->execute(['tenant_id' => 2]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Resultado: " . ($result ? json_encode($result) : "Nenhum registro") . "\n\n";
    
    // Teste 5: Query com dois JOINs (como no método buscarContratoAtivo)
    echo "📋 TESTE 5: Query com dois JOINs (status_contrato)\n";
    echo "-" . str_repeat("-", 80) . "\n";
    $sql = "SELECT tp.id, tp.tenant_id, tp.status_id, 
                   ps.nome as plano_nome, 
                   sc.id as sc_id, sc.nome as status_nome
            FROM tenant_planos_sistema tp
            INNER JOIN planos_sistema ps ON tp.plano_sistema_id = ps.id
            INNER JOIN status_contrato sc ON tp.status_id = sc.id
            WHERE tp.tenant_id = :tenant_id 
            AND tp.status_id = 1";
    echo "SQL: $sql\n";
    $stmt = $db->prepare($sql);
    $stmt->execute(['tenant_id' => 2]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Resultado: " . ($result ? json_encode($result) : "Nenhum registro") . "\n\n";
    
    // Teste 6: Query com SELECT tp.* (como está no código agora)
    echo "📋 TESTE 6: Query com SELECT tp.* (como está no código)\n";
    echo "-" . str_repeat("-", 80) . "\n";
    $sql = "SELECT tp.*, 
                   ps.nome as plano_nome, ps.valor, ps.max_alunos, ps.max_admins, ps.features,
                   sc.nome as status_nome, sc.id as status_id
            FROM tenant_planos_sistema tp
            INNER JOIN planos_sistema ps ON tp.plano_sistema_id = ps.id
            INNER JOIN status_contrato sc ON tp.status_id = sc.id
            WHERE tp.tenant_id = :tenant_id 
            AND tp.status_id = 1
            LIMIT 1";
    echo "SQL: $sql\n";
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute(['tenant_id' => 2]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Resultado: " . ($result ? json_encode($result) : "Nenhum registro") . "\n";
    } catch (PDOException $e) {
        echo "❌ ERRO PDO:\n";
        echo "  Código: " . $e->errorInfo[0] . "\n";
        echo "  Erro SQL: " . $e->errorInfo[1] . "\n";
        echo "  Mensagem: " . $e->errorInfo[2] . "\n";
        echo "  Exception: " . $e->getMessage() . "\n";
    }
    echo "\n";
    
    // Teste 7: Verificar se há coluna gerada "status_ativo_check"
    echo "📋 TESTE 7: Verificar coluna gerada (status_ativo_check)\n";
    echo "-" . str_repeat("-", 80) . "\n";
    $stmt = $db->query("SELECT COLUMN_NAME, EXTRA FROM INFORMATION_SCHEMA.COLUMNS 
                        WHERE TABLE_NAME = 'tenant_planos_sistema' AND TABLE_SCHEMA = 'appcheckin'
                        ORDER BY ORDINAL_POSITION");
    $allColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($allColumns as $col) {
        if ($col['EXTRA']) {
            echo "  - {$col['COLUMN_NAME']}: {$col['EXTRA']}\n";
        }
    }
    echo "\n";
    
    // Teste 8: Tentar criar um contrato
    echo "📋 TESTE 8: Tentar criar um contrato\n";
    echo "-" . str_repeat("-", 80) . "\n";
    $insertSql = "INSERT INTO tenant_planos_sistema 
                  (tenant_id, plano_id, plano_sistema_id, status_id, data_inicio, observacoes) 
                  VALUES 
                  (:tenant_id, :plano_id, :plano_sistema_id, :status_id, :data_inicio, :observacoes)";
    echo "SQL: $insertSql\n";
    try {
        $stmt = $db->prepare($insertSql);
        $result = $stmt->execute([
            'tenant_id' => 2,
            'plano_id' => 1,
            'plano_sistema_id' => 1,
            'status_id' => 2,
            'data_inicio' => date('Y-m-d'),
            'observacoes' => 'Teste de debug'
        ]);
        
        if ($result) {
            $contractId = $db->lastInsertId();
            echo "✅ Contrato criado com sucesso! ID: $contractId\n";
            
            // Verificar se foi criado
            $stmt = $db->prepare("SELECT * FROM tenant_planos_sistema WHERE id = :id");
            $stmt->execute(['id' => $contractId]);
            $created = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "Contrato criado:\n";
            foreach ($created as $key => $value) {
                echo "  - $key: $value\n";
            }
        } else {
            echo "❌ Erro ao criar contrato\n";
        }
    } catch (PDOException $e) {
        echo "❌ ERRO ao inserir:\n";
        echo "  Código: " . $e->errorInfo[0] . "\n";
        echo "  Erro SQL: " . $e->errorInfo[1] . "\n";
        echo "  Mensagem: " . $e->errorInfo[2] . "\n";
        echo "  Exception: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERRO GERAL: " . $e->getMessage() . "\n";
    echo "  Arquivo: " . $e->getFile() . "\n";
    echo "  Linha: " . $e->getLine() . "\n";
}

?>
