-- ==========================================
-- Migration 044: Otimizar Índices Tenant-First
-- ==========================================
-- Descrição: Reorganiza índices compostos com tenant_id como primeira coluna
-- Motivo: Em multi-tenant, toda query começa filtrando por tenant
-- Benefício: Melhora performance e isolamento de dados
-- Autor: Sistema
-- Data: 2026-01-06
-- ==========================================

-- ==========================================
-- PRINCÍPIO: Tenant-First Index Strategy
-- ==========================================
-- Em arquitetura multi-tenant:
-- 1. Toda query filtra por tenant_id PRIMEIRO
-- 2. Índice composto deve ter tenant_id como PRIMEIRA coluna
-- 3. MySQL usa índice da esquerda para direita
-- 4. Índice (tenant_id, usuario_id) serve para:
--    - WHERE tenant_id = X
--    - WHERE tenant_id = X AND usuario_id = Y
--    Mas NÃO serve para:
--    - WHERE usuario_id = Y (sem tenant)

-- ==========================================
-- 1. CONTAS_RECEBER - Otimizar para queries principais
-- ==========================================

-- Índice principal: tenant + usuário (consulta de contas do aluno)
-- Já existe: idx_tenant_usuario (tenant_id, usuario_id) ✅

-- Adicionar: tenant + status (listagem filtrada por status)
DROP INDEX IF EXISTS idx_tenant_status ON contas_receber;
CREATE INDEX idx_tenant_status ON contas_receber(tenant_id, status);

-- Adicionar: tenant + data_vencimento (vencimentos do mês)
DROP INDEX IF EXISTS idx_tenant_vencimento ON contas_receber;
CREATE INDEX idx_tenant_vencimento ON contas_receber(tenant_id, data_vencimento);

-- Adicionar: tenant + referencia_mes (mensalidades de um período)
DROP INDEX IF EXISTS idx_tenant_referencia ON contas_receber;
CREATE INDEX idx_tenant_referencia ON contas_receber(tenant_id, referencia_mes);

-- Índice composto completo para relatórios
DROP INDEX IF EXISTS idx_tenant_usuario_status_venc ON contas_receber;
CREATE INDEX idx_tenant_usuario_status_venc ON contas_receber(
    tenant_id, 
    usuario_id, 
    status, 
    data_vencimento
);

-- ==========================================
-- 2. MATRICULAS - Otimizar consultas de alunos ativos
-- ==========================================

-- Índice principal: tenant + usuário + status
DROP INDEX IF EXISTS idx_tenant_usuario_status ON matriculas;
CREATE INDEX idx_tenant_usuario_status ON matriculas(tenant_id, usuario_id, status);

-- Índice: tenant + plano + status (alunos de um plano)
DROP INDEX IF EXISTS idx_tenant_plano_status ON matriculas;
CREATE INDEX idx_tenant_plano_status ON matriculas(tenant_id, plano_id, status);

-- Índice: tenant + data_vencimento (matrículas vencendo)
DROP INDEX IF EXISTS idx_tenant_data_vencimento ON matriculas;
CREATE INDEX idx_tenant_data_vencimento ON matriculas(tenant_id, data_vencimento);

-- ==========================================
-- 3. CHECKINS - Adicionar tenant_id e criar índices
-- ==========================================
-- IMPORTANTE: Checkins hoje NÃO tem tenant_id
-- Derivar tenant via JOIN com usuarios é ineficiente e arriscado

-- Adicionar coluna tenant_id (NULL permitido temporariamente)
ALTER TABLE checkins 
ADD COLUMN IF NOT EXISTS tenant_id INT NULL AFTER id;

-- Preencher tenant_id existentes (via usuario)
-- Usar primeiro tenant ativo do usuário
UPDATE checkins c
JOIN usuarios u ON c.usuario_id = u.id
JOIN usuario_tenant ut ON u.id = ut.usuario_id AND ut.status = 'ativo'
SET c.tenant_id = ut.tenant_id
WHERE c.tenant_id IS NULL
LIMIT 10000;

-- Verificar se todos checkins têm tenant_id preenchido
-- Se algum checkin ficar sem tenant_id, preencher com tenant padrão (id=1)
UPDATE checkins 
SET tenant_id = 1 
WHERE tenant_id IS NULL;

-- Agora tornar NOT NULL (todos preenchidos)
ALTER TABLE checkins 
MODIFY COLUMN tenant_id INT NOT NULL;

-- Adicionar FK
ALTER TABLE checkins 
ADD CONSTRAINT fk_checkins_tenant 
FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE;

-- Índice principal: tenant + usuário + data
DROP INDEX IF EXISTS idx_tenant_usuario_data ON checkins;
CREATE INDEX idx_tenant_usuario_data ON checkins(tenant_id, usuario_id, data_checkin_date);

-- Índice: tenant + horário + data (ocupação de horário)
DROP INDEX IF EXISTS idx_tenant_horario_data ON checkins;
CREATE INDEX idx_tenant_horario_data ON checkins(tenant_id, horario_id, data_checkin_date);

-- Índice: tenant + data (checkins do dia)
DROP INDEX IF EXISTS idx_tenant_data ON checkins;
CREATE INDEX idx_tenant_data ON checkins(tenant_id, data_checkin_date);

-- ==========================================
-- 4. PLANOS - Índices tenant-first
-- ==========================================

