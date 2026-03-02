-- =====================================================
-- SISTEMA DE PARÂMETROS GENÉRICOS DO APP
-- Criado em: 2026-03-02
-- Permite configurar parâmetros por categoria (pagamentos, checkin, aulas, etc.)
-- =====================================================

-- 1. Tabela de tipos/categorias de parâmetros
CREATE TABLE IF NOT EXISTS tipos_parametro (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL UNIQUE COMMENT 'Código único: pagamentos, checkin, aulas, notificacoes, etc.',
    nome VARCHAR(100) NOT NULL COMMENT 'Nome exibido: Pagamentos, Check-in, Aulas',
    descricao TEXT NULL COMMENT 'Descrição da categoria',
    icone VARCHAR(50) NULL COMMENT 'Ícone para exibição (ex: fa-credit-card, mdi-cash)',
    ordem INT DEFAULT 0 COMMENT 'Ordem de exibição',
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_codigo (codigo),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabela de parâmetros disponíveis no sistema (definição)
CREATE TABLE IF NOT EXISTS parametros (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo_parametro_id INT UNSIGNED NOT NULL,
    codigo VARCHAR(100) NOT NULL COMMENT 'Código único do parâmetro: habilitar_pix, habilitar_cartao, etc.',
    nome VARCHAR(150) NOT NULL COMMENT 'Nome exibido',
    descricao TEXT NULL COMMENT 'Descrição/ajuda do parâmetro',
    tipo_valor ENUM('boolean', 'string', 'integer', 'decimal', 'json', 'select') NOT NULL DEFAULT 'boolean' COMMENT 'Tipo do valor',
    valor_padrao VARCHAR(500) NULL COMMENT 'Valor padrão se não configurado',
    opcoes_select JSON NULL COMMENT 'Opções para tipo select: [{"valor":"pix","label":"PIX"},...]',
    validacao JSON NULL COMMENT 'Regras de validação: {"min":0,"max":100,"required":true}',
    ordem INT DEFAULT 0 COMMENT 'Ordem de exibição dentro da categoria',
    visivel_admin TINYINT(1) DEFAULT 1 COMMENT 'Visível apenas para admin do sistema',
    visivel_tenant TINYINT(1) DEFAULT 1 COMMENT 'Pode ser configurado pelo tenant',
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tipo_codigo (tipo_parametro_id, codigo),
    INDEX idx_tipo (tipo_parametro_id),
    INDEX idx_codigo (codigo),
    INDEX idx_ativo (ativo),
    CONSTRAINT fk_parametros_tipo FOREIGN KEY (tipo_parametro_id) 
        REFERENCES tipos_parametro(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabela de valores dos parâmetros por tenant
CREATE TABLE IF NOT EXISTS parametros_tenant (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    parametro_id INT UNSIGNED NOT NULL,
    valor VARCHAR(500) NULL COMMENT 'Valor configurado pelo tenant',
    ativo TINYINT(1) DEFAULT 1,
    atualizado_por INT NULL COMMENT 'ID do usuário que atualizou',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tenant_parametro (tenant_id, parametro_id),
    INDEX idx_tenant (tenant_id),
    INDEX idx_parametro (parametro_id),
    CONSTRAINT fk_parametros_tenant_parametro FOREIGN KEY (parametro_id) 
        REFERENCES parametros(id) ON DELETE CASCADE,
    CONSTRAINT fk_parametros_tenant_tenant FOREIGN KEY (tenant_id) 
        REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- DADOS INICIAIS - CATEGORIAS
-- =====================================================

INSERT INTO tipos_parametro (codigo, nome, descricao, icone, ordem) VALUES
('pagamentos', 'Pagamentos', 'Configurações de pagamento e cobrança', 'fa-credit-card', 1),
('checkin', 'Check-in', 'Configurações de check-in de alunos', 'fa-qrcode', 2),
('aulas', 'Aulas', 'Configurações de aulas e turmas', 'fa-chalkboard-teacher', 3),
('notificacoes', 'Notificações', 'Configurações de notificações e alertas', 'fa-bell', 4),
('matriculas', 'Matrículas', 'Configurações de matrículas e planos', 'fa-user-plus', 5),
('sistema', 'Sistema', 'Configurações gerais do sistema', 'fa-cog', 99)
ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao);

-- =====================================================
-- DADOS INICIAIS - PARÂMETROS DE PAGAMENTOS
-- =====================================================

INSERT INTO parametros (tipo_parametro_id, codigo, nome, descricao, tipo_valor, valor_padrao, opcoes_select, ordem, visivel_tenant) VALUES
-- Formas de pagamento habilitadas
((SELECT id FROM tipos_parametro WHERE codigo = 'pagamentos'), 
 'habilitar_pix', 'Habilitar PIX', 'Permite pagamentos via PIX', 'boolean', 'true', NULL, 1, 1),

((SELECT id FROM tipos_parametro WHERE codigo = 'pagamentos'), 
 'habilitar_cartao_credito', 'Habilitar Cartão de Crédito', 'Permite pagamentos com cartão de crédito', 'boolean', 'false', NULL, 2, 1),

((SELECT id FROM tipos_parametro WHERE codigo = 'pagamentos'), 
 'habilitar_cartao_debito', 'Habilitar Cartão de Débito', 'Permite pagamentos com cartão de débito', 'boolean', 'false', NULL, 3, 1),

((SELECT id FROM tipos_parametro WHERE codigo = 'pagamentos'), 
 'habilitar_boleto', 'Habilitar Boleto', 'Permite pagamentos via boleto bancário', 'boolean', 'false', NULL, 4, 1),

-- Recorrência
((SELECT id FROM tipos_parametro WHERE codigo = 'pagamentos'), 
 'habilitar_cobranca_recorrente', 'Habilitar Cobrança Recorrente', 'Permite assinaturas com cobrança automática (preapproval)', 'boolean', 'false', NULL, 10, 1),

((SELECT id FROM tipos_parametro WHERE codigo = 'pagamentos'), 
 'modo_cobranca', 'Modo de Cobrança', 'Define como as cobranças serão feitas', 'select', 'avulso', 
 '[{"valor":"avulso","label":"Pagamento Avulso (gera cobrança a cada ciclo)"},{"valor":"recorrente","label":"Cobrança Recorrente Automática"}]', 11, 1),

-- Ciclos e próxima cobrança
((SELECT id FROM tipos_parametro WHERE codigo = 'pagamentos'), 
 'gerar_proxima_cobranca', 'Gerar Próxima Cobrança Automaticamente', 'Após pagamento aprovado, gera automaticamente a próxima cobrança baseado no ciclo', 'boolean', 'true', NULL, 15, 1),

((SELECT id FROM tipos_parametro WHERE codigo = 'pagamentos'), 
 'dias_antecedencia_cobranca', 'Dias de Antecedência para Cobrança', 'Quantos dias antes do vencimento enviar lembrete/cobrança', 'integer', '5', NULL, 16, 1),

-- Gateway
((SELECT id FROM tipos_parametro WHERE codigo = 'pagamentos'), 
 'gateway_pagamento', 'Gateway de Pagamento', 'Gateway utilizado para processar pagamentos', 'select', 'mercadopago', 
 '[{"valor":"mercadopago","label":"Mercado Pago"},{"valor":"pagarme","label":"Pagar.me"},{"valor":"stripe","label":"Stripe"},{"valor":"manual","label":"Manual (sem gateway)"}]', 20, 1),

-- Configurações adicionais
((SELECT id FROM tipos_parametro WHERE codigo = 'pagamentos'), 
 'permitir_pagamento_parcial', 'Permitir Pagamento Parcial', 'Permite que o aluno pague valores menores que o total', 'boolean', 'false', NULL, 30, 1),

((SELECT id FROM tipos_parametro WHERE codigo = 'pagamentos'), 
 'dias_tolerancia_vencimento', 'Dias de Tolerância após Vencimento', 'Dias antes de bloquear acesso após vencimento', 'integer', '5', NULL, 31, 1)

ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao);

-- =====================================================
-- DADOS INICIAIS - PARÂMETROS DE CHECK-IN
-- =====================================================

INSERT INTO parametros (tipo_parametro_id, codigo, nome, descricao, tipo_valor, valor_padrao, opcoes_select, ordem, visivel_tenant) VALUES
((SELECT id FROM tipos_parametro WHERE codigo = 'checkin'), 
 'habilitar_checkin_qrcode', 'Habilitar Check-in por QR Code', 'Permite check-in via leitura de QR Code', 'boolean', 'true', NULL, 1, 1),

((SELECT id FROM tipos_parametro WHERE codigo = 'checkin'), 
 'habilitar_checkin_facial', 'Habilitar Check-in Facial', 'Permite check-in via reconhecimento facial', 'boolean', 'false', NULL, 2, 1),

((SELECT id FROM tipos_parametro WHERE codigo = 'checkin'), 
 'habilitar_checkin_manual', 'Habilitar Check-in Manual', 'Permite check-in manual pelo professor', 'boolean', 'true', NULL, 3, 1),

((SELECT id FROM tipos_parametro WHERE codigo = 'checkin'), 
 'validar_horario_aula', 'Validar Horário da Aula', 'Só permite check-in no horário da aula com tolerância', 'boolean', 'true', NULL, 10, 1),

((SELECT id FROM tipos_parametro WHERE codigo = 'checkin'), 
 'tolerancia_minutos_antes', 'Tolerância Antes da Aula (minutos)', 'Minutos antes do horário que permite check-in', 'integer', '15', NULL, 11, 1),

((SELECT id FROM tipos_parametro WHERE codigo = 'checkin'), 
 'tolerancia_minutos_depois', 'Tolerância Após Início (minutos)', 'Minutos após início que ainda permite check-in', 'integer', '30', NULL, 12, 1),

((SELECT id FROM tipos_parametro WHERE codigo = 'checkin'), 
 'bloquear_inadimplente', 'Bloquear Check-in de Inadimplente', 'Impede check-in de alunos com pagamento atrasado', 'boolean', 'true', NULL, 20, 1)

ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao);

-- =====================================================
-- DADOS INICIAIS - PARÂMETROS DE AULAS
-- =====================================================

INSERT INTO parametros (tipo_parametro_id, codigo, nome, descricao, tipo_valor, valor_padrao, opcoes_select, ordem, visivel_tenant) VALUES
((SELECT id FROM tipos_parametro WHERE codigo = 'aulas'), 
 'permitir_reposicao', 'Permitir Reposição de Aulas', 'Permite que alunos façam reposição de aulas perdidas', 'boolean', 'true', NULL, 1, 1),

((SELECT id FROM tipos_parametro WHERE codigo = 'aulas'), 
 'max_reposicoes_mes', 'Máximo de Reposições por Mês', 'Limite de reposições que um aluno pode fazer por mês', 'integer', '4', NULL, 2, 1),

((SELECT id FROM tipos_parametro WHERE codigo = 'aulas'), 
 'dias_agendar_reposicao', 'Dias para Agendar Reposição', 'Prazo em dias para agendar uma reposição após falta', 'integer', '30', NULL, 3, 1),

((SELECT id FROM tipos_parametro WHERE codigo = 'aulas'), 
 'permitir_aula_experimental', 'Permitir Aula Experimental', 'Permite agendar aulas experimentais para visitantes', 'boolean', 'true', NULL, 10, 1),

((SELECT id FROM tipos_parametro WHERE codigo = 'aulas'), 
 'max_alunos_turma', 'Máximo de Alunos por Turma', 'Limite padrão de alunos por turma', 'integer', '20', NULL, 20, 1)

ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao);

