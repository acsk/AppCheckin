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
                "INSERT INTO wods (tenant_id, modalidade_id, data, titulo, descricao, status, criado_por, created_at, updated_at)
                 VALUES (:tenant_id, :modalidade_id, :data, :titulo, :descricao, :status, :criado_por, NOW(), NOW())"
            );

            $result = $stmt->execute([
                'tenant_id' => $tenantId,
                'modalidade_id' => $data['modalidade_id'] ?? null,
                'data' => $data['data'],
                'titulo' => $data['titulo'],
                'descricao' => $data['descricao'] ?? null,
                'status' => $data['status'] ?? self::STATUS_DRAFT,
                'criado_por' => $data['criado_por'] ?? null,
            ]);

            if (!$result) {
                throw new \Exception('Falha ao executar INSERT de WOD: ' . implode(', ', $stmt->errorInfo()));
            }

            $lastId = $this->db->lastInsertId();
            if (!$lastId) {
                throw new \Exception('Não foi possível obter o ID do WOD criado');
            }

            return (int)$lastId;
        } catch (\Exception $e) {
            error_log('Erro ao criar WOD: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Buscar WOD por ID
     */
    public function findById(int $id, int $tenantId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT w.*, u.nome as criado_por_nome, m.nome as modalidade_nome
             FROM wods w
             LEFT JOIN usuarios u ON w.criado_por = u.id
             LEFT JOIN modalidades m ON w.modalidade_id = m.id
             WHERE w.id = :id AND w.tenant_id = :tenant_id"
        );

        $stmt->execute([
            'id' => $id,
            'tenant_id' => $tenantId,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Buscar WOD por data e modalidade
     * Usado para exibir o WOD do dia em turmas específicas
     */
    public function findByDataModalidade(string $data, int $modalidadeId, int $tenantId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT w.*, u.nome as criado_por_nome, m.nome as modalidade_nome, m.cor as modalidade_cor
             FROM wods w
             LEFT JOIN usuarios u ON w.criado_por = u.id
             LEFT JOIN modalidades m ON w.modalidade_id = m.id
             WHERE w.data = :data 
             AND w.modalidade_id = :modalidade_id 
             AND w.tenant_id = :tenant_id
             AND w.status = 'published'
             LIMIT 1"
        );

        $stmt->execute([
            'data' => $data,
            'modalidade_id' => $modalidadeId,
            'tenant_id' => $tenantId,
        ]);

        $wod = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $wod ?: null;
    }

    /**
     * Listar WODs de um tenant
     */
    public function listByTenant(int $tenantId, array $filters = []): array
    {
        $query = "SELECT w.*, u.nome as criado_por_nome, m.nome as modalidade_nome
                  FROM wods w
                  LEFT JOIN usuarios u ON w.criado_por = u.id
                  LEFT JOIN modalidades m ON w.modalidade_id = m.id
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

        if (!empty($filters['modalidade_id'])) {
            $query .= " AND w.modalidade_id = :modalidade_id";
            $params['modalidade_id'] = $filters['modalidade_id'];
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

            if (isset($data['modalidade_id'])) {
                $updateFields[] = "modalidade_id = :modalidade_id";
                $params['modalidade_id'] = $data['modalidade_id'];
            }

            if (empty($updateFields)) {
                return false;
            }

            $updateFields[] = "updated_at = NOW()";
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
    /**
     * Verificar se existe WOD para uma data específica e modalidade
     */
    public function existePorDataModalidade(string $data, int $modalidadeId, int $tenantId, ?int $wodIdExcluir = null): bool
    {
        $query = "SELECT COUNT(*) FROM wods 
                  WHERE tenant_id = :tenant_id 
                  AND DATE(data) = :data 
                  AND modalidade_id = :modalidade_id";
        $params = [
            'tenant_id' => $tenantId,
            'data' => $data,
            'modalidade_id' => $modalidadeId,
        ];

        if ($wodIdExcluir) {
            $query .= " AND id != :wod_id";
            $params['wod_id'] = $wodIdExcluir;
        }

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() > 0;
    }

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
