SET SESSION sql_mode = '';

-- Checkins na turma 70 (16:00) - 4 alunos
INSERT INTO checkins (tenant_id, aluno_id, turma_id, data_checkin, presente) VALUES
(2, 6, 70, '2026-01-30 16:02:00', NULL),
(2, 7, 70, '2026-01-30 16:05:00', NULL),
(2, 8, 70, '2026-01-30 16:08:00', NULL),
(2, 9, 70, '2026-01-30 16:10:00', NULL);

-- Checkins na turma 71 (17:00) - 5 alunos
INSERT INTO checkins (tenant_id, aluno_id, turma_id, data_checkin, presente) VALUES
(2, 10, 71, '2026-01-30 17:01:00', NULL),
(2, 11, 71, '2026-01-30 17:03:00', NULL),
(2, 12, 71, '2026-01-30 17:05:00', NULL),
(2, 13, 71, '2026-01-30 17:07:00', NULL),
(2, 6, 71, '2026-01-30 17:10:00', NULL);

-- Checkins na turma 72 (18:00) - 6 alunos
INSERT INTO checkins (tenant_id, aluno_id, turma_id, data_checkin, presente) VALUES
(2, 7, 72, '2026-01-30 18:02:00', NULL),
(2, 8, 72, '2026-01-30 18:04:00', NULL),
(2, 9, 72, '2026-01-30 18:06:00', NULL),
(2, 10, 72, '2026-01-30 18:08:00', NULL),
(2, 11, 72, '2026-01-30 18:10:00', NULL),
(2, 12, 72, '2026-01-30 18:12:00', NULL);

SELECT 'Checkins criados com sucesso!' as resultado;
