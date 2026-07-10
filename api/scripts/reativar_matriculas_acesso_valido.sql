-- Reativar em lote matrículas com período PAGO ainda válido
-- Base: MAX(data_vencimento) das parcelas com status_pagamento_id = 2 (Pago)
--
-- 1) Sempre rode o SELECT abaixo antes do UPDATE
-- 2) Rode em transação; COMMIT só se o preview estiver correto

START TRANSACTION;

-- === PREVIEW ===
SELECT m.id, m.tenant_id, m.tipo_cobranca, sm.codigo AS status_atual,
       mp.max_pago,
       DATEDIFF(CURDATE(), mp.max_pago) AS dias_desde_max_pago,
       CASE
           WHEN mp.max_pago >= CURDATE() THEN 'ativa'
           WHEN sm.codigo = 'cancelada' AND DATEDIFF(CURDATE(), mp.max_pago) BETWEEN 1 AND 4 THEN 'vencida'
       END AS status_novo
FROM matriculas m
INNER JOIN status_matricula sm ON sm.id = m.status_id
INNER JOIN (
    SELECT matricula_id, tenant_id, MAX(data_vencimento) AS max_pago
    FROM pagamentos_plano
    WHERE status_pagamento_id = 2
    GROUP BY matricula_id, tenant_id
) mp ON mp.matricula_id = m.id AND mp.tenant_id = m.tenant_id
WHERE sm.codigo IN ('cancelada', 'vencida')
  AND (
      mp.max_pago >= CURDATE()
      OR (sm.codigo = 'cancelada' AND DATEDIFF(CURDATE(), mp.max_pago) BETWEEN 1 AND 4)
  )
ORDER BY m.tenant_id, mp.max_pago DESC;

-- === APLICAR: max_pago >= hoje → ATIVA ===
UPDATE matriculas m
INNER JOIN (
    SELECT matricula_id, tenant_id, MAX(data_vencimento) AS max_pago
    FROM pagamentos_plano WHERE status_pagamento_id = 2
    GROUP BY matricula_id, tenant_id
) mp ON mp.matricula_id = m.id AND mp.tenant_id = m.tenant_id
INNER JOIN status_matricula sm ON sm.id = m.status_id
SET m.status_id = (SELECT id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1),
    m.data_vencimento = mp.max_pago,
    m.proxima_data_vencimento = mp.max_pago,
    m.updated_at = NOW()
WHERE sm.codigo IN ('cancelada', 'vencida')
  AND mp.max_pago >= CURDATE();

-- === APLICAR: cancelada com max_pago há 1-4 dias → VENCIDA ===
UPDATE matriculas m
INNER JOIN (
    SELECT matricula_id, tenant_id, MAX(data_vencimento) AS max_pago
    FROM pagamentos_plano WHERE status_pagamento_id = 2
    GROUP BY matricula_id, tenant_id
) mp ON mp.matricula_id = m.id AND mp.tenant_id = m.tenant_id
INNER JOIN status_matricula sm ON sm.id = m.status_id
SET m.status_id = (SELECT id FROM status_matricula WHERE codigo = 'vencida' LIMIT 1),
    m.data_vencimento = mp.max_pago,
    m.proxima_data_vencimento = mp.max_pago,
    m.updated_at = NOW()
WHERE sm.codigo = 'cancelada'
  AND mp.max_pago < CURDATE()
  AND DATEDIFF(CURDATE(), mp.max_pago) BETWEEN 1 AND 4;

-- COMMIT;
-- ROLLBACK;
