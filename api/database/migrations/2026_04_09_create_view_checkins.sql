-- View: vw_checkins
-- Facilita consultas de check-ins com dados desnormalizados de turma, dia, modalidade e aluno.
-- Data da aula sempre via dias.data (fonte canônica — nunca usar created_at para isso).

DROP VIEW IF EXISTS vw_checkins;

CREATE VIEW vw_checkins AS
SELECT
    c.id,
    c.tenant_id,
    c.turma_id,
    c.presente,
    c.registrado_por_admin,
    c.admin_id,
    c.created_at,

    -- Aluno
    a.id          AS aluno_id,
    a.nome        AS aluno_nome,

    -- Data canônica da aula
    DATE(d.data)  AS data_aula,
    YEAR(d.data)  AS ano,
    MONTH(d.data) AS mes,
    d.id          AS dia_id,

    -- Turma
    t.horario_inicio,
    t.horario_fim,

    -- Modalidade
    m.id          AS modalidade_id,
    m.nome        AS modalidade
FROM checkins c
INNER JOIN turmas      t ON t.id = c.turma_id
INNER JOIN dias        d ON d.id = t.dia_id
INNER JOIN modalidades m ON m.id = t.modalidade_id
INNER JOIN alunos      a ON a.id = c.aluno_id;
