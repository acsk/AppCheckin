#!/bin/bash

# Script para fazer deploy da aplicação para hospedagem compartilhada

echo "🚀 Iniciando build do projeto..."

# 1. Instalar dependências se necessário
if [ ! -d "node_modules" ]; then
    echo "📦 Instalando dependências..."
    npm install
fi

# 2. Build para web
echo "🔨 Fazendo build para web..."
npm run web

# 3. Copiar .htaccess para dist
echo "📋 Configurando .htaccess..."
cp .htaccess dist/.htaccess 2>/dev/null || echo "⚠️  .htaccess não encontrado na raiz"

# 4. Mensagens úteis
echo ""
echo "✅ Build concluído!"
echo ""
echo "📁 Próximos passos:"
echo "1. Fazer FTP da pasta 'dist' para a raiz da hospedagem"
echo "2. Verificar se .htaccess foi copiado"
echo "3. Acessar https://mobile.appcheckin.com.br"
echo ""
echo "📝 Estrutura esperada na hospedagem:"
echo "  /public_html/"
echo "  ├── .htaccess"
echo "  └── dist/"
echo "      ├── index.html"
echo "      ├── [outros arquivos do build]"
echo "      └── .htaccess"
