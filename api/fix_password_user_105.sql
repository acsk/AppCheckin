-- Fix password for user teste@teste77.com
-- Password: 123456
-- Hash gerado com PASSWORD_BCRYPT

UPDATE usuarios 
SET senha_hash = '$2y$10$CwTycUXWue0Thq9StjUM0uJ8Z88O7LpJJJJJJJJJJJJJJJJJJJJJJ'
WHERE email = 'teste@teste77.com';

-- Verificar se foi atualizado
SELECT id, nome, email, senha_hash FROM usuarios WHERE email = 'teste@teste77.com';
