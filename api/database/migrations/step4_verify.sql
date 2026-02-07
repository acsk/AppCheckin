-- Passo 4: Verificar eventos ativos e matrículas atualizadas

-- Ver eventos (deve aparecer atualizar_matriculas_vencidas)
SHOW EVENTS;

-- Ver matrículas vencidas
SELECT 
    m.id,
    u.nome as aluno_nome,
    m.proxima_data_vencimento,
    sm.nome as status_nome,
    sm.codigo as status_codigo
FROM matriculas m
INNER JOIN alunos a ON a.id = m.aluno_id
INNER JOIN usuarios u ON u.id = a.usuario_id
INNER JOIN status_matricula sm ON sm.id = m.status_id
WHERE m.proxima_data_vencimento < CURDATE()
ORDER BY m.proxima_data_vencimento ASC;
