-- Adicionar status "Bloqueado" na tabela status_contrato
INSERT INTO status_contrato (id, nome, descricao) VALUES
(4, 'Bloqueado', 'Contrato bloqueado por falta de pagamento')
ON DUPLICATE KEY UPDATE 
    nome = VALUES(nome),
    descricao = VALUES(descricao);
