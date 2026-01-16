#!/bin/bash

# ============================================
# Script para executar SEED de WODs no Docker
# ============================================

CONTAINER_NAME="appcheckin_mysql"
DB_USER="root"
DB_PASS="root"
DB_NAME="appcheckin"
SEED_FILE="/Users/andrecabral/Projetos/AppCheckin/Backend/database/seeds/seed_wods.sql"

echo "🌱 Executando SEED para popular tabelas de WOD..."
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

# Executar o seed
echo "▶ Carregando dados de exemplo..."

if docker exec -i "$CONTAINER_NAME" mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$SEED_FILE"; then
    echo ""
    echo "=================================================="
    echo "✅ SEED executado com sucesso!"
    echo "=================================================="
    echo ""
    echo "📊 Dados adicionados:"
    docker exec "$CONTAINER_NAME" mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
    SELECT 'WODs:' as tipo, COUNT(*) as total FROM wods
    UNION ALL
    SELECT 'Blocos:', COUNT(*) FROM wod_blocos
    UNION ALL
    SELECT 'Variações:', COUNT(*) FROM wod_variacoes
    UNION ALL
    SELECT 'Resultados:', COUNT(*) FROM wod_resultados;
    "
    
    echo ""
    echo "📋 Primeiros 5 WODs:"
    docker exec "$CONTAINER_NAME" mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT id, data, titulo, status FROM wods LIMIT 5;"
    
    echo ""
    echo "🚀 Próximos passos:"
    echo "1. Teste com: curl -X GET http://localhost:8080/admin/wods"
    echo "2. Ver detalhes de um WOD: curl -X GET http://localhost:8080/admin/wods/1"
    echo ""
else
    echo "❌ Erro ao executar SEED"
    exit 1
fi
