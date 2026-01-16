#!/bin/bash
docker exec appcheckin_mysql mysql -uroot -proot appcheckin -e "ALTER TABLE wods ADD COLUMN modalidade_id INT NULL AFTER tenant_id" 2>&1
docker exec appcheckin_mysql mysql -uroot -proot appcheckin -e "ALTER TABLE wods ADD CONSTRAINT fk_wods_modalidade FOREIGN KEY (modalidade_id) REFERENCES modalidades(id) ON DELETE SET NULL" 2>&1
docker exec appcheckin_mysql mysql -uroot -proot appcheckin -e "ALTER TABLE wods ADD INDEX idx_wods_modalidade (modalidade_id)" 2>&1
echo "Migration concluída!"
docker exec appcheckin_mysql mysql -uroot -proot appcheckin -e "SHOW CREATE TABLE wods\G" | grep modalidade
