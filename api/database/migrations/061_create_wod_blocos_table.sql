-- Criação da tabela de blocos de WOD
CREATE TABLE IF NOT EXISTS wod_blocos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  wod_id INT NOT NULL,
  ordem INT NOT NULL DEFAULT 1,
  tipo ENUM('warmup','strength','metcon','accessory','cooldown','note') NOT NULL,
  titulo VARCHAR(120) NULL,
  conteudo TEXT NOT NULL,
  tempo_cap VARCHAR(20) NULL COMMENT 'Ex: 20min, 15:00 etc',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_wod_ordem (wod_id, ordem),
  CONSTRAINT fk_blocos_wod FOREIGN KEY (wod_id) REFERENCES wods(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
