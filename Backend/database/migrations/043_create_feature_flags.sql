-- Migration 043: Feature Flags
-- Cria tabela de flags e habilita gpt_codex_max_enabled globalmente

CREATE TABLE IF NOT EXISTS feature_flags (
  id INT AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(100) NOT NULL,
  `value_bool` BOOLEAN NULL,
  `value_text` VARCHAR(255) NULL,
  `scope` ENUM('global','tenant') NOT NULL DEFAULT 'global',
  `tenant_id` INT NULL,
  `description` VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_feature_flag_key_scope_tenant (`key`, `scope`, `tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed: habilitar GPT Codex Max globalmente
INSERT INTO feature_flags (`key`, `value_bool`, `scope`, `tenant_id`, `description`)
VALUES ('gpt_codex_max_enabled', TRUE, 'global', NULL, 'Habilita GPT-5.1-Codex-Max para todos os clientes')
ON DUPLICATE KEY UPDATE `value_bool` = VALUES(`value_bool`), `description` = VALUES(`description`);
