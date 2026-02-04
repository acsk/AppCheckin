-- ========================================
-- SEED: Professores sem vínculo com Tenant
-- ========================================
-- 
-- Este seed cria 3 professores globalmente (com usuários),
-- mas SEM vínculos em tenant_usuario_papel.
--
-- OBJETIVO: Testar o fluxo de associação de professores 
-- existentes a um tenant via POST /admin/professores
--
-- Uso:
-- docker exec -i appcheckin_mysql mysql -u root -proot appcheckin < database/seeds/seed_professores_sem_tenant.sql
-- ========================================

USE appcheckin;

-- Verificar se os usuários já existem (evitar duplicação)
SET @usuario1_existe = (SELECT COUNT(*) FROM usuarios WHERE email = 'prof.maria.oliveira@exemplo.com');
SET @usuario2_existe = (SELECT COUNT(*) FROM usuarios WHERE email = 'prof.pedro.santos@exemplo.com');
SET @usuario3_existe = (SELECT COUNT(*) FROM usuarios WHERE email = 'prof.ana.costa@exemplo.com');

-- ========================================
-- PROFESSOR 1: Maria Oliveira
-- ========================================

-- Criar usuário (se não existir)
INSERT INTO usuarios (nome, email, cpf, telefone, senha_hash, ativo, created_at, updated_at)
SELECT 
    'Maria Oliveira',
    'prof.maria.oliveira@exemplo.com',
    '11122233344',
    '11987654321',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- senha: password
    1,
    NOW(),
    NOW()
WHERE @usuario1_existe = 0;

-- Obter ID do usuário
SET @maria_usuario_id = (SELECT id FROM usuarios WHERE email = 'prof.maria.oliveira@exemplo.com');

-- Criar professor (se não existir)
INSERT INTO professores (usuario_id, nome, cpf, email, foto_url, ativo, created_at, updated_at)
SELECT 
    @maria_usuario_id,
    'Maria Oliveira',
    '11122233344',
    'prof.maria.oliveira@exemplo.com',
    NULL,
    1,
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM professores WHERE cpf = '11122233344'
);

SELECT CONCAT('✅ Professor 1 criado: Maria Oliveira (CPF: 11122233344)') as resultado;

-- ========================================
-- PROFESSOR 2: Pedro Santos
-- ========================================

-- Criar usuário (se não existir)
INSERT INTO usuarios (nome, email, cpf, telefone, senha_hash, ativo, created_at, updated_at)
SELECT 
    'Pedro Santos',
    'prof.pedro.santos@exemplo.com',
    '22233344455',
    '11976543210',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- senha: password
    1,
    NOW(),
    NOW()
WHERE @usuario2_existe = 0;

-- Obter ID do usuário
SET @pedro_usuario_id = (SELECT id FROM usuarios WHERE email = 'prof.pedro.santos@exemplo.com');

-- Criar professor (se não existir)
INSERT INTO professores (usuario_id, nome, cpf, email, foto_url, ativo, created_at, updated_at)
SELECT 
    @pedro_usuario_id,
    'Pedro Santos',
    '22233344455',
    'prof.pedro.santos@exemplo.com',
    NULL,
    1,
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM professores WHERE cpf = '22233344455'
);

SELECT CONCAT('✅ Professor 2 criado: Pedro Santos (CPF: 22233344455)') as resultado;

-- ========================================
-- PROFESSOR 3: Ana Costa
-- ========================================

-- Criar usuário (se não existir)
INSERT INTO usuarios (nome, email, cpf, telefone, senha_hash, ativo, created_at, updated_at)
SELECT 
    'Ana Costa',
    'prof.ana.costa@exemplo.com',
    '33344455566',
    '11965432109',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- senha: password
    1,
    NOW(),
    NOW()
WHERE @usuario3_existe = 0;

-- Obter ID do usuário
SET @ana_usuario_id = (SELECT id FROM usuarios WHERE email = 'prof.ana.costa@exemplo.com');

-- Criar professor (se não existir)
INSERT INTO professores (usuario_id, nome, cpf, email, foto_url, ativo, created_at, updated_at)
SELECT 
    @ana_usuario_id,
    'Ana Costa',
    '33344455566',
    'prof.ana.costa@exemplo.com',
    NULL,
    1,
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM professores WHERE cpf = '33344455566'
);

SELECT CONCAT('✅ Professor 3 criado: Ana Costa (CPF: 33344455566)') as resultado;

-- ========================================
-- RESUMO
-- ========================================

SELECT '========================================' as '';
SELECT 'SEED CONCLUÍDO' as '';
SELECT '========================================' as '';
SELECT '' as '';

SELECT 'PROFESSORES CRIADOS (SEM VÍNCULO COM TENANT):' as '';
SELECT '' as '';

SELECT 
    p.id,
    p.nome,
    p.cpf,
    p.email,
    p.usuario_id,
    u.email as usuario_email
FROM professores p
INNER JOIN usuarios u ON u.id = p.usuario_id
WHERE p.cpf IN ('11122233344', '22233344455', '33344455566')
ORDER BY p.id;

SELECT '' as '';
SELECT 'VERIFICAÇÃO - tenant_usuario_papel:' as '';
SELECT CONCAT(
    'Total de vínculos encontrados: ',
    COUNT(*)
) as resultado
FROM tenant_usuario_papel tup
INNER JOIN professores p ON p.usuario_id = tup.usuario_id
WHERE p.cpf IN ('11122233344', '22233344455', '33344455566')
AND tup.papel_id = 2;

SELECT '' as '';
SELECT '✅ Estes professores estão prontos para serem associados a tenants!' as '';
SELECT '' as '';
SELECT 'COMO TESTAR:' as '';
SELECT '1. POST /api/admin/professores com CPF: 11122233344' as '';
SELECT '2. Deve associar Maria Oliveira ao tenant' as '';
SELECT '3. NÃO deve gerar nova senha (usuário já existe)' as '';
SELECT '' as '';

-- ========================================
-- DADOS DE TESTE
-- ========================================

SELECT '========================================' as '';
SELECT 'DADOS PARA TESTE DA API' as '';
SELECT '========================================' as '';
SELECT '' as '';

SELECT 'POST /api/admin/professores' as endpoint;
SELECT '{
  "nome": "Maria Oliveira",
  "email": "prof.maria.oliveira@exemplo.com",
  "cpf": "11122233344",
  "telefone": "11987654321"
}' as body_exemplo;

SELECT '' as '';
SELECT 'Credenciais de Login (todos professores):' as '';
SELECT '  Email: prof.maria.oliveira@exemplo.com' as '';
SELECT '  Email: prof.pedro.santos@exemplo.com' as '';
SELECT '  Email: prof.ana.costa@exemplo.com' as '';
SELECT '  Senha: password' as '';
SELECT '' as '';
