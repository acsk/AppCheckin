#!/bin/bash

# ==========================================
# Script de Validação das Migrations
# ==========================================
# Executa verificações antes de rodar migrations críticas
# ==========================================

set -e

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "========================================="
echo "🔍 VALIDAÇÃO DE MIGRATIONS"
echo "========================================="
echo ""

# Variáveis de ambiente
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-appcheckin}"
DB_USER="${DB_USER:-root}"

# Solicitar senha
echo -n "Digite a senha do MySQL: "
read -s DB_PASS
echo ""
echo ""

# Função para executar query
run_query() {
    mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "$1"
}

# ==========================================
# 1. BACKUP
# ==========================================

echo "📦 1. CRIANDO BACKUP..."
BACKUP_FILE="backup_before_migrations_$(date +%Y%m%d_%H%M%S).sql"
mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_FILE"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Backup criado: $BACKUP_FILE${NC}"
else
    echo -e "${RED}❌ Erro ao criar backup!${NC}"
    exit 1
fi
echo ""

# ==========================================
# 2. VERIFICAR DUPLICATAS
# ==========================================

echo "🔍 2. VERIFICANDO DUPLICATAS..."
mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < verificar_duplicatas.sql > resultado_duplicatas.txt 2>&1

# Contar problemas
EMAIL_DUP=$(grep "emails duplicados encontrados" resultado_duplicatas.txt | grep -oE '[0-9]+' | head -1 || echo "0")
CPF_DUP=$(grep "CPFs duplicados encontrados" resultado_duplicatas.txt | grep -oE '[0-9]+' | head -1 || echo "0")
MENSALIDADES_DUP=$(grep "mensalidades duplicadas encontradas" resultado_duplicatas.txt | grep -oE '[0-9]+' | head -1 || echo "0")

TOTAL_PROBLEMAS=$((EMAIL_DUP + CPF_DUP + MENSALIDADES_DUP))

if [ $TOTAL_PROBLEMAS -eq 0 ]; then
    echo -e "${GREEN}✅ Nenhuma duplicata encontrada${NC}"
else
    echo -e "${RED}❌ Encontrados $TOTAL_PROBLEMAS problemas:${NC}"
    echo "   - $EMAIL_DUP emails duplicados"
    echo "   - $CPF_DUP CPFs duplicados"
    echo "   - $MENSALIDADES_DUP mensalidades duplicadas"
    echo ""
    echo -e "${YELLOW}⚠️  Verifique o arquivo: resultado_duplicatas.txt${NC}"
    echo ""
    echo "Deseja continuar mesmo assim? (y/N)"
    read -r CONTINUE
    if [ "$CONTINUE" != "y" ] && [ "$CONTINUE" != "Y" ]; then
        echo "Abortado pelo usuário"
        exit 1
    fi
fi
echo ""

# ==========================================
# 3. VERIFICAR TABELAS EXISTENTES
# ==========================================

echo "📊 3. VERIFICANDO ESTRUTURA DO BANCO..."

# Verificar se dias já tem tenant_id
DIAS_HAS_TENANT=$(run_query "SHOW COLUMNS FROM dias LIKE 'tenant_id'" | wc -l)
if [ $DIAS_HAS_TENANT -gt 1 ]; then
    echo -e "${GREEN}✅ dias.tenant_id já existe${NC}"
else
    echo -e "${YELLOW}⚠️  dias.tenant_id não existe (será criado)${NC}"
fi

# Verificar se checkins já tem tenant_id
CHECKINS_HAS_TENANT=$(run_query "SHOW COLUMNS FROM checkins LIKE 'tenant_id'" | wc -l)
if [ $CHECKINS_HAS_TENANT -gt 1 ]; then
    echo -e "${YELLOW}⚠️  checkins.tenant_id já existe (migration 044b pode falhar)${NC}"
else
    echo -e "${GREEN}✅ checkins.tenant_id não existe (pronto para 044b)${NC}"
fi

echo ""

# ==========================================
# 4. TESTAR CONEXÃO
# ==========================================

echo "🔌 4. TESTANDO CONEXÃO COM BANCO..."
if run_query "SELECT 1" > /dev/null 2>&1; then
    echo -e "${GREEN}✅ Conexão OK${NC}"
else
    echo -e "${RED}❌ Erro de conexão!${NC}"
    exit 1
fi
echo ""

# ==========================================
# 5. PERGUNTAR QUAIS MIGRATIONS EXECUTAR
# ==========================================

echo "========================================="
echo "📋 MIGRATIONS DISPONÍVEIS"
echo "========================================="
echo ""
echo "1. [042] Padronizar Collation (utf8mb4_unicode_ci)"
echo "2. [043] Adicionar Constraints UNIQUE"
echo "3. [044b] Índices Tenant-First PROGRESSIVO (recomendado)"
echo "4. [044] Índices Tenant-First ORIGINAL (breaking changes)"
echo "5. TODAS (042 + 043 + 044b)"
echo "0. CANCELAR"
echo ""
echo -n "Escolha uma opção [0-5]: "
read -r OPCAO
echo ""

case $OPCAO in
    1)
        echo "Executando Migration 042..."
        mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < 042_padronizar_collation.sql
        echo -e "${GREEN}✅ Migration 042 executada${NC}"
        ;;
    2)
        if [ $TOTAL_PROBLEMAS -gt 0 ]; then
            echo -e "${RED}❌ ERRO: Existem duplicatas! Limpe antes de executar.${NC}"
            exit 1
        fi
        echo "Executando Migration 043..."
        mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < 043_adicionar_constraints_unicidade.sql
        echo -e "${GREEN}✅ Migration 043 executada${NC}"
        ;;
    3)
        echo "Executando Migration 044b (Progressiva)..."
        mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < 044b_checkins_tenant_progressivo.sql
        echo -e "${GREEN}✅ Migration 044b executada${NC}"
        ;;
    4)
        echo -e "${YELLOW}⚠️  ATENÇÃO: Migration 044 tem BREAKING CHANGES!${NC}"
        echo "Tem certeza? (y/N)"
        read -r CONFIRM
        if [ "$CONFIRM" = "y" ] || [ "$CONFIRM" = "Y" ]; then
            echo "Executando Migration 044 (Original)..."
            mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < 044_otimizar_indices_tenant_first.sql
            echo -e "${GREEN}✅ Migration 044 executada${NC}"
        else
            echo "Cancelado"
        fi
        ;;
    5)
        if [ $TOTAL_PROBLEMAS -gt 0 ]; then
            echo -e "${RED}❌ ERRO: Existem duplicatas! Limpe antes de executar.${NC}"
            exit 1
        fi
        echo "Executando TODAS as migrations..."
        mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < 042_padronizar_collation.sql
        echo -e "${GREEN}✅ Migration 042 OK${NC}"
        mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < 043_adicionar_constraints_unicidade.sql
        echo -e "${GREEN}✅ Migration 043 OK${NC}"
        mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < 044b_checkins_tenant_progressivo.sql
        echo -e "${GREEN}✅ Migration 044b OK${NC}"
        ;;
    0)
        echo "Cancelado pelo usuário"
        exit 0
        ;;
    *)
        echo -e "${RED}Opção inválida${NC}"
        exit 1
        ;;
esac

echo ""
echo "========================================="
echo "✅ CONCLUÍDO!"
echo "========================================="
echo ""
echo "Backup salvo em: $BACKUP_FILE"
echo "Relatório de duplicatas: resultado_duplicatas.txt"
echo ""
