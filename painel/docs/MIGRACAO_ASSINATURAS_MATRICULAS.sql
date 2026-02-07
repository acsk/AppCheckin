-- ============================================
-- MIGRATIONS: Integração Assinaturas Mercado Pago + Matrículas
-- ============================================
-- Data: 2025-01-15
-- Atualizado: 2026-02-07
-- Objetivo: Adicionar relacionamento 1:1 entre assinaturas_mercadopago e matrículas
-- NOTA: A tabela assinaturas_mercadopago já possui matricula_id na sua criação

-- ============================================
-- 1. VERIFICAR SE COLUNA EXISTE EM ASSINATURAS_MERCADOPAGO
-- ============================================
-- A tabela assinaturas_mercadopago já foi criada com matricula_id
-- Este ALTER só é necessário se a coluna não existir

/*
ALTER TABLE assinaturas_mercadopago ADD COLUMN IF NOT EXISTS (
  matricula_id INT UNSIGNED UNIQUE NULL COMMENT 'FK para matrícula associada',
  FOREIGN KEY (matricula_id) REFERENCES matriculas(id) ON DELETE CASCADE,
  INDEX idx_matricula_id (matricula_id),
  INDEX idx_assinatura_matricula_status (matricula_id, status)
);
*/

-- ============================================
-- 2. VINCULAR ASSINATURAS EXISTENTES (OPCIONAL)
-- ============================================
-- Se você quiser tentar vincular assinaturas existentes com matrículas
-- baseado no aluno + plano (use com cautela!)

/*
UPDATE assinaturas_mercadopago a
INNER JOIN matriculas m ON 
  a.aluno_id = m.aluno_id 
  AND a.plano_id = m.plano_id 
  AND a.tenant_id = m.tenant_id
  AND a.matricula_id IS NULL
SET a.matricula_id = m.id
WHERE m.status IN ('ativa', 'suspensa')
LIMIT 100;  -- Processar em lotes se houver muitas

-- Verificar vínculos criados
SELECT COUNT(*) as vinculos_criados
FROM assinaturas_mercadopago
WHERE matricula_id IS NOT NULL;
*/

-- ============================================
-- 3. ADICIONAR COLUNA EM MATRÍCULAS (OPCIONAL)
-- ============================================
-- Para acesso rápido da matrícula para a assinatura

ALTER TABLE matriculas ADD COLUMN IF NOT EXISTS (
  assinatura_mercadopago_id INT UNSIGNED UNIQUE NULL COMMENT 'FK para assinatura Mercado Pago ativa',
  INDEX idx_assinatura_mp_id (assinatura_mercadopago_id)
) COMMENT='Desnormalização para acesso rápido';

-- Sincronizar IDs:
/*
UPDATE matriculas m
INNER JOIN assinaturas_mercadopago a ON m.id = a.matricula_id
SET m.assinatura_mercadopago_id = a.id
WHERE a.status IN ('authorized', 'pending');
*/

-- ============================================
-- 4. CRIAR ÍNDICES PARA PERFORMANCE
-- ============================================

-- Buscar assinaturas por matrícula (já existe na criação da tabela)
-- CREATE INDEX idx_assinatura_mp_matricula 
-- ON assinaturas_mercadopago(matricula_id, status, proxima_cobranca);

-- Buscar assinaturas sem matrícula (órfãs)
CREATE INDEX IF NOT EXISTS idx_assinatura_mp_sem_matricula 
ON assinaturas_mercadopago(matricula_id, status);

-- Buscar matrículas com assinaturas
CREATE INDEX IF NOT EXISTS idx_matricula_assinatura_mp 
ON matriculas(assinatura_mercadopago_id, status);

-- ============================================
-- 5. TABELA PARA HISTÓRICO DE SINCRONIZAÇÕES
-- ============================================
-- (OPCIONAL) Rastrear quando assinaturas e matrículas foram sincronizadas

