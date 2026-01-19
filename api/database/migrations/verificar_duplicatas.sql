-- ==========================================
-- Script de Verificação de Duplicatas
-- ==========================================
-- Execute ANTES da Migration 043 (constraints UNIQUE)
-- Identifica dados duplicados que causariam erro
-- ==========================================

SET @linha = 0;

SELECT '========================================' AS '';
SELECT 'VERIFICAÇÃO DE DUPLICATAS' AS '';
SELECT 'Execute ANTES da Migration 043' AS '';
SELECT '========================================' AS '';
SELECT '' AS '';

-- ==========================================
-- 1. VERIFICAR EMAIL_GLOBAL DUPLICADO
-- ==========================================

SELECT '1. EMAIL_GLOBAL DUPLICADO' AS 'VERIFICAÇÃO';
SELECT '' AS '';

SELECT 
    email_global,
    COUNT(*) as total_duplicatas,
    GROUP_CONCAT(id ORDER BY id) as ids_duplicados,
    GROUP_CONCAT(nome ORDER BY id SEPARATOR ' | ') as nomes
FROM usuarios
WHERE email_global IS NOT NULL
GROUP BY email_global
HAVING COUNT(*) > 1
ORDER BY total_duplicatas DESC;

SET @email_duplicados = (
    SELECT COUNT(DISTINCT email_global)
    FROM usuarios
    WHERE email_global IS NOT NULL
    GROUP BY email_global
    HAVING COUNT(*) > 1
);

SELECT CASE 
    WHEN @email_duplicados > 0 THEN CONCAT('❌ ATENÇÃO: ', @email_duplicados, ' emails duplicados encontrados!')
    ELSE '✅ OK: Nenhum email duplicado'
END AS 'RESULTADO';
SELECT '' AS '';

-- ==========================================
-- 2. VERIFICAR CPF DUPLICADO
-- ==========================================

SELECT '2. CPF DUPLICADO' AS 'VERIFICAÇÃO';
SELECT '' AS '';

SELECT 
    cpf,
    COUNT(*) as total_duplicatas,
    GROUP_CONCAT(id ORDER BY id) as ids_duplicados,
    GROUP_CONCAT(nome ORDER BY id SEPARATOR ' | ') as nomes
FROM usuarios
WHERE cpf IS NOT NULL AND cpf != ''
GROUP BY cpf
HAVING COUNT(*) > 1
ORDER BY total_duplicatas DESC;

SET @cpf_duplicados = (
    SELECT COUNT(DISTINCT cpf)
    FROM usuarios
    WHERE cpf IS NOT NULL AND cpf != ''
    GROUP BY cpf
    HAVING COUNT(*) > 1
);

SELECT CASE 
    WHEN @cpf_duplicados > 0 THEN CONCAT('❌ ATENÇÃO: ', @cpf_duplicados, ' CPFs duplicados encontrados!')
    ELSE '✅ OK: Nenhum CPF duplicado'
END AS 'RESULTADO';
SELECT '' AS '';

-- ==========================================
-- 3. VERIFICAR CNPJ DUPLICADO (TENANTS)
-- ==========================================

SELECT '3. CNPJ DUPLICADO (TENANTS)' AS 'VERIFICAÇÃO';
SELECT '' AS '';

SELECT 
    cnpj,
    COUNT(*) as total_duplicatas,
    GROUP_CONCAT(id ORDER BY id) as ids_duplicados,
    GROUP_CONCAT(nome ORDER BY id SEPARATOR ' | ') as nomes
FROM tenants
WHERE cnpj IS NOT NULL AND cnpj != ''
GROUP BY cnpj
HAVING COUNT(*) > 1
ORDER BY total_duplicatas DESC;

SET @cnpj_duplicados = (
    SELECT COUNT(DISTINCT cnpj)
    FROM tenants
    WHERE cnpj IS NOT NULL AND cnpj != ''
    GROUP BY cnpj
    HAVING COUNT(*) > 1
);

SELECT CASE 
    WHEN @cnpj_duplicados > 0 THEN CONCAT('❌ ATENÇÃO: ', @cnpj_duplicados, ' CNPJs duplicados encontrados!')
    ELSE '✅ OK: Nenhum CNPJ duplicado'
END AS 'RESULTADO';
SELECT '' AS '';

-- ==========================================
-- 4. VERIFICAR NOME TENANT DUPLICADO
-- ==========================================

SELECT '4. NOME TENANT DUPLICADO' AS 'VERIFICAÇÃO';
SELECT '' AS '';

SELECT 
    nome,
    COUNT(*) as total_duplicatas,
    GROUP_CONCAT(id ORDER BY id) as ids_duplicados
FROM tenants
GROUP BY nome
HAVING COUNT(*) > 1
ORDER BY total_duplicatas DESC;

SET @nome_tenant_duplicados = (
    SELECT COUNT(DISTINCT nome)
    FROM tenants
    GROUP BY nome
    HAVING COUNT(*) > 1
);

SELECT CASE 
    WHEN @nome_tenant_duplicados > 0 THEN CONCAT('❌ ATENÇÃO: ', @nome_tenant_duplicados, ' nomes de tenant duplicados!')
    ELSE '✅ OK: Nenhum nome de tenant duplicado'
END AS 'RESULTADO';
SELECT '' AS '';

-- ==========================================
-- 5. VERIFICAR MENSALIDADES DUPLICADAS
-- ==========================================

SELECT '5. MENSALIDADES DUPLICADAS (CONTAS_RECEBER)' AS 'VERIFICAÇÃO';
SELECT '' AS '';

