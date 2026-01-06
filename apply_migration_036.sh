#!/bin/bash

# Script para aplicar a migration 036 - Corrigir forma_pagamento em pagamentos_contrato

cd "$(dirname "$0")"

# Cores para output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}========================================${NC}"
echo -e "${YELLOW}Aplicando Migration 036${NC}"
echo -e "${YELLOW}Corrigir forma_pagamento em pagamentos_contrato${NC}"
echo -e "${YELLOW}========================================${NC}"

# Carregar variáveis de ambiente do docker-compose
DB_HOST="localhost"
DB_PORT="3306"
DB_NAME="appcheckin"
DB_USER="root"
DB_PASS="root"

echo -e "\n${YELLOW}Aplicando migration 036...${NC}"
mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < Backend/database/migrations/036_fix_pagamentos_forma_pagamento.sql

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Migration 036 aplicada com sucesso!${NC}"
else
    echo -e "${RED}✗ Erro ao aplicar migration 036${NC}"
    exit 1
fi

echo -e "\n${GREEN}========================================${NC}"
echo -e "${GREEN}Migration concluída!${NC}"
echo -e "${GREEN}========================================${NC}"
