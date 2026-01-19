-- ==========================================
-- Migration 043: Adicionar Regras de Unicidade
-- ==========================================
-- Descrição: Adiciona constraints UNIQUE para prevenir dados duplicados
-- Motivo: Evitar problemas como cobranças duplicadas, emails repetidos
-- Autor: Sistema
-- Data: 2026-01-06
-- ==========================================

-- ==========================================
-- 1. Email Global UNIQUE (já existe índice, adiciona constraint)
-- ==========================================
-- Email global deve ser único no sistema (identificação única cross-tenant)

ALTER TABLE usuarios 
ADD CONSTRAINT unique_email_global UNIQUE (email_global);

-- ==========================================
-- 2. CPF UNIQUE quando preenchido
-- ==========================================
-- CPF deve ser único globalmente (um CPF não pode ter múltiplos usuários)
-- Permite NULL (CPF opcional)

-- Primeiro, criar índice único parcial (apenas valores não-nulos)
-- MySQL não suporta índice parcial diretamente, então usamos UNIQUE com NULL permitido
-- NULL é tratado como valor único em MySQL (múltiplos NULL são permitidos)

ALTER TABLE usuarios 
ADD CONSTRAINT unique_cpf UNIQUE (cpf);

-- ==========================================
-- 3. Contas Receber: Prevenir Duplicação de Mensalidade
-- ==========================================
-- Regra: 1 conta por tenant, usuário, plano e mês de referência
-- Previne cobrança duplicada da mesma mensalidade

-- Verificar se já existe constraint similar
-- ALTER TABLE contas_receber DROP INDEX IF EXISTS unique_conta_mensal;

ALTER TABLE contas_receber 
ADD CONSTRAINT unique_conta_mensal 
UNIQUE (tenant_id, usuario_id, plano_id, referencia_mes);

-- ==========================================
-- 4. Matriculas: Uma matrícula ativa por usuário/plano/tenant
-- ==========================================
-- Previne múltiplas matrículas ativas do mesmo aluno no mesmo plano

-- Nota: Não podemos criar UNIQUE com condição WHERE status='ativa' no MySQL
-- Alternativas:
-- a) Usar trigger para validar antes de INSERT/UPDATE
-- b) Validar na camada de aplicação
-- c) Aceitar múltiplas matrículas e tratar na query (WHERE status='ativa' LIMIT 1)

-- Optamos por criar trigger de validação:

DELIMITER //

DROP TRIGGER IF EXISTS validar_matricula_ativa_unica//

CREATE TRIGGER validar_matricula_ativa_unica
BEFORE INSERT ON matriculas
FOR EACH ROW
BEGIN
    DECLARE matriculas_ativas INT;
    
    IF NEW.status = 'ativa' THEN
        SELECT COUNT(*) INTO matriculas_ativas
        FROM matriculas
        WHERE tenant_id = NEW.tenant_id
          AND usuario_id = NEW.usuario_id
          AND plano_id = NEW.plano_id
          AND status = 'ativa'
          AND id != COALESCE(NEW.id, 0);
        
        IF matriculas_ativas > 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Ja existe uma matricula ativa para este usuario e plano';
        END IF;
    END IF;
END//

DROP TRIGGER IF EXISTS validar_matricula_ativa_unica_update//

CREATE TRIGGER validar_matricula_ativa_unica_update
BEFORE UPDATE ON matriculas
FOR EACH ROW
BEGIN
    DECLARE matriculas_ativas INT;
    
    IF NEW.status = 'ativa' AND (OLD.status != 'ativa' OR NEW.plano_id != OLD.plano_id) THEN
        SELECT COUNT(*) INTO matriculas_ativas
        FROM matriculas
        WHERE tenant_id = NEW.tenant_id
          AND usuario_id = NEW.usuario_id
          AND plano_id = NEW.plano_id
          AND status = 'ativa'
          AND id != NEW.id;
        
        IF matriculas_ativas > 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Ja existe uma matricula ativa para este usuario e plano';
        END IF;
    END IF;
END//

DELIMITER ;

-- ==========================================
-- 5. Tenant Nome UNIQUE (boa prática)
-- ==========================================
-- Nome do tenant (academia) deve ser único no sistema

ALTER TABLE tenants 
ADD CONSTRAINT unique_tenant_nome UNIQUE (nome);

-- ==========================================
-- 6. CNPJ UNIQUE quando preenchido
-- ==========================================
-- CNPJ deve ser único globalmente

ALTER TABLE tenants 
ADD CONSTRAINT unique_tenant_cnpj UNIQUE (cnpj);

-- ==========================================
-- 7. Usuario-Tenant: Prevenir duplicação
-- ==========================================
-- Usuário não pode ter múltiplos registros no mesmo tenant
-- (já existe, mas garantir)

-- Verificar se constraint já existe
SELECT CONSTRAINT_NAME 
FROM information_schema.TABLE_CONSTRAINTS 
WHERE TABLE_NAME = 'usuario_tenant' 
  AND CONSTRAINT_TYPE = 'UNIQUE'
  AND TABLE_SCHEMA = DATABASE();

-- Se não existir, criar:
-- ALTER TABLE usuario_tenant 
-- ADD CONSTRAINT unique_usuario_tenant UNIQUE (usuario_id, tenant_id);

-- ==========================================
-- OBSERVAÇÕES
-- ==========================================
-- 1. Email Global:
--    - Identificação única do usuário no sistema
--    - Permite login cross-tenant
--    - Formato: original@tenant.dominio
--
-- 2. CPF:
--    - UNIQUE permite múltiplos NULL (CPF opcional)
--    - Um CPF válido só pode pertencer a 1 usuário
--    - Validação de formato deve ser na aplicação
--
-- 3. Contas Receber:
--    - referencia_mes formato YYYY-MM
--    - Previne duplicação de mensalidade do mesmo mês
--    - Se precisar 2+ cobranças no mesmo mês: usar observacoes
--
-- 4. Matrículas Ativas:
--    - Trigger valida antes de INSERT/UPDATE
--    - Permite múltiplas matrículas inativas (histórico)
--    - 1 matrícula ativa por usuário/plano/tenant
--
-- 5. Rollback:
--    DROP CONSTRAINT unique_email_global;
--    DROP CONSTRAINT unique_cpf;
--    DROP CONSTRAINT unique_conta_mensal;
--    DROP CONSTRAINT unique_tenant_nome;
--    DROP CONSTRAINT unique_tenant_cnpj;
--    DROP TRIGGER validar_matricula_ativa_unica;
--    DROP TRIGGER validar_matricula_ativa_unica_update;
