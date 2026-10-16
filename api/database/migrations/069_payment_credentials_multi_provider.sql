-- Permite Mercado Pago e Asaas ativos ao mesmo tempo (um registro por tenant+provider).
-- Produção: aplicar manualmente no MySQL compartilhado (não usar php artisan migrate).

ALTER TABLE tenant_payment_credentials DROP INDEX tenant_id;

ALTER TABLE tenant_payment_credentials
    ADD UNIQUE KEY uk_tenant_payment_provider (tenant_id, provider);
