-- Migration: Adicionar permite_reposicao em plano_ciclos
-- Data: 2026-02-11
-- mysql -u user -p database < 2026_02_11_001_add_permite_reposicao_plano_ciclos.sql

ALTER TABLE plano_ciclos
    ADD COLUMN permite_reposicao TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Se permite reposicao de aulas' AFTER permite_recorrencia;
