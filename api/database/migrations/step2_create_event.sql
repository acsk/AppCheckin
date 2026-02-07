-- Passo 2: Criar evento que atualiza status automaticamente
DELIMITER $$

CREATE EVENT atualizar_matriculas_vencidas
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_DATE + INTERVAL 1 DAY + INTERVAL 1 MINUTE
COMMENT 'Atualiza status de matrículas para vencida quando proxima_data_vencimento expirar'
DO
BEGIN
    UPDATE matriculas
    SET status_id = 2,
        updated_at = NOW()
    WHERE status_id = 1
    AND proxima_data_vencimento IS NOT NULL
    AND proxima_data_vencimento < CURDATE();
END$$

DELIMITER ;
