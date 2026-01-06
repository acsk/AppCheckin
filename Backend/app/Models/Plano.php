<?php

namespace App\Models;

use PDO;

class Plano
{
    private PDO $db;
    private int $tenantId;

    public function __construct(PDO $db, int $tenantId = 1)
    {
        $this->db = $db;
        $this->tenantId = $tenantId;
    }

    /**
     * Busca todos os planos ativos
     */
    public function getAll(bool $apenasAtivos = false): array
    {
        $sql = "SELECT p.*, m.nome as modalidade_nome, m.cor as modalidade_cor, m.icone as modalidade_icone 
                FROM planos p
                LEFT JOIN modalidades m ON m.id = p.modalidade_id
                WHERE p.tenant_id = ?";
        
        if ($apenasAtivos) {
            $sql .= " AND p.ativo = 1";
        }
        
        $sql .= " ORDER BY p.valor ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->tenantId]);
        
        return $stmt->fetchAll();
    }

    /**
     * Busca planos disponíveis para novos contratos (atual=true e ativo=true)
     */
    public function getDisponiveis(): array
    {
        $sql = "SELECT p.*, m.nome as modalidade_nome, m.cor as modalidade_cor, m.icone as modalidade_icone 
                FROM planos p
                LEFT JOIN modalidades m ON m.id = p.modalidade_id
                WHERE p.tenant_id = ? AND p.atual = 1 AND p.ativo = 1
                ORDER BY p.valor ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->tenantId]);
        
        return $stmt->fetchAll();
    }

    /**
     * Busca plano por ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT p.*, m.nome as modalidade_nome, m.cor as modalidade_cor, m.icone as modalidade_icone
            FROM planos p
            LEFT JOIN modalidades m ON m.id = p.modalidade_id
            WHERE p.id = ? AND p.tenant_id = ?
        ");
        $stmt->execute([$id, $this->tenantId]);
        $plano = $stmt->fetch();
        
        return $plano ?: null;
    }

    /**
     * Cria novo plano
     */
    public function create(array $data): ?int
    {
        $stmt = $this->db->prepare("
            INSERT INTO planos 
            (tenant_id, modalidade_id, nome, descricao, valor, duracao_dias, checkins_mensais, max_alunos, ativo, atual) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $this->tenantId,
            $data['modalidade_id'], // Obrigatório
            $data['nome'],
            $data['descricao'] ?? null,
            $data['valor'],
            $data['duracao_dias'] ?? 30,
            $data['checkins_mensais'] ?? null,
            $data['max_alunos'] ?? null,
            $data['ativo'] ?? true,
            $data['atual'] ?? true
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Atualiza plano
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [];

        if (isset($data['modalidade_id'])) {
            $fields[] = 'modalidade_id = ?';
            $params[] = $data['modalidade_id'];
        }

        if (isset($data['nome'])) {
            $fields[] = 'nome = ?';
            $params[] = $data['nome'];
        }

        if (isset($data['descricao'])) {
            $fields[] = 'descricao = ?';
            $params[] = $data['descricao'];
        }

        if (isset($data['valor'])) {
            $fields[] = 'valor = ?';
            $params[] = $data['valor'];
        }

        if (isset($data['duracao_dias'])) {
            $fields[] = 'duracao_dias = ?';
            $params[] = $data['duracao_dias'];
        }

        if (isset($data['checkins_mensais'])) {
            $fields[] = 'checkins_mensais = ?';
            $params[] = $data['checkins_mensais'];
        }

        if (isset($data['max_alunos'])) {
            $fields[] = 'max_alunos = ?';
            $params[] = $data['max_alunos'];
        }

        if (isset($data['ativo'])) {
            $fields[] = 'ativo = ?';
            $params[] = $data['ativo'];
        }

        if (isset($data['atual'])) {
            $fields[] = 'atual = ?';
            $params[] = $data['atual'];
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $params[] = $this->tenantId;

        $sql = "UPDATE planos SET " . implode(', ', $fields) . " WHERE id = ? AND tenant_id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($params);
    }

    /**
     * Exclui plano (soft delete - desativa)
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE planos SET ativo = 0 
            WHERE id = ? AND tenant_id = ?
        ");
        
        return $stmt->execute([$id, $this->tenantId]);
    }

    /**
     * Conta quantos usuários estão usando um plano
     */
    public function countUsuarios(int $planoId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total
            FROM usuarios 
            WHERE plano_id = ? AND tenant_id = ?
        ");
        $stmt->execute([$planoId, $this->tenantId]);
        
        return (int) $stmt->fetch()['total'];
    }

    /**
     * Verifica se um plano possui contratos ativos ou inativos (não cancelados)
     * Retorna true se houver contratos vinculados
     */
    public function possuiContratos(int $planoId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total
            FROM tenant_planos_sistema 
            WHERE plano_id = ? AND status != 'cancelado'
        ");
        $stmt->execute([$planoId]);
        
        return (int) $stmt->fetch()['total'] > 0;
    }

    /**
     * Marca um plano como histórico (atual=false)
     * Usado quando se deseja criar uma nova versão do plano
     */
    public function marcarComoHistorico(int $planoId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE planos SET atual = 0 
            WHERE id = ? AND tenant_id = ?
        ");
        
        return $stmt->execute([$planoId, $this->tenantId]);
    }
}
