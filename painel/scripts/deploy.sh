#!/bin/bash

# Script de Deploy - Expo Web + Fontes de Ícones
# Este script faz o export do Expo e copia os fonts automaticamente

set -e  # Parar em caso de erro

echo "🚀 Iniciando deploy do Expo Web..."
echo ""

# Cores para output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Diretórios
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
SOURCE_FONTS="$PROJECT_DIR/node_modules/@expo/vector-icons/build/vendor/react-native-vector-icons/Fonts"
DIST_FONTS="$PROJECT_DIR/dist/_expo/Fonts"
PUBLIC_FONTS_CSS="$PROJECT_DIR/public/fonts.css"
DIST_FONTS_CSS="$PROJECT_DIR/dist/fonts.css"
BASE_PATH="${DEPLOY_BASE_PATH:-}"
# Normalizar base path (ex: "" -> root, "dist" -> "/dist")
if [ -n "$BASE_PATH" ]; then
    if [[ "$BASE_PATH" != /* ]]; then
        BASE_PATH="/$BASE_PATH"
    fi
    BASE_PATH="${BASE_PATH%/}"
fi

# Step 1: Executar export do Expo
echo -e "${BLUE}📦 Step 1: Exportando Expo para Web...${NC}"
cd "$PROJECT_DIR"
# Limpa o dist antigo (bundles velhos ficavam junto) e o cache do Metro: as variáveis EXPO_PUBLIC_*
# ficam gravadas no JS transformado, e o cache reaproveitava um build feito com a URL local.
rm -rf "$PROJECT_DIR/dist/_expo/static"
npx expo export --platform web --clear

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Erro ao executar expo export${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Export concluído${NC}"
echo ""

# Step 1b: Conferir a API gravada no bundle
API_ESPERADA="${EXPO_PUBLIC_API_V2_URL:-}"
BUNDLES=$(ls "$PROJECT_DIR"/dist/_expo/static/js/web/index-*.js 2>/dev/null || true)
if [ -z "$API_ESPERADA" ]; then
    echo -e "${RED}❌ EXPO_PUBLIC_API_V2_URL não definida. Use npm run web:build (produção) ou web:build:dev.${NC}"
    exit 1
fi
if ! grep -qF "$API_ESPERADA" $BUNDLES; then
    echo -e "${RED}❌ O bundle não usa $API_ESPERADA. Build cancelado.${NC}"
    exit 1
fi
if [[ "$API_ESPERADA" != *localhost* ]] && grep -qE "localhost:(8080|9090)" $BUNDLES; then
    echo -e "${RED}❌ O bundle de produção ainda aponta para localhost. Build cancelado.${NC}"
    exit 1
fi
echo -e "${GREEN}✅ API no bundle: $API_ESPERADA${NC}"
echo ""

# Step 2: Copiar fonts
echo -e "${BLUE}📋 Step 2: Copiando fonts dos ícones...${NC}"

# Criar diretório de destino se não existir
mkdir -p "$DIST_FONTS"

# Copiar fonts
if [ ! -d "$SOURCE_FONTS" ]; then
    echo -e "${RED}❌ Diretório de fonts não encontrado: $SOURCE_FONTS${NC}"
    exit 1
fi

FONT_COUNT=0
for font_file in "$SOURCE_FONTS"/*.ttf "$SOURCE_FONTS"/*.otf; do
    if [ -f "$font_file" ]; then
        cp "$font_file" "$DIST_FONTS/"
        FONT_COUNT=$((FONT_COUNT + 1))
    fi
done

if [ $FONT_COUNT -gt 0 ]; then
    echo -e "${GREEN}✅ $FONT_COUNT fonts copiados para: $DIST_FONTS${NC}"
else
    echo -e "${YELLOW}⚠️  Nenhum font copiado${NC}"
fi
echo ""

# Step 3: Copiar fonts.css
echo -e "${BLUE}📄 Step 3: Copiando fonts.css...${NC}"

if [ -f "$PUBLIC_FONTS_CSS" ]; then
    cp "$PUBLIC_FONTS_CSS" "$DIST_FONTS_CSS"
    echo -e "${GREEN}✅ fonts.css copiado para: $DIST_FONTS_CSS${NC}"
else
    echo -e "${RED}❌ Arquivo fonts.css não encontrado: $PUBLIC_FONTS_CSS${NC}"
    exit 1
fi
echo ""

# Step 4: Injetar link no HTML
echo -e "${BLUE}🔗 Step 4: Injetando link de fonts no index.html...${NC}"

INDEX_HTML="$PROJECT_DIR/dist/index.html"

if [ ! -f "$INDEX_HTML" ]; then
    echo -e "${RED}❌ Arquivo index.html não encontrado: $INDEX_HTML${NC}"
    exit 1
fi

# Verificar se já existe o link
if grep -q "href=\"${BASE_PATH}/fonts.css\"" "$INDEX_HTML"; then
    echo -e "${YELLOW}ℹ️  Link para fonts.css já existe${NC}"
else
    # Adicionar link no head
    sed -i '' "s|</head>|  <link rel=\"stylesheet\" href=\"${BASE_PATH}/fonts.css\">\\n</head>|g" "$INDEX_HTML"
    echo -e "${GREEN}✅ Link para fonts.css injetado${NC}"
fi

# Ajustar paths conforme base path configurado
sed -i '' "s|href=\"/_expo/|href=\"${BASE_PATH}/_expo/|g" "$INDEX_HTML"
sed -i '' "s|src=\"/_expo/|src=\"${BASE_PATH}/_expo/|g" "$INDEX_HTML"
sed -i '' "s|href=\"/favicon.ico\"|href=\"${BASE_PATH}/favicon.ico\"|g" "$INDEX_HTML"

echo ""

# Step 5: Verificação final
echo -e "${BLUE}✨ Step 5: Verificando distribuição...${NC}"
echo ""

echo -e "${YELLOW}📊 Resumo:${NC}"
echo "  • Dist criado em: $PROJECT_DIR/dist"
echo "  • Base path: ${BASE_PATH:-/} (use DEPLOY_BASE_PATH para alterar)"
echo "  • Fonts copiados: $FONT_COUNT arquivos"
echo "  • Fonte: $DIST_FONTS/"
echo "  • CSS: $DIST_FONTS_CSS"
echo ""

# Listar alguns arquivos importantes
echo -e "${YELLOW}📁 Arquivos principais:${NC}"
ls -lh "$PROJECT_DIR/dist/index.html" 2>/dev/null | awk '{print "  • index.html: " $5}'
ls -lh "$PROJECT_DIR/dist/fonts.css" 2>/dev/null | awk '{print "  • fonts.css: " $5}'
du -sh "$PROJECT_DIR/dist/_expo/Fonts" 2>/dev/null | awk '{print "  • Fonts: " $1}'
echo ""

# Verificar links no HTML
echo -e "${YELLOW}🔍 Verificando links no HTML:${NC}"
if grep -q "href=\"${BASE_PATH}/_expo/static/css/" "$INDEX_HTML"; then
    echo -e "${GREEN}  ✅ CSS links corretos${NC}"
else
    echo -e "${RED}  ❌ CSS links com problema${NC}"
fi

if grep -q "src=\"${BASE_PATH}/_expo/static/js/" "$INDEX_HTML"; then
    echo -e "${GREEN}  ✅ JS links corretos${NC}"
else
    echo -e "${RED}  ❌ JS links com problema${NC}"
fi

if grep -q "href=\"${BASE_PATH}/fonts.css\"" "$INDEX_HTML"; then
    echo -e "${GREEN}  ✅ Fonts CSS link correto${NC}"
else
    echo -e "${RED}  ❌ Fonts CSS link com problema${NC}"
fi

echo ""
echo -e "${GREEN}✨ Deploy concluído com sucesso!${NC}"
echo ""
echo -e "${YELLOW}📝 Próximos passos:${NC}"
if [ -z "$BASE_PATH" ]; then
    echo "  1. Fazer upload do CONTEÚDO de 'dist' para a raiz do servidor"
    echo "  2. Certificar que o .htaccess está na raiz"
    echo "  3. Testar em: https://seu-dominio.com"
else
    echo "  1. Fazer upload da pasta 'dist' para seu servidor"
    echo "  2. Certificar que nginx/apache está configurado para servir de '/' (raiz)"
    echo "  3. Testar em: https://seu-dominio.com"
fi
echo ""
echo -e "${YELLOW}📝 Para testar localmente:${NC}"
echo "  cd $PROJECT_DIR/dist && python3 -m http.server 3000"
echo "  Acesse: http://localhost:3000"
echo ""
