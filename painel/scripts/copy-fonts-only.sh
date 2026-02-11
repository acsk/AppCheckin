#!/bin/bash

# Script auxiliar: Copia apenas fonts e arquivos depois que Expo já gerou o dist
# Use este script se você já fez o export manualmente

set -e

echo "🔧 Copiando fonts e configurando dist..."
echo ""

# Cores
GREEN='\033[0;32m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m'

# Diretórios
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
SOURCE_FONTS="$PROJECT_DIR/node_modules/@expo/vector-icons/build/vendor/react-native-vector-icons/Fonts"
DIST_FONTS="$PROJECT_DIR/dist/_expo/Fonts"
PUBLIC_FONTS_CSS="$PROJECT_DIR/public/fonts.css"
DIST_FONTS_CSS="$PROJECT_DIR/dist/fonts.css"
INDEX_HTML="$PROJECT_DIR/dist/index.html"

# Verificar se dist existe
if [ ! -d "$PROJECT_DIR/dist" ]; then
    echo -e "${RED}❌ Pasta dist não encontrada!${NC}"
    echo "Execute primeiro: npx expo export --platform web"
    exit 1
fi

echo -e "${BLUE}📋 Copiando fonts...${NC}"
mkdir -p "$DIST_FONTS"

if [ ! -d "$SOURCE_FONTS" ]; then
    echo -e "${RED}❌ Fonts não encontrados em: $SOURCE_FONTS${NC}"
    exit 1
fi

cp "$SOURCE_FONTS"/*.ttf "$DIST_FONTS/" 2>/dev/null || true
cp "$SOURCE_FONTS"/*.otf "$DIST_FONTS/" 2>/dev/null || true

FONT_COUNT=$(ls "$DIST_FONTS"/*.ttf 2>/dev/null | wc -l)
echo -e "${GREEN}✅ $FONT_COUNT fonts copiados${NC}"

echo -e "${BLUE}📄 Copiando fonts.css...${NC}"
cp "$PUBLIC_FONTS_CSS" "$DIST_FONTS_CSS"
echo -e "${GREEN}✅ fonts.css copiado${NC}"

echo -e "${BLUE}🔗 Atualizando index.html...${NC}"

# Adicionar link se não existir
if ! grep -q 'href="/fonts.css"' "$INDEX_HTML"; then
    sed -i '' 's|</head>|  <link rel="stylesheet" href="/fonts.css">\n</head>|g' "$INDEX_HTML"
    echo -e "${GREEN}✅ Link de fonts.css adicionado${NC}"
else
    echo -e "${GREEN}✅ Link de fonts.css já existe${NC}"
fi

# Corrigir duplicatas
sed -i '' 's|href="/dist/fonts.css"|href="/fonts.css"|g' "$INDEX_HTML"

echo ""
echo -e "${GREEN}✨ Concluído!${NC}"
echo "Dist está pronto para deploy em: $PROJECT_DIR/dist"
