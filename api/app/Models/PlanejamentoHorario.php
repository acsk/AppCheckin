<?php

namespace App\Models;

use PDO;

class PlanejamentoHorario
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getAll(int $tenantId, bool $apenasAtivos = true): array
    {
        $sql = "SELECT * FROM planejamento_horarios WHERE tenant_id = :tenant_id";
        
        if ($apenasAtivos) {
            $sql .= " AND ativo = 1";
        }
        
        $sql .= " ORDER BY 
            FIELD(dia_semana, 'segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo'),
            horario_inicio";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['tenant_id' => $tenantId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id, int $tenantId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM planejamento_horarios 
             WHERE id = :id AND tenant_id = :tenant_id"
        );
        
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }

    public function create(array $data, int $tenantId): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO planejamento_horarios 
             (tenant_id, titulo, dia_semana, horario_inicio, horario_fim, vagas, data_inicio, data_fim, ativo)
             VALUES (:tenant_id, :titulo, :dia_semana, :horario_inicio, :horario_fim, :vagas, :data_inicio, :data_fim, :ativo)"
        );

        $stmt->execute([
            'tenant_id' => $tenantId,
            'titulo' => $data['titulo'],
            'dia_semana' => $data['dia_semana'],
            'horario_inicio' => $data['horario_inicio'],
            'horario_fim' => $data['horario_fim'],
            'vagas' => $data['vagas'] ?? 10,
            'data_inicio' => $data['data_inicio'],
            'data_fim' => $data['data_fim'] ?? null,
            'ativo' => $data['ativo'] ?? true
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data, int $tenantId): bool
    {
        $fields = [];
        $params = ['id' => $id, 'tenant_id' => $tenantId];

        $allowedFields = ['titulo', 'dia_semana', 'horario_inicio', 'horario_fim', 'vagas', 'data_inicio', 'data_fim', 'ativo'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE planejamento_horarios SET " . implode(', ', $fields) . 
               " WHERE id = :id AND tenant_id = :tenant_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id, int $tenantId): bool
    {
        // Soft delete
        $stmt = $this->db->prepare(
            "UPDATE planejamento_horarios SET ativo = 0 
             WHERE id = :id AND tenant_id = :tenant_id"
        );
        
        return $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
    }

    /**
     * Gera dias e horários baseado no planejamento semanal
     * para um período específico
     */
    public function gerarDiasHorarios(int $planejamentoId, int $tenantId, string $dataInicio, string $dataFim): array
    {
        $planejamento = $this->findById($planejamentoId, $tenantId);
        
        if (!$planejamento) {
            return ['error' => 'Planejamento não encontrado'];
        }

        $diasGerados = [];
        $horariosGerados = [];
        
        // Mapear dia da semana para número (1 = segunda, 7 = domingo)
        $diasSemana = [
            'segunda' => 1,
            'terca' => 2,
            'quarta' => 3,
            'quinta' => 4,
            'sexta' => 5,
            'sabado' => 6,
            'domingo' => 7
        ];
        
        $diaSemanaNum = $diasSemana[$planejamento['dia_semana']];
        
        $inicio = new \DateTime($dataInicio);
        $fim = new \DateTime($dataFim);
        
        // Encontrar o primeiro dia da semana desejado
        while ($inicio->format('N') != $diaSemanaNum && $inicio <= $fim) {
            $inicio->modify('+1 day');
        }
        
        // Criar dias e horários para cada ocorrência
        while ($inicio <= $fim) {
            $dataAtual = $inicio->format('Y-m-d');
            
            // Criar dia se não existir
            $stmtDia = $this->db->prepare(
                "INSERT IGNORE INTO dias (data, tenant_id, ativo) VALUES (:data, :tenant_id, 1)"
            );
            $stmtDia->execute(['data' => $dataAtual, 'tenant_id' => $tenantId]);
            
            // Buscar ID do dia
            $stmtGetDia = $this->db->prepare(
                "SELECT id FROM dias WHERE data = :data AND tenant_id = :tenant_id"
            );
            $stmtGetDia->execute(['data' => $dataAtual, 'tenant_id' => $tenantId]);
            $dia = $stmtGetDia->fetch(PDO::FETCH_ASSOC);
            
            if ($dia) {
                $diasGerados[] = $dataAtual;
                
                // Criar horário
                $stmtHorario = $this->db->prepare(
                    "INSERT INTO horarios (dia_id, hora, vagas, ativo, tenant_id)
                     VALUES (:dia_id, :hora, :vagas, 1, :tenant_id)
                     ON DUPLICATE KEY UPDATE vagas = :vagas"
                );
                
                $stmtHorario->execute([
                    'dia_id' => $dia['id'],
                    'hora' => $planejamento['horario_inicio'],
                    'vagas' => $planejamento['vagas'],
                    'tenant_id' => $tenantId
                ]);
                
                $horariosGerados[] = $dataAtual . ' ' . $planejamento['horario_inicio'];
            }
            
            // Próxima semana
            $inicio->modify('+7 days');
        }
        
        return [
            'dias_gerados' => count($diasGerados),
            'horarios_gerados' => count($horariosGerados),
            'detalhes' => [
                'dias' => $diasGerados,
                'horarios' => $horariosGerados
            ]
        ];
    }
}
