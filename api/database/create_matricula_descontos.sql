-- ============================================================
-- Tabela: matricula_descontos
-- Descontos vinculados a uma matrícula, aplicados automaticamente
-- na geração de parcelas (pagamentos_plano).
-- ============================================================

CREATE TABLE IF NOT EXISTS matricula_descontos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    matricula_id INT NOT NULL,

    -- Quando aplicar
    tipo ENUM('primeira_mensalidade', 'recorrente') NOT NULL,

    -- Valor: usar UM dos dois (valor fixo OU percentual)
    valor DECIMAL(10,2) NULL COMMENT 'Desconto fixo em R$',
    percentual DECIMAL(5,2) NULL COMMENT 'Desconto percentual (ex: 10.00 = 10%)',

    -- Vigência
    vigencia_inicio DATE NOT NULL COMMENT 'Data a partir da qual o desconto vale',
    vigencia_fim DATE NULL COMMENT 'Data final da vigência. NULL = infinito (até desativação)',

    -- Limitar quantidade de parcelas (NULL = sem limite, respeita só vigência)
    parcelas_restantes INT NULL COMMENT 'Decrementado a cada aplicação. NULL = sem limite',

    motivo VARCHAR(255) NOT NULL COMMENT 'Ex: Promoção, Funcionário, Indicação',
    ativo TINYINT(1) NOT NULL DEFAULT 1,

    criado_por INT NULL,
    autorizado_por INT NULL COMMENT 'ID do admin que autorizou o desconto',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (matricula_id) REFERENCES matriculas(id) ON DELETE CASCADE,
    INDEX idx_tenant_matricula (tenant_id, matricula_id, ativo),
    INDEX idx_vigencia (tenant_id, ativo, vigencia_inicio, vigencia_fim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
