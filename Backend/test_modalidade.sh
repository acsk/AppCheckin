#!/bin/bash

echo "Verificando estrutura da tabela wods..."
docker exec appcheckin_mysql mysql -uroot -proot appcheckin -e "SHOW COLUMNS FROM wods" 2>&1 | tail -20

echo ""
echo "Testando criação de WOD com modalidade_id..."
docker exec appcheckin_php php test_modalidade_wod.php 2>&1
