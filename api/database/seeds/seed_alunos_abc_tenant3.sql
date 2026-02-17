-- Seed: criar 3 alunos (AlunoA, AlunoB, AlunoC) e associar ao tenant 3
-- Senha padrão: 123456 (hash bcrypt)
SET @senha_hash = '$2y$10$D/9bvlBKp8DXqT/6EeH1XOCl5d4/Ah8xS7EcJ5t9zXDPvGK5qJqnG';
SET @tenant_id = 3;
SET @papel_aluno = 1;

-- 1) USUÁRIOS
INSERT INTO usuarios (nome, email, senha_hash, ativo)
SELECT 'AlunoA', 'alunoa@teste.com', @senha_hash, 1
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE email = 'alunoa@teste.com');

INSERT INTO usuarios (nome, email, senha_hash, ativo)
SELECT 'AlunoB', 'alunob@teste.com', @senha_hash, 1
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE email = 'alunob@teste.com');

INSERT INTO usuarios (nome, email, senha_hash, ativo)
SELECT 'AlunoC', 'alunoc@teste.com', @senha_hash, 1
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE email = 'alunoc@teste.com');

-- 2) ALUNOS
INSERT INTO alunos (usuario_id, nome, telefone, cpf, cidade, estado, ativo)
SELECT u.id, u.nome, '11999990001', '111.111.111-01', 'São Paulo', 'SP', 1
FROM usuarios u
WHERE u.email = 'alunoa@teste.com'
ON DUPLICATE KEY UPDATE nome = VALUES(nome), ativo = VALUES(ativo);

INSERT INTO alunos (usuario_id, nome, telefone, cpf, cidade, estado, ativo)
SELECT u.id, u.nome, '11999990002', '111.111.111-02', 'São Paulo', 'SP', 1
FROM usuarios u
WHERE u.email = 'alunob@teste.com'
ON DUPLICATE KEY UPDATE nome = VALUES(nome), ativo = VALUES(ativo);

INSERT INTO alunos (usuario_id, nome, telefone, cpf, cidade, estado, ativo)
SELECT u.id, u.nome, '11999990003', '111.111.111-03', 'São Paulo', 'SP', 1
FROM usuarios u
WHERE u.email = 'alunoc@teste.com'
ON DUPLICATE KEY UPDATE nome = VALUES(nome), ativo = VALUES(ativo);

-- 3) VÍNCULO COM TENANT (usuario_tenant) - apenas se a tabela existir
SET @usuario_tenant_existe = (
    SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'usuario_tenant'
);
SET @sql_usuario_tenant = IF(@usuario_tenant_existe > 0,
    'INSERT INTO usuario_tenant (usuario_id, tenant_id, status, data_inicio)
     SELECT u.id, 3, \"ativo\", CURDATE()
     FROM usuarios u
     WHERE u.email IN (\"alunoa@teste.com\", \"alunob@teste.com\", \"alunoc@teste.com\")
     ON DUPLICATE KEY UPDATE status = VALUES(status), data_inicio = VALUES(data_inicio)',
    'SELECT \"Tabela usuario_tenant não existe - ignorado\" AS info'
);
PREPARE stmt_ut FROM @sql_usuario_tenant;
EXECUTE stmt_ut;
DEALLOCATE PREPARE stmt_ut;

-- 4) PAPEL DE ALUNO (tenant_usuario_papel)
INSERT IGNORE INTO tenant_usuario_papel (tenant_id, usuario_id, papel_id, ativo)
SELECT @tenant_id, u.id, @papel_aluno, 1
FROM usuarios u
WHERE u.email IN ('alunoa@teste.com', 'alunob@teste.com', 'alunoc@teste.com');

-- Resumo
SELECT 'Seed executado com sucesso!' as status;
SELECT CONCAT('Usuários (tenant 3): ', COUNT(*)) as total
FROM usuarios u
INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = u.id
WHERE tup.tenant_id = @tenant_id
AND u.email IN ('alunoa@teste.com', 'alunob@teste.com', 'alunoc@teste.com');
