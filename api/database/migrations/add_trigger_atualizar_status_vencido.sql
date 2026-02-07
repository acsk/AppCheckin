-- ============================================================================
-- Migration: Atualizar status de matrículas vencidas automaticamente
-- Data: 2026-02-06
-- Descrição: Cria evento MySQL que roda diariamente às 00:01 para atualizar
--            o status das matrículas de 'ativa' (1) para 'vencida' (2)
--            quando proxima_data_vencimento < hoje
-- ============================================================================

-- 1. Ativar o event scheduler (necessário para eventos funcionarem)
SET GLOBAL event_scheduler = ON;

-- 2. Remover evento se já existir (para re-execução segura)
DROP EVENT IF EXISTS atualizar_matriculas_vencidas;

-- 3. Criar o evento que roda diariamente às 00:01
CREATE EVENT atualizar_matriculas_vencidas
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_DATE + INTERVAL 1 DAY + INTERVAL 1 MINUTE
COMMENT 'Atualiza status de matrículas para vencida quando proxima_data_vencimento expirar'
DO
BEGIN
    -- Atualizar matrículas ativas que venceram
    UPDATE matriculas
    SET status_id = 2, -- vencida
        updated_at = NOW()
    WHERE status_id = 1 -- ativa
    AND proxima_data_vencimento IS NOT NULL
    AND proxima_data_vencimento < CURDATE();
END;

-- 4. Executar a primeira vez manualmente (atualizar matrículas já vencidas)
UPDATE matriculas
SET status_id = 2, -- vencida
    updated_at = NOW()
WHERE status_id = 1 -- ativa
AND proxima_data_vencimento IS NOT NULL
AND proxima_data_vencimento < CURDATE();

-- 5. Verificar matrículas que foram atualizadas
SELECT 
    m.id,
    u.nome as aluno_nome,
    m.proxima_data_vencimento,
    sm.nome as status_nome
FROM matriculas m
INNER JOIN alunos a ON a.id = m.aluno_id
INNER JOIN usuarios u ON u.id = a.usuario_id
INNER JOIN status_matricula sm ON sm.id = m.status_id
WHERE m.proxima_data_vencimento < CURDATE()
ORDER BY m.proxima_data_vencimento ASC;

-- ============================================================================
-- IMPORTANTE: 
-- - O event_scheduler precisa estar ativado (SET GLOBAL event_scheduler = ON)
-- - O evento roda automaticamente todo dia às 00:01
-- - Para verificar se o evento está ativo: SHOW EVENTS;
-- ============================================================================
