-- ============================================================
-- ALTER TABLE: Adicionar campo autorizado_por em matricula_descontos
-- ID do admin que autorizou o desconto (opcional)
-- ============================================================

ALTER TABLE matricula_descontos
    ADD COLUMN autorizado_por INT NULL COMMENT 'ID do admin que autorizou o desconto'
    AFTER criado_por;
