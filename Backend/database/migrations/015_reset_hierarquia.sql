-- ========================================
-- RESET COMPLETO DO BANCO DE DADOS
-- ========================================
-- ATENÇÃO: Este script apaga TODOS os dados!
-- Use apenas em desenvolvimento

-- Desabilitar verificação de foreign keys
SET FOREIGN_KEY_CHECKS = 0;

-- Limpar dados de todas as tabelas (manter estrutura)
TRUNCATE TABLE checkins;
TRUNCATE TABLE matriculas;
TRUNCATE TABLE contas_receber;
TRUNCATE TABLE historico_planos;
TRUNCATE TABLE horarios;
TRUNCATE TABLE dias;
TRUNCATE TABLE usuario_tenant;
TRUNCATE TABLE usuarios;
TRUNCATE TABLE planos;
TRUNCATE TABLE tenants;

-- Reabilitar verificação
SET FOREIGN_KEY_CHECKS = 1;

-- ========================================
-- CRIAR TENANT PADRÃO (Sistema)
-- ========================================
INSERT INTO tenants (id, nome, slug, email, ativo) VALUES
(1, 'Sistema AppCheckin', 'sistema', 'admin@appcheckin.com', 1);

-- ========================================
-- CRIAR SUPER ADMIN (único usuário inicial)
-- ========================================
-- Email: superadmin@appcheckin.com
-- Senha: SuperAdmin@2025
INSERT INTO usuarios (
    id, 
    tenant_id, 
    nome, 
    email, 
    email_global, 
    role_id, 
    senha_hash
) VALUES (
    1,
    1,
    'Super Administrador',
    'superadmin@appcheckin.com',
    'superadmin@appcheckin.com',
    3, -- super_admin
    '$2y$10$vD8HFxqN7Xh5rQJK5kZ.XeYGYPE5V7kP.0OqW4tZqGYMxH7p7LqKK' -- SuperAdmin@2025
);

-- Criar vínculo do super admin com tenant sistema
INSERT INTO usuario_tenant (usuario_id, tenant_id, status, data_inicio) VALUES
(1, 1, 'ativo', CURRENT_DATE);

-- ========================================
-- RESUMO
-- ========================================
SELECT '✓ Banco de dados resetado com sucesso!' as status;
SELECT '✓ Super Admin criado' as status;
SELECT '' as '';
SELECT 'CREDENCIAIS INICIAIS:' as info;
SELECT '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━' as '';
SELECT 'Email: superadmin@appcheckin.com' as credencial;
SELECT 'Senha: SuperAdmin@2025' as credencial;
SELECT '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━' as '';
SELECT '' as '';
SELECT 'PRÓXIMOS PASSOS:' as info;
SELECT '1. Faça login como Super Admin' as passo;
SELECT '2. Crie academias/tenants' as passo;
SELECT '3. Crie usuários Admin para cada academia' as passo;
SELECT '4. Admins poderão criar seus planos e usuários' as passo;
