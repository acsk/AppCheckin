-- =====================================================
-- Sistema de Vencimento e Transição Teste → Pagamento
-- Data: 06/02/2026
-- =====================================================

-- NOTA: Se os campos/índices já existirem, ignore os erros de duplicação
-- Esta migration é segura para re-executar

-- Adicionar dia_vencimento
ALTER TABLE matriculas 
ADD COLUMN dia_vencimento TINYINT(2) DEFAULT NULL 
COMMENT 'Dia do mês para vencimento (1-31)' 
AFTER data_vencimento;

-- Adicionar flag de período teste
ALTER TABLE matriculas 
ADD COLUMN periodo_teste TINYINT(1) DEFAULT 0 
COMMENT '1 = período gratuito, 0 = cobrança normal'
AFTER dia_vencimento;

-- Adicionar data de início de cobrança
ALTER TABLE matriculas 
ADD COLUMN data_inicio_cobranca DATE NULL 
COMMENT 'Data que iniciará a cobrança (após período teste)'
AFTER periodo_teste;

-- Adicionar próxima data de vencimento (controla bloqueio check-in)
ALTER TABLE matriculas 
ADD COLUMN proxima_data_vencimento DATE NULL 
COMMENT 'Data real do próximo vencimento (controla acesso e bloqueio check-in)'
AFTER data_inicio_cobranca;

-- Índices para otimizar consultas (IF NOT EXISTS disponível no MySQL 8.0+)
CREATE INDEX IF NOT EXISTS idx_vencimento ON matriculas(dia_vencimento, status_id);
CREATE INDEX IF NOT EXISTS idx_cobranca ON matriculas(data_inicio_cobranca, periodo_teste);
CREATE INDEX IF NOT EXISTS idx_proxima_vencimento ON matriculas(proxima_data_vencimento, status_id);

SELECT '✅ Migration executada com sucesso!' AS resultado;
