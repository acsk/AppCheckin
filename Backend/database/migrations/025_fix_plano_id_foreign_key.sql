-- Corrigir foreign key do plano_id para referenciar planos_sistema
-- O campo plano_id estava referenciando a tabela planos, mas deveria referenciar planos_sistema

-- Remover a constraint antiga
ALTER TABLE tenant_planos_sistema 
DROP FOREIGN KEY tenant_planos_sistema_ibfk_2;

-- Adicionar a constraint correta referenciando planos_sistema
ALTER TABLE tenant_planos_sistema 
ADD CONSTRAINT tenant_planos_sistema_plano_id_fk 
FOREIGN KEY (plano_id) REFERENCES planos_sistema(id) ON DELETE RESTRICT;
