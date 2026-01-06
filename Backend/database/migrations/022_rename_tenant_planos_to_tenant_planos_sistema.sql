-- Renomear tenant_planos para tenant_planos_sistema
-- Deixa claro que são contratos das academias com planos do sistema

RENAME TABLE tenant_planos TO tenant_planos_sistema;

-- Atualizar comentário
ALTER TABLE tenant_planos_sistema COMMENT = 'Contratos das academias com planos do sistema. Cada academia contrata um plano_sistema que define capacidade e recursos.';
