-- Script para corrigir formato de CPFs no banco de dados
-- Remove máscaras dos CPFs (pontos e traços)

UPDATE usuarios 
SET cpf = REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '')
WHERE cpf IS NOT NULL;

-- Exibir resultado
SELECT id, nome, email, cpf 
FROM usuarios 
WHERE cpf IS NOT NULL
ORDER BY nome;
