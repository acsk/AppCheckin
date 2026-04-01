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

    // ========== DEFINIÇÕES ==========

    public function listarDefinicoes(int $tenantId, bool $apenasAtivas = true, ?int $modalidadeId = null, ?string $categoria = null): array
    {
        $sql = "
            SELECT rd.*, m.nome as modalidade_nome
            FROM recorde_definicoes rd
            LEFT JOIN modalidades m ON m.id = rd.modalidade_id
            WHERE rd.tenant_id = ?
        ";
        $params = [$tenantId];

        if ($apenasAtivas) {
            $sql .= " AND rd.ativo = 1";
        }
        if ($modalidadeId) {
            $sql .= " AND rd.modalidade_id = ?";
            $params[] = $modalidadeId;
        }
        if ($categoria) {
            $sql .= " AND rd.categoria = ?";
            $params[] = $categoria;
        }

        $sql .= " ORDER BY rd.ordem ASC, rd.nome ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $definicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Carregar métricas de cada definição
        if (!empty($definicoes)) {
            $ids = array_column($definicoes, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmtMetricas = $this->db->prepare("
                SELECT * FROM recorde_definicao_metricas
                WHERE definicao_id IN ({$placeholders})
                ORDER BY ordem_comparacao ASC
            ");
            $stmtMetricas->execute($ids);
            $todasMetricas = $stmtMetricas->fetchAll(PDO::FETCH_ASSOC);

            $metricasPorDef = [];
            foreach ($todasMetricas as $m) {
                $metricasPorDef[$m['definicao_id']][] = $m;
            }
            foreach ($definicoes as &$def) {
                $def['metricas'] = $metricasPorDef[$def['id']] ?? [];
            }
        }

        return $definicoes;
    }

    public function buscarDefinicao(int $id, int $tenantId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT rd.*, m.nome as modalidade_nome
            FROM recorde_definicoes rd
            LEFT JOIN modalidades m ON m.id = rd.modalidade_id
            WHERE rd.id = ? AND rd.tenant_id = ?
        ");
        $stmt->execute([$id, $tenantId]);
        $def = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$def) return null;

        $stmtM = $this->db->prepare("
            SELECT * FROM recorde_definicao_metricas WHERE definicao_id = ? ORDER BY ordem_comparacao ASC
        ");
        $stmtM->execute([$def['id']]);
        $def['metricas'] = $stmtM->fetchAll(PDO::FETCH_ASSOC);

        return $def;
    }

    public function criarDefinicao(array $dados): int
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO recorde_definicoes (tenant_id, modalidade_id, nome, categoria, descricao, ordem)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $dados['tenant_id'],
                $dados['modalidade_id'] ?? null,
                $dados['nome'],
                $dados['categoria'] ?? 'movimento',
                $dados['descricao'] ?? null,
                $dados['ordem'] ?? 0,
            ]);
            $defId = (int) $this->db->lastInsertId();

            if (!empty($dados['metricas'])) {
                $this->salvarMetricas($defId, $dados['metricas']);
            }

            $this->db->commit();
            return $defId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function atualizarDefinicao(int $id, int $tenantId, array $dados): bool
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                UPDATE recorde_definicoes
                SET modalidade_id = ?, nome = ?, categoria = ?, descricao = ?, ordem = ?, ativo = ?, updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([
                $dados['modalidade_id'] ?? null,
                $dados['nome'],
                $dados['categoria'] ?? 'movimento',
                $dados['descricao'] ?? null,
                $dados['ordem'] ?? 0,
                $dados['ativo'] ?? 1,
                $id,
                $tenantId,
            ]);

            if (isset($dados['metricas'])) {
                // Remove métricas antigas e insere novas
                $stmtDel = $this->db->prepare("DELETE FROM recorde_definicao_metricas WHERE definicao_id = ?");
                $stmtDel->execute([$id]);
                $this->salvarMetricas($id, $dados['metricas']);
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function desativarDefinicao(int $id, int $tenantId): bool
    {
        $stmt = $this->db->prepare("UPDATE recorde_definicoes SET ativo = 0, updated_at = NOW() WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenantId]);
        return $stmt->rowCount() > 0;
    }

    private function salvarMetricas(int $definicaoId, array $metricas): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO recorde_definicao_metricas
            (definicao_id, codigo, nome, tipo_valor, unidade, ordem_comparacao, direcao, obrigatoria)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($metricas as $i => $m) {
            $stmt->execute([
                $definicaoId,
                $m['codigo'],
                $m['nome'],
                $m['tipo_valor'] ?? 'decimal',
                $m['unidade'] ?? null,
                $m['ordem_comparacao'] ?? ($i + 1),
                $m['direcao'],
                $m['obrigatoria'] ?? 1,
            ]);
        }
    }

    // ========== RECORDES ==========

    public function listarPorAluno(int $tenantId, int $alunoId, ?int $definicaoId = null): array
    {
        $sql = "
            SELECT r.*, rd.nome as definicao_nome, rd.categoria,
                   m.nome as modalidade_nome,
                   u.nome as registrado_por_nome
            FROM recordes r
            INNER JOIN recorde_definicoes rd ON rd.id = r.definicao_id
            LEFT JOIN modalidades m ON m.id = rd.modalidade_id
            LEFT JOIN usuarios u ON u.id = r.registrado_por
            WHERE r.tenant_id = ? AND r.aluno_id = ? AND r.origem = 'aluno' AND r.valido = 1
        ";
        $params = [$tenantId, $alunoId];

        if ($definicaoId) {
            $sql .= " AND r.definicao_id = ?";
            $params[] = $definicaoId;
        }

        $sql .= " ORDER BY rd.ordem ASC, r.data_recorde DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $recordes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->carregarValoresRecordes($recordes);
    }

    public function melhoresPorAluno(int $tenantId, int $alunoId): array
    {
        // Buscar todas as definicoes que o aluno tem registros
        $stmt = $this->db->prepare("
            SELECT DISTINCT r.definicao_id
            FROM recordes r
            WHERE r.tenant_id = ? AND r.aluno_id = ? AND r.origem = 'aluno' AND r.valido = 1
        ");
        $stmt->execute([$tenantId, $alunoId]);
        $definicaoIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($definicaoIds)) return [];

        $melhores = [];
        foreach ($definicaoIds as $defId) {
            $melhor = $this->melhorRecorde($tenantId, (int) $defId, $alunoId);
            if ($melhor) {
                $melhores[] = $melhor;
            }
        }
        return $melhores;
    }

    /**
     * Buscar o melhor recorde para uma definição, opcionalmente filtrando por aluno.
     * Usa a métrica principal (ordem_comparacao = 1) e sua direção para determinar o melhor.
     */
    public function melhorRecorde(int $tenantId, int $definicaoId, ?int $alunoId = null): ?array
    {
        // Buscar métrica principal
        $stmtMetrica = $this->db->prepare("
            SELECT * FROM recorde_definicao_metricas
            WHERE definicao_id = ? ORDER BY ordem_comparacao ASC LIMIT 1
        ");
        $stmtMetrica->execute([$definicaoId]);
        $metricaPrincipal = $stmtMetrica->fetch(PDO::FETCH_ASSOC);
        if (!$metricaPrincipal) return null;

        $campoValor = $this->campoValorPorTipo($metricaPrincipal['tipo_valor']);
        $orderDir = $metricaPrincipal['direcao'] === 'menor_melhor' ? 'ASC' : 'DESC';

        $sql = "
            SELECT r.*, rd.nome as definicao_nome, rd.categoria,
                   m.nome as modalidade_nome,
                   rv.{$campoValor} as valor_principal
            FROM recordes r
            INNER JOIN recorde_definicoes rd ON rd.id = r.definicao_id
            LEFT JOIN modalidades m ON m.id = rd.modalidade_id
            INNER JOIN recorde_valores rv ON rv.recorde_id = r.id AND rv.metrica_id = ?
            WHERE r.tenant_id = ? AND r.definicao_id = ? AND r.valido = 1
        ";
        $params = [$metricaPrincipal['id'], $tenantId, $definicaoId];

        if ($alunoId) {
            $sql .= " AND r.aluno_id = ? AND r.origem = 'aluno'";
            $params[] = $alunoId;
        }

        $sql .= " ORDER BY rv.{$campoValor} {$orderDir} LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $recorde = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$recorde) return null;

        return $this->carregarValoresRecordes([$recorde])[0];
    }

    public function listarRecordesAcademia(int $tenantId, ?int $definicaoId = null, ?int $modalidadeId = null): array
    {
        $sql = "
            SELECT r.*, rd.nome as definicao_nome, rd.categoria,
                   rd.modalidade_id, m.nome as modalidade_nome,
                   a.nome as aluno_nome, u.nome as registrado_por_nome
            FROM recordes r
            INNER JOIN recorde_definicoes rd ON rd.id = r.definicao_id
            LEFT JOIN modalidades m ON m.id = rd.modalidade_id
            LEFT JOIN alunos a ON a.id = r.aluno_id
            LEFT JOIN usuarios u ON u.id = r.registrado_por
            WHERE r.tenant_id = ? AND r.origem = 'academia' AND r.valido = 1
        ";
        $params = [$tenantId];

        if ($definicaoId) {
            $sql .= " AND r.definicao_id = ?";
            $params[] = $definicaoId;
        }
        if ($modalidadeId) {
            $sql .= " AND rd.modalidade_id = ?";
            $params[] = $modalidadeId;
        }

        $sql .= " ORDER BY rd.ordem ASC, r.data_recorde DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $recordes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->carregarValoresRecordes($recordes);
    }

    /**
     * Ranking por definição: melhor valor da métrica principal de cada aluno
     */
    public function rankingPorDefinicao(int $tenantId, int $definicaoId, int $limit = 50): array
    {
        // Buscar métrica principal
        $stmtMetrica = $this->db->prepare("
            SELECT * FROM recorde_definicao_metricas
            WHERE definicao_id = ? ORDER BY ordem_comparacao ASC LIMIT 1
        ");
        $stmtMetrica->execute([$definicaoId]);
        $metricaPrincipal = $stmtMetrica->fetch(PDO::FETCH_ASSOC);
        if (!$metricaPrincipal) return [];

        $campoValor = $this->campoValorPorTipo($metricaPrincipal['tipo_valor']);
        $aggFunc = $metricaPrincipal['direcao'] === 'menor_melhor' ? 'MIN' : 'MAX';
        $orderDir = $metricaPrincipal['direcao'] === 'menor_melhor' ? 'ASC' : 'DESC';

        $stmt = $this->db->prepare("
            SELECT r.aluno_id, a.nome as aluno_nome,
                   {$aggFunc}(rv.{$campoValor}) as melhor_valor,
                   MAX(r.data_recorde) as data_recorde
            FROM recordes r
            INNER JOIN recorde_valores rv ON rv.recorde_id = r.id AND rv.metrica_id = ?
            INNER JOIN alunos a ON a.id = r.aluno_id
            WHERE r.tenant_id = ? AND r.definicao_id = ? AND r.origem = 'aluno' AND r.valido = 1
            GROUP BY r.aluno_id, a.nome
            ORDER BY melhor_valor {$orderDir}
            LIMIT ?
        ");
        $stmt->execute([$metricaPrincipal['id'], $tenantId, $definicaoId, $limit]);
        $ranking = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Adicionar info da métrica
        foreach ($ranking as &$item) {
            $item['metrica_codigo'] = $metricaPrincipal['codigo'];
            $item['metrica_nome'] = $metricaPrincipal['nome'];
            $item['metrica_unidade'] = $metricaPrincipal['unidade'];
            $item['metrica_direcao'] = $metricaPrincipal['direcao'];
            $item['metrica_tipo_valor'] = $metricaPrincipal['tipo_valor'];
        }

        return $ranking;
    }

    public function criar(array $dados): int
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO recordes
                (tenant_id, aluno_id, definicao_id, origem, data_recorde, observacoes, registrado_por)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $dados['tenant_id'],
                $dados['aluno_id'] ?? null,
                $dados['definicao_id'],
                $dados['origem'] ?? 'aluno',
                $dados['data_recorde'],
                $dados['observacoes'] ?? null,
                $dados['registrado_por'] ?? null,
            ]);
            $recordeId = (int) $this->db->lastInsertId();

            if (!empty($dados['valores'])) {
                $this->salvarValores($recordeId, $dados['valores']);
            }

            $this->db->commit();
            return $recordeId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function atualizar(int $id, int $tenantId, array $dados): bool
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                UPDATE recordes
                SET definicao_id = ?, data_recorde = ?, observacoes = ?, updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([
                $dados['definicao_id'],
                $dados['data_recorde'],
                $dados['observacoes'] ?? null,
                $id,
                $tenantId,
            ]);

            if (isset($dados['valores'])) {
                $stmtDel = $this->db->prepare("DELETE FROM recorde_valores WHERE recorde_id = ?");
                $stmtDel->execute([$id]);
                $this->salvarValores($id, $dados['valores']);
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function buscar(int $id, int $tenantId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT r.*, rd.nome as definicao_nome, rd.categoria,
                   rd.modalidade_id, m.nome as modalidade_nome,
                   a.nome as aluno_nome
            FROM recordes r
            INNER JOIN recorde_definicoes rd ON rd.id = r.definicao_id
            LEFT JOIN modalidades m ON m.id = rd.modalidade_id
            LEFT JOIN alunos a ON a.id = r.aluno_id
            WHERE r.id = ? AND r.tenant_id = ?
        ");
        $stmt->execute([$id, $tenantId]);
        $recorde = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$recorde) return null;

        $recordes = $this->carregarValoresRecordes([$recorde]);
        return $recordes[0];
    }

    public function excluir(int $id, int $tenantId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM recordes WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenantId]);
        return $stmt->rowCount() > 0;
    }

    public function contarPorAluno(int $tenantId, int $alunoId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM recordes WHERE tenant_id = ? AND aluno_id = ? AND origem = 'aluno'
        ");
        $stmt->execute([$tenantId, $alunoId]);
        return (int) $stmt->fetchColumn();
    }

    // ========== HELPERS ==========

    private function salvarValores(int $recordeId, array $valores): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO recorde_valores (recorde_id, metrica_id, valor_int, valor_decimal, valor_tempo_ms)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($valores as $v) {
            $stmt->execute([
                $recordeId,
                $v['metrica_id'],
                $v['valor_int'] ?? null,
                $v['valor_decimal'] ?? null,
                $v['valor_tempo_ms'] ?? null,
            ]);
        }
    }

    private function carregarValoresRecordes(array $recordes): array
    {
        if (empty($recordes)) return [];

        $ids = array_column($recordes, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("
            SELECT rv.*, rdm.codigo, rdm.nome as metrica_nome, rdm.tipo_valor,
                   rdm.unidade, rdm.direcao, rdm.ordem_comparacao
            FROM recorde_valores rv
            INNER JOIN recorde_definicao_metricas rdm ON rdm.id = rv.metrica_id
            WHERE rv.recorde_id IN ({$placeholders})
            ORDER BY rdm.ordem_comparacao ASC
        ");
        $stmt->execute($ids);
        $todosValores = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $valoresPorRecorde = [];
        foreach ($todosValores as $v) {
            $valoresPorRecorde[$v['recorde_id']][] = $v;
        }

        foreach ($recordes as &$r) {
            $r['valores'] = $valoresPorRecorde[$r['id']] ?? [];
        }

        return $recordes;
    }

    private function campoValorPorTipo(string $tipoValor): string
    {
        return match ($tipoValor) {
            'inteiro' => 'valor_int',
            'tempo_ms' => 'valor_tempo_ms',
            default => 'valor_decimal',
        };
    }
}
