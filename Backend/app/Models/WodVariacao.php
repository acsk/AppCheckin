<?php

namespace App\Models;

use PDO;

class WodVariacao
{
    private PDO $db;

    public const NOME_RX = 'RX';
    public const NOME_SCALED = 'Scaled';
    public const NOME_BEGINNER = 'Beginner';

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Criar uma nova variação
     */
    public function create(array $data): ?int
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO wod_variacoes (wod_id, nome, descricao, criado_em, atualizado_em)
                 VALUES (:wod_id, :nome, :descricao, NOW(), NOW())"
            );

            $stmt->execute([
                'wod_id' => $data['wod_id'],
                'nome' => $data['nome'],
                'descricao' => $data['descricao'] ?? null,
            ]);

            return $this->db->lastInsertId() ? (int)$this->db->lastInsertId() : null;
        } catch (\Exception $e) {
            error_log('Erro ao criar variação: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Listar variações de um WOD
     */
    public function listByWod(int $wodId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM wod_variacoes WHERE wod_id = :wod_id ORDER BY id ASC"
        );

        $stmt->execute(['wod_id' => $wodId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar variação por ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM wod_variacoes WHERE id = :id"
        );

        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Buscar variação por nome em um WOD
     */
    public function findByNome(int $wodId, string $nome): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM wod_variacoes WHERE wod_id = :wod_id AND nome = :nome"
        );

        $stmt->execute([
            'wod_id' => $wodId,
            'nome' => $nome,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Atualizar variação
     */
    public function update(int $id, array $data): bool
    {
        try {
            $updateFields = [];
            $params = ['id' => $id];

            if (isset($data['nome'])) {
                $updateFields[] = "nome = :nome";
                $params['nome'] = $data['nome'];
            }

            if (isset($data['descricao'])) {
                $updateFields[] = "descricao = :descricao";
                $params['descricao'] = $data['descricao'];
            }

            if (empty($updateFields)) {
                return false;
            }

            $updateFields[] = "atualizado_em = NOW()";
            $query = "UPDATE wod_variacoes SET " . implode(", ", $updateFields) . " WHERE id = :id";

            $stmt = $this->db->prepare($query);
            return $stmt->execute($params);
        } catch (\Exception $e) {
            error_log('Erro ao atualizar variação: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletar variação
     */
    public function delete(int $id): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM wod_variacoes WHERE id = :id");
            return $stmt->execute(['id' => $id]);
        } catch (\Exception $e) {
            error_log('Erro ao deletar variação: ' . $e->getMessage());
            return false;
        }
    }
}
