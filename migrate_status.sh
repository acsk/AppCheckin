#!/bin/bash

# =====================================================
# Script para aplicar migrations de padronização de status
# =====================================================

echo "🔄 Iniciando padronização de status..."
echo ""

# Configurações do banco de dados
DB_HOST="localhost"
DB_USER="root"
DB_PASS="senha123"
DB_NAME="appcheckin"

# Caminho das migrations
MIGRATIONS_PATH="./Backend/database/migrations"

# Cores para output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Função para executar SQL
execute_sql() {
    local file=$1
    local description=$2
    
    echo -e "${YELLOW}📄 Executando: ${description}${NC}"
    
    if mysql -h${DB_HOST} -u${DB_USER} -p${DB_PASS} ${DB_NAME} < "${file}" 2>&1; then
        echo -e "${GREEN}✅ Sucesso!${NC}"
        echo ""
        return 0
    else
        echo -e "${RED}❌ Erro ao executar migration!${NC}"
        echo ""
        return 1
    fi
}

# Backup do banco antes de iniciar
echo -e "${YELLOW}💾 Criando backup do banco...${NC}"
mysqldump -h${DB_HOST} -u${DB_USER} -p${DB_PASS} ${DB_NAME} > "backup_antes_status_$(date +%Y%m%d_%H%M%S).sql"
echo -e "${GREEN}✅ Backup criado!${NC}"
echo ""

# Executar migrations em ordem
echo "🚀 Aplicando migrations..."
echo ""

# Migration 037: Criar tabelas de status
execute_sql "${MIGRATIONS_PATH}/037_create_status_tables.sql" "037 - Criar tabelas de status"

# Migration 038: Adicionar colunas status_id e migrar dados
execute_sql "${MIGRATIONS_PATH}/038_add_status_id_columns.sql" "038 - Adicionar status_id e migrar dados"

# Verificar dados migrados
echo -e "${YELLOW}🔍 Verificando migração de dados...${NC}"
echo ""

mysql -h${DB_HOST} -u${DB_USER} -p${DB_PASS} ${DB_NAME} << EOF
SELECT 'Contas a Receber:' as tabela;
SELECT 
    status as status_antigo, 
    status_id as status_novo,
    COUNT(*) as total
FROM contas_receber 
GROUP BY status, status_id;

SELECT 'Matrículas:' as tabela;
SELECT 
    status as status_antigo, 
    status_id as status_novo,
    COUNT(*) as total
FROM matriculas 
GROUP BY status, status_id;
EOF

echo ""
echo -e "${GREEN}✅ Padronização de status concluída!${NC}"
echo ""
echo "📝 Próximos passos:"
echo "   1. Testar a API: GET /api/status/conta-receber"
echo "   2. Atualizar Models do backend para usar JOINs"
echo "   3. Atualizar componentes do frontend"
echo "   4. Após validação, executar: 039_remove_enum_columns.sql"
echo ""
echo "⚠️  As colunas ENUM antigas foram mantidas para rollback seguro"
echo "   Remova-as somente após validar que tudo funciona"
