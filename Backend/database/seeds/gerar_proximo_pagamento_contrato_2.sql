-- Gerar próximo pagamento baseado no último existente
INSERT INTO pagamentos_contrato (
    contrato_id,
    valor,
    data_vencimento,
    data_pagamento,
    status_pagamento_id,
    forma_pagamento,
    comprovante,
    observacoes,
    created_at,
    updated_at
)
SELECT 
    contrato_id,
    valor,
    DATE_ADD(MAX(data_vencimento), INTERVAL 1 MONTH) as data_vencimento,
    NULL as data_pagamento,
    1 as status_pagamento_id, -- Aguardando
    forma_pagamento,
    NULL as comprovante,
    'Próximo pagamento gerado para normalizar fluxo' as observacoes,
    NOW() as created_at,
    NOW() as updated_at
FROM pagamentos_contrato
WHERE contrato_id = 2
GROUP BY contrato_id, valor, forma_pagamento;

-- Verificar todos os pagamentos do contrato 2
SELECT 
    id,
    valor,
    data_vencimento,
    data_pagamento,
    (SELECT nome FROM status_pagamento WHERE id = status_pagamento_id) as status,
    observacoes
FROM pagamentos_contrato
WHERE contrato_id = 2
ORDER BY data_vencimento;
