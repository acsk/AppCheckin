-- ============================================
-- ALINHAR PROFESSORES COM ESTRUTURA DE PRODUÇÃO
-- ============================================
-- Em produção:
-- - professores tem tenant_id (tenant principal de cadastro)
-- - unique_tenant_email na tabela professores
-- - tenant_professor gerencia vínculos adicionais
-- ============================================

-- 1. Adicionar tenant_id na tabela professores
ALTER TABLE professores 
ADD COLUMN tenant_id INT NOT NULL AFTER usuario_id,
ADD KEY idx_professores_tenant (tenant_id),
ADD CONSTRAINT fk_professores_tenant 
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE;

-- 2. Popular tenant_id dos professores existentes
-- Pega o tenant_id do primeiro vínculo de cada professor
UPDATE professores p
SET p.tenant_id = (
    SELECT tp.tenant_id 
    FROM tenant_professor tp 
    WHERE tp.professor_id = p.id 
    ORDER BY tp.data_inicio ASC 
    LIMIT 1
);

-- 3. Remover o índice unique_tenant_email de tenant_professor (se existir)
ALTER TABLE tenant_professor 
DROP INDEX IF EXISTS unique_tenant_email;

-- 4. Adicionar unique_tenant_email na tabela professores
ALTER TABLE professores
ADD UNIQUE KEY unique_tenant_email (tenant_id, email);

-- 5. Remover cpf e email de tenant_professor
-- (esses campos ficam apenas em professores)
ALTER TABLE tenant_professor
DROP COLUMN IF EXISTS cpf,
DROP COLUMN IF EXISTS email;

-- 6. Remover o índice 'cpf' de tenant_professor (se existir)
ALTER TABLE tenant_professor
DROP INDEX IF EXISTS cpf;

SELECT 'Estrutura alinhada com produção!' as status;

-- Verificar estrutura final
SELECT 
    'professores' as tabela,
    COUNT(*) as total_registros,
    COUNT(DISTINCT tenant_id) as tenants_distintos
FROM professores;

SELECT 
    'tenant_professor' as tabela,
    COUNT(*) as total_vinculos,
    COUNT(DISTINCT professor_id) as professores_distintos,
    COUNT(DISTINCT tenant_id) as tenants_distintos
FROM tenant_professor;
