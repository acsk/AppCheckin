#!/bin/bash
# Garante link para fonts.css no HTML gerado pelo Expo (idempotente).

set -euo pipefail

HTML_FILE="dist/index.html"

if [ ! -f "$HTML_FILE" ]; then
  echo "Erro: $HTML_FILE não encontrado"
  exit 1
fi

if grep -q 'href="/fonts.css"' "$HTML_FILE"; then
  echo "fonts.css já referenciado em dist/index.html"
  exit 0
fi

echo "Injetando fonts.css no dist/index.html..."

if [[ "$OSTYPE" == "darwin"* ]]; then
  sed -i '' 's/<head>/<head><link rel="stylesheet" href="\/fonts.css">/' "$HTML_FILE"
else
  sed -i 's/<head>/<head><link rel="stylesheet" href="\/fonts.css">/' "$HTML_FILE"
fi

echo "Fontes injetadas com sucesso"
