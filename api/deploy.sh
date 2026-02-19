#!/bin/bash
# Deploy script - execute isto na VPS em produção

echo "📦 DEPLOY - Pacote de Pagamento Simplificado"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

cd /home/u304177849/public_html/api || exit 1

# 1. Fazer git pull para pegar código novo
echo "📥 Atualizando código..."
git pull origin main

# 2. Verificar status
echo ""
echo "📊 Verificando banco de dados..."
php check_webhook_status.php

echo ""
echo "✅ Deploy concluído!"
echo ""
echo "PRÓXIMOS PASSOS:"
echo "1. Fazer novo teste de pagamento de pacote"
echo "2. Executar novamente: php check_webhook_status.php"
echo ""