-- Índice: tenant + ativo (planos disponíveis)
DROP INDEX IF EXISTS idx_tenant_ativo ON planos;
CREATE INDEX idx_tenant_ativo ON planos(tenant_id, ativo);

-- Índice: tenant + atual + ativo (planos atuais)
DROP INDEX IF EXISTS idx_tenant_atual_ativo ON planos;
CREATE INDEX idx_tenant_atual_ativo ON planos(tenant_id, atual, ativo);

-- Índice: tenant + modalidade (planos por modalidade)
DROP INDEX IF EXISTS idx_tenant_modalidade ON planos;
CREATE INDEX idx_tenant_modalidade ON planos(tenant_id, modalidade_id);

-- ==========================================
-- 5. HORARIOS/DIAS - Otimizar índices (tenant_id já existe)
-- ==========================================
-- NOTA: dias.tenant_id já existe desde migration 004_add_tenancy.sql
-- Apenas otimizar índices existentes

-- Verificar se constraint já existe
SET @constraint_exists = (
    SELECT COUNT(*) 
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE TABLE_NAME = 'dias' 
      AND CONSTRAINT_NAME = 'fk_dias_tenant'
      AND TABLE_SCHEMA = DATABASE()
);

-- Adicionar constraint apenas se não existir
SET @sql = IF(@constraint_exists = 0,
    'ALTER TABLE dias ADD CONSTRAINT fk_dias_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE',
    'SELECT "Constraint fk_dias_tenant já existe" AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índice: tenant + data + ativo
DROP INDEX IF EXISTS idx_tenant_data_ativo ON dias;
CREATE INDEX idx_tenant_data_ativo ON dias(tenant_id, data, ativo);

-- Horários já tem relacionamento com dias (que tem tenant)
-- Índice: dia + hora + ativo
DROP INDEX IF EXISTS idx_dia_hora ON horarios;
CREATE INDEX idx_dia_hora_ativo ON horarios(dia_id, hora, ativo);

-- ==========================================
-- 6. TURMAS - Índices tenant-first
-- ==========================================

-- Índice: tenant + status (turmas ativas)
DROP INDEX IF EXISTS idx_tenant_status_turma ON turmas;
CREATE INDEX idx_tenant_status_turma ON turmas(tenant_id, status);

-- Índice: tenant + modalidade (turmas por modalidade)
DROP INDEX IF EXISTS idx_tenant_modalidade_turma ON turmas;
CREATE INDEX idx_tenant_modalidade_turma ON turmas(tenant_id, modalidade_id);

-- ==========================================
-- 7. PAGAMENTOS_CONTRATO - Índices tenant-first
-- ==========================================

-- Adicionar tenant_id se não existir
-- ALTER TABLE pagamentos_contrato 
-- ADD COLUMN tenant_id INT NOT NULL AFTER id;

-- Índice: tenant + tenant_plano
DROP INDEX IF EXISTS idx_tenant_plano_pagamento ON pagamentos_contrato;
CREATE INDEX idx_tenant_plano_pagamento ON pagamentos_contrato(tenant_id, tenant_plano_id);

-- Índice: tenant + data_pagamento
DROP INDEX IF EXISTS idx_tenant_data_pagamento ON pagamentos_contrato;
CREATE INDEX idx_tenant_data_pagamento ON pagamentos_contrato(tenant_id, data_pagamento);

-- ==========================================
-- 8. REMOVER ÍNDICES ANTIGOS (não tenant-first)
-- ==========================================

-- contas_receber
DROP INDEX IF EXISTS idx_status ON contas_receber;
DROP INDEX IF EXISTS idx_data_vencimento ON contas_receber;
DROP INDEX IF EXISTS idx_plano ON contas_receber;
DROP INDEX IF EXISTS idx_referencia ON contas_receber;

-- checkins (antigos)
DROP INDEX IF EXISTS idx_checkins_usuario ON checkins;
DROP INDEX IF EXISTS idx_checkins_horario ON checkins;

-- planos
DROP INDEX IF EXISTS idx_planos_disponiveis ON planos;

-- ==========================================
-- OBSERVAÇÕES
-- ==========================================
-- 1. Tenant-First Strategy:
--    - Melhora isolamento de dados entre tenants
--    - Reduz risco de "data leak" entre academias
--    - Otimiza queries mais comuns (sempre filtram por tenant)
--
-- 2. Índices Compostos:
--    - Ordem das colunas IMPORTA
--    - (tenant_id, usuario_id) serve para:
--      ✅ WHERE tenant_id = 1
--      ✅ WHERE tenant_id = 1 AND usuario_id = 10
--      ❌ WHERE usuario_id = 10 (precisa outro índice)
--
-- 3. Trade-offs:
--    - Mais índices = Mais espaço em disco
--    - Mais índices = INSERT/UPDATE mais lentos
--    - Benefício: SELECT muito mais rápidos
--
-- 4. Tenant_id em checkins/dias:
--    - CRÍTICO para isolamento de dados
--    - Evita JOIN complexos para filtrar por tenant
--    - Melhora performance significativamente
--
-- 5. Queries Beneficiadas:
--    - Listagem de contas de um tenant
--    - Checkins do dia de uma academia
--    - Matrículas ativas por academia
--    - Relatórios financeiros por tenant
--
-- 6. Rollback:
--    - DROP INDEX para cada índice criado
--    - ALTER TABLE ... DROP COLUMN tenant_id (checkins, dias)
--    - Recriar índices antigos se necessário
