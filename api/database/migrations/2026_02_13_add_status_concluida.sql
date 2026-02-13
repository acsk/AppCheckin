-- Adiciona status "concluida" para matrículas diárias
INSERT INTO status_matricula (codigo, nome, descricao, cor, icone, ordem, permite_checkin, ativo, dias_tolerancia, automatico)
SELECT 'concluida', 'Concluída', 'Matrícula concluída após presença confirmada', '#22c55e', 'check-circle', 5, 0, 1, NULL, 0
WHERE NOT EXISTS (SELECT 1 FROM status_matricula WHERE codigo = 'concluida');

