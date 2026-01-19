-- =====================================================
-- MIGRATION 038: Adicionar status_id nas tabelas
-- Fase de transição: mantém ENUM + adiciona FK
-- =====================================================

-- 1. Adicionar status_id em contas_receber
ALTER TABLE contas_receber 
ADD COLUMN status_id INT NULL COMMENT 'FK para status_conta_receber' AFTER status;

-- 2. Adicionar status_id em matriculas
ALTER TABLE matriculas 
ADD COLUMN status_id INT NULL COMMENT 'FK para status_matricula' AFTER status;

-- 3. Adicionar status_id em pagamentos (se existir coluna status)
-- ALTER TABLE pagamentos 
-- ADD COLUMN status_id INT NULL COMMENT 'FK para status_pagamento' AFTER status;

-- 4. Migrar dados existentes de ENUM para FK
UPDATE contas_receber cr 
JOIN status_conta_receber scr ON cr.status = scr.codigo 
SET cr.status_id = scr.id
WHERE cr.status_id IS NULL;

UPDATE matriculas m 
JOIN status_matricula sm ON m.status = sm.codigo 
SET m.status_id = sm.id
WHERE m.status_id IS NULL;

-- UPDATE pagamentos p 
-- JOIN status_pagamento sp ON p.status = sp.codigo 
-- SET p.status_id = sp.id
-- WHERE p.status_id IS NULL;

-- 5. Adicionar Foreign Keys
ALTER TABLE contas_receber 
ADD CONSTRAINT fk_contas_receber_status 
FOREIGN KEY (status_id) REFERENCES status_conta_receber(id);

ALTER TABLE matriculas 
ADD CONSTRAINT fk_matriculas_status 
FOREIGN KEY (status_id) REFERENCES status_matricula(id);

-- ALTER TABLE pagamentos 
-- ADD CONSTRAINT fk_pagamentos_status 
-- FOREIGN KEY (status_id) REFERENCES status_pagamento(id);

-- 6. Criar índices para performance
CREATE INDEX idx_contas_receber_status_id ON contas_receber(status_id);
CREATE INDEX idx_matriculas_status_id ON matriculas(status_id);
-- CREATE INDEX idx_pagamentos_status_id ON pagamentos(status_id);

-- 7. Tornar status_id obrigatório (após migração dos dados)
ALTER TABLE contas_receber MODIFY status_id INT NOT NULL;
ALTER TABLE matriculas MODIFY status_id INT NOT NULL;
-- ALTER TABLE pagamentos MODIFY status_id INT NOT NULL;
