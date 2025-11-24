-- Seed com dados de teste completos: alunos e check-ins

-- Limpar dados anteriores
DELETE FROM checkins;
DELETE FROM usuarios WHERE email LIKE '%@teste.com' OR email LIKE '%@exemplo.com';

-- Criar 20 alunos de teste
INSERT INTO usuarios (nome, email, senha_hash) VALUES
    ('João Silva', 'joao.silva@teste.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
    ('Maria Santos', 'maria.santos@teste.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
    ('Pedro Oliveira', 'pedro.oliveira@teste.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
    ('Ana Costa', 'ana.costa@teste.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
    ('Lucas Almeida', 'lucas.almeida@teste.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
    ('Juliana Pereira', 'juliana.pereira@teste.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
    ('Carlos Rodrigues', 'carlos.rodrigues@teste.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
    ('Fernanda Lima', 'fernanda.lima@teste.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
    ('Rafael Martins', 'rafael.martins@teste.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
    ('Camila Souza', 'camila.souza@teste.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
    ('Bruno Carvalho', 'bruno.carvalho@teste.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
    ('Larissa Ferreira', 'larissa.ferreira@teste.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
    ('Gabriel Rocha', 'gabriel.rocha@teste.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
    ('Patricia Mendes', 'patricia.mendes@teste.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
    ('Rodrigo Barbosa', 'rodrigo.barbosa@teste.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
    ('Amanda Freitas', 'amanda.freitas@teste.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
    ('Thiago Ribeiro', 'thiago.ribeiro@teste.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
    ('Beatriz Cardoso', 'beatriz.cardoso@teste.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
    ('Marcelo Dias', 'marcelo.dias@teste.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
    ('Vanessa Pinto', 'vanessa.pinto@teste.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Check-ins para as turmas de HOJE (2025-11-23)
-- Turma 06h (ID 253) - 8 alunos
INSERT INTO checkins (usuario_id, horario_id, data_checkin) 
SELECT u.id, 253, '2025-11-23 06:02:00' FROM usuarios u WHERE u.email = 'joao.silva@teste.com'
UNION ALL
SELECT u.id, 253, '2025-11-23 06:03:30' FROM usuarios u WHERE u.email = 'maria.santos@teste.com'
UNION ALL
SELECT u.id, 253, '2025-11-23 06:04:15' FROM usuarios u WHERE u.email = 'pedro.oliveira@teste.com'
UNION ALL
SELECT u.id, 253, '2025-11-23 06:05:00' FROM usuarios u WHERE u.email = 'ana.costa@teste.com'
UNION ALL
SELECT u.id, 253, '2025-11-23 06:06:20' FROM usuarios u WHERE u.email = 'lucas.almeida@teste.com'
UNION ALL
SELECT u.id, 253, '2025-11-23 06:07:10' FROM usuarios u WHERE u.email = 'juliana.pereira@teste.com'
UNION ALL
SELECT u.id, 253, '2025-11-23 06:08:45' FROM usuarios u WHERE u.email = 'carlos.rodrigues@teste.com'
UNION ALL
SELECT u.id, 253, '2025-11-23 06:09:30' FROM usuarios u WHERE u.email = 'fernanda.lima@teste.com';

-- Turma 07h (ID 254) - 12 alunos
INSERT INTO checkins (usuario_id, horario_id, data_checkin) 
SELECT u.id, 254, '2025-11-23 07:01:00' FROM usuarios u WHERE u.email = 'rafael.martins@teste.com'
UNION ALL
SELECT u.id, 254, '2025-11-23 07:02:15' FROM usuarios u WHERE u.email = 'camila.souza@teste.com'
UNION ALL
SELECT u.id, 254, '2025-11-23 07:03:30' FROM usuarios u WHERE u.email = 'bruno.carvalho@teste.com'
UNION ALL
SELECT u.id, 254, '2025-11-23 07:04:00' FROM usuarios u WHERE u.email = 'larissa.ferreira@teste.com'
UNION ALL
SELECT u.id, 254, '2025-11-23 07:05:20' FROM usuarios u WHERE u.email = 'gabriel.rocha@teste.com'
UNION ALL
SELECT u.id, 254, '2025-11-23 07:06:10' FROM usuarios u WHERE u.email = 'patricia.mendes@teste.com'
UNION ALL
SELECT u.id, 254, '2025-11-23 07:07:00' FROM usuarios u WHERE u.email = 'rodrigo.barbosa@teste.com'
UNION ALL
SELECT u.id, 254, '2025-11-23 07:08:15' FROM usuarios u WHERE u.email = 'amanda.freitas@teste.com'
UNION ALL
SELECT u.id, 254, '2025-11-23 07:09:00' FROM usuarios u WHERE u.email = 'thiago.ribeiro@teste.com'
UNION ALL
SELECT u.id, 254, '2025-11-23 07:09:45' FROM usuarios u WHERE u.email = 'beatriz.cardoso@teste.com'
UNION ALL
SELECT u.id, 254, '2025-11-23 07:10:00' FROM usuarios u WHERE u.email = 'marcelo.dias@teste.com'
UNION ALL
SELECT u.id, 254, '2025-11-23 07:10:30' FROM usuarios u WHERE u.email = 'vanessa.pinto@teste.com';

-- Turma 08h (ID 255) - 5 alunos
INSERT INTO checkins (usuario_id, horario_id, data_checkin) 
SELECT u.id, 255, '2025-11-23 08:01:00' FROM usuarios u WHERE u.email = 'joao.silva@teste.com'
UNION ALL
SELECT u.id, 255, '2025-11-23 08:03:00' FROM usuarios u WHERE u.email = 'lucas.almeida@teste.com'
UNION ALL
SELECT u.id, 255, '2025-11-23 08:05:00' FROM usuarios u WHERE u.email = 'rafael.martins@teste.com'
UNION ALL
SELECT u.id, 255, '2025-11-23 08:07:00' FROM usuarios u WHERE u.email = 'gabriel.rocha@teste.com'
UNION ALL
SELECT u.id, 255, '2025-11-23 08:09:00' FROM usuarios u WHERE u.email = 'thiago.ribeiro@teste.com';

-- Turma 16h (ID 256) - 15 alunos
INSERT INTO checkins (usuario_id, horario_id, data_checkin) 
SELECT u.id, 256, '2025-11-23 16:00:30' FROM usuarios u WHERE u.email = 'maria.santos@teste.com'
UNION ALL
SELECT u.id, 256, '2025-11-23 16:01:00' FROM usuarios u WHERE u.email = 'pedro.oliveira@teste.com'
UNION ALL
SELECT u.id, 256, '2025-11-23 16:02:00' FROM usuarios u WHERE u.email = 'ana.costa@teste.com'
UNION ALL
SELECT u.id, 256, '2025-11-23 16:03:00' FROM usuarios u WHERE u.email = 'juliana.pereira@teste.com'
UNION ALL
SELECT u.id, 256, '2025-11-23 16:04:00' FROM usuarios u WHERE u.email = 'carlos.rodrigues@teste.com'
UNION ALL
SELECT u.id, 256, '2025-11-23 16:05:00' FROM usuarios u WHERE u.email = 'fernanda.lima@teste.com'
UNION ALL
SELECT u.id, 256, '2025-11-23 16:06:00' FROM usuarios u WHERE u.email = 'camila.souza@teste.com'
UNION ALL
SELECT u.id, 256, '2025-11-23 16:07:00' FROM usuarios u WHERE u.email = 'bruno.carvalho@teste.com'
UNION ALL
SELECT u.id, 256, '2025-11-23 16:08:00' FROM usuarios u WHERE u.email = 'larissa.ferreira@teste.com'
UNION ALL
SELECT u.id, 256, '2025-11-23 16:09:00' FROM usuarios u WHERE u.email = 'patricia.mendes@teste.com'
UNION ALL
SELECT u.id, 256, '2025-11-23 16:09:30' FROM usuarios u WHERE u.email = 'rodrigo.barbosa@teste.com'
UNION ALL
SELECT u.id, 256, '2025-11-23 16:10:00' FROM usuarios u WHERE u.email = 'amanda.freitas@teste.com'
UNION ALL
SELECT u.id, 256, '2025-11-23 16:10:15' FROM usuarios u WHERE u.email = 'beatriz.cardoso@teste.com'
UNION ALL
SELECT u.id, 256, '2025-11-23 16:10:30' FROM usuarios u WHERE u.email = 'marcelo.dias@teste.com'
UNION ALL
SELECT u.id, 256, '2025-11-23 16:10:45' FROM usuarios u WHERE u.email = 'vanessa.pinto@teste.com';

-- Turma 17h (ID 257) - 10 alunos
INSERT INTO checkins (usuario_id, horario_id, data_checkin) 
SELECT u.id, 257, '2025-11-23 17:00:00' FROM usuarios u WHERE u.email = 'joao.silva@teste.com'
UNION ALL
SELECT u.id, 257, '2025-11-23 17:01:00' FROM usuarios u WHERE u.email = 'lucas.almeida@teste.com'
UNION ALL
SELECT u.id, 257, '2025-11-23 17:02:00' FROM usuarios u WHERE u.email = 'rafael.martins@teste.com'
UNION ALL
SELECT u.id, 257, '2025-11-23 17:03:00' FROM usuarios u WHERE u.email = 'gabriel.rocha@teste.com'
UNION ALL
SELECT u.id, 257, '2025-11-23 17:04:00' FROM usuarios u WHERE u.email = 'thiago.ribeiro@teste.com'
UNION ALL
SELECT u.id, 257, '2025-11-23 17:05:00' FROM usuarios u WHERE u.email = 'maria.santos@teste.com'
UNION ALL
SELECT u.id, 257, '2025-11-23 17:06:00' FROM usuarios u WHERE u.email = 'ana.costa@teste.com'
UNION ALL
SELECT u.id, 257, '2025-11-23 17:07:00' FROM usuarios u WHERE u.email = 'juliana.pereira@teste.com'
UNION ALL
SELECT u.id, 257, '2025-11-23 17:08:00' FROM usuarios u WHERE u.email = 'camila.souza@teste.com'
UNION ALL
SELECT u.id, 257, '2025-11-23 17:09:00' FROM usuarios u WHERE u.email = 'larissa.ferreira@teste.com';

-- Turma 18h (ID 258) - 18 alunos
INSERT INTO checkins (usuario_id, horario_id, data_checkin) 
SELECT u.id, 258, '2025-11-23 18:00:00' FROM usuarios u WHERE u.email = 'pedro.oliveira@teste.com'
UNION ALL
SELECT u.id, 258, '2025-11-23 18:01:00' FROM usuarios u WHERE u.email = 'carlos.rodrigues@teste.com'
UNION ALL
SELECT u.id, 258, '2025-11-23 18:02:00' FROM usuarios u WHERE u.email = 'fernanda.lima@teste.com'
UNION ALL
SELECT u.id, 258, '2025-11-23 18:03:00' FROM usuarios u WHERE u.email = 'bruno.carvalho@teste.com'
UNION ALL
SELECT u.id, 258, '2025-11-23 18:04:00' FROM usuarios u WHERE u.email = 'patricia.mendes@teste.com'
UNION ALL
SELECT u.id, 258, '2025-11-23 18:05:00' FROM usuarios u WHERE u.email = 'rodrigo.barbosa@teste.com'
UNION ALL
SELECT u.id, 258, '2025-11-23 18:06:00' FROM usuarios u WHERE u.email = 'amanda.freitas@teste.com'
UNION ALL
SELECT u.id, 258, '2025-11-23 18:07:00' FROM usuarios u WHERE u.email = 'beatriz.cardoso@teste.com'
UNION ALL
SELECT u.id, 258, '2025-11-23 18:08:00' FROM usuarios u WHERE u.email = 'marcelo.dias@teste.com'
UNION ALL
SELECT u.id, 258, '2025-11-23 18:09:00' FROM usuarios u WHERE u.email = 'vanessa.pinto@teste.com'
UNION ALL
SELECT u.id, 258, '2025-11-23 18:09:15' FROM usuarios u WHERE u.email = 'joao.silva@teste.com'
UNION ALL
SELECT u.id, 258, '2025-11-23 18:09:30' FROM usuarios u WHERE u.email = 'lucas.almeida@teste.com'
UNION ALL
SELECT u.id, 258, '2025-11-23 18:09:45' FROM usuarios u WHERE u.email = 'rafael.martins@teste.com'
UNION ALL
SELECT u.id, 258, '2025-11-23 18:10:00' FROM usuarios u WHERE u.email = 'gabriel.rocha@teste.com'
UNION ALL
SELECT u.id, 258, '2025-11-23 18:10:10' FROM usuarios u WHERE u.email = 'thiago.ribeiro@teste.com'
UNION ALL
SELECT u.id, 258, '2025-11-23 18:10:20' FROM usuarios u WHERE u.email = 'maria.santos@teste.com'
UNION ALL
SELECT u.id, 258, '2025-11-23 18:10:30' FROM usuarios u WHERE u.email = 'ana.costa@teste.com'
UNION ALL
SELECT u.id, 258, '2025-11-23 18:10:40' FROM usuarios u WHERE u.email = 'juliana.pereira@teste.com';

-- Turma 19h (ID 259) - 6 alunos
INSERT INTO checkins (usuario_id, horario_id, data_checkin) 
SELECT u.id, 259, '2025-11-23 19:00:00' FROM usuarios u WHERE u.email = 'camila.souza@teste.com'
UNION ALL
SELECT u.id, 259, '2025-11-23 19:02:00' FROM usuarios u WHERE u.email = 'larissa.ferreira@teste.com'
UNION ALL
SELECT u.id, 259, '2025-11-23 19:04:00' FROM usuarios u WHERE u.email = 'bruno.carvalho@teste.com'
UNION ALL
SELECT u.id, 259, '2025-11-23 19:06:00' FROM usuarios u WHERE u.email = 'patricia.mendes@teste.com'
UNION ALL
SELECT u.id, 259, '2025-11-23 19:08:00' FROM usuarios u WHERE u.email = 'rodrigo.barbosa@teste.com'
UNION ALL
SELECT u.id, 259, '2025-11-23 19:10:00' FROM usuarios u WHERE u.email = 'amanda.freitas@teste.com';
