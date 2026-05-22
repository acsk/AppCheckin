#!/bin/bash

# Script para injetar fontes do vector-icons no HTML gerado pelo Expo

echo "🔧 Injetando fonts.css no dist/index.html..."

HTML_FILE="dist/index.html"

# Verifica se o arquivo existe
if [ ! -f "$HTML_FILE" ]; then
    echo "❌ Erro: $HTML_FILE não encontrado"
    exit 1
fi

# Injeta o link para fonts.css após a tag <head>
# Usa sed para inserir o link antes de <title>
if [[ "$OSTYPE" == "darwin"* ]]; then
    # macOS
    sed -i '' 's/<head>/<head><link rel="stylesheet" href="\/fonts.css">/' "$HTML_FILE"
else
    # Linux
    sed -i 's/<head>/<head><link rel="stylesheet" href="\/fonts.css">/' "$HTML_FILE"
fi

echo "✅ Fontes injetadas com sucesso"
