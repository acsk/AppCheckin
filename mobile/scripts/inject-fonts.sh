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

# Cria o CSS inline com as fontes
FONTS_CSS='<link rel="stylesheet" href="/fonts.css" />'

# Adiciona o link ao <head> do HTML (antes de </head>)
if grep -q "$FONTS_CSS" "$HTML_FILE"; then
    echo "ℹ️  Fontes já estão injetadas"
else
    # Usa sed para injetar antes de </head>
    sed -i.bak 's|</head>|'"$FONTS_CSS"'</head>|' "$HTML_FILE"
    echo "✅ Fontes injetadas com sucesso!"
    rm -f "${HTML_FILE}.bak"
fi