-- =====================================================
-- DADOS INICIAIS - PARÂMETROS DE NOTIFICAÇÕES
-- =====================================================

INSERT INTO parametros (tipo_parametro_id, codigo, nome, descricao, tipo_valor, valor_padrao, opcoes_select, ordem, visivel_tenant) VALUES
((SELECT id FROM tipos_parametro WHERE codigo = 'notificacoes'), 
 'enviar_email_pagamento', 'Enviar E-mail de Pagamento', 'Envia e-mail quando pagamento é aprovado', 'boolean', 'true', NULL, 1, 1),

((SELECT id FROM tipos_parametro WHERE codigo = 'notificacoes'), 
 'enviar_email_vencimento', 'Enviar Lembrete de Vencimento', 'Envia e-mail dias antes do vencimento', 'boolean', 'true', NULL, 2, 1),

((SELECT id FROM tipos_parametro WHERE codigo = 'notificacoes'), 
 'enviar_whatsapp', 'Habilitar Notificações WhatsApp', 'Envia notificações via WhatsApp', 'boolean', 'false', NULL, 10, 1),

((SELECT id FROM tipos_parametro WHERE codigo = 'notificacoes'), 
 'enviar_push', 'Habilitar Push Notifications', 'Envia notificações push no app', 'boolean', 'true', NULL, 11, 1)

ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao);

