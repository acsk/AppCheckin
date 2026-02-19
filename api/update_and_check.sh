#!/bin/bash
# Update e teste de webhook de pacote em produção

echo "📦 SINCRONIZANDO CÓDIGO DE PRODUÇÃO"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

cd /home/u304177849/public_html/api || { echo "❌ Diretório não encontrado"; exit 1; }

echo "📥 1. Fazendo git reset --hard"
git reset --hard

echo ""
echo "📥 2. Fazendo git fetch"
git fetch origin

echo ""
echo "📥 3. Fazendo git pull origin main"
git pull origin main

echo ""
echo "✅ Código atualizado!"

echo ""
echo "📊 Verificando status de webhooks..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

php check_pacote_status.php

echo ""
echo "✅ PRONTO!"
echo ""
echo "PRÓXIMAS ETAPAS:"
echo "1. Ir para o app mobile"
echo "2. Criar novo pacote ou usar PAC-4"
echo "3. Clique em 'Pagar Pacote'"
echo "4. Complete o pagamento em Mercado Pago"
echo "5. Execute novamente: php check_pacote_status.php"
echo ""
