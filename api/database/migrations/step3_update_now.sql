-- Passo 3: Atualizar matrículas já vencidas (execução manual)
UPDATE matriculas
SET status_id = 2,
    updated_at = NOW()
WHERE status_id = 1
AND proxima_data_vencimento IS NOT NULL
AND proxima_data_vencimento < CURDATE();
