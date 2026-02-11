#!/bin/bash

# Script de Diagnóstico - Deploy Expo Web
# Verifica se todos os arquivos estão no lugar correto no servidor

echo "🔍 Diagnóstico de Deploy Expo Web"
echo "=================================="
echo ""

# Cores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# URL do site
SITE_URL="${1:-https://painel.appcheckin.com.br}"

echo -e "${BLUE}Testando: $SITE_URL${NC}"
echo ""

# Função para testar URL
test_url() {
    local url="$1"
    local name="$2"
    
    status=$(curl -s -o /dev/null -w "%{http_code}" -I "$url" 2>/dev/null)
    
    if [ "$status" = "200" ]; then
        echo -e "${GREEN}✅${NC} $name - HTTP $status"
        return 0
    else
        echo -e "${RED}❌${NC} $name - HTTP $status (Esperado 200)"
        return 1
    fi
}

echo -e "${BLUE}1. Verificando HTML principal:${NC}"
test_url "$SITE_URL/index.html" "index.html"
echo ""

echo -e "${BLUE}2. Verificando CSS do Expo:${NC}"
test_url "$SITE_URL/_expo/static/css/web-7c347f7ba1c2b5fdd8e1ec682d3ced07.css" "CSS principal"
echo ""

echo -e "${BLUE}3. Verificando JS do Expo:${NC}"
test_url "$SITE_URL/_expo/static/js/web/index-7adf61ec8f7ca3f2634b4e53f81e9f75.js" "JS principal"
echo ""

echo -e "${BLUE}4. Verificando CSS de Fonts:${NC}"
test_url "$SITE_URL/fonts.css" "fonts.css"
echo ""

echo -e "${BLUE}5. Verificando Fonts TTF:${NC}"
test_url "$SITE_URL/_expo/Fonts/Feather.ttf" "Feather.ttf"
test_url "$SITE_URL/_expo/Fonts/Ionicons.ttf" "Ionicons.ttf"
echo ""

echo -e "${BLUE}6. Verificando Favicon:${NC}"
test_url "$SITE_URL/favicon.ico" "favicon.ico"
echo ""

echo -e "${BLUE}7. Verificando estrutura de diretórios:${NC}"
echo "Arquivos esperados em dist/:"
echo "  • index.html"
echo "  • fonts.css"
echo "  • favicon.ico"
echo "  • _expo/static/css/web-*.css"
echo "  • _expo/static/js/web/index-*.js"
echo "  • _expo/Fonts/*.ttf (19 arquivos)"
echo ""

# Testa se o root está correto
echo -e "${YELLOW}📝 DIAGNÓSTICO:${NC}"
echo ""

# Verificar se o HTML contém os links corretos
echo "Verificando conteúdo do HTML..."
html=$(curl -s "$SITE_URL/index.html" 2>/dev/null | head -100)

if echo "$html" | grep -q "/_expo/static/css"; then
    echo -e "${GREEN}✅${NC} Links CSS estão corretos no HTML"
else
    echo -e "${RED}❌${NC} Links CSS podem estar incorretos no HTML"
fi

if echo "$html" | grep -q "/_expo/static/js"; then
    echo -e "${GREEN}✅${NC} Links JS estão corretos no HTML"
else
    echo -e "${RED}❌${NC} Links JS podem estar incorretos no HTML"
fi

if echo "$html" | grep -q "/fonts.css"; then
    echo -e "${GREEN}✅${NC} Link de fonts.css está no HTML"
else
    echo -e "${RED}❌${NC} Link de fonts.css FALTANDO no HTML"
fi

echo ""
echo -e "${YELLOW}🔧 PRÓXIMOS PASSOS:${NC}"
echo ""
echo "Se há muitos 404s:"
echo "1. SSH no servidor: ssh usuario@seu-servidor"
echo "2. Verificar diretório: ls -la /caminho/para/dist/"
echo "3. Verificar permissões: chmod -R 755 /caminho/para/dist/"
echo "4. Verificar nginx/apache config (ver docs/DEPLOY_SCRIPTS.md)"
echo ""
