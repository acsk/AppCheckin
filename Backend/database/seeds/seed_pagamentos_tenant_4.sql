-- Seed de teste para Tenant 4 - Sporte e Saúde - Baixa Grande
-- Criar contrato de teste e pagamentos

-- Limpar dados anteriores do tenant 4
DELETE FROM pagamentos_contrato WHERE contrato_id IN (SELECT id FROM tenant_planos_sistema WHERE tenant_id = 4);
DELETE FROM tenant_planos_sistema WHERE tenant_id = 4;

-- Criar contrato com status Pendente (aguardando pagamento)
INSERT INTO tenant_planos_sistema 
(tenant_id, plano_id, plano_sistema_id, status_id, data_inicio, data_vencimento, forma_pagamento, observacoes, created_at)
VALUES 
(4, 3, 3, 2, '2026-01-05', '2026-02-04', 'pix', 'Contrato de teste com pagamentos', NOW());

SET @contrato_id = LAST_INSERT_ID();

-- Criar primeiro pagamento (Aguardando - vencimento hoje)
INSERT INTO pagamentos_contrato 
(contrato_id, valor, data_vencimento, status_pagamento_id, forma_pagamento, observacoes, created_at)
VALUES 
(@contrato_id, 250.00, '2026-01-05', 1, 'pix', 'Primeiro pagamento do contrato', NOW());

-- Criar segundo pagamento (Aguardando - vencimento próximo mês)
INSERT INTO pagamentos_contrato 
(contrato_id, valor, data_vencimento, status_pagamento_id, forma_pagamento, observacoes, created_at)
VALUES 
(@contrato_id, 250.00, '2026-02-05', 1, 'pix', 'Segundo pagamento do contrato', NOW());

-- Criar terceiro pagamento (Aguardando - vencimento daqui 2 meses)
INSERT INTO pagamentos_contrato 
(contrato_id, valor, data_vencimento, status_pagamento_id, forma_pagamento, observacoes, created_at)
VALUES 
(@contrato_id, 250.00, '2026-03-05', 1, 'pix', 'Terceiro pagamento do contrato', NOW());

SELECT 'Seed de teste criado com sucesso para Tenant 4!' as status;
SELECT * FROM tenant_planos_sistema WHERE tenant_id = 4;
SELECT * FROM pagamentos_contrato WHERE contrato_id = @contrato_id;