-- =====================================================
-- DADOS INICIAIS - PARÂMETROS DE MATRÍCULAS
-- =====================================================

INSERT INTO parametros (tipo_parametro_id, codigo, nome, descricao, tipo_valor, valor_padrao, opcoes_select, ordem, visivel_tenant) VALUES
((SELECT id FROM tipos_parametro WHERE codigo = 'matriculas'), 
 'permitir_matricula_online', 'Permitir Matrícula Online', 'Permite que alunos se matriculem pelo app/site', 'boolean', 'true', NULL, 1, 1),

((SELECT id FROM tipos_parametro WHERE codigo = 'matriculas'), 
 'exigir_documentos', 'Exigir Upload de Documentos', 'Exige upload de documentos na matrícula', 'boolean', 'false', NULL, 2, 1),

((SELECT id FROM tipos_parametro WHERE codigo = 'matriculas'), 
 'aprovar_matricula_automatica', 'Aprovar Matrícula Automaticamente', 'Aprova matrícula após pagamento sem intervenção manual', 'boolean', 'true', NULL, 3, 1)

ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao);

-- =====================================================
-- VIEW PARA CONSULTA FACILITADA
-- =====================================================

CREATE OR REPLACE VIEW vw_parametros_completos AS
SELECT 
    tp.codigo as categoria_codigo,
    tp.nome as categoria_nome,
    p.id as parametro_id,
    p.codigo as parametro_codigo,
    p.nome as parametro_nome,
    p.descricao as parametro_descricao,
    p.tipo_valor,
    p.valor_padrao,
    p.opcoes_select,
    p.ordem,
    p.visivel_tenant
