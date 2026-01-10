-- ════════════════════════════════════════════════════════════════════
-- SQL: Apagar Agendamentos Replicados (Manter apenas dia 09/01/2026)
-- ════════════════════════════════════════════════════════════════════
-- 
-- Este script deleta todas as turmas criadas pela replicação
-- mantendo apenas os 3 agendamentos originais do dia 09/01/2026 (dia_id 17)
-- Tenant: 5 (CrossFit Premium)
--
-- ⚠️  ATENÇÃO: Este comando é IRREVERSÍVEL!
-- ════════════════════════════════════════════════════════════════════

-- 1️⃣  VISUALIZAR turmas que serão deletadas
SELECT 
    t.id,
    t.nome as turma,
    d.data,
    t.horario_inicio,
    t.horario_fim,
    p.nome as professor,
    m.nome as modalidade
FROM turmas t
JOIN dias d ON t.dia_id = d.id
JOIN professores p ON t.professor_id = p.id
JOIN modalidades m ON t.modalidade_id = m.id
WHERE t.tenant_id = 5 
AND t.dia_id != 17
ORDER BY d.data, t.horario_inicio;

-- ════════════════════════════════════════════════════════════════════
-- 2️⃣  DELETAR turmas replicadas (descomentar para executar)
-- ════════════════════════════════════════════════════════════════════

-- DELETE FROM turmas
-- WHERE tenant_id = 5 
-- AND dia_id != 17;

-- ════════════════════════════════════════════════════════════════════
-- 3️⃣  VERIFICAR turmas mantidas (dia 09/01/2026 - dia_id 17)
-- ════════════════════════════════════════════════════════════════════

SELECT 
    t.id,
    t.nome as turma,
    d.data,
    t.horario_inicio,
    t.horario_fim,
    p.nome as professor,
    m.nome as modalidade
FROM turmas t
JOIN dias d ON t.dia_id = d.id
JOIN professores p ON t.professor_id = p.id
JOIN modalidades m ON t.modalidade_id = m.id
WHERE t.tenant_id = 5 
AND t.dia_id = 17
ORDER BY t.horario_inicio;

-- ════════════════════════════════════════════════════════════════════
-- 4️⃣  CONTAGEM
-- ════════════════════════════════════════════════════════════════════

SELECT 
    (SELECT COUNT(*) FROM turmas WHERE tenant_id = 5 AND dia_id != 17) as a_deletar,
    (SELECT COUNT(*) FROM turmas WHERE tenant_id = 5 AND dia_id = 17) as a_manter;
