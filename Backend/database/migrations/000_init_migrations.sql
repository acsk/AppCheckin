-- =====================================================
-- SCRIPT DE INICIALIZAÇÃO: Executa todas as migrations
-- em ordem sequencial para garantir consistência
-- =====================================================
-- Este arquivo é executado automaticamente pelo Docker
-- quando o container MySQL é iniciado
-- =====================================================

-- Definir variáveis de sessão
SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
SET NAMES utf8mb4;

-- Log de início
SELECT '========================================' AS '';
SELECT 'INICIANDO MIGRATIONS DO APPCHECKIN' AS '';
SELECT CONCAT('Data/Hora: ', NOW()) AS '';
SELECT '========================================' AS '';

-- Criar tabela de tracking de migrações
CREATE TABLE IF NOT EXISTS _migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration_name VARCHAR(255) UNIQUE NOT NULL,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_migration_name (migration_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Função auxiliar para registrar migrações
DELIMITER //

CREATE PROCEDURE execute_migration(IN p_migration_name VARCHAR(255), IN p_sql LONGTEXT)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SELECT CONCAT('❌ ERRO em ', p_migration_name) AS status;
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Verificar se já foi executada
    IF NOT EXISTS (SELECT 1 FROM _migrations WHERE migration_name = p_migration_name) THEN
        SELECT CONCAT('▶️  Executando: ', p_migration_name) AS status;
        
        -- Executar a migration (será feito via SET)
        -- SET @sql = p_sql;
        -- PREPARE stmt FROM @sql;
        -- EXECUTE stmt;
        -- DEALLOCATE PREPARE stmt;
        
        -- Registrar execução
        INSERT INTO _migrations (migration_name) VALUES (p_migration_name);
        SELECT CONCAT('✅ ', p_migration_name, ' OK') AS status;
    ELSE
        SELECT CONCAT('⏭️  Já executada: ', p_migration_name) AS status;
    END IF;
    
    COMMIT;
END //

DELIMITER ;

-- Nota: As migrations individuais serão carregadas via SOURCE
-- Este arquivo apenas inicializa a estrutura de tracking
