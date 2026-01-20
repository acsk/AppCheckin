-- ===============================================
-- SCRIPT DE LIMPEZA DO BANCO DE DADOS
-- Mantém: SuperAdmin, PlanosSistema, FormaPagamento
-- ===============================================

-- ⚠️ AVISO: Este script APAGA DADOS!
-- Backup recomendado antes de executar

SET FOREIGN_KEY_CHECKS = 0;

-- ===============================================
-- 1. LIMPAR DADOS DE USUÁRIOS (mantém SuperAdmin)
-- ===============================================

-- Limpar tokens/sessões
DELETE FROM sessions WHERE 1=1;

-- Limpar check-ins
DELETE FROM checkins WHERE 1=1;

-- Limpar presenças
DELETE FROM presenqas WHERE 1=1;

-- Limpar histórico de planos
DELETE FROM historico_planos WHERE 1=1;

-- Limpar matrículas
DELETE FROM matriculas WHERE 1=1;

-- Limpar contas a receber
DELETE FROM contas_receber WHERE 1=1;

-- Limpar pagamentos
DELETE FROM pagamentos WHERE 1=1;

-- Limpar usuários (MANTÉM SuperAdmin com role_id = 3)
-- SuperAdmin é aquele com role_id = 3
DELETE FROM usuarios WHERE role_id != 3;

-- Limpar relação usuario_tenant (mas mantém SuperAdmin)
DELETE FROM usuario_tenant WHERE usuario_id NOT IN (
    SELECT id FROM usuarios WHERE role_id = 3
);

-- ===============================================
-- 2. LIMPAR TURMAS E PLANEJAMENTOS
-- ===============================================

DELETE FROM planejamento_horarios WHERE 1=1;
DELETE FROM planejamento_semanal WHERE 1=1;
DELETE FROM horarios WHERE 1=1;
DELETE FROM turmas WHERE 1=1;
DELETE FROM professores WHERE 1=1;

-- ===============================================
-- 3. LIMPAR MODALIDADES (exceto as padrão)
-- ===============================================

-- Se quiser manter modalidades específicas, descomente e ajuste:
-- DELETE FROM modalidades WHERE id NOT IN (1, 2, 3);

-- Se quiser limpar tudo:
DELETE FROM modalidades WHERE 1=1;

-- ===============================================
-- 4. LIMPAR DIAS
-- ===============================================

DELETE FROM dias WHERE 1=1;

-- ===============================================
-- 5. LIMPAR TENANTS (deixa tenant_id = 1 padrão)
-- ===============================================

-- Limpar feature flags
DELETE FROM feature_flags WHERE tenant_id > 1;

-- Limpar planos do tenant (exceto tenant 1)
DELETE FROM tenant_planos WHERE tenant_id > 1;

-- Limpar formas de pagamento por tenant (exceto tenant 1)
DELETE FROM tenant_formas_pagamento WHERE tenant_id > 1;

-- Limpar relacionamento tenant-plano-sistema (exceto tenant 1)
DELETE FROM tenant_planos_sistema WHERE tenant_id > 1;

-- Limpar tenants (exceto o padrão ID = 1)
DELETE FROM tenants WHERE id > 1;

-- ===============================================
-- 6. LIMPAR WOD (CrossFit - se houver)
-- ===============================================

DELETE FROM wod_resultados WHERE 1=1;
DELETE FROM wod_variacoes WHERE 1=1;
DELETE FROM wod_blocos WHERE 1=1;
DELETE FROM wods WHERE 1=1;

-- ===============================================
-- 7. LIMPAR OUTROS DADOS
-- ===============================================

DELETE FROM auxiliar WHERE 1=1;
DELETE FROM status WHERE 1=1;

-- ===============================================
-- DADOS QUE SERÃO MANTIDOS
-- ===============================================
-- ✅ usuarios: SuperAdmin (role_id = 3)
-- ✅ planos_sistema: Todos os planos
-- ✅ formas_pagamento: Todas as formas
-- ✅ tenants: tenant_id = 1 (padrão)
-- ✅ roles: Todas as roles

-- ===============================================
-- RESETAR AUTO_INCREMENT (opcional)
-- ===============================================

-- Descomente se quiser resetar IDs
-- ALTER TABLE usuarios AUTO_INCREMENT = 1;
-- ALTER TABLE turmas AUTO_INCREMENT = 1;
-- ALTER TABLE matriculas AUTO_INCREMENT = 1;
-- ALTER TABLE checkins AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;

-- ===============================================
-- FIM DO SCRIPT
-- ===============================================
-- Status final:
-- ✅ Banco limpo
-- ✅ SuperAdmin mantido
-- ✅ PlanosSistema mantido
-- ✅ FormaPagamento mantido
-- ✅ Pronto para novo ciclo de testes

ECHO 'Banco de dados limpo com sucesso!';
