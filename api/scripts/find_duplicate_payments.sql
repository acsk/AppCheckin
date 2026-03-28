-- =============================================================
-- Encontrar pagamentos_plano duplicados no mesmo mês
-- para o mesmo aluno + matrícula
-- =============================================================

-- 1. Listar os grupos com duplicatas (resumo)
SELECT
    pp.tenant_id,
    pp.aluno_id,
    a.nome AS aluno_nome,
    pp.matricula_id,
    pp.plano_id,
    YEAR(pp.data_vencimento) AS ano,
    MONTH(pp.data_vencimento) AS mes,
    COUNT(*) AS total_parcelas,
    GROUP_CONCAT(pp.id ORDER BY pp.id) AS ids_pagamentos,
    GROUP_CONCAT(pp.valor ORDER BY pp.id) AS valores,
    GROUP_CONCAT(
        CASE pp.status_pagamento_id
            WHEN 1 THEN 'Aguardando'
            WHEN 2 THEN 'Pago'
            WHEN 3 THEN 'Atrasado'
            WHEN 4 THEN 'Cancelado'
            ELSE CONCAT('Status_', pp.status_pagamento_id)
        END
        ORDER BY pp.id
    ) AS statuses
FROM pagamentos_plano pp
LEFT JOIN alunos a ON a.id = pp.aluno_id
WHERE pp.status_pagamento_id != 4  -- ignorar cancelados
GROUP BY pp.tenant_id, pp.aluno_id, a.nome, pp.matricula_id, pp.plano_id,
         YEAR(pp.data_vencimento), MONTH(pp.data_vencimento)
HAVING COUNT(*) > 1
ORDER BY pp.tenant_id, pp.aluno_id, ano DESC, mes DESC;

-- 2. Detalhe completo dos pagamentos duplicados
SELECT
    pp.id,
    pp.tenant_id,
    pp.aluno_id,
    a.nome AS aluno_nome,
    pp.matricula_id,
    pp.plano_id,
    p.nome AS plano_nome,
    pp.valor,
    pp.data_vencimento,
    pp.data_pagamento,
    CASE pp.status_pagamento_id
        WHEN 1 THEN 'Aguardando'
        WHEN 2 THEN 'Pago'
        WHEN 3 THEN 'Atrasado'
        WHEN 4 THEN 'Cancelado'
        ELSE CONCAT('Status_', pp.status_pagamento_id)
    END AS status,
    pp.credito_id,
    pp.credito_aplicado,
    pp.observacoes,
    pp.created_at
FROM pagamentos_plano pp
INNER JOIN (
    SELECT tenant_id, aluno_id, matricula_id, plano_id,
           YEAR(data_vencimento) AS ano, MONTH(data_vencimento) AS mes
    FROM pagamentos_plano
    WHERE status_pagamento_id != 4
    GROUP BY tenant_id, aluno_id, matricula_id, plano_id,
             YEAR(data_vencimento), MONTH(data_vencimento)
    HAVING COUNT(*) > 1
) dup ON pp.tenant_id = dup.tenant_id
     AND pp.aluno_id = dup.aluno_id
     AND pp.matricula_id = dup.matricula_id
     AND pp.plano_id = dup.plano_id
     AND YEAR(pp.data_vencimento) = dup.ano
     AND MONTH(pp.data_vencimento) = dup.mes
LEFT JOIN alunos a ON a.id = pp.aluno_id
LEFT JOIN planos p ON p.id = pp.plano_id
WHERE pp.status_pagamento_id != 4
ORDER BY pp.tenant_id, pp.aluno_id, pp.data_vencimento, pp.id;

-- 3. Contagem geral
SELECT
    COUNT(*) AS total_grupos_duplicados,
    SUM(total) AS total_pagamentos_envolvidos
FROM (
    SELECT COUNT(*) AS total
    FROM pagamentos_plano
    WHERE status_pagamento_id != 4
    GROUP BY tenant_id, aluno_id, matricula_id, plano_id,
             YEAR(data_vencimento), MONTH(data_vencimento)
    HAVING COUNT(*) > 1
) sub;
