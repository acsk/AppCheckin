-- ==========================================
-- Migration 042: Padronizar Collation
-- ==========================================
-- Descrição: Padroniza todas as tabelas para utf8mb4_unicode_ci
-- Motivo: Evita problemas com comparações, ordenação e índices
-- Autor: Sistema
-- Data: 2026-01-06
-- ==========================================

-- Configurar charset da sessão
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET CHARACTER SET utf8mb4;

-- ==========================================
-- 1. Alterar database padrão
-- ==========================================
-- ALTER DATABASE appcheckin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ==========================================
-- 2. Converter tabelas principais
-- ==========================================

-- Tabela: usuarios
ALTER TABLE usuarios 
CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tabela: tenants
ALTER TABLE tenants 
CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tabela: usuario_tenant
ALTER TABLE usuario_tenant 
CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tabela: roles
ALTER TABLE roles 
CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tabela: dias
ALTER TABLE dias 
CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tabela: horarios
ALTER TABLE horarios 
CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tabela: checkins
ALTER TABLE checkins 
CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ==========================================
-- 3. Converter tabelas de planos
-- ==========================================

-- Tabela: planos
ALTER TABLE planos 
CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tabela: planos_sistema
ALTER TABLE planos_sistema 
CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tabela: tenant_planos_sistema
ALTER TABLE tenant_planos_sistema 
CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tabela: historico_planos
ALTER TABLE historico_planos 
CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ==========================================
-- 4. Converter tabelas financeiras
-- ==========================================

-- Tabela: contas_receber
ALTER TABLE contas_receber 
CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tabela: matriculas
ALTER TABLE matriculas 
CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tabela: pagamentos_contrato
ALTER TABLE pagamentos_contrato 
CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ==========================================
-- 5. Converter tabelas auxiliares
-- ==========================================

-- Tabela: formas_pagamento
ALTER TABLE formas_pagamento 
CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tabela: tenant_formas_pagamento
ALTER TABLE tenant_formas_pagamento 
CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tabela: modalidades
ALTER TABLE modalidades 
CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ==========================================
-- OBSERVAÇÕES
-- ==========================================
-- 1. utf8mb4_unicode_ci:
--    - Suporta todos os caracteres Unicode (emojis, acentos)
--    - Case-insensitive (a = A)
--    - Melhor para ordenação multilíngue
--
-- 2. Alternativas:
--    - utf8mb4_general_ci: Mais rápido, menos preciso
--    - utf8mb4_0900_ai_ci: MySQL 8.0+, mais preciso
--
-- 3. Impacto:
--    - Índices podem ser reconstruídos automaticamente
--    - Queries existentes continuam funcionando
--    - Comparações de strings ficam consistentes
--
-- 4. Rollback:
--    - Reexecutar ALTER TABLE com collation antiga
--    - Ex: CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci
