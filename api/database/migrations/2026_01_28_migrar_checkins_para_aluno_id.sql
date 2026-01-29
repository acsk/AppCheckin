-- ============================================================
-- Migration: Migrar checkins de usuario_id para aluno_id
-- Data: 2026-01-28
-- Status: EXECUTADA COM SUCESSO
-- Descrição: Altera a tabela checkins para usar aluno_id ao invés de usuario_id
--            Isso alinha a arquitetura corretamente:
--            - usuario: autenticação (email, senha, role)
--            - aluno: dados de perfil e operações (checkins, matrículas, pagamentos)
-- ============================================================

-- 1. Adicionar coluna aluno_id ✅
ALTER TABLE checkins ADD COLUMN aluno_id INT NULL AFTER tenant_id;

-- 2. Popular aluno_id baseado em usuario_id + tenant_id ✅
UPDATE checkins c
INNER JOIN alunos a ON a.usuario_id = c.usuario_id
SET c.aluno_id = a.id
WHERE c.aluno_id IS NULL;

-- 3. Verificar se todos os checkins foram migrados ✅
SELECT 
    COUNT(*) as total_checkins,
    SUM(CASE WHEN aluno_id IS NOT NULL THEN 1 ELSE 0 END) as com_aluno_id,
    SUM(CASE WHEN aluno_id IS NULL THEN 1 ELSE 0 END) as sem_aluno_id
FROM checkins;

-- 4. Criar índice para aluno_id ✅
CREATE INDEX idx_checkins_aluno_id ON checkins(aluno_id);

-- 5. Criar índice composto para buscas comuns ✅
CREATE INDEX idx_checkins_aluno_tenant ON checkins(aluno_id, tenant_id);

-- 6. Adicionar FK para alunos (garante integridade referencial) ✅
ALTER TABLE checkins 
ADD CONSTRAINT fk_checkins_aluno 
FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE;

-- 7. Tornar aluno_id NOT NULL (após validar que todos foram migrados)
-- EXECUTE APENAS SE O SELECT ACIMA MOSTRAR sem_aluno_id = 0
-- ALTER TABLE checkins MODIFY COLUMN aluno_id INT NOT NULL;

-- 8. Remover coluna usuario_id (opcional, pode manter para histórico)
-- ALTER TABLE checkins DROP FOREIGN KEY IF EXISTS fk_checkins_usuario;
-- ALTER TABLE checkins DROP INDEX IF EXISTS idx_checkins_usuario;
-- ALTER TABLE checkins DROP COLUMN usuario_id;

-- ============================================================
-- NOTA: Mantemos usuario_id por enquanto para rollback se necessário
-- Após validar que tudo funciona, pode remover com:
-- ALTER TABLE checkins DROP COLUMN usuario_id;
-- ============================================================
