-- Criação da tabela de resultados/scores de WOD
CREATE TABLE IF NOT EXISTS wod_resultados (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  wod_id INT NOT NULL,
  usuario_id INT NOT NULL,
  variacao_id INT NULL,
  tipo_score ENUM('time','reps','weight','rounds_reps','distance','calories','points') NOT NULL,
  valor_num DECIMAL(10,2) NULL COMMENT 'Para peso, reps, distância, calorias, pontos',
  valor_texto VARCHAR(40) NULL COMMENT 'Para formatos tipo 10+15 (rounds+reps) ou 12:34',
  observacao VARCHAR(255) NULL,
  registrado_por INT NULL,
  registrado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_resultado (tenant_id, wod_id, usuario_id),
  KEY idx_wod (tenant_id, wod_id),
  KEY idx_usuario (usuario_id),
  KEY idx_variacao (variacao_id),
  CONSTRAINT fk_res_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_res_wod FOREIGN KEY (wod_id) REFERENCES wods(id) ON DELETE CASCADE,
  CONSTRAINT fk_res_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_res_variacao FOREIGN KEY (variacao_id) REFERENCES wod_variacoes(id) ON DELETE SET NULL,
  CONSTRAINT fk_res_registrado_por FOREIGN KEY (registrado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
