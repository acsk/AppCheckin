-- Criar tabela de tenants (academias/empresas)
CREATE TABLE IF NOT EXISTS tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) NOT NULL,
    telefone VARCHAR(50),
    endereco TEXT,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Adicionar tenant_id em todas as tabelas existentes
ALTER TABLE usuarios 
ADD COLUMN tenant_id INT NOT NULL DEFAULT 1 AFTER id,
ADD INDEX idx_tenant_usuarios (tenant_id);

ALTER TABLE dias 
ADD COLUMN tenant_id INT NOT NULL DEFAULT 1 AFTER id,
ADD INDEX idx_tenant_dias (tenant_id);

ALTER TABLE horarios 
ADD INDEX idx_tenant_horarios (dia_id);

-- Criar tenant padrão
INSERT INTO tenants (nome, slug, email, ativo) 
VALUES ('Academia Principal', 'principal', 'contato@academia.com', 1);

-- Atualizar dados existentes para usar o tenant padrão
UPDATE usuarios SET tenant_id = 1;
UPDATE dias SET tenant_id = 1;

-- Adicionar foreign keys
ALTER TABLE usuarios 
ADD CONSTRAINT fk_usuarios_tenant 
FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE;

ALTER TABLE dias 
ADD CONSTRAINT fk_dias_tenant 
FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE;

-- Criar índices compostos para melhor performance
CREATE INDEX idx_tenant_email ON usuarios(tenant_id, email);
CREATE INDEX idx_tenant_data ON dias(tenant_id, data);
