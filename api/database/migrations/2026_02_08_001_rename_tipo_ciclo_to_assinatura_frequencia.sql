-- Migration: Renomear tipo_ciclo_id para assinatura_frequencia_id em plano_ciclos
-- E criar tabela assinatura_frequencias se não existir (migrando dados de tipos_ciclo)
--
-- Execução:
-- mysql -u user -p database < 2026_02_08_001_rename_tipo_ciclo_to_assinatura_frequencia.sql

-- =====================================================================
-- 1. Criar tabela assinatura_frequencias (se ainda não existir)
-- =====================================================================

CREATE TABLE IF NOT EXISTS assinatura_frequencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL COMMENT 'Mensal, Trimestral, Semestral, Anual',
    codigo VARCHAR(20) NOT NULL UNIQUE COMMENT 'mensal, trimestral, semestral, anual',
    meses INT NOT NULL DEFAULT 1 COMMENT 'Quantidade de meses do ciclo',
    ordem INT DEFAULT 1 COMMENT 'Ordem de exibição',
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_assinatura_frequencias_codigo (codigo),
    INDEX idx_assinatura_frequencias_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Frequências de assinatura (mensal, trimestral, semestral, anual)';

-- Migrar dados de tipos_ciclo para assinatura_frequencias (se tipos_ciclo existir e assinatura_frequencias estiver vazia)
INSERT IGNORE INTO assinatura_frequencias (id, nome, codigo, meses, ordem, ativo, created_at)
SELECT id, nome, codigo, meses, ordem, ativo, created_at
FROM tipos_ciclo
WHERE NOT EXISTS (SELECT 1 FROM assinatura_frequencias LIMIT 1);

-- =====================================================================
-- 2. Renomear coluna tipo_ciclo_id para assinatura_frequencia_id em plano_ciclos
-- =====================================================================

-- Remover a foreign key antiga (descobrir o nome automaticamente não é possível em SQL puro)
-- Tentar remover pelo nome mais provável:
SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
    WHERE TABLE_NAME = 'plano_ciclos' 
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    AND CONSTRAINT_NAME LIKE '%tipo_ciclo%'
    AND TABLE_SCHEMA = DATABASE()
);

-- Se a coluna tipo_ciclo_id existir, fazer a migração
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_NAME = 'plano_ciclos' 
    AND COLUMN_NAME = 'tipo_ciclo_id'
    AND TABLE_SCHEMA = DATABASE()
);

-- Executar renomeação apenas se a coluna antiga existir
-- Nota: Execute os comandos abaixo manualmente se o IF não funcionar no seu cliente MySQL

-- Passo 1: Remover FK antiga
ALTER TABLE plano_ciclos DROP FOREIGN KEY IF EXISTS fk_plano_ciclos_tipo_ciclo;

-- Passo 2: Remover índice antigo
ALTER TABLE plano_ciclos DROP INDEX IF EXISTS idx_plano_ciclos_tipo;
ALTER TABLE plano_ciclos DROP INDEX IF EXISTS uk_plano_tipo_ciclo;

-- Passo 3: Renomear a coluna
ALTER TABLE plano_ciclos CHANGE COLUMN tipo_ciclo_id assinatura_frequencia_id INT NOT NULL COMMENT 'FK para assinatura_frequencias';

-- Passo 4: Recriar índice e FK com novos nomes
ALTER TABLE plano_ciclos ADD INDEX idx_plano_ciclos_frequencia (assinatura_frequencia_id);
ALTER TABLE plano_ciclos ADD CONSTRAINT fk_plano_ciclos_assinatura_frequencia 
    FOREIGN KEY (assinatura_frequencia_id) REFERENCES assinatura_frequencias(id) ON DELETE RESTRICT;
ALTER TABLE plano_ciclos ADD UNIQUE KEY uk_plano_frequencia (plano_id, assinatura_frequencia_id);

-- =====================================================================
-- 3. (Opcional) Remover tabela tipos_ciclo após confirmar que tudo funciona
-- =====================================================================
-- DROP TABLE IF EXISTS tipos_ciclo;
-- Descomente a linha acima SOMENTE após confirmar que tudo está funcionando

SELECT 'Migration concluída: tipo_ciclo_id renomeado para assinatura_frequencia_id' AS status;
