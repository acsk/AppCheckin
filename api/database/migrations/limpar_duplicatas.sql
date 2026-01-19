-- ==========================================
-- Script de Limpeza de Duplicatas
-- ==========================================
-- Execute APENAS se verificar_duplicatas.sql encontrar problemas
-- ATENÇÃO: Este script DELETA dados! Revise antes de executar!
-- ==========================================

-- ==========================================
-- IMPORTANTE: BACKUP PRIMEIRO!
-- ==========================================
-- mysqldump -u root -p appcheckin > backup_antes_limpeza.sql
-- ==========================================

SET @linha = 0;

-- ==========================================
-- 1. LIMPAR EMAIL_GLOBAL DUPLICADO
-- ==========================================

SELECT '========================================' AS '';
SELECT '1. LIMPANDO EMAILS DUPLICADOS' AS '';
SELECT '========================================' AS '';
SELECT '' AS '';

-- Estratégia: Manter o registro mais ANTIGO (menor ID)
-- Deletar os demais

-- Criar tabela temporária com IDs a deletar
CREATE TEMPORARY TABLE IF NOT EXISTS temp_email_duplicados AS
SELECT u.id
FROM usuarios u
WHERE u.id NOT IN (
    SELECT MIN(id)
    FROM usuarios
    WHERE email_global IS NOT NULL
    GROUP BY email_global
)
AND u.email_global IN (
    SELECT email_global
    FROM usuarios
    WHERE email_global IS NOT NULL
    GROUP BY email_global
    HAVING COUNT(*) > 1
);

-- Mostrar o que será deletado
SELECT 
    u.id,
    u.nome,
    u.email,
    u.email_global,
    'SERÁ DELETADO (duplicata)' as acao
FROM usuarios u
JOIN temp_email_duplicados t ON u.id = t.id;

-- DESCOMENTAR PARA EXECUTAR A DELEÇÃO:
-- DELETE FROM usuarios WHERE id IN (SELECT id FROM temp_email_duplicados);

DROP TEMPORARY TABLE IF EXISTS temp_email_duplicados;

SELECT '✅ Emails duplicados identificados (DESCOMENTE para deletar)' AS '';
SELECT '' AS '';

-- ==========================================
-- 2. LIMPAR CPF DUPLICADO
-- ==========================================

SELECT '========================================' AS '';
SELECT '2. LIMPANDO CPFs DUPLICADOS' AS '';
SELECT '========================================' AS '';
SELECT '' AS '';

-- Estratégia: Manter o registro mais ANTIGO (menor ID)

CREATE TEMPORARY TABLE IF NOT EXISTS temp_cpf_duplicados AS
SELECT u.id
FROM usuarios u
WHERE u.cpf IS NOT NULL 
  AND u.cpf != ''
  AND u.id NOT IN (
    SELECT MIN(id)
    FROM usuarios
    WHERE cpf IS NOT NULL AND cpf != ''
    GROUP BY cpf
)
AND u.cpf IN (
    SELECT cpf
    FROM usuarios
    WHERE cpf IS NOT NULL AND cpf != ''
    GROUP BY cpf
    HAVING COUNT(*) > 1
);

-- Mostrar o que será deletado
SELECT 
    u.id,
    u.nome,
    u.cpf,
    'SERÁ DELETADO (duplicata)' as acao
FROM usuarios u
JOIN temp_cpf_duplicados t ON u.id = t.id;

-- DESCOMENTAR PARA EXECUTAR A DELEÇÃO:
-- DELETE FROM usuarios WHERE id IN (SELECT id FROM temp_cpf_duplicados);

DROP TEMPORARY TABLE IF EXISTS temp_cpf_duplicados;

SELECT '✅ CPFs duplicados identificados (DESCOMENTE para deletar)' AS '';
SELECT '' AS '';

-- ==========================================
-- 3. LIMPAR MENSALIDADES DUPLICADAS
-- ==========================================

SELECT '========================================' AS '';
SELECT '3. LIMPANDO MENSALIDADES DUPLICADAS' AS '';
SELECT '========================================' AS '';
SELECT '' AS '';

-- Estratégia: Manter o registro com status mais importante
-- Prioridade: pago > pendente > vencido > cancelado
-- Se status igual, manter o mais recente (maior ID)

