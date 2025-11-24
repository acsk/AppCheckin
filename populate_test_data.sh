#!/bin/bash

# Script para popular o banco de dados com massa de dados para testes do Dashboard

echo "🚀 Populando banco de dados com dados de teste..."
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

echo -e "${YELLOW}📊 Executando script SQL...${NC}"
echo ""

# Executar o script SQL
docker exec -i $(docker ps -qf "name=mysql") mysql -uroot -proot checkin_db < Backend/database/seeds/seed_dashboard_test.sql

if [ $? -eq 0 ]; then
    echo ""
    echo -e "${GREEN}✅ Massa de dados criada com sucesso!${NC}"
    echo ""
    echo "📈 Dados criados:"
    echo "   • 150 alunos no total"
    echo "   • 120 alunos ativos (com plano válido)"
    echo "   • 30 alunos inativos (sem plano ou vencido)"
    echo "   • 12 novos alunos este mês (novembro/2025)"
    echo "   • 8 planos vencendo nos próximos 7 dias"
    echo "   • ~45 check-ins hoje"
    echo "   • ~890 check-ins no mês"
    echo "   • 5 planos diferentes"
    echo "   • Receita mensal estimada: ~R$ 15.000,00"
    echo ""
    echo "🎯 Você já pode:"
    echo "   1. Acessar o dashboard admin no frontend"
    echo "   2. Ver as estatísticas atualizadas"
    echo "   3. Gerenciar alunos"
    echo "   4. Testar todos os filtros"
    echo ""
else
    echo -e "${RED}❌ Erro ao executar script SQL${NC}"
    exit 1
fi
