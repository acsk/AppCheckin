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
            $stmt = $this->db->prepare(
                "INSERT INTO wod_resultados 
                    (tenant_id, wod_id, usuario_id, variacao_id, tipo_score, valor_num, valor_texto, observacao, registrado_por, registrado_em, atualizado_em)
                 VALUES 
                    (:tenant_id, :wod_id, :usuario_id, :variacao_id, :tipo_score, :valor_num, :valor_texto, :observacao, :registrado_por, NOW(), NOW())"
            );

            $stmt->execute([
                'tenant_id' => $data['tenant_id'],
                'wod_id' => $data['wod_id'],
                'usuario_id' => $data['usuario_id'],
                'variacao_id' => $data['variacao_id'] ?? null,
                'tipo_score' => $data['tipo_score'],
                'valor_num' => $data['valor_num'] ?? null,
                'valor_texto' => $data['valor_texto'] ?? null,
                'observacao' => $data['observacao'] ?? null,
                'registrado_por' => $data['registrado_por'] ?? null,
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
             ORDER BY CAST(wr.valor_num AS DECIMAL(10,2)) DESC NULLS LAST, wr.registrado_em DESC"
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

            if (isset($data['valor_num'])) {
                $updateFields[] = "valor_num = :valor_num";
                $params['valor_num'] = $data['valor_num'];
            }

            if (isset($data['valor_texto'])) {
                $updateFields[] = "valor_texto = :valor_texto";
                $params['valor_texto'] = $data['valor_texto'];
            }

            if (isset($data['observacao'])) {
                $updateFields[] = "observacao = :observacao";
                $params['observacao'] = $data['observacao'];
            }

            if (isset($data['variacao_id'])) {
                $updateFields[] = "variacao_id = :variacao_id";
                $params['variacao_id'] = $data['variacao_id'];
            }

            if (empty($updateFields)) {
                return false;
            }

            $updateFields[] = "atualizado_em = NOW()";
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
