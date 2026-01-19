#!/bin/bash

# Script para executar migrations de WOD no banco de dados
# Uso: ./run_wod_migrations.sh

# Configurações
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-appcheckin}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-root}"

echo "======================================"
echo "🚀 Executando Migrações de WOD"
echo "======================================"
echo ""

# Função para executar migration
run_migration() {
    local migration_file=$1
    local migration_name=$(basename "$migration_file")
    
    echo "📄 Executando: $migration_name"
    
    mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$migration_file"
    
    if [ $? -eq 0 ]; then
        echo "✅ $migration_name - OK"
    else
        echo "❌ $migration_name - ERRO"
        return 1
    fi
    echo ""
}

# Executar migrations de WOD
echo "📦 Criando tabelas de WOD..."
echo ""

run_migration "060_create_wods_table.sql"
run_migration "061_create_wod_blocos_table.sql"
run_migration "062_create_wod_variacoes_table.sql"
run_migration "063_create_wod_resultados_table.sql"

echo "======================================"
echo "✅ Migrações de WOD Concluídas!"
echo "======================================"
echo ""

# Verificar se as tabelas foram criadas
echo "Verificando tabelas criadas..."
mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SHOW TABLES LIKE 'wod%';"

echo ""
echo "🎉 Pronto para usar!"
