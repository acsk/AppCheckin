-- Tabela de Planejamento Semanal/Mensal
-- Permite ao admin definir horários recorrentes
CREATE TABLE IF NOT EXISTS planejamento_horarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    titulo VARCHAR(255) NOT NULL COMMENT 'Ex: Horários Janeiro 2024, Horários Crossfit',
    dia_semana ENUM('segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo') NOT NULL,
    horario_inicio TIME NOT NULL,
    horario_fim TIME NOT NULL,
    vagas INT NOT NULL DEFAULT 10,
    data_inicio DATE NOT NULL COMMENT 'A partir de quando este planejamento é válido',
    data_fim DATE NULL COMMENT 'Até quando é válido (NULL = indeterminado)',
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant_ativo (tenant_id, ativo),
    INDEX idx_dia_semana (dia_semana),
    INDEX idx_periodo (data_inicio, data_fim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Planejamento de horários recorrentes semanais';

-- Adicionar coluna para identificar se check-in foi feito pelo próprio aluno ou pelo admin
ALTER TABLE checkins 
ADD COLUMN registrado_por_admin BOOLEAN DEFAULT FALSE COMMENT 'TRUE se admin fez check-in manual do aluno',
ADD COLUMN admin_id INT NULL COMMENT 'ID do admin que registrou (se aplicável)',
ADD CONSTRAINT fk_checkin_admin FOREIGN KEY (admin_id) REFERENCES usuarios(id) ON DELETE SET NULL;

-- Adicionar índice
CREATE INDEX idx_checkins_admin ON checkins(registrado_por_admin, admin_id);
