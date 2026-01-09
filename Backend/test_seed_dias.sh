#!/bin/bash

# Script para testar o job de geração de dias

echo "🧪 Testando Job de Geração de Dias"
echo "═════════════════════════════════════"
echo ""

# Cores
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Verificar se arquivo existe
if [ ! -f "jobs/gerar_dias_anuais.php" ]; then
    echo -e "${RED}❌ Erro: arquivo jobs/gerar_dias_anuais.php não encontrado${NC}"
    exit 1
fi

echo -e "${YELLOW}1. Testando verificação de status${NC}"
php jobs/gerar_dias_anuais.php --status
echo ""

echo -e "${YELLOW}2. Testando geração de dias${NC}"
php jobs/gerar_dias_anuais.php
echo ""

echo -e "${YELLOW}3. Verificando status novamente${NC}"
php jobs/gerar_dias_anuais.php --status
echo ""

echo -e "${GREEN}✅ Todos os testes concluídos!${NC}"
echo ""
echo "Para usar em produção:"
echo "  • Script SQL: database/seeds/seed_dias_ano.sql"
echo "  • Job PHP: php jobs/gerar_dias_anuais.php"
echo "  • Cron: Adicionar agendamento conforme SEED_JOBS_DIAS.md"
