-- Migration: Adicionar CNPJ e endereço completo aos tenants
-- Data: 2025-12-28
-- Descrição: Melhora o cadastro de academias/tenants com dados mais completos

-- Adicionar campo CNPJ
ALTER TABLE tenants 
ADD COLUMN cnpj VARCHAR(18) NULL AFTER email,
ADD INDEX idx_tenants_cnpj (cnpj);

-- Dividir endereço em campos separados
ALTER TABLE tenants
ADD COLUMN cep VARCHAR(10) NULL AFTER endereco,
ADD COLUMN logradouro VARCHAR(255) NULL AFTER cep,
ADD COLUMN numero VARCHAR(20) NULL AFTER logradouro,
ADD COLUMN complemento VARCHAR(100) NULL AFTER numero,
ADD COLUMN bairro VARCHAR(100) NULL AFTER complemento,
ADD COLUMN cidade VARCHAR(100) NULL AFTER bairro,
ADD COLUMN estado VARCHAR(2) NULL AFTER cidade;

-- Comentários:
-- CNPJ: Cadastro Nacional de Pessoa Jurídica (formato: XX.XXX.XXX/XXXX-XX)
-- CEP: Código de Endereçamento Postal (formato: XXXXX-XXX)
-- Estado: Sigla do estado (UF) com 2 caracteres
-- Campo 'endereco' mantido para compatibilidade, pode conter endereço completo concatenado
