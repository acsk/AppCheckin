#!/bin/bash
# Pós-processamento do expo export --platform web (assets + fontes usadas no app).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

FONTS_SRC="$ROOT/node_modules/@expo/vector-icons/build/vendor/react-native-vector-icons/Fonts"
FONTS=(Feather MaterialIcons MaterialCommunityIcons)

if [ ! -d "$FONTS_SRC" ]; then
  echo "Erro: fontes do @expo/vector-icons não encontradas em $FONTS_SRC"
  exit 1
fi

cp -r assets dist/
mkdir -p dist/fonts

for font in "${FONTS[@]}"; do
  src="$FONTS_SRC/${font}.ttf"
  if [ ! -f "$src" ]; then
    echo "Erro: fonte ausente: $src"
    exit 1
  fi
  cp "$src" dist/fonts/
done

bash scripts/inject-fonts.sh
cp public/fonts.css dist/fonts.css

echo "Pós-export web concluído (3 fontes de ícones)."
