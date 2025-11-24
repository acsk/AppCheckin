-- Seed de dados para teste

-- Inserir dias (próximos 7 dias)
INSERT INTO dias (data, ativo) VALUES
    (CURDATE() + INTERVAL 1 DAY, TRUE),
    (CURDATE() + INTERVAL 2 DAY, TRUE),
    (CURDATE() + INTERVAL 3 DAY, TRUE),
    (CURDATE() + INTERVAL 4 DAY, TRUE),
    (CURDATE() + INTERVAL 5 DAY, TRUE),
    (CURDATE() + INTERVAL 6 DAY, TRUE),
    (CURDATE() + INTERVAL 7 DAY, TRUE);

-- Inserir horários para cada dia (8h às 18h, a cada 2 horas)
INSERT INTO horarios (dia_id, hora, vagas, ativo)
SELECT 
    d.id,
    h.hora,
    10 as vagas,
    TRUE as ativo
FROM dias d
CROSS JOIN (
    SELECT '08:00:00' as hora UNION ALL
    SELECT '10:00:00' UNION ALL
    SELECT '12:00:00' UNION ALL
    SELECT '14:00:00' UNION ALL
    SELECT '16:00:00' UNION ALL
    SELECT '18:00:00'
) h;

-- Usuário de teste
-- Senha: password123
INSERT INTO usuarios (nome, email, senha_hash) VALUES
    ('Usuário Teste', 'teste@exemplo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
