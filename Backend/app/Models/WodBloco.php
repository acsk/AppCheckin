<?php

namespace App\Models;

use PDO;

class WodBloco
{
    private PDO $db;

    public const TIPO_WARMUP = 'warmup';
    public const TIPO_STRENGTH = 'strength';
    public const TIPO_METCON = 'metcon';
    public const TIPO_ACCESSORY = 'accessory';
    public const TIPO_COOLDOWN = 'cooldown';
    public const TIPO_NOTE = 'note';

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Criar um novo bloco
     */
    public function create(array $data): ?int
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO wod_blocos (wod_id, ordem, tipo, titulo, conteudo, tempo_cap, criado_em, atualizado_em)
                 VALUES (:wod_id, :ordem, :tipo, :titulo, :conteudo, :tempo_cap, NOW(), NOW())"
            );

            $stmt->execute([
                'wod_id' => $data['wod_id'],
                'ordem' => $data['ordem'] ?? 1,
                'tipo' => $data['tipo'],
                'titulo' => $data['titulo'] ?? null,
                'conteudo' => $data['conteudo'],
                'tempo_cap' => $data['tempo_cap'] ?? null,
            ]);

            return $this->db->lastInsertId() ? (int)$this->db->lastInsertId() : null;
        } catch (\Exception $e) {
            error_log('Erro ao criar bloco de WOD: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Listar blocos de um WOD
     */
    public function listByWod(int $wodId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM wod_blocos WHERE wod_id = :wod_id ORDER BY ordem ASC"
        );

        $stmt->execute(['wod_id' => $wodId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar bloco por ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM wod_blocos WHERE id = :id"
        );

        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Atualizar bloco
     */
    public function update(int $id, array $data): bool
    {
        try {
            $updateFields = [];
            $params = ['id' => $id];

            if (isset($data['titulo'])) {
                $updateFields[] = "titulo = :titulo";
                $params['titulo'] = $data['titulo'];
            }

            if (isset($data['conteudo'])) {
                $updateFields[] = "conteudo = :conteudo";
                $params['conteudo'] = $data['conteudo'];
            }

            if (isset($data['tipo'])) {
                $updateFields[] = "tipo = :tipo";
                $params['tipo'] = $data['tipo'];
            }

            if (isset($data['ordem'])) {
                $updateFields[] = "ordem = :ordem";
                $params['ordem'] = $data['ordem'];
            }

            if (isset($data['tempo_cap'])) {
                $updateFields[] = "tempo_cap = :tempo_cap";
                $params['tempo_cap'] = $data['tempo_cap'];
            }

            if (empty($updateFields)) {
                return false;
            }

            $updateFields[] = "atualizado_em = NOW()";
            $query = "UPDATE wod_blocos SET " . implode(", ", $updateFields) . " WHERE id = :id";

            $stmt = $this->db->prepare($query);
            return $stmt->execute($params);
        } catch (\Exception $e) {
            error_log('Erro ao atualizar bloco: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletar bloco
     */
    public function delete(int $id): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM wod_blocos WHERE id = :id");
            return $stmt->execute(['id' => $id]);
        } catch (\Exception $e) {
            error_log('Erro ao deletar bloco: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obter label do tipo
     */
    public static function getTipoLabel(string $tipo): string
    {
        return match($tipo) {
            self::TIPO_WARMUP => 'Aquecimento',
            self::TIPO_STRENGTH => 'Força',
            self::TIPO_METCON => 'Metcon',
            self::TIPO_ACCESSORY => 'Acessório',
            self::TIPO_COOLDOWN => 'Desaquecimento',
            self::TIPO_NOTE => 'Nota',
            default => 'Desconhecido',
        };
    }
}
