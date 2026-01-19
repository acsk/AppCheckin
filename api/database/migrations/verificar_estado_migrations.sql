-- ==========================================
-- Script: Verificar Estado das Migrations
-- ==========================================
-- Verifica se as migrations foram aplicadas
-- ==========================================

SET @resultado = '';

SELECT '========================================' AS '';
SELECT 'VERIFICAÇÃO DE MIGRATIONS APLICADAS' AS '';
SELECT '========================================' AS '';
SELECT '' AS '';

-- ==========================================
-- 1. VERIFICAR CONSTRAINT DE CHECKIN (Migration 040)
-- ==========================================

SELECT '1. CHECK-IN: Constraint de recorrência' AS 'VERIFICAÇÃO';
SELECT '' AS '';

-- Verificar se constraint antiga existe
SET @constraint_antiga = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_NAME = 'checkins'
      AND CONSTRAINT_NAME = 'unique_usuario_horario'
      AND TABLE_SCHEMA = DATABASE()
);

-- Verificar se constraint nova existe
SET @constraint_nova = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_NAME = 'checkins'
      AND CONSTRAINT_NAME = 'unique_usuario_horario_data'
      AND TABLE_SCHEMA = DATABASE()
);

-- Verificar se coluna data_checkin_date existe
SET @coluna_data = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_NAME = 'checkins'
      AND COLUMN_NAME = 'data_checkin_date'
      AND TABLE_SCHEMA = DATABASE()
);

SELECT CASE
    WHEN @constraint_nova > 0 AND @coluna_data > 0 THEN '✅ Migration 040 APLICADA - Checkins recorrentes permitidos'
    WHEN @constraint_antiga > 0 THEN '❌ Migration 040 NÃO APLICADA - Constraint antiga ainda existe (bloqueia recorrência)'
    ELSE '⚠️  INDEFINIDO - Nenhuma constraint encontrada'
END AS 'STATUS';

SELECT '' AS '';

-- ==========================================
-- 2. VERIFICAR tenant_id EM usuarios (Migration 003)
-- ==========================================

SELECT '2. MULTI-TENANT: usuarios.tenant_id' AS 'VERIFICAÇÃO';
SELECT '' AS '';

SET @usuarios_tenant_id = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'tenant_id'
      AND TABLE_SCHEMA = DATABASE()
);

SELECT CASE
    WHEN @usuarios_tenant_id = 0 THEN '✅ Migration 003 APLICADA - usuarios.tenant_id removido (fonte única)'
    ELSE '❌ Migration 003 NÃO APLICADA - usuarios.tenant_id ainda existe'
END AS 'STATUS';

SELECT '' AS '';

-- ==========================================
-- 3. VERIFICAR plano_id EM usuarios (Migration 036)
-- ==========================================

SELECT '3. FINANCEIRO: usuarios.plano_id' AS 'VERIFICAÇÃO';
SELECT '' AS '';

SET @usuarios_plano_id = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'plano_id'
      AND TABLE_SCHEMA = DATABASE()
);

SELECT CASE
    WHEN @usuarios_plano_id = 0 THEN '✅ Migration 036 APLICADA - usuarios.plano_id removido'
    ELSE '❌ Migration 036 NÃO APLICADA - usuarios.plano_id ainda existe'
END AS 'STATUS';

SELECT '' AS '';

-- ==========================================
-- 4. VERIFICAR TABELAS DE STATUS (Migration 037)
-- ==========================================

SELECT '4. STATUS: Tabelas de status' AS 'VERIFICAÇÃO';
SELECT '' AS '';

SET @status_tables = (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_NAME IN (
        'status_conta_receber',
        'status_matricula',
        'status_pagamento',
        'status_checkin',
        'status_usuario',
        'status_contrato'
    )
    AND TABLE_SCHEMA = DATABASE()
);

SELECT CASE
    WHEN @status_tables = 6 THEN '✅ Migration 037 APLICADA - 6 tabelas de status criadas'
    WHEN @status_tables > 0 THEN CONCAT('⚠️  PARCIAL - Apenas ', @status_tables, ' de 6 tabelas criadas')
    ELSE '❌ Migration 037 NÃO APLICADA - Tabelas de status não existem'
END AS 'STATUS';

SELECT '' AS '';

-- ==========================================
-- 5. VERIFICAR COLLATION (Migration 042)
-- ==========================================

SELECT '5. COLLATION: Padronização UTF-8' AS 'VERIFICAÇÃO';
SELECT '' AS '';

SET @tables_utf8mb4_unicode = (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_COLLATION = 'utf8mb4_unicode_ci'
);

SET @total_tables = (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_TYPE = 'BASE TABLE'
);

SELECT CASE
    WHEN @tables_utf8mb4_unicode = @total_tables THEN '✅ Migration 042 APLICADA - Todas tabelas utf8mb4_unicode_ci'
    WHEN @tables_utf8mb4_unicode > 0 THEN CONCAT('⚠️  PARCIAL - ', @tables_utf8mb4_unicode, ' de ', @total_tables, ' tabelas convertidas')
    ELSE '❌ Migration 042 NÃO APLICADA - Collation não padronizada'
END AS 'STATUS';

SELECT '' AS '';

-- ==========================================
-- 6. VERIFICAR CONSTRAINTS UNIQUE (Migration 043)
-- ==========================================

SELECT '6. UNICIDADE: UNIQUE constraints' AS 'VERIFICAÇÃO';
SELECT '' AS '';

-- email_global
SET @unique_email_global = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_NAME = 'usuarios'
      AND CONSTRAINT_NAME = 'unique_email_global'
      AND TABLE_SCHEMA = DATABASE()
);

