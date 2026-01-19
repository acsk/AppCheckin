#!/bin/bash

# ================================================
# Validador de Variáveis de Ambiente
# ================================================

echo "🔍 Validando arquivo .env..."
echo ""

# Cores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

ERRORS=0
WARNINGS=0

# Função para verificar variável
check_var() {
    local var=$1
    local required=$2
    
    if grep -q "^${var}=" .env 2>/dev/null; then
        local value=$(grep "^${var}=" .env | cut -d'=' -f2-)
        
        if [ -z "$value" ] && [ "$required" = true ]; then
            echo -e "${RED}❌ $var${NC}: está vazio (obrigatório)"
            ((ERRORS++))
        else
            echo -e "${GREEN}✅ $var${NC}: OK"
        fi
    elif [ "$required" = true ]; then
        echo -e "${RED}❌ $var${NC}: não encontrado (obrigatório)"
        ((ERRORS++))
    else
        echo -e "${YELLOW}⚠️ $var${NC}: não configurado (opcional)"
        ((WARNINGS++))
    fi
}

echo "================================================"
echo "Variáveis Obrigatórias"
echo "================================================"
check_var "APP_ENV" true
check_var "DB_HOST" true
check_var "DB_PORT" true
check_var "DB_NAME" true
check_var "DB_USER" true
check_var "DB_PASS" true
check_var "JWT_SECRET" true
check_var "APP_URL" true

echo ""
echo "================================================"
echo "Variáveis Recomendadas"
echo "================================================"
check_var "APP_DEBUG" false
check_var "LOG_LEVEL" false
check_var "LOG_PATH" false
check_var "CORS_ALLOWED_ORIGINS" false

echo ""
echo "================================================"
echo "Teste de Conexão MySQL"
echo "================================================"

DB_HOST=$(grep "^DB_HOST=" .env | cut -d'=' -f2-)
DB_PORT=$(grep "^DB_PORT=" .env | cut -d'=' -f2-)
DB_USER=$(grep "^DB_USER=" .env | cut -d'=' -f2-)
DB_PASS=$(grep "^DB_PASS=" .env | cut -d'=' -f2-)
DB_NAME=$(grep "^DB_NAME=" .env | cut -d'=' -f2-)

if mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" -e "SELECT 1" >/dev/null 2>&1; then
    echo -e "${GREEN}✅ Conexão MySQL${NC}: OK"
else
    echo -e "${RED}❌ Conexão MySQL${NC}: FALHOU"
    echo "   Verifique credenciais: $DB_USER@$DB_HOST:$DB_PORT"
    ((ERRORS++))
fi

echo ""
echo "================================================"
echo "Resumo"
echo "================================================"
echo -e "Erros: ${RED}$ERRORS${NC}"
echo -e "Avisos: ${YELLOW}$WARNINGS${NC}"

if [ $ERRORS -eq 0 ]; then
    echo -e "\n${GREEN}✅ Todas as validações passaram!${NC}"
    exit 0
else
    echo -e "\n${RED}❌ Existem erros de configuração${NC}"
    exit 1
fi
