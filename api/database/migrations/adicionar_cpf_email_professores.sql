-- =====================================================
-- ADICIONAR CPF E EMAIL NA TABELA PROFESSORES
-- =====================================================
-- Criado em: 2026-02-03
-- 
-- OBJETIVO:
-- Adicionar campos CPF e EMAIL diretamente na tabela professores
-- para facilitar buscas e evitar JOINs desnecessários.
--
-- JUSTIFICATIVA:
-- - CPF e EMAIL são identificadores únicos importantes
-- - Facilita busca de professor existente antes de associar ao tenant
-- - Mantém dados também em usuarios (redundância controlada)
-- - Melhora performance de queries de busca
-- =====================================================

USE appcheckin;

-- 1. Adicionar campo CPF na tabela professores
ALTER TABLE `professores` 
ADD COLUMN `cpf` VARCHAR(14) NULL COMMENT 'CPF do professor (pode ser NULL para casos antigos)' AFTER `nome`,
ADD COLUMN `email` VARCHAR(255) NULL COMMENT 'Email do professor (facilita buscas)' AFTER `cpf`;

-- 2. Criar índice único para CPF (permite NULL)
ALTER TABLE `professores` 
ADD UNIQUE KEY `uk_professor_cpf` (`cpf`);

-- 3. Criar índice para email (facilita buscas)
ALTER TABLE `professores` 
ADD KEY `idx_professor_email` (`email`);

-- 4. Migrar dados de CPF e EMAIL da tabela usuarios para professores
UPDATE professores p
INNER JOIN usuarios u ON u.id = p.usuario_id
SET 
    p.cpf = u.cpf,
    p.email = u.email
WHERE u.cpf IS NOT NULL OR u.email IS NOT NULL;

-- Verificações
SELECT 'Campos CPF e EMAIL adicionados com sucesso!' as status;

-- Verificar migração de dados
SELECT 
    'Dados migrados:' as info,
    COUNT(*) as total_professores,
    COUNT(p.cpf) as com_cpf,
    COUNT(p.email) as com_email,
    COUNT(*) - COUNT(p.cpf) as sem_cpf
FROM professores p;

-- Mostrar professores atualizados
SELECT 
    p.id,
    p.nome,
    p.cpf,
    p.email,
    u.cpf as cpf_usuario,
    u.email as email_usuario,
    CASE 
        WHEN p.cpf = u.cpf THEN 'OK'
        WHEN p.cpf IS NULL AND u.cpf IS NULL THEN 'AMBOS NULL'
        ELSE 'DIVERGENTE'
    END as status_cpf
FROM professores p
LEFT JOIN usuarios u ON u.id = p.usuario_id
ORDER BY p.id;
