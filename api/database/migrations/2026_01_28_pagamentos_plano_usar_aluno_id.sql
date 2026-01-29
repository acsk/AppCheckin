-- ============================================================
-- MIGRAÇÃO: Alterar pagamentos_plano para usar aluno_id
-- Data: 2026-01-28
-- Descrição: A tabela pagamentos_plano deve referenciar alunos.id
--            ao invés de usuarios.id para manter consistência
-- ============================================================

-- Passo 1: Adicionar coluna aluno_id
ALTER TABLE pagamentos_plano ADD COLUMN aluno_id INT NULL AFTER tenant_id;

-- Passo 2: Popular aluno_id baseado no usuario_id existente
UPDATE pagamentos_plano pp
INNER JOIN alunos a ON a.usuario_id = pp.usuario_id
SET pp.aluno_id = a.id;

-- Passo 3: Verificar se há pagamentos órfãos
SELECT COUNT(*) as orfaos FROM pagamentos_plano WHERE aluno_id IS NULL;

-- Passo 4: Tornar aluno_id NOT NULL
ALTER TABLE pagamentos_plano MODIFY COLUMN aluno_id INT NOT NULL;

-- Passo 5: Remover FK antiga de usuario_id
ALTER TABLE pagamentos_plano DROP FOREIGN KEY pagamentos_plano_ibfk_3;

-- Passo 6: Adicionar FK para alunos
ALTER TABLE pagamentos_plano ADD CONSTRAINT fk_pagamentos_plano_aluno FOREIGN KEY (aluno_id) REFERENCES alunos(id);

-- Passo 7: Remover coluna usuario_id
ALTER TABLE pagamentos_plano DROP COLUMN usuario_id;

-- Passo 8: Criar índice
CREATE INDEX idx_pagamentos_plano_aluno ON pagamentos_plano(aluno_id);

-- ============================================================
-- VERIFICAÇÃO FINAL
-- ============================================================
DESCRIBE pagamentos_plano;
