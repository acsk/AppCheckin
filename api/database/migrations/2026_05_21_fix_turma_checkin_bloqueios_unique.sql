-- Corrige UNIQUE para multi-tenant (se a migration inicial já foi aplicada com só turma_id)
SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'turma_checkin_bloqueios'
      AND index_name = 'uk_turma_checkin_bloqueio'
);

SET @sql_drop := IF(
    @idx_exists > 0,
    'ALTER TABLE turma_checkin_bloqueios DROP INDEX uk_turma_checkin_bloqueio',
    'SELECT 1'
);
PREPARE stmt_drop FROM @sql_drop;
EXECUTE stmt_drop;
DEALLOCATE PREPARE stmt_drop;

SET @idx_new_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'turma_checkin_bloqueios'
      AND index_name = 'uk_tenant_turma_checkin_bloqueio'
);

SET @sql_add := IF(
    @idx_new_exists = 0,
    'ALTER TABLE turma_checkin_bloqueios ADD UNIQUE KEY uk_tenant_turma_checkin_bloqueio (tenant_id, turma_id)',
    'SELECT 1'
);
PREPARE stmt_add FROM @sql_add;
EXECUTE stmt_add;
DEALLOCATE PREPARE stmt_add;