-- CPF
SET @unique_cpf = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_NAME = 'usuarios'
      AND CONSTRAINT_NAME = 'unique_cpf'
      AND TABLE_SCHEMA = DATABASE()
);

-- Mensalidades
SET @unique_conta_mensal = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_NAME = 'contas_receber'
      AND CONSTRAINT_NAME = 'unique_conta_mensal'
      AND TABLE_SCHEMA = DATABASE()
);

SET @total_constraints = @unique_email_global + @unique_cpf + @unique_conta_mensal;

SELECT CASE
    WHEN @total_constraints = 3 THEN '✅ Migration 043 APLICADA - Todas UNIQUE constraints criadas'
    WHEN @total_constraints > 0 THEN CONCAT('⚠️  PARCIAL - ', @total_constraints, ' de 3 constraints criadas')
    ELSE '❌ Migration 043 NÃO APLICADA - Constraints UNIQUE faltando'
END AS 'STATUS';

SELECT '' AS '';

-- ==========================================
-- 7. VERIFICAR tenant_id EM checkins (Migration 044b)
-- ==========================================

SELECT '7. TENANT-FIRST: checkins.tenant_id' AS 'VERIFICAÇÃO';
SELECT '' AS '';

SET @checkins_tenant_id = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_NAME = 'checkins'
      AND COLUMN_NAME = 'tenant_id'
      AND TABLE_SCHEMA = DATABASE()
);

SET @trigger_checkins = (
    SELECT COUNT(*)
    FROM information_schema.TRIGGERS
    WHERE TRIGGER_NAME = 'checkins_before_insert_tenant'
      AND EVENT_OBJECT_SCHEMA = DATABASE()
);

SELECT CASE
    WHEN @checkins_tenant_id > 0 AND @trigger_checkins > 0 THEN '✅ Migration 044b APLICADA - tenant_id + trigger criados'
    WHEN @checkins_tenant_id > 0 THEN '⚠️  PARCIAL - tenant_id existe mas trigger falta'
    ELSE '❌ Migration 044b NÃO APLICADA - tenant_id não existe em checkins'
END AS 'STATUS';

SELECT '' AS '';

-- ==========================================
-- RESUMO GERAL
-- ==========================================

SELECT '========================================' AS '';
SELECT 'RESUMO GERAL' AS '';
SELECT '========================================' AS '';
SELECT '' AS '';

SET @total_aplicadas = 0;
IF @constraint_nova > 0 AND @coluna_data > 0 THEN SET @total_aplicadas = @total_aplicadas + 1; END IF;
IF @usuarios_tenant_id = 0 THEN SET @total_aplicadas = @total_aplicadas + 1; END IF;
IF @usuarios_plano_id = 0 THEN SET @total_aplicadas = @total_aplicadas + 1; END IF;
IF @status_tables = 6 THEN SET @total_aplicadas = @total_aplicadas + 1; END IF;
IF @tables_utf8mb4_unicode = @total_tables THEN SET @total_aplicadas = @total_aplicadas + 1; END IF;
IF @total_constraints = 3 THEN SET @total_aplicadas = @total_aplicadas + 1; END IF;
IF @checkins_tenant_id > 0 THEN SET @total_aplicadas = @total_aplicadas + 1; END IF;

SELECT 
    CONCAT(@total_aplicadas, ' de 7 migrations aplicadas') AS 'PROGRESSO',
    CASE
        WHEN @total_aplicadas = 7 THEN '✅ TODAS APLICADAS - Sistema atualizado'
        WHEN @total_aplicadas > 0 THEN '⚠️  APLICAÇÃO PARCIAL - Execute migrations faltantes'
        ELSE '❌ NENHUMA APLICADA - Execute ./executar_migrations.sh'
    END AS 'STATUS GERAL';

SELECT '' AS '';

-- Detalhes do que falta
SELECT 'MIGRATIONS PENDENTES:' AS '';

IF NOT (@constraint_nova > 0 AND @coluna_data > 0) THEN
    SELECT '❌ 040_fix_checkin_constraint.sql' AS '';
END IF;

IF @usuarios_tenant_id > 0 THEN
    SELECT '❌ 003_remove_tenant_id_from_usuarios.sql' AS '';
END IF;

IF @usuarios_plano_id > 0 THEN
    SELECT '❌ 036_remove_plano_from_usuarios.sql' AS '';
END IF;

IF @status_tables < 6 THEN
    SELECT '❌ 037_create_status_tables.sql' AS '';
END IF;

IF @tables_utf8mb4_unicode < @total_tables THEN
    SELECT '❌ 042_padronizar_collation.sql' AS '';
END IF;

IF @total_constraints < 3 THEN
    SELECT '❌ 043_adicionar_constraints_unicidade.sql' AS '';
END IF;

IF @checkins_tenant_id = 0 THEN
    SELECT '❌ 044b_checkins_tenant_progressivo.sql' AS '';
END IF;

IF @total_aplicadas = 7 THEN
    SELECT '✅ Nenhuma - Todas aplicadas!' AS '';
END IF;

SELECT '' AS '';
SELECT '========================================' AS '';
SELECT 'PRÓXIMA AÇÃO' AS '';
SELECT '========================================' AS '';

SELECT CASE
    WHEN @total_aplicadas = 7 THEN 'Sistema 100% atualizado! Pode usar normalmente.'
    WHEN @total_aplicadas = 0 THEN 'Execute: cd Backend/database/migrations && ./executar_migrations.sh'
    ELSE 'Execute as migrations pendentes listadas acima, na ordem numérica'
END AS 'RECOMENDAÇÃO';
