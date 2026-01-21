#!/bin/bash

# Script para injetar fontes do vector-icons no HTML gerado pelo Expo

echo "🔧 Injetando fontes do vector-icons no dist/index.html..."

# Caminho do arquivo index.html
HTML_FILE="dist/index.html"

# Verifica se o arquivo existe
if [ ! -f "$HTML_FILE" ]; then
    echo "❌ Erro: $HTML_FILE não encontrado"
    exit 1
fi

# Cria o CSS inline com as fontes (usando caminho absoluto /fonts.css)
