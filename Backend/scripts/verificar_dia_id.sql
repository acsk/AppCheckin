-- Verificar qual é o dia_id para 09/01/2026 e quais turmas existem

SELECT 
    d.id,
    d.data,
    DAYNAME(d.data) as dia_semana,
    COUNT(t.id) as total_turmas
FROM dias d
LEFT JOIN turmas t ON d.id = t.dia_id AND t.tenant_id = 5
WHERE YEAR(d.data) = 2026 
AND MONTH(d.data) = 1
AND DAY(d.data) = 9
GROUP BY d.id;

-- Se a query acima não retornar nada, tenta encontrar turmas do tenant 5
SELECT DISTINCT
    d.id,
    d.data,
    DAYNAME(d.data) as dia_semana,
    COUNT(t.id) as total_turmas
FROM dias d
LEFT JOIN turmas t ON d.id = t.dia_id AND t.tenant_id = 5
WHERE t.tenant_id = 5
GROUP BY d.id
ORDER BY d.data;
