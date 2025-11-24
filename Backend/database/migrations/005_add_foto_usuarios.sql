-- Adiciona campo para armazenar foto em base64
ALTER TABLE usuarios ADD COLUMN foto_base64 LONGTEXT NULL AFTER email;
