-- Script de Teste: Validar Bloqueio de Alteração de Plano
-- Execute este script para testar a validação

-- 1. VERIFICAR ALUNOS QUE ESTÃO BLOQUEADOS PARA ALTERAÇÃO
SELECT 
    u.id,
    u.nome,
    u.email,
    p.nome as plano_atual,
    m.data_vencimento,
    DATEDIFF(m.data_vencimento, CURDATE()) as dias_restantes,
    CASE 
        WHEN m.data_vencimento >= CURDATE() 
         AND EXISTS (
             SELECT 1 FROM contas_receber cr 
             WHERE cr.usuario_id = u.id 
               AND cr.status = 'pago'
               AND cr.data_vencimento <= CURDATE()
               AND DATE_ADD(cr.data_vencimento, INTERVAL cr.intervalo_dias DAY) >= CURDATE()
         )
        THEN '🔒 BLOQUEADO - Não pode alterar plano'
        ELSE '✅ LIBERADO - Pode alterar plano'
    END as status_bloqueio
FROM usuarios u
INNER JOIN planos p ON u.plano_id = p.id
INNER JOIN matriculas m ON m.usuario_id = u.id AND m.status = 'ativa'
WHERE u.tenant_id = 1 
  AND u.role_id = 1
  AND m.data_vencimento >= CURDATE()
ORDER BY status_bloqueio DESC, dias_restantes DESC;

-- 2. CRIAR UM CENÁRIO DE TESTE (AMANDA FREITAS)
-- Inserir matrícula ativa se não existir
INSERT INTO matriculas (tenant_id, usuario_id, plano_id, data_matricula, data_inicio, data_vencimento, valor, status, motivo)
SELECT 1, 20, 2, CURDATE(), CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 149.90, 'ativa', 'nova'
WHERE NOT EXISTS (
    SELECT 1 FROM matriculas WHERE usuario_id = 20 AND status = 'ativa'
);

-- Inserir conta paga
INSERT INTO contas_receber (tenant_id, usuario_id, plano_id, valor, data_vencimento, data_pagamento, status, referencia_mes, intervalo_dias)
SELECT 1, 20, 2, 149.90, DATE_ADD(CURDATE(), INTERVAL 30 DAY), CURDATE(), 'pago', DATE_FORMAT(CURDATE(), '%Y-%m'), 30
WHERE NOT EXISTS (
    SELECT 1 FROM contas_receber 
    WHERE usuario_id = 20 
      AND status = 'pago' 
      AND data_vencimento >= CURDATE()
);

-- Atualizar dados do usuário
UPDATE usuarios 
SET plano_id = 2, data_vencimento_plano = DATE_ADD(CURDATE(), INTERVAL 30 DAY)
WHERE id = 20;

-- 3. VERIFICAR SE AMANDA ESTÁ BLOQUEADA
SELECT 
    'TESTE: Amanda Freitas' as teste,
    CASE 
        WHEN m.data_vencimento >= CURDATE() 
         AND EXISTS (
             SELECT 1 FROM contas_receber cr 
             WHERE cr.usuario_id = 20 
               AND cr.status = 'pago'
               AND cr.data_vencimento <= CURDATE()
               AND DATE_ADD(cr.data_vencimento, INTERVAL cr.intervalo_dias DAY) >= CURDATE()
         )
        THEN '🔒 BLOQUEADO - API retornará erro 400'
        ELSE '✅ LIBERADO - API permitirá alteração'
    END as resultado_esperado,
    m.data_vencimento as vencimento,
    p.nome as plano_atual
FROM usuarios u
INNER JOIN planos p ON u.plano_id = p.id
LEFT JOIN matriculas m ON m.usuario_id = u.id AND m.status = 'ativa'
WHERE u.id = 20;

-- 4. LISTAR TODAS AS CONTAS PAGAS DE AMANDA
SELECT 
    id,
    valor,
    data_vencimento,
    data_pagamento,
    status,
    intervalo_dias,
    DATE_ADD(data_vencimento, INTERVAL intervalo_dias DAY) as fim_periodo,
    CASE 
        WHEN data_vencimento <= CURDATE() 
         AND DATE_ADD(data_vencimento, INTERVAL intervalo_dias DAY) >= CURDATE()
        THEN '✅ PERÍODO ATIVO'
        ELSE '❌ FORA DO PERÍODO'
    END as validacao_periodo
FROM contas_receber
WHERE usuario_id = 20
  AND status = 'pago'
ORDER BY data_vencimento DESC;

-- 5. SIMULAR TENTATIVA DE ALTERAÇÃO (via SQL)
-- Este SELECT simula o que a API faria
SELECT 
    CASE 
        WHEN EXISTS (
            SELECT 1 FROM matriculas m
            WHERE m.usuario_id = 20 
              AND m.status = 'ativa'
              AND m.plano_id != 4  -- Tentando mudar para plano 4 (Anual)
              AND m.data_vencimento >= CURDATE()
              AND EXISTS (
                  SELECT 1 FROM contas_receber cr
                  WHERE cr.usuario_id = 20
                    AND cr.status = 'pago'
                    AND cr.data_vencimento <= CURDATE()
                    AND DATE_ADD(cr.data_vencimento, INTERVAL cr.intervalo_dias DAY) >= CURDATE()
              )
        )
        THEN 'ERRO 400: Não é possível alterar o plano enquanto o aluno estiver ativo'
        ELSE 'SUCESSO 200: Alteração permitida'
    END as resultado_api_simulado;

-- 6. VERIFICAR DETALHES COMPLETOS
SELECT 
    'Amanda Freitas' as aluno,
    u.plano_id as plano_id_atual,
    p.nome as plano_nome_atual,
    m.status as status_matricula,
    m.data_vencimento as vencimento_matricula,
    CASE WHEN m.data_vencimento >= CURDATE() THEN 'SIM' ELSE 'NÃO' END as dentro_periodo,
    (SELECT COUNT(*) FROM contas_receber cr 
     WHERE cr.usuario_id = 20 
       AND cr.status = 'pago'
       AND cr.data_vencimento <= CURDATE()
       AND DATE_ADD(cr.data_vencimento, INTERVAL cr.intervalo_dias DAY) >= CURDATE()
    ) as qtd_pagamentos_ativos,
    CASE 
        WHEN m.data_vencimento >= CURDATE() 
         AND EXISTS (
             SELECT 1 FROM contas_receber cr 
             WHERE cr.usuario_id = 20 
               AND cr.status = 'pago'
               AND cr.data_vencimento <= CURDATE()
               AND DATE_ADD(cr.data_vencimento, INTERVAL cr.intervalo_dias DAY) >= CURDATE()
         )
        THEN '🔒 BLOQUEADO'
        ELSE '✅ LIBERADO'
    END as pode_alterar_plano
FROM usuarios u
INNER JOIN planos p ON u.plano_id = p.id
LEFT JOIN matriculas m ON m.usuario_id = u.id AND m.status = 'ativa'
WHERE u.id = 20;

-- 7. LIMPAR TESTE (executar somente se quiser resetar)
-- DELETE FROM matriculas WHERE usuario_id = 20;
-- DELETE FROM contas_receber WHERE usuario_id = 20;
-- UPDATE usuarios SET plano_id = NULL, data_vencimento_plano = NULL WHERE id = 20;
