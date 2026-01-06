-- Limpar e reinserir dados com encoding correto
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET CHARACTER SET utf8mb4;

-- Deletar dados existentes (com cuidado nas FK)
SET FOREIGN_KEY_CHECKS = 0;
DELETE FROM forma_pagamento;
SET FOREIGN_KEY_CHECKS = 1;

-- Inserir dados com encoding correto
INSERT INTO forma_pagamento (id, nome, descricao, ativo) VALUES
(1, _utf8mb4'PIX', _utf8mb4'Pagamento via PIX', 1),
(2, _utf8mb4'Cartão', _utf8mb4'Cartão de crédito ou débito', 1),
(3, _utf8mb4'Boleto', _utf8mb4'Boleto bancário', 1),
(4, _utf8mb4'Dinheiro', _utf8mb4'Pagamento em dinheiro', 1),
(5, _utf8mb4'Operadora', _utf8mb4'Pagamento via operadora de cartões', 1),
(6, _utf8mb4'Transferência', _utf8mb4'Transferência bancária', 1),
(7, _utf8mb4'Cheque', _utf8mb4'Pagamento via cheque', 1),
(8, _utf8mb4'Crédito Loja', _utf8mb4'Crédito da loja/academia', 1);

SELECT 'Dados inseridos com encoding UTF-8 correto!' as status;
SELECT id, nome, descricao FROM forma_pagamento ORDER BY nome;
