-- Métodos aceitos por provedor (PIX, boleto, cartão) na tela de credenciais.
-- Produção: aplicar manualmente no MySQL compartilhado (não usar php artisan migrate).

ALTER TABLE tenant_payment_credentials
    ADD COLUMN enable_pix TINYINT(1) NOT NULL DEFAULT 1 AFTER is_active,
    ADD COLUMN enable_boleto TINYINT(1) NOT NULL DEFAULT 1 AFTER enable_pix,
    ADD COLUMN enable_credit_card TINYINT(1) NOT NULL DEFAULT 1 AFTER enable_boleto;

UPDATE parametros
SET valor_padrao = 'true'
WHERE codigo IN ('habilitar_pix', 'habilitar_boleto', 'habilitar_cartao_credito')
  AND ativo = 1;
