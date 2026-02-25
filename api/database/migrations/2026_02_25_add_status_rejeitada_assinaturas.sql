-- Adicionar status 'rejeitada' para assinaturas com pagamento recusado
INSERT IGNORE INTO assinatura_status (codigo, nome, descricao, cor) 
VALUES ('rejeitada', 'Rejeitada', 'Pagamento rejeitado pelo gateway', '#DC3545');
