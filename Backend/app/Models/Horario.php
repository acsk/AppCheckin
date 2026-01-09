<?php

namespace App\Models;

use PDO;
use DateTime;

class Horario
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getByDiaId(int $diaId): array
    {
        $stmt = $this->db->prepare(
            "SELECT h.*
             FROM horarios h
             WHERE h.dia_id = :dia_id AND h.ativo = 1
             ORDER BY h.horario_inicio ASC"
        );
        $stmt->execute(['dia_id' => $diaId]);
        
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT h.*, 
                    d.data,
                    (SELECT COUNT(*) FROM checkins WHERE horario_id = h.id) as alunos_registrados,
                    (h.limite_alunos - (SELECT COUNT(*) FROM checkins WHERE horario_id = h.id)) as vagas_disponiveis
             FROM horarios h
             INNER JOIN dias d ON d.id = h.dia_id
             WHERE h.id = :id"
        );
        $stmt->execute(['id' => $id]);
        $horario = $stmt->fetch();
        
        return $horario ?: null;
    }

    /**
     * Buscar horário por dia_id e horario_inicio/horario_fim
     * Aceita formatos: HH:MM ou HH:MM:SS
     */
    public function findByDiaAndHorario(int $diaId, string $horarioInicio, string $horarioFim): ?array
    {
        // Normalizar para HH:MM:SS se estiver em HH:MM
        $horarioInicio = $this->normalizarHora($horarioInicio);
        $horarioFim = $this->normalizarHora($horarioFim);
        
        $stmt = $this->db->prepare(
            "SELECT h.*, 
                    d.data
             FROM horarios h
             INNER JOIN dias d ON d.id = h.dia_id
             WHERE h.dia_id = :dia_id AND h.horario_inicio = :horario_inicio AND h.horario_fim = :horario_fim"
        );
        $stmt->execute([
            'dia_id' => $diaId,
            'horario_inicio' => $horarioInicio,
            'horario_fim' => $horarioFim
        ]);
        $horario = $stmt->fetch();
        
        return $horario ?: null;
    }

    /**
     * Normalizar hora para formato HH:MM:SS
     * Aceita: 06:00 ou 06:00:00
     */
    private function normalizarHora(string $hora): string
    {
        $partes = explode(':', $hora);
        if (count($partes) === 2) {
            // Adicionar :00 se estiver em formato HH:MM
            return $hora . ':00';
        }
        return $hora;
    }

    public function temVagasDisponiveis(int $id): bool
    {
        $horario = $this->findById($id);
        
        if (!$horario) {
            return false;
        }

        return $horario['vagas_disponiveis'] > 0;
    }

    /**
     * Verifica se o check-in pode ser feito dentro da tolerância
     * @param int $id ID do horário
     * @return array ['permitido' => bool, 'motivo' => string]
     */
    public function podeRealizarCheckin(int $id): array
    {
        $horario = $this->findById($id);
        
        if (!$horario) {
            return ['permitido' => false, 'motivo' => 'Horário não encontrado'];
        }

        if (!$horario['ativo']) {
            return ['permitido' => false, 'motivo' => 'Horário inativo'];
        }

        // Verificar se há vagas disponíveis
        if ($horario['vagas_disponiveis'] <= 0) {
            return ['permitido' => false, 'motivo' => 'Turma lotada'];
        }

        // Verificar se está dentro do prazo (tolerância antes E depois do início)
        $agora = new DateTime();
        $dataHorarioInicio = new DateTime($horario['data'] . ' ' . $horario['horario_inicio']);
        
        // Permitir check-in X minutos ANTES do início
        $toleranciaAntes = $horario['tolerancia_antes_minutos'] ?? $horario['tolerancia_minutos'];
        $dataInicioCheckin = clone $dataHorarioInicio;
        $dataInicioCheckin->modify("-{$toleranciaAntes} minutes");
        
        // Permitir check-in X minutos APÓS o início
        $toleranciaDepois = $horario['tolerancia_minutos'];
        $dataLimiteCheckin = clone $dataHorarioInicio;
        $dataLimiteCheckin->modify("+{$toleranciaDepois} minutes");

        // Não pode fazer check-in antes da tolerância inicial
        if ($agora < $dataInicioCheckin) {
            $horasAntes = floor($toleranciaAntes / 60);
            $minutosAntes = $toleranciaAntes % 60;
            $tempoTexto = $horasAntes > 0 ? "{$horasAntes}h" . ($minutosAntes > 0 ? "{$minutosAntes}min" : "") : "{$minutosAntes} minutos";
            
            return [
                'permitido' => false, 
                'motivo' => "Check-in só pode ser feito a partir de {$tempoTexto} antes do início da aula"
            ];
        }

        // Não pode fazer check-in após a tolerância final
        if ($agora > $dataLimiteCheckin) {
            return [
                'permitido' => false, 
                'motivo' => "Check-in não permitido. Prazo limite: {$toleranciaDepois} minutos após o início"
            ];
        }

        return ['permitido' => true, 'motivo' => ''];
    }

    /**
     * Lista todos os horários com estatísticas de check-ins
     * @return array
     */
    public function getAllWithStats(): array
    {
        $stmt = $this->db->prepare(
            "SELECT h.*, 
                    d.data,
                    d.ativo as dia_ativo,
                    (SELECT COUNT(*) FROM checkins WHERE horario_id = h.id) as alunos_registrados,
                    (h.limite_alunos - (SELECT COUNT(*) FROM checkins WHERE horario_id = h.id)) as vagas_disponiveis
             FROM horarios h
             INNER JOIN dias d ON d.id = h.dia_id
             WHERE d.ativo = 1
             ORDER BY d.data ASC, h.hora ASC"
        );
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Lista alunos que fizeram check-in em um horário específico
     * @param int $id ID do horário
     * @return array
     */
    public function getAlunosByHorarioId(int $id): array
    {
        $stmt = $this->db->prepare(
            "SELECT u.id as usuario_id, u.nome, u.email, c.id as checkin_id, c.data_checkin, c.created_at
             FROM checkins c
             INNER JOIN usuarios u ON u.id = c.usuario_id
             WHERE c.horario_id = :horario_id
             ORDER BY c.data_checkin ASC"
        );
        $stmt->execute(['horario_id' => $id]);
        
        return $stmt->fetchAll();
    }

    /**
     * Retorna o ID da turma em que o usuário está registrado em uma data específica
     * @param int $userId ID do usuário
     * @param string $data Data no formato Y-m-d
     * @return int|null ID da turma ou null se não estiver registrado
     */
    public function getTurmaRegistradaHoje(int $userId, string $data): ?int
    {
        $stmt = $this->db->prepare(
            "SELECT h.id
             FROM checkins c
             INNER JOIN horarios h ON h.id = c.horario_id
             INNER JOIN dias d ON d.id = h.dia_id
             WHERE c.usuario_id = :usuario_id AND d.data = :data
             LIMIT 1"
        );
        $stmt->execute([
            'usuario_id' => $userId,
            'data' => $data
        ]);
        
        $result = $stmt->fetch();
        return $result ? (int)$result['id'] : null;
    }
}
