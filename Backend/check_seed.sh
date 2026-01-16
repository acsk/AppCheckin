#!/bin/bash

# Script para verificar dados no banco de dados

echo "🔍 Verificando dados no banco..."
echo ""

echo "=== Usuários ===" 
docker exec appcheckin_mysql mysql -uroot -proot appcheckin -e "SELECT id, nome, email, tenant_id FROM usuarios LIMIT 10;"

echo ""
echo "=== WODs ===" 
docker exec appcheckin_mysql mysql -uroot -proot appcheckin -e "SELECT id, tenant_id, titulo, data FROM wods;"

echo ""
echo "=== Blocos ===" 
docker exec appcheckin_mysql mysql -uroot -proot appcheckin -e "SELECT COUNT(*) as total_blocos FROM wod_blocos;"

echo ""
echo "=== Variações ===" 
docker exec appcheckin_mysql mysql -uroot -proot appcheckin -e "SELECT COUNT(*) as total_variacoes FROM wod_variacoes;"

echo ""
echo "=== Seu tenant_id ===" 
echo "Se você está logado, qual é seu tenant_id?"
echo "Você pode verificar decodificando seu JWT token em jwt.io"
echo ""
echo "Depois, execute este comando para ver WODs do SEU tenant:"
echo "docker exec appcheckin_mysql mysql -uroot -proot appcheckin -e \"SELECT * FROM wods WHERE tenant_id = SEUTENANT_ID;\""
