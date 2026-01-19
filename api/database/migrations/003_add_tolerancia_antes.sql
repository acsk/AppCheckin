-- Adicionar tolerância antes do início da aula (separada da tolerância depois)

ALTER TABLE horarios 
ADD COLUMN tolerancia_antes_minutos INT NOT NULL DEFAULT 480 AFTER tolerancia_minutos;

-- Atualizar dados existentes: 8 horas (480 minutos) antes
UPDATE horarios SET tolerancia_antes_minutos = 480;

-- Manter tolerancia_minutos como 10 minutos DEPOIS do início
UPDATE horarios SET tolerancia_minutos = 10;
