<?php

namespace App\Models;

use PDO;

class Wod
{
    private PDO $db;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Criar um novo WOD
     */
    public function create(array $data, int $tenantId): ?int
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO wods (tenant_id, data, titulo, descricao, status, criado_por, criado_em, atualizado_em)
                 VALUES (:tenant_id, :data, :titulo, :descricao, :status, :criado_por, NOW(), NOW())"
            );

            $stmt->execute([
                'tenant_id' => $tenantId,
                'data' => $data['data'],
                'titulo' => $data['titulo'],
                'descricao' => $data['descricao'] ?? null,
                'status' => $data['status'] ?? self::STATUS_DRAFT,
                'criado_por' => $data['criado_por'] ?? null,
            ]);

            return $this->db->lastInsertId() ? (int)$this->db->lastInsertId() : null;
        } catch (\Exception $e) {
            error_log('Erro ao criar WOD: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Buscar WOD por ID
     */
    public function findById(int $id, int $tenantId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT w.*, u.nome as criado_por_nome
             FROM wods w
             LEFT JOIN usuarios u ON w.criado_por = u.id
             WHERE w.id = :id AND w.tenant_id = :tenant_id"
        );

        $stmt->execute([
            'id' => $id,
            'tenant_id' => $tenantId,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Listar WODs de um tenant
     */
    public function listByTenant(int $tenantId, array $filters = []): array
    {
        $query = "SELECT w.*, u.nome as criado_por_nome
                  FROM wods w
                  LEFT JOIN usuarios u ON w.criado_por = u.id
                  WHERE w.tenant_id = :tenant_id";

        $params = ['tenant_id' => $tenantId];

        if (!empty($filters['status'])) {
            $query .= " AND w.status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['data_inicio']) && !empty($filters['data_fim'])) {
            $query .= " AND w.data BETWEEN :data_inicio AND :data_fim";
            $params['data_inicio'] = $filters['data_inicio'];
            $params['data_fim'] = $filters['data_fim'];
        }

        if (!empty($filters['data'])) {
            $query .= " AND DATE(w.data) = :data";
            $params['data'] = $filters['data'];
        }

        $query .= " ORDER BY w.data DESC";

        if (!empty($filters['limit'])) {
            $query .= " LIMIT " . (int)$filters['limit'];
        }
        if (!empty($filters['offset'])) {
            $query .= " OFFSET " . (int)$filters['offset'];
        }

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Atualizar WOD
     */
    public function update(int $id, int $tenantId, array $data): bool
    {
        try {
            $updateFields = [];
            $params = [
                'id' => $id,
                'tenant_id' => $tenantId,
            ];

            if (isset($data['titulo'])) {
                $updateFields[] = "titulo = :titulo";
                $params['titulo'] = $data['titulo'];
            }

            if (isset($data['descricao'])) {
                $updateFields[] = "descricao = :descricao";
                $params['descricao'] = $data['descricao'];
            }

            if (isset($data['status'])) {
                $updateFields[] = "status = :status";
                $params['status'] = $data['status'];
            }

            if (isset($data['data'])) {
                $updateFields[] = "data = :data";
                $params['data'] = $data['data'];
            }

            if (empty($updateFields)) {
                return false;
            }

            $updateFields[] = "atualizado_em = NOW()";
            $query = "UPDATE wods SET " . implode(", ", $updateFields) . " WHERE id = :id AND tenant_id = :tenant_id";

            $stmt = $this->db->prepare($query);
            return $stmt->execute($params);
        } catch (\Exception $e) {
            error_log('Erro ao atualizar WOD: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletar WOD
     */
    public function delete(int $id, int $tenantId): bool
    {
        try {
            $stmt = $this->db->prepare(
                "DELETE FROM wods WHERE id = :id AND tenant_id = :tenant_id"
            );

            return $stmt->execute([
                'id' => $id,
                'tenant_id' => $tenantId,
            ]);
        } catch (\Exception $e) {
            error_log('Erro ao deletar WOD: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar se existe WOD para uma data específica
     */
    public function existePorData(string $data, int $tenantId, ?int $wodIdExcluir = null): bool
    {
        $query = "SELECT COUNT(*) FROM wods WHERE tenant_id = :tenant_id AND DATE(data) = :data";
        $params = [
            'tenant_id' => $tenantId,
            'data' => $data,
        ];

        if ($wodIdExcluir) {
            $query .= " AND id != :wod_id";
            $params['wod_id'] = $wodIdExcluir;
        }

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Publicar WOD
     */
    public function publicar(int $id, int $tenantId): bool
    {
        return $this->update($id, $tenantId, ['status' => self::STATUS_PUBLISHED]);
    }

    /**
     * Arquivar WOD
     */
    public function arquivar(int $id, int $tenantId): bool
    {
        return $this->update($id, $tenantId, ['status' => self::STATUS_ARCHIVED]);
    }
}
