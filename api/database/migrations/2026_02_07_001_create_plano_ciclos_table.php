<?php
/**
 * Migration: Criar tabela plano_ciclos
 * 
 * Esta tabela permite definir diferentes ciclos de pagamento para cada plano,
 * como mensal, trimestral, semestral e anual, cada um com seu próprio valor.
 * 
 * Execução:
 * php database/migrations/2026_02_07_001_create_plano_ciclos_table.php
 */

require_once __DIR__ . '/../../config/database.php';

try {
    echo "=== Criando tabela plano_ciclos ===\n\n";
    
    // Verificar se a tabela já existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'plano_ciclos'");
    if ($stmt->rowCount() > 0) {
        echo "⚠️  Tabela plano_ciclos já existe. Pulando criação.\n";
    } else {
        $sql = "
            CREATE TABLE plano_ciclos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                plano_id INT NOT NULL,
                nome VARCHAR(50) NOT NULL COMMENT 'mensal, trimestral, semestral, anual',
                codigo VARCHAR(20) NOT NULL COMMENT 'mensal, trimestral, semestral, anual',
                meses INT NOT NULL DEFAULT 1 COMMENT 'Quantidade de meses do ciclo',
                valor DECIMAL(10,2) NOT NULL COMMENT 'Valor total do ciclo',
                valor_mensal_equivalente DECIMAL(10,2) GENERATED ALWAYS AS (valor / meses) STORED COMMENT 'Valor mensal equivalente',
                desconto_percentual DECIMAL(5,2) DEFAULT 0 COMMENT 'Percentual de desconto em relação ao mensal',
                permite_recorrencia TINYINT(1) DEFAULT 1 COMMENT 'Se permite cobrança recorrente (assinatura)',
                ativo TINYINT(1) DEFAULT 1,
                ordem INT DEFAULT 1 COMMENT 'Ordem de exibição',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                INDEX idx_plano_ciclos_tenant (tenant_id),
                INDEX idx_plano_ciclos_plano (plano_id),
                INDEX idx_plano_ciclos_codigo (codigo),
                INDEX idx_plano_ciclos_ativo (ativo),
                
                FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
                FOREIGN KEY (plano_id) REFERENCES planos(id) ON DELETE CASCADE,
                
                UNIQUE KEY uk_plano_ciclo (plano_id, codigo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Ciclos de pagamento dos planos (mensal, trimestral, etc)'
        ";
        
        $pdo->exec($sql);
        echo "✅ Tabela plano_ciclos criada com sucesso!\n";
    }
    
    // Criar tabela de assinaturas do MercadoPago
    $stmt = $pdo->query("SHOW TABLES LIKE 'assinaturas_mercadopago'");
    if ($stmt->rowCount() > 0) {
        echo "⚠️  Tabela assinaturas_mercadopago já existe. Pulando criação.\n";
    } else {
        $sql = "
            CREATE TABLE assinaturas_mercadopago (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                matricula_id INT NOT NULL,
                aluno_id INT NOT NULL,
                plano_ciclo_id INT NULL COMMENT 'Ciclo contratado',
                
                -- Dados do MercadoPago
                mp_preapproval_id VARCHAR(100) NULL COMMENT 'ID da assinatura no MP',
                mp_plan_id VARCHAR(100) NULL COMMENT 'ID do plano no MP (se usar plano pré-criado)',
                mp_payer_id VARCHAR(100) NULL COMMENT 'ID do pagador no MP',
                
                -- Status e valores
                status ENUM('pending', 'authorized', 'paused', 'cancelled', 'finished') DEFAULT 'pending',
                valor DECIMAL(10,2) NOT NULL,
                moeda VARCHAR(3) DEFAULT 'BRL',
                
                -- Datas de cobrança
                dia_cobranca INT DEFAULT 1 COMMENT 'Dia do mês para cobrança',
                data_inicio DATE NOT NULL,
                data_fim DATE NULL COMMENT 'Data de término (se não for indefinido)',
                proxima_cobranca DATE NULL,
                ultima_cobranca DATE NULL,
                
                -- Controle
                tentativas_falha INT DEFAULT 0,
                motivo_cancelamento TEXT NULL,
                cancelado_por INT NULL,
                data_cancelamento DATETIME NULL,
                
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                INDEX idx_assinaturas_tenant (tenant_id),
                INDEX idx_assinaturas_matricula (matricula_id),
                INDEX idx_assinaturas_aluno (aluno_id),
                INDEX idx_assinaturas_status (status),
                INDEX idx_assinaturas_mp_id (mp_preapproval_id),
                INDEX idx_assinaturas_proxima_cobranca (proxima_cobranca),
                
                FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
                FOREIGN KEY (matricula_id) REFERENCES matriculas(id) ON DELETE CASCADE,
                FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE,
                FOREIGN KEY (plano_ciclo_id) REFERENCES plano_ciclos(id) ON DELETE SET NULL,
                FOREIGN KEY (cancelado_por) REFERENCES usuarios(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Assinaturas recorrentes do MercadoPago'
        ";
        
        $pdo->exec($sql);
        echo "✅ Tabela assinaturas_mercadopago criada com sucesso!\n";
    }
    
    // Adicionar coluna na tabela matriculas para vincular ao ciclo
    $stmt = $pdo->query("SHOW COLUMNS FROM matriculas LIKE 'plano_ciclo_id'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE matriculas ADD COLUMN plano_ciclo_id INT NULL AFTER plano_id");
        $pdo->exec("ALTER TABLE matriculas ADD FOREIGN KEY (plano_ciclo_id) REFERENCES plano_ciclos(id) ON DELETE SET NULL");
        echo "✅ Coluna plano_ciclo_id adicionada à tabela matriculas!\n";
    } else {
        echo "⚠️  Coluna plano_ciclo_id já existe em matriculas. Pulando.\n";
    }
    
    // Adicionar coluna tipo_cobranca na tabela matriculas
    $stmt = $pdo->query("SHOW COLUMNS FROM matriculas LIKE 'tipo_cobranca'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE matriculas ADD COLUMN tipo_cobranca ENUM('avulso', 'recorrente') DEFAULT 'avulso' AFTER plano_ciclo_id");
        echo "✅ Coluna tipo_cobranca adicionada à tabela matriculas!\n";
    } else {
        echo "⚠️  Coluna tipo_cobranca já existe em matriculas. Pulando.\n";
    }
    
    echo "\n✅ Migration executada com sucesso!\n";
    
} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
