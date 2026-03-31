-- Tabela de provas/eventos disponíveis (25m Crawl, 50m Crawl, etc.)
CREATE TABLE IF NOT EXISTS recorde_provas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL COMMENT 'Ex: 25m Crawl, 50m Costas',
    distancia_metros INT NULL COMMENT 'Distância em metros (25, 50, 100, 200)',
    estilo VARCHAR(50) NULL COMMENT 'Crawl, Costas, Peito, Borboleta, Medley',
    unidade_medida ENUM('tempo', 'metros', 'repeticoes', 'peso_kg') NOT NULL DEFAULT 'tempo' COMMENT 'Tipo de medida do recorde',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ordem INT NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_ativo (tenant_id, ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de recordes pessoais (registros de alunos e escola)
CREATE TABLE IF NOT EXISTS recordes_pessoais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    aluno_id INT NULL COMMENT 'NULL = recorde da escola/academia',
    prova_id INT NOT NULL,
    tempo_segundos DECIMAL(10,2) NULL COMMENT 'Tempo em segundos (ex: 32.45)',
    valor DECIMAL(10,2) NULL COMMENT 'Valor genérico (metros, reps, kg) para unidades não-tempo',
    data_registro DATE NOT NULL COMMENT 'Data em que o recorde foi alcançado',
    observacoes TEXT NULL,
    origem ENUM('aluno', 'escola') NOT NULL DEFAULT 'aluno' COMMENT 'Se é PR do aluno ou recorde da escola',
    registrado_por INT NULL COMMENT 'ID do usuário que registrou (professor/admin/aluno)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant_aluno (tenant_id, aluno_id),
    INDEX idx_tenant_prova (tenant_id, prova_id),
    INDEX idx_origem (tenant_id, origem),
    INDEX idx_ranking (tenant_id, prova_id, tempo_segundos),
    FOREIGN KEY (prova_id) REFERENCES recorde_provas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Provas padrão (natação) - serão criadas por tenant quando necessário
-- INSERT via migration PHP abaixo
