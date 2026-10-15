-- Versões publicadas dos apps (mobile / painel). Controle do atualizador obrigatório.

CREATE TABLE IF NOT EXISTS app_versions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    app VARCHAR(32) NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    `release` INT UNSIGNED NOT NULL DEFAULT 0,
    release_date DATE NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_app_versions_app (app)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO app_versions (app, version, `release`, release_date, created_at, updated_at)
SELECT 'mobile', 1, 0, CURDATE(), NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM app_versions WHERE app = 'mobile');
