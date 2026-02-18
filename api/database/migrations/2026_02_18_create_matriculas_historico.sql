-- Criar tabela de histórico de matrículas para rastrear atualizações
CREATE TABLE IF NOT EXISTS matriculas_historico (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    matricula_id INT NOT NULL,
    aluno_id BIGINT UNSIGNED NOT NULL,
    tipo_operacao ENUM('INSERT', 'UPDATE') NOT NULL COMMENT 'Tipo de operação realizada',
    dados_anteriores JSON NULL COMMENT 'Dados da matrícula antes da mudança (null para INSERT)',
    dados_novos JSON NOT NULL COMMENT 'Dados da matrícula após a mudança',
    motivo VARCHAR(255) NOT NULL COMMENT 'Motivo da mudança (ex: cobrança recorrente, renovação, etc)',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_matricula_id (matricula_id),
    INDEX idx_aluno_id (aluno_id),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_criado_em (criado_em),
    INDEX idx_tipo_operacao (tipo_operacao),
    
    -- Chave estrangeira
    CONSTRAINT fk_historico_matricula FOREIGN KEY (matricula_id) 
        REFERENCES matriculas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Histórico de mudanças em matrículas para auditoria e rastreamento de recorrências';
