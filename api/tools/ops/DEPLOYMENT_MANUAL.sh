#!/bin/bash

# ============================================
# GUIA DE DEPLOYMENT - SERVIDOR COMPARTILHADO
# ============================================
# Este script documenta os passos para fazer upload da aplicação

echo "📋 GUIA DE DEPLOYMENT MANUAL - AppCheckin API"
echo "=========================================="
echo ""

echo "✅ PASSO 1: Upload dos arquivos via FTP/SFTP"
echo "-------------------------------------------"
echo "1. Conecte via FTP/SFTP ao servidor"
echo "2. Navegue até: /public_html/ (ou seu DocumentRoot)"
echo "3. Upload dos arquivos:"
echo "   - Copie TODO conteúdo da pasta 'api' para /public_html/"
echo "   - NÃO inclua: /vendor, /node_modules, .git"
echo ""
echo "Comando rsync (alternativa via SSH):"
echo "rsync -avz --exclude=vendor --exclude=.git --exclude=node_modules ./ usuario@servidor:/public_html/"
echo ""

echo "✅ PASSO 2: Configurar .env no servidor"
echo "-------------------------------------------"
echo "Via SSH/Terminal:"
echo ""
echo "1. Conecte ao servidor:"
echo "   ssh usuario@api.appcheckin.com.br"
echo ""
echo "2. Navegue até a pasta da aplicação:"
echo "   cd /public_html"
echo "   (ou aonde você fez o upload)"
echo ""
echo "3. Crie o arquivo .env:"
echo "   cp .env.example .env"
echo "   nano .env  (ou vim .env)"
echo ""
echo "4. Edite e coloque as variáveis corretas (veja abaixo)"
echo ""
echo "5. Salve (Ctrl+X, Y, Enter no nano)"
echo ""

echo "✅ PASSO 3: Instalar dependências PHP"
echo "-------------------------------------------"
echo "No terminal do servidor:"
echo ""
echo "cd /public_html"
echo "composer install --no-dev --optimize-autoloader"
echo ""
echo "⏱️  Isso pode levar alguns minutos..."
echo ""

echo "✅ PASSO 4: Dar permissões corretas"
echo "-------------------------------------------"
echo "No terminal do servidor:"
echo ""
echo "chmod 755 public"
echo "chmod 755 app"
echo "chmod 755 config"
echo "chmod 644 .env"
echo ""

echo "✅ PASSO 5: Executar migrations no banco"
echo "-------------------------------------------"
echo "No terminal do servidor (ou via phpMyAdmin):"
echo ""
echo "mysql -h localhost -u u304177849_api -p u304177849_api < database/migrations/000_init_migrations.sql"
echo "mysql -h localhost -u u304177849_api -p u304177849_api < database/migrations/001_create_tables.sql"
echo "(execute cada migration nesta ordem)"
echo ""
echo "⚠️  Você já executou as migrations? Se sim, pule este passo!"
echo ""

echo "✅ PASSO 6: Configurar .htaccess para Slim"
echo "-------------------------------------------"
echo "Crie o arquivo public/.htaccess com:"
echo ""
cat << 'EOF'
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.php [QSA,L]
</IfModule>
EOF
echo ""

echo "✅ PASSO 7: Testar a aplicação"
echo "-------------------------------------------"
echo "1. Abra no navegador:"
echo "   https://api.appcheckin.com.br/status"
echo ""
echo "2. Você deve receber uma resposta JSON com o status da API"
echo ""
echo "3. Se receber erro, verifique:"
echo "   - Arquivo .env existe e tem as variáveis corretas"
echo "   - Permissões de pasta (755 para pastas, 644 para arquivos)"
echo "   - Conexão com banco de dados (testar credenciais)"
echo "   - Logs (tail -f /var/log/appcheckin/error.log)"
echo ""

echo "✅ VARIÁVEIS DE AMBIENTE (.env)"
echo "-------------------------------------------"
cat << 'EOF'
# Database (do seu servidor compartilhado)
DB_HOST=localhost
DB_PORT=3306
DB_NAME=u304177849_api
DB_USER=u304177849_api
DB_PASS=+DEEJ&7t

# JWT (gere uma chave segura!)
JWT_SECRET=GERE_UMA_CHAVE_COM_openssl_rand_-base64_64
JWT_EXPIRATION=86400

# App
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.appcheckin.com.br

# CORS
CORS_ALLOWED_ORIGINS=https://appcheckin.com.br,https://www.appcheckin.com.br,https://app.appcheckin.com.br

# Timezone
APP_TIMEZONE=America/Sao_Paulo

# Logs
LOG_LEVEL=error
LOG_PATH=/var/log/appcheckin

# Rate Limiting
RATE_LIMIT_ENABLED=true
RATE_LIMIT_MAX_REQUESTS=100
RATE_LIMIT_WINDOW_SECONDS=60
EOF
echo ""

echo "📌 DICAS IMPORTANTES"
echo "-------------------------------------------"
echo "• Gerar JWT_SECRET seguro: openssl rand -base64 64"
echo "• NÃO faça commit do .env no git"
echo "• Sempre use HTTPS em produção"
echo "• Monitore logs: tail -f /var/log/appcheckin/error.log"
echo "• PHP mínimo 8.1, recomendado 8.2+"
echo "• Composer deve estar instalado no servidor"
echo ""

echo "✅ DEPLOYMENT CONCLUÍDO!"
echo "=========================================="
