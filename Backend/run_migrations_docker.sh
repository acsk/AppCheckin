#!/bin/bash

# ============================================
# Script para Executar Migrations no Docker
# ============================================

CONTAINER_NAME="appcheckin_mysql"
DB_USER="root"
DB_PASS="root"
DB_NAME="appcheckin"
MIGRATIONS_DIR="/Users/andrecabral/Projetos/AppCheckin/Backend/database/migrations"

echo "🚀 Iniciando execução das migrations no Docker..."
echo "Container: $CONTAINER_NAME"
echo "Database: $DB_NAME"
echo ""

# Verificar se container está rodando
if ! docker ps | grep -q "$CONTAINER_NAME"; then
    echo "❌ Container '$CONTAINER_NAME' não está rodando!"
    echo "Execute: docker-compose up -d"
    exit 1
fi

echo "✅ Container encontrado e rodando"
echo ""

# Arrays com as migrations
migrations=(
    "060_create_wods_table.sql"
    "061_create_wod_blocos_table.sql"
    "062_create_wod_variacoes_table.sql"
    "063_create_wod_resultados_table.sql"
)

# Executar cada migration
for migration in "${migrations[@]}"; do
    migration_file="$MIGRATIONS_DIR/$migration"
    
    if [ ! -f "$migration_file" ]; then
        echo "❌ Arquivo não encontrado: $migration_file"
        continue
    fi
    
    echo "▶ Executando: $migration"
    
    if docker exec -i "$CONTAINER_NAME" mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$migration_file"; then
        echo "✅ $migration concluído com sucesso"
    else
        echo "❌ Erro ao executar $migration"
        exit 1
    fi
    
    echo ""
done

echo "=================================================="
echo "🎉 Todas as migrations foram executadas!"
echo "=================================================="
echo ""

# Verificar tabelas criadas
echo "📋 Verificando tabelas criadas..."
docker exec "$CONTAINER_NAME" mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SHOW TABLES LIKE 'wod%';"

echo ""
echo "✅ As tabelas estão prontas!"
echo ""
echo "Próximos passos:"
echo "1. Teste o endpoint com: curl -X POST http://localhost:8080/admin/wods/completo ..."
echo "2. Ou use: ./test_wod_completo.sh"
echo ""