SELECT 
    tenant_id,
    usuario_id,
    plano_id,
    referencia_mes,
    COUNT(*) as total_duplicatas,
    GROUP_CONCAT(id ORDER BY id) as ids_duplicados,
    GROUP_CONCAT(CONCAT('R$ ', valor) ORDER BY id SEPARATOR ' | ') as valores,
    GROUP_CONCAT(status ORDER BY id SEPARATOR ' | ') as status_list
FROM contas_receber
WHERE referencia_mes IS NOT NULL
GROUP BY tenant_id, usuario_id, plano_id, referencia_mes
HAVING COUNT(*) > 1
ORDER BY total_duplicatas DESC
LIMIT 20;

SET @mensalidades_duplicadas = (
    SELECT COUNT(*)
    FROM (
        SELECT tenant_id, usuario_id, plano_id, referencia_mes
        FROM contas_receber
        WHERE referencia_mes IS NOT NULL
        GROUP BY tenant_id, usuario_id, plano_id, referencia_mes
        HAVING COUNT(*) > 1
    ) as tmp
);

SELECT CASE 
    WHEN @mensalidades_duplicadas > 0 THEN CONCAT('❌ ATENÇÃO: ', @mensalidades_duplicadas, ' mensalidades duplicadas encontradas!')
    ELSE '✅ OK: Nenhuma mensalidade duplicada'
END AS 'RESULTADO';
SELECT '' AS '';

-- ==========================================
-- 6. VERIFICAR MATRÍCULAS ATIVAS DUPLICADAS
-- ==========================================

SELECT '6. MATRÍCULAS ATIVAS DUPLICADAS' AS 'VERIFICAÇÃO';
SELECT '' AS '';

SELECT 
    m.tenant_id,
    m.usuario_id,
    m.plano_id,
    COUNT(*) as total_ativas,
    GROUP_CONCAT(m.id ORDER BY m.id) as ids_matriculas,
    u.nome as usuario_nome,
    p.nome as plano_nome
FROM matriculas m
JOIN usuarios u ON m.usuario_id = u.id
JOIN planos p ON m.plano_id = p.id
WHERE m.status = 'ativa'
GROUP BY m.tenant_id, m.usuario_id, m.plano_id
HAVING COUNT(*) > 1
ORDER BY total_ativas DESC;

SET @matriculas_ativas_duplicadas = (
    SELECT COUNT(*)
    FROM (
        SELECT tenant_id, usuario_id, plano_id
        FROM matriculas
        WHERE status = 'ativa'
        GROUP BY tenant_id, usuario_id, plano_id
        HAVING COUNT(*) > 1
    ) as tmp
);

SELECT CASE 
    WHEN @matriculas_ativas_duplicadas > 0 THEN CONCAT('❌ ATENÇÃO: ', @matriculas_ativas_duplicadas, ' matrículas ativas duplicadas!')
    ELSE '✅ OK: Nenhuma matrícula ativa duplicada'
END AS 'RESULTADO';
SELECT '' AS '';

-- ==========================================
-- RESUMO FINAL
-- ==========================================

SELECT '========================================' AS '';
SELECT 'RESUMO FINAL' AS '';
SELECT '========================================' AS '';
SELECT '' AS '';

SELECT 
    CASE 
        WHEN COALESCE(@email_duplicados, 0) + 
             COALESCE(@cpf_duplicados, 0) + 
             COALESCE(@cnpj_duplicados, 0) + 
             COALESCE(@nome_tenant_duplicados, 0) + 
             COALESCE(@mensalidades_duplicadas, 0) + 
             COALESCE(@matriculas_ativas_duplicadas, 0) = 0 
        THEN '✅ TUDO OK - Pode executar Migration 043 com segurança'
        ELSE '❌ ATENÇÃO - Limpar duplicatas ANTES de executar Migration 043'
    END AS 'STATUS GERAL';

SELECT '' AS '';

SELECT 'PROBLEMAS ENCONTRADOS:' AS '';
SELECT CONCAT('- Emails duplicados: ', COALESCE(@email_duplicados, 0)) AS '';
SELECT CONCAT('- CPFs duplicados: ', COALESCE(@cpf_duplicados, 0)) AS '';
SELECT CONCAT('- CNPJs duplicados: ', COALESCE(@cnpj_duplicados, 0)) AS '';
SELECT CONCAT('- Nomes tenant duplicados: ', COALESCE(@nome_tenant_duplicados, 0)) AS '';
SELECT CONCAT('- Mensalidades duplicadas: ', COALESCE(@mensalidades_duplicadas, 0)) AS '';
SELECT CONCAT('- Matrículas ativas duplicadas: ', COALESCE(@matriculas_ativas_duplicadas, 0)) AS '';

SELECT '' AS '';
SELECT '========================================' AS '';
SELECT 'PRÓXIMOS PASSOS' AS '';
SELECT '========================================' AS '';
SELECT '' AS '';

SELECT 
    CASE 
        WHEN COALESCE(@email_duplicados, 0) + 
             COALESCE(@cpf_duplicados, 0) + 
             COALESCE(@cnpj_duplicados, 0) + 
             COALESCE(@nome_tenant_duplicados, 0) + 
             COALESCE(@mensalidades_duplicadas, 0) + 
             COALESCE(@matriculas_ativas_duplicadas, 0) = 0 
        THEN 'Execute: mysql < 043_adicionar_constraints_unicidade.sql'
        ELSE 'Execute: mysql < limpar_duplicatas.sql (criar script de limpeza)'
    END AS 'AÇÃO RECOMENDADA';
