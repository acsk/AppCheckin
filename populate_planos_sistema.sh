#!/bin/bash

# Script para popular o banco de dados com associações de academias aos planos do sistema

echo "🚀 Populando banco de dados com associações academia-planos..."
echo ""

# Cores para output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Verificar se o MySQL está rodando
if ! docker ps | grep -q mysql; then
    echo -e "${RED}❌ Container MySQL não está rodando!${NC}"
    echo "Execute: docker-compose up -d"
    exit 1
fi

echo -e "${YELLOW}📊 Executando script SQL de associações...${NC}"
echo ""

# Executar o script SQL
docker exec -i $(docker ps -qf "name=mysql") mysql -uroot -proot appcheckin < Backend/database/seeds/seed_tenant_planos_sistema.sql

if [ $? -eq 0 ]; then
    echo ""
    echo -e "${GREEN}✅ Associações criadas com sucesso!${NC}"
    echo ""
    echo -e "${GREEN}📋 Dados inseridos:${NC}"
    echo "   - Academia 1: Plano Professional (ativo)"
    echo "   - Academia 2: Plano Enterprise (ativo)"
    echo "   - Academia 3: Plano Starter (ativo)"
    echo "   - Histórico de contratos anteriores"
    echo ""
    echo -e "${YELLOW}💡 Dica: Execute as queries de verificação no arquivo SQL para ver os relatórios${NC}"
else
    echo ""
    echo -e "${RED}❌ Erro ao criar associações!${NC}"
    exit 1
fi
