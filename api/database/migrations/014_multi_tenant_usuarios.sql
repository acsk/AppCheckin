-- Migration: Suporte a usuários em múltiplos tenants
-- Data: 2025-12-26
-- Descrição: Permite que um usuário tenha contratos com diferentes academias/tenants

-- Criar tabela de relacionamento entre usuários e tenants
CREATE TABLE IF NOT EXISTS usuario_tenant (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tenant_id INT NOT NULL,
    plano_id INT NULL,
    status ENUM('ativo', 'inativo', 'suspenso', 'cancelado') DEFAULT 'ativo',
    data_inicio DATE NOT NULL DEFAULT (CURRENT_DATE),
    data_fim DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_usuario_tenant (usuario_id, tenant_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_tenant (tenant_id),
    INDEX idx_status (status),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (plano_id) REFERENCES planos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migrar dados existentes: criar relacionamento para usuários já existentes
INSERT INTO usuario_tenant (usuario_id, tenant_id, status, data_inicio)
SELECT id, tenant_id, 'ativo', CURRENT_DATE
FROM usuarios
WHERE NOT EXISTS (
    SELECT 1 FROM usuario_tenant 
    WHERE usuario_tenant.usuario_id = usuarios.id 
    AND usuario_tenant.tenant_id = usuarios.tenant_id
);

-- Adicionar campo email_global na tabela usuarios (email único global)
-- O email no tenant pode ser diferente do global
ALTER TABLE usuarios 
ADD COLUMN email_global VARCHAR(255) NULL AFTER email,
ADD INDEX idx_email_global (email_global);

-- Copiar emails existentes para email_global
UPDATE usuarios SET email_global = email WHERE email_global IS NULL;

-- Remover constraint UNIQUE do email (agora pode repetir entre tenants)
ALTER TABLE usuarios DROP INDEX email;

-- Criar índice composto para email por tenant
CREATE INDEX idx_tenant_email ON usuarios(tenant_id, email);

-- Comentários para documentação
ALTER TABLE usuario_tenant COMMENT = 'Relacionamento N:N entre usuários e tenants, permitindo múltiplos contratos';
