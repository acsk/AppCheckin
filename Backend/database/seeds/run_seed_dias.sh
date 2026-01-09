#!/bin/bash

# Script para executar o seed de dias do ano

set -e

DB_HOST=${DB_HOST:-localhost}
DB_USER=${DB_USER:-root}
DB_PASSWORD=${DB_PASSWORD:-}
DB_NAME=${DB_NAME:-app_checkin}

echo "🗂️  Executando seed de dias do ano..."
echo "📅 Banco de dados: $DB_NAME"
echo "---"

# Executar seed
if [ -z "$DB_PASSWORD" ]; then
    mysql -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" < database/seeds/seed_dias_ano.sql
else
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < database/seeds/seed_dias_ano.sql
fi

echo "---"
echo "✅ Seed executado com sucesso!"
echo ""
echo "Para usar o job de atualização anual, execute:"
echo "   php jobs/gerar_dias_anuais.php"
echo ""
echo "Para ver o status dos dias cadastrados:"
echo "   php jobs/gerar_dias_anuais.php --status"