CREATE TEMPORARY TABLE IF NOT EXISTS temp_mensalidade_duplicadas AS
SELECT cr.id
FROM contas_receber cr
WHERE cr.id NOT IN (
    SELECT MAX(id) FROM (
        SELECT 
            id,
            CASE 
                WHEN status = 'pago' THEN 1
                WHEN status = 'pendente' THEN 2
                WHEN status = 'vencido' THEN 3
                WHEN status = 'cancelado' THEN 4
                ELSE 5
            END as prioridade
        FROM contas_receber
        WHERE referencia_mes IS NOT NULL
    ) as sub
    GROUP BY tenant_id, usuario_id, plano_id, referencia_mes
)
AND EXISTS (
    SELECT 1 
    FROM contas_receber cr2 
    WHERE cr2.tenant_id = cr.tenant_id
      AND cr2.usuario_id = cr.usuario_id
      AND cr2.plano_id = cr.plano_id
      AND cr2.referencia_mes = cr.referencia_mes
      AND cr2.id != cr.id
);

-- Mostrar o que será deletado
SELECT 
    cr.id,
    cr.tenant_id,
    cr.usuario_id,
    u.nome as usuario_nome,
    cr.plano_id,
    p.nome as plano_nome,
    cr.referencia_mes,
    cr.valor,
    cr.status,
    'SERÁ DELETADO (duplicata)' as acao
FROM contas_receber cr
JOIN temp_mensalidade_duplicadas t ON cr.id = t.id
JOIN usuarios u ON cr.usuario_id = u.id
LEFT JOIN planos p ON cr.plano_id = p.id
ORDER BY cr.tenant_id, cr.usuario_id, cr.referencia_mes;

-- DESCOMENTAR PARA EXECUTAR A DELEÇÃO:
-- DELETE FROM contas_receber WHERE id IN (SELECT id FROM temp_mensalidade_duplicadas);

DROP TEMPORARY TABLE IF EXISTS temp_mensalidade_duplicadas;

SELECT '✅ Mensalidades duplicadas identificadas (DESCOMENTE para deletar)' AS '';
SELECT '' AS '';

-- ==========================================
-- 4. LIMPAR MATRÍCULAS ATIVAS DUPLICADAS
-- ==========================================

SELECT '========================================' AS '';
SELECT '4. LIMPANDO MATRÍCULAS ATIVAS DUPLICADAS' AS '';
SELECT '========================================' AS '';
SELECT '' AS '';

-- Estratégia: Manter a matrícula mais RECENTE (maior data_inicio)

CREATE TEMPORARY TABLE IF NOT EXISTS temp_matricula_duplicadas AS
SELECT m.id
FROM matriculas m
WHERE m.status = 'ativa'
  AND m.id NOT IN (
    SELECT MAX(id)
    FROM matriculas
    WHERE status = 'ativa'
    GROUP BY tenant_id, usuario_id, plano_id
)
AND EXISTS (
    SELECT 1 
    FROM matriculas m2 
    WHERE m2.tenant_id = m.tenant_id
      AND m2.usuario_id = m.usuario_id
      AND m2.plano_id = m.plano_id
      AND m2.status = 'ativa'
      AND m2.id != m.id
);

-- Mostrar o que será INATIVADO (não deletado)
SELECT 
    m.id,
    m.tenant_id,
    m.usuario_id,
    u.nome as usuario_nome,
    m.plano_id,
    p.nome as plano_nome,
    m.data_inicio,
    m.data_vencimento,
    'SERÁ INATIVADA (duplicata)' as acao
FROM matriculas m
JOIN temp_matricula_duplicadas t ON m.id = t.id
JOIN usuarios u ON m.usuario_id = u.id
LEFT JOIN planos p ON m.plano_id = p.id
ORDER BY m.tenant_id, m.usuario_id, m.data_inicio;

-- DESCOMENTAR PARA EXECUTAR A INATIVAÇÃO:
-- UPDATE matriculas SET status = 'suspensa' WHERE id IN (SELECT id FROM temp_matricula_duplicadas);

DROP TEMPORARY TABLE IF EXISTS temp_matricula_duplicadas;

SELECT '✅ Matrículas duplicadas identificadas (DESCOMENTE para inativar)' AS '';
SELECT '' AS '';

-- ==========================================
-- 5. LIMPAR CNPJ DUPLICADO (TENANTS)
-- ==========================================

SELECT '========================================' AS '';
SELECT '5. LIMPANDO CNPJs DUPLICADOS' AS '';
SELECT '========================================' AS '';
SELECT '' AS '';

-- Estratégia: Manter o tenant mais ANTIGO (menor ID)

