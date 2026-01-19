-- Confirmar todos os pagamentos do contrato 2
UPDATE pagamentos_contrato 
SET status_pagamento_id = 2, 
    data_pagamento = '2026-01-05',
    updated_at = NOW()
WHERE id IN (1, 2);

-- Ativar o contrato (mudar de Pendente para Ativo)
UPDATE tenant_planos_sistema 
SET status_id = 1,
    updated_at = NOW()
WHERE id = 2;

-- Verificar resultado
SELECT 'Pagamentos do Contrato 2:' as '';
SELECT 
    id,
    valor,
    data_vencimento,
    data_pagamento,
    status_pagamento_id,
    (SELECT nome FROM status_pagamento WHERE id = status_pagamento_id) as status
FROM pagamentos_contrato 
WHERE contrato_id = 2
ORDER BY data_vencimento;

SELECT '' as '';
SELECT 'Status do Contrato 2:' as '';
SELECT 
    tps.id,
    t.nome as academia,
    (SELECT nome FROM status_contrato WHERE id = tps.status_id) as status_contrato,
    tps.data_inicio,
    tps.data_vencimento
FROM tenant_planos_sistema tps
INNER JOIN tenants t ON tps.tenant_id = t.id
WHERE tps.id = 2;
