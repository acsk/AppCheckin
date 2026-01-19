-- Criação da tabela de variações de WOD (RX, Scaled, etc)
CREATE TABLE IF NOT EXISTS wod_variacoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  wod_id INT NOT NULL,
  nome VARCHAR(40) NOT NULL COMMENT 'RX, Scaled, Beginner, etc',
  descricao TEXT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wod_nome (wod_id, nome),
  CONSTRAINT fk_var_wod FOREIGN KEY (wod_id) REFERENCES wods(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
