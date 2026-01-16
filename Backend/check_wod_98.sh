#!/bin/bash
set -e

# Função para executar query MySQL
query() {
    docker exec appcheckin_mysql mysql -uroot -proot -N -B appcheckin -e "$1" 2>/dev/null
}

echo "=== Verificando WOD ID 98 ==="
query "SELECT id, tenant_id, modalidade_id, data, titulo, status FROM wods WHERE id = 98"

echo ""
echo "=== Estrutura da tabela wods ==="
query "SHOW COLUMNS FROM wods"

echo ""
echo "=== Últimos 3 WODs criados ==="
query "SELECT id, tenant_id, modalidade_id, titulo, data FROM wods ORDER BY id DESC LIMIT 3"