CREATE TABLE IF NOT EXISTS assinatura_mp_sincronizacoes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assinatura_mercadopago_id INT UNSIGNED NOT NULL,
  matricula_id INT UNSIGNED NOT NULL,
  status_anterior_assinatura VARCHAR(20),
  status_novo_assinatura VARCHAR(20),
  status_anterior_matricula VARCHAR(20),
  status_novo_matricula VARCHAR(20),
  tipo_sincronizacao ENUM('manual', 'automatica', 'webhook') DEFAULT 'automatica',
  usuario_id INT UNSIGNED NULL COMMENT 'Quem solicitou a sincronização',
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (assinatura_mercadopago_id) REFERENCES assinaturas_mercadopago(id) ON DELETE CASCADE,
  FOREIGN KEY (matricula_id) REFERENCES matriculas(id) ON DELETE CASCADE,
  INDEX idx_assinatura_mp_data (assinatura_mercadopago_id, criado_em),
  INDEX idx_matricula_data (matricula_id, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Histórico de sincronizações entre assinatura MP e matrícula';

-- ============================================
-- 6. VALIDAÇÕES COM TRIGGERS (OPCIONAL)
-- ============================================
-- Garantir que status permaneçam sincronizados
-- NOTA: Os status do Mercado Pago são: authorized, pending, paused, cancelled

DELIMITER $$

-- Trigger ao atualizar status na matrícula
CREATE TRIGGER tr_matricula_update_sincroniza_assinatura_mp
AFTER UPDATE ON matriculas
FOR EACH ROW
BEGIN
  IF OLD.status != NEW.status AND NEW.assinatura_mercadopago_id IS NOT NULL THEN
    -- Mapear status da matrícula para status do MP
    DECLARE mp_status VARCHAR(20);
    SET mp_status = CASE NEW.status
      WHEN 'ativa' THEN 'authorized'
      WHEN 'suspensa' THEN 'paused'
      WHEN 'cancelada' THEN 'cancelled'
      ELSE 'pending'
    END;
    
    -- Atualizar status da assinatura associada
    UPDATE assinaturas_mercadopago 
    SET status = mp_status,
        atualizado_em = NOW()
    WHERE id = NEW.assinatura_mercadopago_id;
    
    -- Registrar sincronização
    INSERT INTO assinatura_mp_sincronizacoes 
    (assinatura_mercadopago_id, matricula_id, status_anterior_matricula, 
     status_novo_matricula, tipo_sincronizacao)
    VALUES 
    (NEW.assinatura_mercadopago_id, NEW.id, OLD.status, NEW.status, 'automatica');
  END IF;
END$$

-- Trigger ao atualizar status na assinatura MP
CREATE TRIGGER tr_assinatura_mp_update_sincroniza_matricula
AFTER UPDATE ON assinaturas_mercadopago
FOR EACH ROW
BEGIN
  IF OLD.status != NEW.status AND NEW.matricula_id IS NOT NULL THEN
    -- Mapear status do MP para status da matrícula
    DECLARE mat_status VARCHAR(20);
    SET mat_status = CASE NEW.status
      WHEN 'authorized' THEN 'ativa'
      WHEN 'pending' THEN 'pendente'
      WHEN 'paused' THEN 'suspensa'
      WHEN 'cancelled' THEN 'cancelada'
      ELSE 'pendente'
    END;
    
    -- Atualizar status da matrícula associada
    UPDATE matriculas 
    SET status = mat_status,
        atualizado_em = NOW()
    WHERE id = NEW.matricula_id;
    
    -- Registrar sincronização
    INSERT INTO assinatura_mp_sincronizacoes 
    (assinatura_mercadopago_id, matricula_id, status_anterior_assinatura, 
     status_novo_assinatura, tipo_sincronizacao)
    VALUES 
    (NEW.id, NEW.matricula_id, OLD.status, NEW.status, 'automatica');
  END IF;
END$$

DELIMITER ;

-- ============================================
-- 7. VERIFICAÇÕES E LIMPEZA
-- ============================================

-- Contar assinaturas sem matrícula (órfãs)
SELECT COUNT(*) as assinaturas_mp_orfas
FROM assinaturas_mercadopago
WHERE matricula_id IS NULL
  AND status IN ('authorized', 'pending');

-- Contar matrículas sem assinatura
SELECT COUNT(*) as matriculas_sem_assinatura_mp
FROM matriculas m
LEFT JOIN assinaturas_mercadopago a ON m.id = a.matricula_id
WHERE m.status IN ('ativa', 'suspensa')
  AND a.id IS NULL;

-- Verificar desincronizações (considerando mapeamento de status)
SELECT 
  a.id as assinatura_mp_id,
  m.id as matricula_id,
  a.status as status_assinatura_mp,
  m.status as status_matricula,
  CASE 
    WHEN a.status = 'authorized' AND m.status = 'ativa' THEN 'OK'
    WHEN a.status = 'pending' AND m.status IN ('pendente', 'ativa') THEN 'OK'
    WHEN a.status = 'paused' AND m.status = 'suspensa' THEN 'OK'
    WHEN a.status = 'cancelled' AND m.status = 'cancelada' THEN 'OK'
    ELSE 'DESINCRONIZADO'
  END as sincronizacao
FROM assinaturas_mercadopago a
INNER JOIN matriculas m ON a.matricula_id = m.id;

-- ============================================
-- 8. SCRIPT DE ROLLBACK
-- ============================================
-- Execute se precisar desfazer as mudanças

/*
-- Remover triggers
DROP TRIGGER IF EXISTS tr_matricula_update_sincroniza_assinatura_mp;
DROP TRIGGER IF EXISTS tr_assinatura_mp_update_sincroniza_matricula;

-- Remover tabela de histórico
DROP TABLE IF EXISTS assinatura_mp_sincronizacoes;

-- Remover índices
DROP INDEX IF EXISTS idx_assinatura_mp_sem_matricula ON assinaturas_mercadopago;
DROP INDEX IF EXISTS idx_matricula_assinatura_mp ON matriculas;

-- Remover coluna de matrículas (se foi adicionada)
ALTER TABLE matriculas DROP COLUMN IF EXISTS assinatura_mercadopago_id;
*/

-- ============================================
-- 9. VERIFICAÇÃO FINAL
-- ============================================

-- Verificar estrutura atualizada
DESC assinaturas_mercadopago;
DESC matriculas;

-- Verificar índices
SHOW INDEX FROM assinaturas_mercadopago;
SHOW INDEX FROM matriculas;

-- Verificar triggers
SHOW TRIGGERS WHERE `Table` IN ('assinaturas_mercadopago', 'matriculas');

-- ============================================
-- MAPEAMENTO DE STATUS
-- ============================================
-- 
-- | Mercado Pago (assinaturas_mercadopago) | Matrícula (matriculas) |
-- |----------------------------------------|------------------------|
-- | authorized                             | ativa                  |
-- | pending                                | pendente               |
-- | paused                                 | suspensa               |
-- | cancelled                              | cancelada              |
--
-- ============================================
-- FIM DAS MIGRATIONS
-- ============================================
