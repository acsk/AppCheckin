-- Criação da tabela de WODs (Workout of the Day)
CREATE TABLE IF NOT EXISTS wods (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  data DATE NOT NULL,
  titulo VARCHAR(120) NOT NULL,
  descricao TEXT NULL,
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  criado_por INT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tenant_data (tenant_id, data),
  KEY idx_tenant_status_data (tenant_id, status, data),
  KEY idx_data (data),
  CONSTRAINT fk_wods_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_wods_criado_por FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
