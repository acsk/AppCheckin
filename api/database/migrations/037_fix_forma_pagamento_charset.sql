-- Corrigir charset da tabela forma_pagamento
ALTER TABLE forma_pagamento CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Atualizar dados existentes com encoding correto
UPDATE forma_pagamento SET nome = 'PIX', descricao = 'Pagamento via PIX' WHERE id = 1;
UPDATE forma_pagamento SET nome = 'Cartão', descricao = 'Cartão de crédito ou débito' WHERE id = 2;
UPDATE forma_pagamento SET nome = 'Boleto', descricao = 'Boleto bancário' WHERE id = 3;
UPDATE forma_pagamento SET nome = 'Dinheiro', descricao = 'Pagamento em dinheiro' WHERE id = 4;
UPDATE forma_pagamento SET nome = 'Operadora', descricao = 'Pagamento via operadora de cartões' WHERE id = 5;

-- Adicionar novas formas de pagamento
INSERT INTO forma_pagamento (id, nome, descricao, ativo) VALUES
(6, 'Transferência', 'Transferência bancária', 1),
(7, 'Cheque', 'Pagamento via cheque', 1),
(8, 'Crédito Loja', 'Crédito da loja/academia', 1)
ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao);

SELECT 'Migration 037 executada com sucesso! Charset corrigido e formas de pagamento atualizadas.' as status;
