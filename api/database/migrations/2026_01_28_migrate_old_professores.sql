-- Migration: Migrar professores antigos para nova estrutura
-- Data: 2026-01-28
-- Descrição: Cria usuários para professores que não têm usuario_id
--            e adiciona o papel de professor no tenant

-- ==============================================
-- MIGRAÇÃO DE DADOS DOS PROFESSORES ANTIGOS
-- ==============================================

-- Nota: Os professores antigos não têm email definido na nova estrutura
-- Vamos criar usuários com email temporário baseado no nome

-- Professor 1: Carlos Mendes
-- Criar usuário se não existir
INSERT INTO usuarios (nome, email, email_global, senha_hash, role_id, ativo, created_at)
SELECT 'Carlos Mendes', 'carlos.mendes@temp.local', 'carlos.mendes@temp.local', 
       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE email = 'carlos.mendes@temp.local');

SET @usuario_carlos = (SELECT id FROM usuarios WHERE email = 'carlos.mendes@temp.local');

-- Atualizar professor com usuario_id
UPDATE professores SET usuario_id = @usuario_carlos WHERE id = 1 AND usuario_id IS NULL;

-- Vincular ao tenant 2 (assumindo que era do tenant 2)
INSERT IGNORE INTO usuario_tenant (usuario_id, tenant_id, status, data_inicio)
SELECT @usuario_carlos, 2, 'ativo', CURDATE()
WHERE @usuario_carlos IS NOT NULL;

-- Adicionar papel de professor
INSERT IGNORE INTO tenant_usuario_papel (tenant_id, usuario_id, papel_id, ativo)
SELECT 2, @usuario_carlos, 2, 1
WHERE @usuario_carlos IS NOT NULL;

-- =============================================

-- Professor 2: Marcela Oliveira
INSERT INTO usuarios (nome, email, email_global, senha_hash, role_id, ativo, created_at)
SELECT 'Marcela Oliveira', 'marcela.oliveira@temp.local', 'marcela.oliveira@temp.local', 
       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE email = 'marcela.oliveira@temp.local');

SET @usuario_marcela = (SELECT id FROM usuarios WHERE email = 'marcela.oliveira@temp.local');

-- Atualizar professor com usuario_id
UPDATE professores SET usuario_id = @usuario_marcela WHERE id = 2 AND usuario_id IS NULL;

-- Vincular ao tenant 2
INSERT IGNORE INTO usuario_tenant (usuario_id, tenant_id, status, data_inicio)
SELECT @usuario_marcela, 2, 'ativo', CURDATE()
WHERE @usuario_marcela IS NOT NULL;

-- Adicionar papel de professor
INSERT IGNORE INTO tenant_usuario_papel (tenant_id, usuario_id, papel_id, ativo)
SELECT 2, @usuario_marcela, 2, 1
WHERE @usuario_marcela IS NOT NULL;

-- ==============================================
-- VERIFICAÇÃO
-- ==============================================
-- SELECT p.id, p.nome, p.usuario_id, u.email 
-- FROM professores p 
-- LEFT JOIN usuarios u ON u.id = p.usuario_id;
