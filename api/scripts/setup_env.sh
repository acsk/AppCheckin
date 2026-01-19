#!/bin/bash

# ================================================
# Setup de Variáveis de Ambiente - Produção
# ================================================

echo "🔧 Configurando variáveis de ambiente para produção..."
echo ""

# Gerar JWT_SECRET seguro
JWT_SECRET=$(openssl rand -base64 64)
echo "✅ JWT_SECRET gerado:"
echo "   $JWT_SECRET"
echo ""

# Gerar token de API (opcional)
API_TOKEN=$(openssl rand -hex 32)
echo "✅ API_TOKEN gerado:"
echo "   $API_TOKEN"
echo ""

# Mostrar comando para configurar variáveis no servidor
echo "================================================"
echo "📋 Variáveis para configurar no servidor:"
echo "================================================"
echo ""
echo "export JWT_SECRET='$JWT_SECRET'"
echo "export API_TOKEN='$API_TOKEN'"
echo "export APP_ENV=production"
echo "export DB_HOST=localhost"
echo "export DB_PORT=3306"
echo "export DB_NAME=u304177849_api"
echo "export DB_USER=u304177849_api"
echo "export DB_PASS='+DEEJ&7t'"
echo "export APP_URL=https://api.appcheckin.com.br"
echo ""

# Criar arquivo de backup
cat > .env.production.backup << EOF
# ================================================
# BACKUP - AMBIENTE DE PRODUÇÃO
# ================================================
# Gerado em: $(date)

JWT_SECRET=$JWT_SECRET
API_TOKEN=$API_TOKEN
APP_ENV=production
DB_HOST=localhost
DB_PORT=3306
DB_NAME=u304177849_api
DB_USER=u304177849_api
DB_PASS=+DEEJ&7t
APP_URL=https://api.appcheckin.com.br
CORS_ALLOWED_ORIGINS=https://appcheckin.com.br,https://www.appcheckin.com.br,https://app.appcheckin.com.br
APP_TIMEZONE=America/Sao_Paulo
LOG_LEVEL=error
LOG_PATH=/var/log/appcheckin
EOF

echo "✅ Arquivo .env.production.backup criado (não versionado)"
echo "   Salve este arquivo em local seguro!"
