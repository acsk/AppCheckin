<?php

namespace App\Models;

use PDO;

class RecordePessoal
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // ========== PROVAS ==========

    /**
     * Listar provas disponíveis do tenant
     */
    public function listarProvas(int $tenantId, bool $apenasAtivas = true): array
    {
        $sql = "SELECT * FROM recorde_provas WHERE tenant_id = ?";
        if ($apenasAtivas) {
            $sql .= " AND ativo = 1";
        }
        $sql .= " ORDER BY ordem ASC, nome ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar prova por ID
     */
    public function buscarProva(int $id, int $tenantId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM recorde_provas WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Criar prova
     */
    public function criarProva(array $dados): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO recorde_provas (tenant_id, nome, distancia_metros, estilo, unidade_medida, ordem)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $dados['tenant_id'],
            $dados['nome'],
            $dados['distancia_metros'] ?? null,
            $dados['estilo'] ?? null,
            $dados['unidade_medida'] ?? 'tempo',
            $dados['ordem'] ?? 0,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Atualizar prova
     */
    public function atualizarProva(int $id, int $tenantId, array $dados): bool
    {
        $stmt = $this->db->prepare("
            UPDATE recorde_provas
            SET nome = ?, distancia_metros = ?, estilo = ?, unidade_medida = ?, ordem = ?, ativo = ?, updated_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([
            $dados['nome'],
            $dados['distancia_metros'] ?? null,
            $dados['estilo'] ?? null,
            $dados['unidade_medida'] ?? 'tempo',
            $dados['ordem'] ?? 0,
            $dados['ativo'] ?? 1,
            $id,
            $tenantId,
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Excluir prova (soft: desativa)
     */
    public function desativarProva(int $id, int $tenantId): bool
    {
        $stmt = $this->db->prepare("UPDATE recorde_provas SET ativo = 0, updated_at = NOW() WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenantId]);
        return $stmt->rowCount() > 0;
    }

    // ========== RECORDES ==========

    /**
     * Listar recordes de um aluno (com nome da prova)
     */
    public function listarPorAluno(int $tenantId, int $alunoId, ?int $provaId = null): array
    {
        $sql = "
            SELECT rp.*, pr.nome as prova_nome, pr.distancia_metros, pr.estilo, pr.unidade_medida,
                   u.nome as registrado_por_nome
            FROM recordes_pessoais rp
            INNER JOIN recorde_provas pr ON pr.id = rp.prova_id
            LEFT JOIN usuarios u ON u.id = rp.registrado_por
            WHERE rp.tenant_id = ? AND rp.aluno_id = ? AND rp.origem = 'aluno'
        ";
        $params = [$tenantId, $alunoId];

        if ($provaId) {
            $sql .= " AND rp.prova_id = ?";
            $params[] = $provaId;
        }

        $sql .= " ORDER BY pr.ordem ASC, rp.data_registro DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar melhor recorde (PR) de um aluno por prova
     */
    public function melhorPorAluno(int $tenantId, int $alunoId, int $provaId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT rp.*, pr.nome as prova_nome, pr.unidade_medida
            FROM recordes_pessoais rp
            INNER JOIN recorde_provas pr ON pr.id = rp.prova_id
            WHERE rp.tenant_id = ? AND rp.aluno_id = ? AND rp.prova_id = ? AND rp.origem = 'aluno'
            ORDER BY CASE WHEN pr.unidade_medida = 'tempo' THEN rp.tempo_segundos ELSE -rp.valor END ASC
            LIMIT 1
        ");
        $stmt->execute([$tenantId, $alunoId, $provaId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Listar todos os PRs (melhores) de um aluno, agrupado por prova
     */
    public function melhoresPorAluno(int $tenantId, int $alunoId): array
    {
        // Para tempo: menor é melhor. Para outros: maior é melhor.
        $stmt = $this->db->prepare("
            SELECT rp.*, pr.nome as prova_nome, pr.distancia_metros, pr.estilo, pr.unidade_medida
            FROM recordes_pessoais rp
            INNER JOIN recorde_provas pr ON pr.id = rp.prova_id
            INNER JOIN (
                SELECT prova_id,
                    CASE 
                        WHEN (SELECT unidade_medida FROM recorde_provas WHERE id = rp2.prova_id) = 'tempo'
                        THEN MIN(rp2.tempo_segundos)
                        ELSE MAX(rp2.valor)
                    END as melhor_valor
                FROM recordes_pessoais rp2
                WHERE rp2.tenant_id = ? AND rp2.aluno_id = ? AND rp2.origem = 'aluno'
                GROUP BY rp2.prova_id
            ) best ON best.prova_id = rp.prova_id
                AND (
                    (pr.unidade_medida = 'tempo' AND rp.tempo_segundos = best.melhor_valor)
                    OR (pr.unidade_medida != 'tempo' AND rp.valor = best.melhor_valor)
                )
            WHERE rp.tenant_id = ? AND rp.aluno_id = ? AND rp.origem = 'aluno'
            GROUP BY rp.prova_id
            ORDER BY pr.ordem ASC
        ");
        $stmt->execute([$tenantId, $alunoId, $tenantId, $alunoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Listar recordes da escola
     */
    public function listarRecordesEscola(int $tenantId, ?int $provaId = null): array
    {
        $sql = "
            SELECT rp.*, pr.nome as prova_nome, pr.distancia_metros, pr.estilo, pr.unidade_medida,
                   a.nome as aluno_nome, u.nome as registrado_por_nome
            FROM recordes_pessoais rp
            INNER JOIN recorde_provas pr ON pr.id = rp.prova_id
            LEFT JOIN alunos a ON a.id = rp.aluno_id
            LEFT JOIN usuarios u ON u.id = rp.registrado_por
            WHERE rp.tenant_id = ? AND rp.origem = 'escola'
        ";
        $params = [$tenantId];

        if ($provaId) {
            $sql .= " AND rp.prova_id = ?";
            $params[] = $provaId;
        }

        $sql .= " ORDER BY pr.ordem ASC, rp.data_registro DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ranking por prova (melhores tempos/valores de todos os alunos)
     */
    public function ranking(int $tenantId, int $provaId, int $limit = 20): array
    {
        $stmt = $this->db->prepare("
            SELECT rp.*, a.nome as aluno_nome, pr.unidade_medida, pr.nome as prova_nome
            FROM recordes_pessoais rp
            INNER JOIN recorde_provas pr ON pr.id = rp.prova_id
            INNER JOIN alunos a ON a.id = rp.aluno_id
            WHERE rp.tenant_id = ? AND rp.prova_id = ?
            AND rp.id IN (
                SELECT MIN(r2.id) FROM recordes_pessoais r2
                WHERE r2.tenant_id = ? AND r2.prova_id = ?
                GROUP BY r2.aluno_id
                HAVING CASE 
                    WHEN (SELECT unidade_medida FROM recorde_provas WHERE id = r2.prova_id) = 'tempo'
                    THEN MIN(r2.tempo_segundos)
                    ELSE MAX(r2.valor)
                END IS NOT NULL
            )
            ORDER BY CASE WHEN pr.unidade_medida = 'tempo' THEN rp.tempo_segundos ELSE -rp.valor END ASC
            LIMIT ?
        ");
        $stmt->execute([$tenantId, $provaId, $tenantId, $provaId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ranking simplificado: melhor tempo de cada aluno por prova
     */
    public function rankingPorProva(int $tenantId, int $provaId, int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT rp.aluno_id, a.nome as aluno_nome,
                   MIN(rp.tempo_segundos) as melhor_tempo,
                   MAX(rp.valor) as melhor_valor,
                   MAX(rp.data_registro) as data_recorde,
                   pr.unidade_medida
            FROM recordes_pessoais rp
            INNER JOIN recorde_provas pr ON pr.id = rp.prova_id
            INNER JOIN alunos a ON a.id = rp.aluno_id
            WHERE rp.tenant_id = ? AND rp.prova_id = ? AND rp.origem = 'aluno'
            GROUP BY rp.aluno_id, a.nome, pr.unidade_medida
            ORDER BY CASE WHEN pr.unidade_medida = 'tempo' THEN MIN(rp.tempo_segundos) ELSE -MAX(rp.valor) END ASC
            LIMIT ?
        ");
        $stmt->execute([$tenantId, $provaId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Criar recorde
     */
    public function criar(array $dados): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO recordes_pessoais
            (tenant_id, aluno_id, prova_id, tempo_segundos, valor, data_registro, observacoes, origem, registrado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $dados['tenant_id'],
            $dados['aluno_id'] ?? null,
            $dados['prova_id'],
            $dados['tempo_segundos'] ?? null,
            $dados['valor'] ?? null,
            $dados['data_registro'],
            $dados['observacoes'] ?? null,
            $dados['origem'] ?? 'aluno',
            $dados['registrado_por'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Atualizar recorde
     */
    public function atualizar(int $id, int $tenantId, array $dados): bool
    {
        $stmt = $this->db->prepare("
            UPDATE recordes_pessoais
            SET prova_id = ?, tempo_segundos = ?, valor = ?, data_registro = ?, observacoes = ?, updated_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([
            $dados['prova_id'],
            $dados['tempo_segundos'] ?? null,
            $dados['valor'] ?? null,
            $dados['data_registro'],
            $dados['observacoes'] ?? null,
            $id,
            $tenantId,
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Buscar recorde por ID
     */
    public function buscar(int $id, int $tenantId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT rp.*, pr.nome as prova_nome, pr.unidade_medida, pr.distancia_metros, pr.estilo,
                   a.nome as aluno_nome
            FROM recordes_pessoais rp
            INNER JOIN recorde_provas pr ON pr.id = rp.prova_id
            LEFT JOIN alunos a ON a.id = rp.aluno_id
            WHERE rp.id = ? AND rp.tenant_id = ?
        ");
        $stmt->execute([$id, $tenantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Excluir recorde
     */
    public function excluir(int $id, int $tenantId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM recordes_pessoais WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenantId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Contar recordes de um aluno
     */
    public function contarPorAluno(int $tenantId, int $alunoId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM recordes_pessoais WHERE tenant_id = ? AND aluno_id = ? AND origem = 'aluno'
        ");
        $stmt->execute([$tenantId, $alunoId]);
        return (int) $stmt->fetchColumn();
    }
}
