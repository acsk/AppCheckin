#!/bin/bash
# Hostinger e vários FTPs ignoram ou não enviam pastas que começam com "_".
# Renomeia dist/_expo -> dist/expo-static e atualiza referências nos HTMLs.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST="$ROOT/dist"

if [ ! -d "$DIST" ]; then
  echo "Erro: pasta dist/ não encontrada."
  exit 1
fi

if [ -d "$DIST/_expo" ]; then
  rm -rf "$DIST/expo-static"
  mv "$DIST/_expo" "$DIST/expo-static"
  echo "Renomeado: dist/_expo -> dist/expo-static"
elif [ -d "$DIST/expo-static" ]; then
  echo "dist/expo-static já existe."
else
  echo "Erro: nem dist/_expo nem dist/expo-static encontrados."
  exit 1
fi

replace_in_file() {
  local file="$1"
  if [[ "$OSTYPE" == darwin* ]]; then
    sed -i '' 's|/_expo/|/expo-static/|g' "$file"
    sed -i '' 's|"_expo/|"/expo-static/|g' "$file"
  else
    sed -i 's|/_expo/|/expo-static/|g' "$file"
    sed -i 's|"_expo/|"/expo-static/|g' "$file"
  fi
}

while IFS= read -r -d '' html; do
  replace_in_file "$html"
done < <(find "$DIST" -name '*.html' -type f -print0)

echo "Paths /_expo/ substituídos por /expo-static/ nos HTMLs."
