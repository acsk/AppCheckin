-- Adiciona campo role para controle de permissões (aluno, admin, super_admin)
ALTER TABLE usuarios ADD COLUMN role ENUM('aluno', 'admin', 'super_admin') DEFAULT 'aluno' AFTER email;

-- Atualizar usuário teste para admin
UPDATE usuarios SET role = 'admin' WHERE email = 'teste@exemplo.com';
