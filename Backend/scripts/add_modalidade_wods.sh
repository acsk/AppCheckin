#!/bin/bash

echo "Executando migration para adicionar modalidade_id..."

# Tentar adicionar coluna
docker exec appcheckin_mysql mysql -uroot -proot appcheckin -e "ALTER TABLE wods ADD COLUMN modalidade_id INT NULL AFTER tenant_id;" 2>&1 | grep -v "Duplicate column"

# Tentar adicionar FK
docker exec appcheckin_mysql mysql -uroot -proot appcheckin -e "ALTER TABLE wods ADD CONSTRAINT fk_wods_modalidade FOREIGN KEY (modalidade_id) REFERENCES modalidades(id) ON DELETE SET NULL;" 2>&1 | grep -v "Duplicate"

# Tentar adicionar índice
docker exec appcheckin_mysql mysql -uroot -proot appcheckin -e "ALTER TABLE wods ADD INDEX idx_wods_modalidade (modalidade_id);" 2>&1 | grep -v "Duplicate"

echo ""
echo "✓ Migration concluída!"
echo ""
echo "Verificando estrutura:"
docker exec appcheckin_mysql mysql -uroot -proot appcheckin -e "SHOW COLUMNS FROM wods LIKE 'modalidade%';"

