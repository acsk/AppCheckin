#!/bin/bash

cd /Users/andrecabral/Projetos/AppCheckin/api

echo "🔧 Fazendo commit do fix..."
git add app/Services/ImageCompressionService.php docs/FIX_ERRO_500_UPLOAD_FOTO.md

git commit -m "fix: Adicionar fallback para compressão de imagens quando biblioteca não disponível

- Verifica disponibilidade de intervention/image no construtor
- Se não disponível, usa fallback com copy simples
- Retorna estrutura de dados com aviso
- Mantém endpoint funcional mesmo sem compressão
- Resolve erro 500 em produção"

echo "📤 Fazendo push para GitHub..."
git push origin main

echo "✅ Done!"
echo ""
echo "Próximo passo em produção:"
echo "ssh u304177849@appcheckin.com.br"
echo "cd /home/u304177849/domains/appcheckin.com.br/public_html/api"
echo "git pull origin main && composer update"
