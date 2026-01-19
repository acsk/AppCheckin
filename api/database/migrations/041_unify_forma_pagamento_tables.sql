-- Unifica tabelas de forma de pagamento
-- Migration 041: Remove duplicação entre forma_pagamento e formas_pagamento

-- 1. Desabilitar temporariamente a constraint UNIQUE
ALTER TABLE formas_pagamento DROP INDEX uk_forma_pagamento_nome;

-- 2. Atualizar formas_pagamento com dados de forma_pagamento
UPDATE formas_pagamento fp
INNER JOIN forma_pagamento f ON f.nome = fp.nome
SET 
    fp.descricao = f.descricao;

-- 3. Inserir novos registros (que não têm correspondência por nome)
INSERT INTO formas_pagamento (nome, descricao, percentual_desconto, ativo)
SELECT 
    f.nome,
    f.descricao,
    0.00 as percentual_desconto,
    f.ativo
FROM forma_pagamento f
LEFT JOIN formas_pagamento fp ON fp.nome = f.nome
WHERE fp.id IS NULL
ON DUPLICATE KEY UPDATE descricao = VALUES(descricao);

-- 4. Recriar constraint UNIQUE
ALTER TABLE formas_pagamento ADD UNIQUE KEY uk_forma_pagamento_nome (nome);

-- 5. Atualizar FK da tabela pagamentos_contrato
ALTER TABLE pagamentos_contrato DROP FOREIGN KEY fk_pagamento_forma_pagamento;

ALTER TABLE pagamentos_contrato
ADD CONSTRAINT fk_pagamento_forma_pagamento
    FOREIGN KEY (forma_pagamento_id)
    REFERENCES formas_pagamento(id)
    ON DELETE RESTRICT;

-- 6. Dropar tabela duplicada
DROP TABLE forma_pagamento;
