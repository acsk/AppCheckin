-- Migration: Adicionar coluna dias_tolerancia na tabela status_pagamento
-- Data: 2026-01-08
-- Descrição: Adiciona campo para definir dias de tolerância por status de pagamento

-- Adicionar coluna dias_tolerancia
ALTER TABLE `status_pagamento` 
ADD COLUMN `dias_tolerancia` INT DEFAULT 0 COMMENT 'Número de dias de tolerância para este status' AFTER `descricao`;

-- Atualizar valores padrão
-- Aguardando: 5 dias de tolerância antes de virar Atrasado
UPDATE `status_pagamento` SET `dias_tolerancia` = 5 WHERE `id` = 1;

-- Pago: não tem tolerância (já foi pago)
UPDATE `status_pagamento` SET `dias_tolerancia` = 0 WHERE `id` = 2;

-- Atrasado: não tem mais tolerância
UPDATE `status_pagamento` SET `dias_tolerancia` = 0 WHERE `id` = 3;

-- Cancelado: não tem tolerância
UPDATE `status_pagamento` SET `dias_tolerancia` = 0 WHERE `id` = 4;