FROM parametros p
INNER JOIN tipos_parametro tp ON tp.id = p.tipo_parametro_id
WHERE p.ativo = 1 AND tp.ativo = 1
ORDER BY tp.ordem, p.ordem;

-- =====================================================
-- FUNÇÃO PARA OBTER VALOR DE PARÂMETRO
-- =====================================================

DROP FUNCTION IF EXISTS fn_get_parametro;

DELIMITER //
CREATE FUNCTION fn_get_parametro(p_tenant_id INT, p_codigo VARCHAR(100)) 
RETURNS VARCHAR(500)
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE v_valor VARCHAR(500);
    DECLARE v_valor_padrao VARCHAR(500);
    
    -- Buscar valor configurado pelo tenant
    SELECT pt.valor INTO v_valor
    FROM parametros_tenant pt
    INNER JOIN parametros p ON p.id = pt.parametro_id
    WHERE pt.tenant_id = p_tenant_id AND p.codigo = p_codigo AND pt.ativo = 1
    LIMIT 1;
    
    -- Se não encontrou, buscar valor padrão
    IF v_valor IS NULL THEN
        SELECT p.valor_padrao INTO v_valor
        FROM parametros p
        WHERE p.codigo = p_codigo AND p.ativo = 1
        LIMIT 1;
    END IF;
    
    RETURN v_valor;
END //
DELIMITER ;

-- Exemplo de uso:
-- SELECT fn_get_parametro(3, 'habilitar_pix'); -- Retorna 'true' ou 'false'
-- SELECT fn_get_parametro(3, 'modo_cobranca'); -- Retorna 'avulso' ou 'recorrente'
