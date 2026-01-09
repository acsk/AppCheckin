#!/bin/bash

# Script para testar a aplicação Mobile AppCheckin

echo "================================"
echo "🚀 Iniciando App Mobile AppCheckin"
echo "================================"
echo ""

cd /Users/andrecabral/Projetos/AppCheckin/AppCheckin/appcheckin-mobile

echo "📦 Verificando dependências..."
if [ ! -d "node_modules" ]; then
  echo "⬇️ Instalando dependências..."
  npm install
fi

echo ""
echo "✅ Pronto para iniciar!"
echo ""
echo "Opções:"
echo "  1. npm start      - Iniciar Expo (Web ou Simulator)"
echo "  2. npm run ios    - Iniciar em simulador iOS"
echo "  3. npm run android - Iniciar em simulador Android"
echo ""
echo "Executando: npm start"
echo ""

npm start
