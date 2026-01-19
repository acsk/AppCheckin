-- Criar tabela de roles (papéis/perfis)
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL UNIQUE,
    descricao VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir roles padrão
INSERT INTO roles (id, nome, descricao) VALUES
(1, 'aluno', 'Usuário comum com acesso ao app'),
(2, 'admin', 'Administrador da academia'),
(3, 'super_admin', 'Super administrador com acesso total');

-- Remover coluna role ENUM e adicionar role_id
ALTER TABLE usuarios DROP COLUMN role;
ALTER TABLE usuarios ADD COLUMN role_id INT DEFAULT 1 AFTER email;
ALTER TABLE usuarios ADD FOREIGN KEY (role_id) REFERENCES roles(id);

-- Atualizar usuário teste para admin
UPDATE usuarios SET role_id = 2 WHERE email = 'teste@exemplo.com';
