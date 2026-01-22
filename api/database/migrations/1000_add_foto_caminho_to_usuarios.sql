-- Adicionar coluna foto_caminho na tabela usuarios para armazenar caminho das fotos de perfil
ALTER TABLE usuarios 
ADD COLUMN foto_caminho VARCHAR(255) NULL COMMENT 'Caminho relativo da foto de perfil (ex: /uploads/fotos/usuario_123_1234567890.jpg)';

-- Criar índice para melhor performance
CREATE INDEX idx_usuarios_foto_caminho ON usuarios(foto_caminho);
