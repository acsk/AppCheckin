#!/bin/bash
# Executa migrate_recordes_pessoais.php (DDL + seed de definições padrão).
# Uso: ./run_recordes_migration.sh
# Docker: docker exec appcheckin_php php /var/www/html/database/migrate_recordes_pessoais.php

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
API_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
MIGRATION="$API_DIR/migrate_recordes_pessoais.php"

if [ ! -f "$MIGRATION" ]; then
  echo "❌ Arquivo não encontrado: $MIGRATION"
  exit 1
fi

echo "======================================"
echo "🚀 Migração: Recordes pessoais"
echo "======================================"
echo ""

cd "$API_DIR/.."
php database/migrate_recordes_pessoais.php

echo ""
echo "======================================"
echo "✅ Concluído"
echo "======================================"
