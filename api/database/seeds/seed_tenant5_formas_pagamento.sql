-- Seed: Criar configurações de formas de pagamento para o tenant 5
-- Tenant 5 = Jonas Amaro (FitPro Academia)

INSERT INTO tenant_formas_pagamento 
(tenant_id, forma_pagamento_id, ativo, taxa_percentual, taxa_fixa, prazo_recebimento_dias, permite_parcelamento, max_parcelas, taxa_parcelamento, ordem_exibicao)
SELECT 
    5 as tenant_id,
    id as forma_pagamento_id,
    1 as ativo,
    percentual_desconto as taxa_percentual,
    0.00 as taxa_fixa,
    1 as prazo_recebimento_dias,
    0 as permite_parcelamento,
    1 as max_parcelas,
    0.00 as taxa_parcelamento,
    id as ordem_exibicao
FROM formas_pagamento 
WHERE ativo = 1
AND NOT EXISTS (
    SELECT 1 FROM tenant_formas_pagamento 
    WHERE tenant_id = 5 AND forma_pagamento_id = formas_pagamento.id
);

SELECT 'Configurações de formas de pagamento criadas para tenant 5' as status;
