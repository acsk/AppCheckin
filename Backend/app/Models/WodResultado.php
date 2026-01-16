<?php

namespace App\Models;

use PDO;

class WodResultado
{
    private PDO $db;

    public const TIPO_TIME = 'time';
    public const TIPO_REPS = 'reps';
    public const TIPO_WEIGHT = 'weight';
    public const TIPO_ROUNDS_REPS = 'rounds_reps';
    public const TIPO_DISTANCE = 'distance';
    public const TIPO_CALORIES = 'calories';
    public const TIPO_POINTS = 'points';

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Registrar um resultado
     */
    public function create(array $data): ?int
    {
        try {
            $tipoScore = $data['tipo_score'] ?? null;

            $peso = ($tipoScore === self::TIPO_WEIGHT) ? ($data['valor_num'] ?? null) : null;
            $repeticoes = ($tipoScore === self::TIPO_REPS || $tipoScore === self::TIPO_ROUNDS_REPS) ? ($data['valor_num'] ?? null) : null;
            $tempoTotal = ($tipoScore === self::TIPO_TIME) ? ($data['valor_texto'] ?? null) : null;

            $resultadoTexto = null;
            if ($tipoScore === self::TIPO_ROUNDS_REPS || $tipoScore === self::TIPO_POINTS || $tipoScore === self::TIPO_DISTANCE || $tipoScore === self::TIPO_CALORIES) {
                $resultadoTexto = $data['valor_texto'] ?? ($data['valor_num'] ?? null);
            } elseif ($tipoScore === null) {
                $resultadoTexto = $data['valor_texto'] ?? null;
            }

            $stmt = $this->db->prepare(
                "INSERT INTO wod_resultados 
                    (tenant_id, wod_id, usuario_id, variacao_id, resultado, tempo_total, repeticoes, peso, nota, created_at, updated_at)
                 VALUES 
                    (:tenant_id, :wod_id, :usuario_id, :variacao_id, :resultado, :tempo_total, :repeticoes, :peso, :nota, NOW(), NOW())"
            );

            $stmt->execute([
                'tenant_id' => $data['tenant_id'],
                'wod_id' => $data['wod_id'],
                'usuario_id' => $data['usuario_id'],
                'variacao_id' => $data['variacao_id'] ?? null,
                'resultado' => $resultadoTexto,
                'tempo_total' => $tempoTotal,
                'repeticoes' => $repeticoes,
                'peso' => $peso,
                'nota' => $data['observacao'] ?? null,
            ]);

            return $this->db->lastInsertId() ? (int)$this->db->lastInsertId() : null;
        } catch (\Exception $e) {
            error_log('Erro ao registrar resultado de WOD: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Listar resultados de um WOD (Leaderboard)
     */
    public function listByWod(int $wodId, int $tenantId): array
    {
        $stmt = $this->db->prepare(
            "SELECT wr.*, u.nome as usuario_nome, wv.nome as variacao_nome
             FROM wod_resultados wr
             LEFT JOIN usuarios u ON wr.usuario_id = u.id
             LEFT JOIN wod_variacoes wv ON wr.variacao_id = wv.id
             WHERE wr.wod_id = :wod_id AND wr.tenant_id = :tenant_id
             ORDER BY wr.peso IS NULL, CAST(wr.peso AS DECIMAL(10,2)) DESC, wr.created_at DESC"
        );

        $stmt->execute([
            'wod_id' => $wodId,
            'tenant_id' => $tenantId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar resultado de um usuário em um WOD
     */
    public function findByUsuarioWod(int $usuarioId, int $wodId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT wr.*, u.nome as usuario_nome, wv.nome as variacao_nome
             FROM wod_resultados wr
             LEFT JOIN usuarios u ON wr.usuario_id = u.id
             LEFT JOIN wod_variacoes wv ON wr.variacao_id = wv.id
             WHERE wr.usuario_id = :usuario_id AND wr.wod_id = :wod_id"
        );

        $stmt->execute([
            'usuario_id' => $usuarioId,
            'wod_id' => $wodId,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Buscar resultado por ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT wr.*, u.nome as usuario_nome, wv.nome as variacao_nome
             FROM wod_resultados wr
             LEFT JOIN usuarios u ON wr.usuario_id = u.id
             LEFT JOIN wod_variacoes wv ON wr.variacao_id = wv.id
             WHERE wr.id = :id"
        );

        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Atualizar resultado
     */
    public function update(int $id, array $data): bool
    {
        try {
            $updateFields = [];
            $params = ['id' => $id];

            if (isset($data['resultado'])) {
                $updateFields[] = "resultado = :resultado";
                $params['resultado'] = $data['resultado'];
            }

            if (isset($data['tempo_total'])) {
                $updateFields[] = "tempo_total = :tempo_total";
                $params['tempo_total'] = $data['tempo_total'];
            }

            if (isset($data['repeticoes'])) {
                $updateFields[] = "repeticoes = :repeticoes";
                $params['repeticoes'] = $data['repeticoes'];
            }

            if (isset($data['peso'])) {
                $updateFields[] = "peso = :peso";
                $params['peso'] = $data['peso'];
            }

            if (isset($data['observacao'])) {
                $updateFields[] = "nota = :nota";
                $params['nota'] = $data['observacao'];
            }

            if (isset($data['valor_num']) && !isset($params['peso']) && !isset($params['repeticoes'])) {
                $updateFields[] = "peso = :valor_num";
                $params['valor_num'] = $data['valor_num'];
            }

            if (isset($data['valor_texto']) && !isset($params['resultado'])) {
                $updateFields[] = "resultado = :valor_texto";
                $params['valor_texto'] = $data['valor_texto'];
            }

            if (isset($data['variacao_id'])) {
                $updateFields[] = "variacao_id = :variacao_id";
                $params['variacao_id'] = $data['variacao_id'];
            }

            if (empty($updateFields)) {
                return false;
            }

                $updateFields[] = "updated_at = NOW()";
            $query = "UPDATE wod_resultados SET " . implode(", ", $updateFields) . " WHERE id = :id";

            $stmt = $this->db->prepare($query);
            return $stmt->execute($params);
        } catch (\Exception $e) {
            error_log('Erro ao atualizar resultado: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletar resultado
     */
    public function delete(int $id): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM wod_resultados WHERE id = :id");
            return $stmt->execute(['id' => $id]);
        } catch (\Exception $e) {
            error_log('Erro ao deletar resultado: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obter label do tipo de score
     */
    public static function getTipoLabel(string $tipo): string
    {
        return match($tipo) {
            self::TIPO_TIME => 'Tempo',
            self::TIPO_REPS => 'Repetições',
            self::TIPO_WEIGHT => 'Peso',
            self::TIPO_ROUNDS_REPS => 'Rounds + Reps',
            self::TIPO_DISTANCE => 'Distância',
            self::TIPO_CALORIES => 'Calorias',
            self::TIPO_POINTS => 'Pontos',
            default => 'Desconhecido',
        };
    }
}
