-- Links de grupos WhatsApp da academia (JSON array: [{ "nome": "...", "url": "https://chat.whatsapp.com/..." }])
ALTER TABLE tenants
    ADD COLUMN whatsapp_links TEXT NULL COMMENT 'JSON com links de grupos WhatsApp' AFTER telefone;
