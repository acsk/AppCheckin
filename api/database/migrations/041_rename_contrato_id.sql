-- =====================================================
-- MIGRATION 041: Renomear FK de Pagamentos (Clareza)
-- Problema: pagamentos_contrato.contrato_id aponta para tenant_planos_sistema
--           mas o nome é enganoso
-- Solução: Renomear para tenant_plano_id para deixar claro
-- =====================================================

-- 1. Remover FK antiga
ALTER TABLE pagamentos_contrato 
DROP FOREIGN KEY pagamentos_contrato_ibfk_1;

-- 2. Renomear coluna
ALTER TABLE pagamentos_contrato 
CHANGE COLUMN contrato_id tenant_plano_id INT NOT NULL
COMMENT 'FK para tenant_planos_sistema (contrato da academia com plano do sistema)';

-- 3. Recriar FK com nome descritivo
ALTER TABLE pagamentos_contrato
ADD CONSTRAINT fk_pagamentos_tenant_plano
FOREIGN KEY (tenant_plano_id) 
REFERENCES tenant_planos_sistema(id) 
ON DELETE CASCADE;

-- 4. Atualizar índice se necessário
CREATE INDEX idx_pagamentos_tenant_plano ON pagamentos_contrato(tenant_plano_id);

-- =====================================================
-- NOTA: Se houver código no backend/frontend usando 'contrato_id',
-- atualizar para 'tenant_plano_id'
-- =====================================================

-- =====================================================
-- ALTERNATIVA FUTURA (comentada):
-- Se quiser criar uma tabela 'contratos' real:
-- 
-- CREATE TABLE contratos (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     tenant_plano_id INT NOT NULL,
--     numero_contrato VARCHAR(50) UNIQUE,
--     data_inicio DATE NOT NULL,
--     data_fim DATE,
--     valor_mensal DECIMAL(10,2),
--     status_id INT NOT NULL,
--     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     FOREIGN KEY (tenant_plano_id) REFERENCES tenant_planos_sistema(id),
--     FOREIGN KEY (status_id) REFERENCES status_contrato(id)
-- );
-- 
-- ALTER TABLE pagamentos_contrato 
-- ADD COLUMN contrato_id INT,
-- ADD FOREIGN KEY (contrato_id) REFERENCES contratos(id);
-- =====================================================
