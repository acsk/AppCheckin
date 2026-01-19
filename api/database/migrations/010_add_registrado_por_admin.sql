-- Adiciona colunas de check-in manual se ainda não existirem
ALTER TABLE checkins
  ADD COLUMN IF NOT EXISTS registrado_por_admin BOOLEAN DEFAULT FALSE COMMENT 'TRUE se admin fez check-in manual do aluno',
  ADD COLUMN IF NOT EXISTS admin_id INT NULL COMMENT 'ID do admin que registrou (se aplicável)';

ALTER TABLE checkins
  ADD CONSTRAINT fk_checkin_admin FOREIGN KEY (admin_id) REFERENCES usuarios(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_checkins_admin ON checkins(registrado_por_admin, admin_id);
