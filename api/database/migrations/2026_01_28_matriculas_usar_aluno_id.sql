-- ============================================================
-- MIGRAÇÃO: Alterar matriculas para usar aluno_id ao invés de usuario_id
-- Data: 2026-01-28
-- Descrição: A tabela matriculas deve referenciar alunos.id diretamente
--            ao invés de usuarios.id para manter consistência
-- ============================================================

-- Passo 1: Adicionar coluna aluno_id
ALTER TABLE matriculas 
ADD COLUMN aluno_id INT NULL AFTER tenant_id;

-- Passo 2: Popular aluno_id baseado no usuario_id existente
UPDATE matriculas m
INNER JOIN alunos a ON a.usuario_id = m.usuario_id
SET m.aluno_id = a.id;

-- Passo 3: Verificar se há matrículas órfãs (sem aluno correspondente)
-- Se houver, precisamos decidir o que fazer (pode comentar ou deletar)
SELECT m.* FROM matriculas m 
LEFT JOIN alunos a ON a.usuario_id = m.usuario_id 
WHERE a.id IS NULL;

-- Passo 4: Tornar aluno_id NOT NULL (só executar após verificar que não há órfãos)
ALTER TABLE matriculas 
MODIFY COLUMN aluno_id INT NOT NULL;

-- Passo 5: Adicionar FK para alunos
ALTER TABLE matriculas
ADD CONSTRAINT fk_matriculas_aluno 
FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE;

-- Passo 6: Remover FK antiga de usuario_id (se existir)
-- Primeiro verificar se existe
SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'matriculas' 
AND COLUMN_NAME = 'usuario_id' 
AND REFERENCED_TABLE_NAME IS NOT NULL;

-- Se houver FK, remover (exemplo, ajustar nome se diferente)
-- ALTER TABLE matriculas DROP FOREIGN KEY fk_matriculas_usuario;

-- Passo 7: Remover coluna usuario_id
ALTER TABLE matriculas DROP COLUMN usuario_id;

-- Passo 8: Criar índice em aluno_id
CREATE INDEX idx_matriculas_aluno ON matriculas(aluno_id);

-- Passo 9: Criar índice composto para consultas frequentes
CREATE INDEX idx_matriculas_tenant_aluno ON matriculas(tenant_id, aluno_id);

-- ============================================================
-- VERIFICAÇÃO FINAL
-- ============================================================
DESCRIBE matriculas;
SELECT m.id, m.aluno_id, m.tenant_id, a.nome as aluno_nome 
FROM matriculas m 
INNER JOIN alunos a ON a.id = m.aluno_id 
LIMIT 5;
