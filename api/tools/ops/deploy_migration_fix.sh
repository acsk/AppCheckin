#!/bin/bash

# =====================================================
# DEPLOY EMERGENCIAL - Correção Migração usuario_tenant
# =====================================================
# Este script envia apenas os arquivos corrigidos para produção
# Data: 04/02/2026
# =====================================================

set -e  # Parar em caso de erro

echo "🚀 Deploy Emergencial - Correção Migração"
echo "=========================================="

# Cores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configurações
SERVER="appcheckin.com.br"
REMOTE_PATH="/home/u304177849/domains/api.appcheckin.com.br/public_html"
LOCAL_PATH="."

echo ""
echo -e "${YELLOW}⚠️  Este script enviará apenas os arquivos corrigidos:${NC}"
echo "   - app/Controllers/AdminController.php"
echo "   - app/Controllers/AlunoController.php"
echo "   - app/Models/Usuario.php"
echo ""
read -p "Confirma o envio dos arquivos? (s/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Ss]$ ]]
then
    echo -e "${RED}❌ Deploy cancelado${NC}"
    exit 1
fi

echo ""
echo -e "${GREEN}📦 Enviando arquivos corrigidos...${NC}"

# Lista de arquivos a enviar
FILES=(
    "app/Controllers/AdminController.php"
    "app/Controllers/AlunoController.php"
    "app/Models/Usuario.php"
)

# Verificar se os arquivos existem localmente
echo ""
echo "🔍 Verificando arquivos locais..."
for file in "${FILES[@]}"; do
    if [ ! -f "$LOCAL_PATH/$file" ]; then
        echo -e "${RED}❌ Arquivo não encontrado: $file${NC}"
        exit 1
    fi
    echo -e "${GREEN}✅ $file${NC}"
done

# Enviar via rsync
echo ""
echo "📤 Enviando para produção via rsync..."
for file in "${FILES[@]}"; do
    echo "   Enviando: $file"
    rsync -avz --progress \
        "$LOCAL_PATH/$file" \
        "$SERVER:$REMOTE_PATH/$file"
done

echo ""
echo -e "${GREEN}✅ Arquivos enviados com sucesso!${NC}"
echo ""
echo "🔄 Próximos passos:"
echo "   1. Teste a API: https://api.appcheckin.com.br/admin/alunos"
echo "   2. Verifique os logs em caso de erro"
echo "   3. Se necessário, limpe o cache do servidor"
echo ""
echo "💡 Comando para limpar cache (se necessário):"
echo "   ssh $SERVER 'cd $REMOTE_PATH && php artisan cache:clear'"
echo ""
echo "📋 Comando para verificar logs:"
echo "   ssh $SERVER 'tail -50 $REMOTE_PATH/public/php-error.log'"
echo ""
