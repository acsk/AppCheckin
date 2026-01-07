#!/bin/bash

# ==========================================
# Script Rápido: Verificar Estado das Migrations
# ==========================================

echo "🔍 Verificando estado das migrations..."
echo ""

# Cores
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

# Variáveis
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-appcheckin}"
DB_USER="${DB_USER:-root}"

# Solicitar senha
echo -n "Senha do MySQL: "
read -s DB_PASS
echo ""
echo ""

# Executar verificação
mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < verificar_estado_migrations.sql

echo ""
echo "=========================================="
echo ""
echo "💡 DICA:"
echo "   Se migrations não foram aplicadas, execute:"
echo "   ./executar_migrations.sh"
echo ""
