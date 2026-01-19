-- Seed de usuários para o Tenant 4
-- Criando 2 usuários de teste

-- Senha padrão para todos: "senha123" (hash bcrypt)
-- Hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi

-- Inserir os usuários
INSERT INTO usuarios (
    nome, 
    email, 
    senha_hash, 
    telefone, 
    cpf,
    cep,
    logradouro,
    numero,
    complemento,
    bairro,
    cidade,
    estado,
    role_id
) VALUES 
(
    'Ricardo Mendes',
    'ricardo.mendes@tenant4.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    '(11) 98765-4321',
    '12345678900',
    '01310-100',
    'Avenida Paulista',
    '1578',
    'Apto 101',
    'Bela Vista',
    'São Paulo',
    'SP',
    1  -- Role: Aluno
),
(
    'Carolina Ferreira',
    'carolina.ferreira@tenant4.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    '(11) 97654-3210',
    '98765432100',
    '04538-133',
    'Avenida Brigadeiro Faria Lima',
    '3477',
    'Sala 502',
    'Itaim Bibi',
    'São Paulo',
    'SP',
    1  -- Role: Aluno
);

-- Associar os usuários ao tenant 4
INSERT INTO usuario_tenant (
    usuario_id,
    tenant_id,
    status,
    data_inicio
)
SELECT 
    u.id,
    4 as tenant_id,
    'ativo' as status,
    NOW() as data_inicio
FROM usuarios u
WHERE u.email IN (
    'ricardo.mendes@tenant4.com',
    'carolina.ferreira@tenant4.com'
);

-- Exibir resultado
SELECT 
    u.id,
    u.nome,
    u.email,
    u.cpf,
    u.telefone,
    ut.tenant_id,
    ut.status,
    'senha123' as senha_padrao
FROM usuarios u
INNER JOIN usuario_tenant ut ON ut.usuario_id = u.id
WHERE ut.tenant_id = 4
AND u.email IN (
    'ricardo.mendes@tenant4.com',
    'carolina.ferreira@tenant4.com'
)
ORDER BY u.nome;
