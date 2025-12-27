#!/bin/bash

# Script para aplicar migration 016 - Adicionar plano_id aos tenants

echo "=== Aplicando Migration 016 - Adicionar plano_id aos tenants ==="

# Entrar no diretório correto
cd "$(dirname "$0")"

# Aplicar migration
docker exec appcheckin_mysql sh -c "mysql -u root -prootpass appcheckin < /var/lib/mysql-files/016_add_plano_to_tenants.sql"

if [ $? -eq 0 ]; then
    echo "✅ Migration 016 aplicada com sucesso!"
else
    echo "❌ Erro ao aplicar migration 016"
    exit 1
fi

echo ""
echo "=== Estrutura atualizada da tabela tenants ==="
docker exec appcheckin_mysql mysql -u root -prootpass appcheckin -e "DESCRIBE tenants;"

echo ""
echo "=== Migration concluída! ==="
