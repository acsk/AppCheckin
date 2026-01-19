-- Criar tabela de inscrições de alunos em turmas
CREATE TABLE IF NOT EXISTS inscricoes_turmas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    turma_id INT NOT NULL,
    usuario_id INT NOT NULL,
    data_inscricao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_conclusao DATE NULL,
    presencas INT DEFAULT 0,
    faltas INT DEFAULT 0,
    status ENUM('ativa', 'finalizada', 'cancelada') DEFAULT 'ativa',
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_inscricoes_turma FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE,
    CONSTRAINT fk_inscricoes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY uk_inscricoes_turma_usuario (turma_id, usuario_id),
    INDEX idx_inscricoes_turma (turma_id),
    INDEX idx_inscricoes_usuario (usuario_id),
    INDEX idx_inscricoes_status (status)
);
