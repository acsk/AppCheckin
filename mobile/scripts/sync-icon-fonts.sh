#!/bin/bash
# Copia fontes de ícones usadas no app para public/fonts (dev) e dist/fonts (build).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

FONTS_SRC="$ROOT/node_modules/@expo/vector-icons/build/vendor/react-native-vector-icons/Fonts"
FONTS=(Feather MaterialIcons MaterialCommunityIcons)

if [ ! -d "$FONTS_SRC" ]; then
  echo "Erro: fontes do @expo/vector-icons não encontradas. Rode npm install em mobile/."
  exit 1
fi

copy_fonts_to() {
  local dest_dir="$1"
  mkdir -p "$dest_dir"
  for font in "${FONTS[@]}"; do
    local src="$FONTS_SRC/${font}.ttf"
    if [ ! -f "$src" ]; then
      echo "Erro: fonte ausente: $src"
      exit 1
    fi
    cp "$src" "$dest_dir/"
  done
}

copy_fonts_to "$ROOT/public/fonts"

if [ -d "$ROOT/dist" ]; then
  copy_fonts_to "$ROOT/dist/fonts"
fi
