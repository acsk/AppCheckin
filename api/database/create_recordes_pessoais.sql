-- =============================================
-- Modelagem genérica de Recordes/PRs
-- Suporta qualquer modalidade: natação, cross,
-- musculação, corrida, testes físicos, etc.
-- =============================================

-- 1) Definição do teste/recorde (substitui recorde_provas)
CREATE TABLE IF NOT EXISTS recorde_definicoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    modalidade_id INT NULL COMMENT 'FK para modalidades (Natação, CrossFit, Musculação...)',
    nome VARCHAR(150) NOT NULL COMMENT 'Ex: Deadlift, BMU Max Reps, 100m Crawl, AMRAP 12 min',
    categoria ENUM('movimento', 'prova', 'workout', 'teste_fisico') NOT NULL DEFAULT 'movimento',
    descricao TEXT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ordem INT NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_ativo (tenant_id, ativo),
    INDEX idx_modalidade (tenant_id, modalidade_id),
    INDEX idx_categoria (tenant_id, categoria),
    FOREIGN KEY (modalidade_id) REFERENCES modalidades(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Métricas de cada definição (como o recorde é medido)
CREATE TABLE IF NOT EXISTS recorde_definicao_metricas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    definicao_id INT NOT NULL,
    codigo VARCHAR(50) NOT NULL COMMENT 'Ex: tempo_ms, peso_kg, repeticoes, distancia_m, rounds',
    nome VARCHAR(100) NOT NULL COMMENT 'Ex: Tempo, Carga, Repetições, Distância',
    tipo_valor ENUM('inteiro', 'decimal', 'tempo_ms') NOT NULL DEFAULT 'decimal',
    unidade VARCHAR(30) NULL COMMENT 'Ex: ms, kg, reps, m',
    ordem_comparacao INT NOT NULL DEFAULT 1 COMMENT '1 = principal, 2 = desempate...',
    direcao ENUM('maior_melhor', 'menor_melhor') NOT NULL,
    obrigatoria TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_definicao_codigo (definicao_id, codigo),
    INDEX idx_definicao (definicao_id),
    FOREIGN KEY (definicao_id) REFERENCES recorde_definicoes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Registro do recorde (a tentativa/PR em si)
CREATE TABLE IF NOT EXISTS recordes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    aluno_id INT NULL COMMENT 'NULL = recorde da academia/escola',
    definicao_id INT NOT NULL,
    origem ENUM('aluno', 'academia') NOT NULL DEFAULT 'aluno',
    data_recorde DATE NOT NULL,
    observacoes TEXT NULL,
    registrado_por INT NULL COMMENT 'ID do usuário que registrou',
    valido TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant_aluno (tenant_id, aluno_id),
    INDEX idx_tenant_definicao (tenant_id, definicao_id),
    INDEX idx_origem (tenant_id, origem),
    INDEX idx_data (tenant_id, data_recorde),
    FOREIGN KEY (definicao_id) REFERENCES recorde_definicoes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Valores medidos de cada recorde (1 ou mais métricas por recorde)
CREATE TABLE IF NOT EXISTS recorde_valores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recorde_id INT NOT NULL,
    metrica_id INT NOT NULL,
    valor_int BIGINT NULL,
    valor_decimal DECIMAL(12,3) NULL,
    valor_tempo_ms BIGINT NULL COMMENT 'Tempo em milissegundos',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_recorde (recorde_id),
    INDEX idx_metrica (metrica_id),
    UNIQUE KEY uk_recorde_metrica (recorde_id, metrica_id),
    FOREIGN KEY (recorde_id) REFERENCES recordes(id) ON DELETE CASCADE,
    FOREIGN KEY (metrica_id) REFERENCES recorde_definicao_metricas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Definições padrão serão inseridas via migration PHP por tenant
