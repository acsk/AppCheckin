-- Migration: Adicionar CPF, telefone e endereço completo aos usuários
-- Data: 2026-01-06
-- Descrição: Adiciona campos de CPF, telefone e endereço aos usuários, similar ao implementado em tenants

-- Adicionar campo telefone
ALTER TABLE usuarios 
ADD COLUMN telefone VARCHAR(20) NULL AFTER email;

-- Adicionar campo CPF
ALTER TABLE usuarios 
ADD COLUMN cpf VARCHAR(14) NULL AFTER telefone,
ADD INDEX idx_usuarios_cpf (cpf);

-- Adicionar campos de endereço
ALTER TABLE usuarios
ADD COLUMN cep VARCHAR(10) NULL AFTER cpf,
ADD COLUMN logradouro VARCHAR(255) NULL AFTER cep,
ADD COLUMN numero VARCHAR(20) NULL AFTER logradouro,
ADD COLUMN complemento VARCHAR(100) NULL AFTER numero,
ADD COLUMN bairro VARCHAR(100) NULL AFTER complemento,
ADD COLUMN cidade VARCHAR(100) NULL AFTER bairro,
ADD COLUMN estado VARCHAR(2) NULL AFTER cidade;

-- Comentários:
-- Telefone: Formato com DDD (XX) XXXXX-XXXX
-- CPF: Cadastro de Pessoa Física (formato: XXX.XXX.XXX-XX)
-- CEP: Código de Endereçamento Postal (formato: XXXXX-XXX)
-- Estado: Sigla do estado (UF) com 2 caracteres
-- Todos os campos são opcionais para permitir cadastros parciais
