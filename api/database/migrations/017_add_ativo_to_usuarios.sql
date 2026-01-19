-- Migration: Adicionar campo ativo na tabela usuarios
-- Data: 2025-12-28
-- Descrição: Adiciona campo ativo para permitir soft delete de usuários

-- Adicionar coluna ativo na tabela usuarios
ALTER TABLE usuarios 
ADD COLUMN ativo BOOLEAN DEFAULT TRUE AFTER email_global;

-- Criar índice para o campo ativo
CREATE INDEX idx_usuarios_ativo ON usuarios(ativo);

-- Comentário: 
-- Este campo permite desativar completamente um usuário do sistema (soft delete)
-- Diferente do status em usuario_tenant que controla o vínculo com cada tenant
-- Um usuário pode estar ativo=false (desativado globalmente) ou ter status='inativo' em um tenant específico
