#!/bin/bash

# Script para adicionar dist ao git

cd "$(dirname "$0")"

echo "Adicionando dist/ ao git..."

# Remover arquivo .gitkeep se existir (para não conflitar)
find dist -name ".gitkeep" -delete 2>/dev/null

# Adicionar todos os arquivos
git add dist/

# Verificar se há mudanças
if git diff --cached --quiet; then
    echo "❌ Nenhuma mudança para adicionar"
else
    echo "✅ Arquivos prontos para commit"
    echo ""
    echo "Fazendo commit..."
    git commit -m "build: adicionar dist gerado pelo Expo

- Adiciona pasta dist/ com export de produção
- Contém index.html, fonts.css, favicon.ico
- Contém _expo/Fonts/ com 19 arquivos .ttf
- Pronto para deploy em produção"
    
    echo ""
    echo "✅ Commit concluído!"
    echo ""
    echo "Próximas ações:"
    echo "1. git push origin main"
    echo "2. Fazer pull no servidor"
    echo "3. Ou fazer scp de dist/ para /var/www/painel/"
fi
