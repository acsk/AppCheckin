-- Tabela de Turmas (Aulas)
CREATE TABLE IF NOT EXISTS turmas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    professor_id INT NOT NULL,
    modalidade_id INT NOT NULL,
    dia_id INT NOT NULL,
    horario_id INT NOT NULL,
    nome VARCHAR(255) NOT NULL,
    limite_alunos INT NOT NULL DEFAULT 20,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (professor_id) REFERENCES professores(id) ON DELETE CASCADE,
    FOREIGN KEY (modalidade_id) REFERENCES modalidades(id) ON DELETE CASCADE,
    FOREIGN KEY (dia_id) REFERENCES dias(id) ON DELETE CASCADE,
    FOREIGN KEY (horario_id) REFERENCES horarios(id) ON DELETE CASCADE,
    INDEX idx_turmas_tenant (tenant_id),
    INDEX idx_turmas_professor (professor_id),
    INDEX idx_turmas_modalidade (modalidade_id),
    INDEX idx_turmas_dia (dia_id),
    INDEX idx_turmas_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