CREATE TEMPORARY TABLE IF NOT EXISTS temp_cnpj_duplicados AS
SELECT t.id
FROM tenants t
WHERE t.cnpj IS NOT NULL 
  AND t.cnpj != ''
  AND t.id NOT IN (
    SELECT MIN(id)
    FROM tenants
    WHERE cnpj IS NOT NULL AND cnpj != ''
    GROUP BY cnpj
)
AND t.cnpj IN (
    SELECT cnpj
    FROM tenants
    WHERE cnpj IS NOT NULL AND cnpj != ''
    GROUP BY cnpj
    HAVING COUNT(*) > 1
);

-- Mostrar o que será afetado
SELECT 
    t.id,
    t.nome,
    t.cnpj,
    'ATENÇÃO: Afetará usuários, matrículas, etc!' as acao
FROM tenants t
JOIN temp_cnpj_duplicados tmp ON t.id = tmp.id;

-- IMPORTANTE: NÃO DELETE TENANTS diretamente!
-- Considere mesclar dados antes ou remover CNPJ duplicado:
-- UPDATE tenants SET cnpj = NULL WHERE id IN (SELECT id FROM temp_cnpj_duplicados);

DROP TEMPORARY TABLE IF EXISTS temp_cnpj_duplicados;

SELECT '⚠️  CNPJs duplicados identificados (NÃO delete tenants!)' AS '';
SELECT '' AS '';

-- ==========================================
-- 6. LIMPAR NOMES TENANT DUPLICADOS
-- ==========================================

SELECT '========================================' AS '';
SELECT '6. LIMPANDO NOMES TENANT DUPLICADOS' AS '';
SELECT '========================================' AS '';
SELECT '' AS '';

-- Estratégia: Adicionar sufixo numérico aos duplicados

CREATE TEMPORARY TABLE IF NOT EXISTS temp_nome_tenant_duplicados AS
SELECT 
    t1.id,
    t1.nome,
    CONCAT(t1.nome, ' (', t1.id, ')') as novo_nome
FROM tenants t1
WHERE t1.id NOT IN (
    SELECT MIN(id)
    FROM tenants
    GROUP BY nome
)
AND t1.nome IN (
    SELECT nome
    FROM tenants
    GROUP BY nome
    HAVING COUNT(*) > 1
);

-- Mostrar o que será renomeado
SELECT 
    id,
    nome as nome_atual,
    novo_nome,
    'SERÁ RENOMEADO' as acao
FROM temp_nome_tenant_duplicados;

-- DESCOMENTAR PARA EXECUTAR A RENOMEAÇÃO:
-- UPDATE tenants t
-- JOIN temp_nome_tenant_duplicados tmp ON t.id = tmp.id
-- SET t.nome = tmp.novo_nome;

DROP TEMPORARY TABLE IF EXISTS temp_nome_tenant_duplicados;

SELECT '✅ Nomes duplicados identificados (DESCOMENTE para renomear)' AS '';
SELECT '' AS '';

-- ==========================================
-- RESUMO E PRÓXIMOS PASSOS
-- ==========================================

SELECT '========================================' AS '';
SELECT 'RESUMO' AS '';
SELECT '========================================' AS '';
SELECT '' AS '';

SELECT 'AÇÕES RECOMENDADAS:' AS '';
SELECT '1. Revise os dados identificados acima' AS '';
SELECT '2. Descomente as linhas de DELETE/UPDATE conforme necessário' AS '';
SELECT '3. Execute este script novamente' AS '';
SELECT '4. Reexecute verificar_duplicatas.sql para confirmar' AS '';
SELECT '5. Se OK, execute: mysql < 043_adicionar_constraints_unicidade.sql' AS '';
SELECT '' AS '';

-- ==========================================
-- OBSERVAÇÕES IMPORTANTES
-- ==========================================

/*
ESTRATÉGIAS DE LIMPEZA:

1. EMAILS/CPFs DUPLICADOS:
   - Manter registro mais ANTIGO (menor ID)
   - Assumindo que primeiro cadastro é o correto
   - Alternativa: Manter mais RECENTE se preferir

2. MENSALIDADES DUPLICADAS:
   - Manter registro com status mais importante (pago > pendente)
   - Se empate, manter mais recente (maior ID)
   - Verificar se valores são iguais antes de deletar

3. MATRÍCULAS DUPLICADAS:
   - NÃO deletar, apenas INATIVAR
   - Manter matrícula mais recente ativa
   - Permite histórico de matrículas

4. TENANTS DUPLICADOS:
   - CUIDADO: Não delete tenants levianamente!
   - Considere mesclar dados entre tenants
   - Ou remover CNPJ/nome duplicado ao invés de deletar

ROLLBACK:
- Se cometer erro, restaure o backup:
  mysql -u root -p appcheckin < backup_antes_limpeza.sql
*/
