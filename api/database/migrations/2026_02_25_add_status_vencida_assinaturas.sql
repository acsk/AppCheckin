-- Adicionar status 'vencida' na tabela assinatura_status
-- Diferente de 'expirada': vencida = pagamento vencido, pode renovar
INSERT IGNORE INTO assinatura_status (codigo, nome, descricao, cor) VALUES
('vencida', 'Vencida', 'Assinatura com pagamento vencido', '#FF6B35');

-- Migrar assinaturas que estão como 'expirada' para 'vencida'
UPDATE assinaturas 
SET status_id = (SELECT id FROM assinatura_status WHERE codigo = 'vencida'),
    updated_at = NOW()
WHERE status_id = (SELECT id FROM assinatura_status WHERE codigo = 'expirada');
