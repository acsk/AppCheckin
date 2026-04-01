<?php

namespace App\Models;

use PDO;

class MatriculaDesconto
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Listar descontos de uma matrícula
     */
    public function listarPorMatricula(int $tenantId, int $matriculaId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT md.*, u.nome as criado_por_nome
            FROM matricula_descontos md
            LEFT JOIN usuarios u ON md.criado_por = u.id
            WHERE md.tenant_id = :tenant_id AND md.matricula_id = :matricula_id
            ORDER BY md.tipo ASC, md.created_at DESC
        ");
        $stmt->execute(['tenant_id' => $tenantId, 'matricula_id' => $matriculaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar desconto por ID
     */
    public function buscarPorId(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT md.*, u.nome as criado_por_nome
            FROM matricula_descontos md
            LEFT JOIN usuarios u ON md.criado_por = u.id
            WHERE md.tenant_id = :tenant_id AND md.id = :id
        ");
        $stmt->execute(['tenant_id' => $tenantId, 'id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Criar desconto
     */
    public function criar(array $dados): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO matricula_descontos
            (tenant_id, matricula_id, tipo, valor, percentual, vigencia_inicio, vigencia_fim,
             parcelas_restantes, motivo, ativo, criado_por)
            VALUES
            (:tenant_id, :matricula_id, :tipo, :valor, :percentual, :vigencia_inicio, :vigencia_fim,
             :parcelas_restantes, :motivo, 1, :criado_por)
        ");
        $stmt->execute([
            'tenant_id' => $dados['tenant_id'],
            'matricula_id' => $dados['matricula_id'],
            'tipo' => $dados['tipo'],
            'valor' => $dados['valor'] ?? null,
            'percentual' => $dados['percentual'] ?? null,
            'vigencia_inicio' => $dados['vigencia_inicio'],
            'vigencia_fim' => $dados['vigencia_fim'] ?? null,
            'parcelas_restantes' => $dados['parcelas_restantes'] ?? null,
            'motivo' => $dados['motivo'],
            'criado_por' => $dados['criado_por'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Atualizar desconto
     */
    public function atualizar(int $tenantId, int $id, array $dados): bool
    {
        $allowed = ['tipo', 'valor', 'percentual', 'vigencia_inicio', 'vigencia_fim',
                     'parcelas_restantes', 'motivo', 'ativo'];
        $sets = [];
        $params = ['tenant_id' => $tenantId, 'id' => $id];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $dados)) {
                $sets[] = "{$field} = :{$field}";
                $params[$field] = $dados[$field];
            }
        }

        if (empty($sets)) {
            return false;
        }

        $sets[] = "updated_at = NOW()";
        $sql = "UPDATE matricula_descontos SET " . implode(', ', $sets) .
               " WHERE tenant_id = :tenant_id AND id = :id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Desativar desconto (soft delete)
     */
    public function desativar(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE matricula_descontos
            SET ativo = 0, updated_at = NOW()
            WHERE tenant_id = :tenant_id AND id = :id
        ");
        return $stmt->execute(['tenant_id' => $tenantId, 'id' => $id]);
    }

    /**
     * Excluir fisicamente
     */
    public function excluir(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM matricula_descontos WHERE tenant_id = :tenant_id AND id = :id
        ");
        return $stmt->execute(['tenant_id' => $tenantId, 'id' => $id]);
    }

    /**
     * Buscar descontos aplicáveis para uma parcela.
     *
     * @param int    $tenantId
     * @param int    $matriculaId
     * @param string $dataVencimento Data de vencimento da parcela (YYYY-MM-DD) — usada para checar vigência
     * @param bool   $isPrimeiraParcela Se é a primeira parcela da matrícula
     * @return array Lista de descontos aplicáveis
     */
    public function buscarAplicaveis(int $tenantId, int $matriculaId, string $dataVencimento, bool $isPrimeiraParcela): array
    {
        $tipos = $isPrimeiraParcela
            ? "('primeira_mensalidade', 'recorrente')"
            : "('recorrente')";

        $sql = "
            SELECT *
            FROM matricula_descontos
            WHERE tenant_id = :tenant_id
              AND matricula_id = :matricula_id
              AND ativo = 1
              AND tipo IN {$tipos}
              AND vigencia_inicio <= :data_vencimento
              AND (vigencia_fim IS NULL OR vigencia_fim >= :data_vencimento2)
              AND (parcelas_restantes IS NULL OR parcelas_restantes > 0)
            ORDER BY tipo ASC, created_at ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'matricula_id' => $matriculaId,
            'data_vencimento' => $dataVencimento,
            'data_vencimento2' => $dataVencimento,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calcular desconto total a partir de uma lista de descontos aplicáveis.
     *
     * @param float $valorBase Valor cheio da parcela (sem desconto)
     * @param array $descontos Resultado de buscarAplicaveis()
     * @return array ['desconto_total' => float, 'motivos' => string, 'ids' => int[], 'detalhes' => array]
     */
    public function calcularDesconto(float $valorBase, array $descontos): array
    {
        $descontoTotal = 0.0;
        $motivos = [];
        $ids = [];
        $detalhes = [];

        foreach ($descontos as $d) {
            $valorDesconto = 0.0;
            if ($d['valor'] !== null) {
                $valorDesconto = (float) $d['valor'];
            } elseif ($d['percentual'] !== null) {
                $valorDesconto = $valorBase * ((float) $d['percentual'] / 100);
            }
            $descontoTotal += $valorDesconto;
            $motivos[] = $d['motivo'];
            $ids[] = (int) $d['id'];
            $detalhes[] = [
                'matricula_desconto_id' => (int) $d['id'],
                'valor_desconto' => round($valorDesconto, 2),
            ];
        }

        // Desconto não pode exceder o valor base — ajustar proporcionalmente
        if ($descontoTotal > $valorBase && $descontoTotal > 0) {
            $ratio = $valorBase / $descontoTotal;
            foreach ($detalhes as &$det) {
                $det['valor_desconto'] = round($det['valor_desconto'] * $ratio, 2);
            }
            unset($det);
            $descontoTotal = $valorBase;
        }

        return [
            'desconto_total' => round($descontoTotal, 2),
            'motivos' => implode(' + ', $motivos),
            'ids' => $ids,
            'detalhes' => $detalhes,
        ];
    }

    /**
     * Salvar os descontos aplicados na tabela pivot pagamento_desconto_aplicado.
     *
     * @param int   $pagamentoPlanoId ID do pagamento_plano
     * @param array $detalhes         Resultado de calcularDesconto()['detalhes']
     */
    public function salvarDescontosAplicados(int $pagamentoPlanoId, array $detalhes): void
    {
        if (empty($detalhes)) {
            return;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO pagamento_desconto_aplicado (pagamento_plano_id, matricula_desconto_id, valor_desconto)
            VALUES (?, ?, ?)
        ");

        foreach ($detalhes as $d) {
            if ($d['valor_desconto'] > 0) {
                $stmt->execute([$pagamentoPlanoId, $d['matricula_desconto_id'], $d['valor_desconto']]);
            }
        }
    }

    /**
     * Decrementar parcelas_restantes dos descontos usados.
     *
     * @param int[] $ids IDs dos descontos aplicados
     */
    public function decrementarParcelas(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("
            UPDATE matricula_descontos
            SET parcelas_restantes = parcelas_restantes - 1,
                ativo = CASE WHEN parcelas_restantes - 1 <= 0 THEN 0 ELSE ativo END,
                updated_at = NOW()
            WHERE id IN ({$placeholders})
              AND parcelas_restantes IS NOT NULL
              AND parcelas_restantes > 0
        ");
        $stmt->execute($ids);
    }

    /**
     * Recalcular e aplicar descontos em todos os pagamentos PENDENTES (status=1) de uma matrícula.
     * Chamado quando um desconto é criado/atualizado/desativado para manter os valores sincronizados.
     *
     * @return int Quantidade de pagamentos atualizados
     */
    public function recalcularDescontosPendentes(int $tenantId, int $matriculaId): int
    {
        // Buscar todos os pagamentos pendentes (Aguardando = 1)
        $stmtPend = $this->pdo->prepare("
            SELECT id, valor, valor_original, desconto, data_vencimento
            FROM pagamentos_plano
            WHERE tenant_id = ? AND matricula_id = ? AND status_pagamento_id = 1
            ORDER BY data_vencimento ASC
        ");
        $stmtPend->execute([$tenantId, $matriculaId]);
        $pendentes = $stmtPend->fetchAll(PDO::FETCH_ASSOC);

        if (empty($pendentes)) {
            return 0;
        }

        // Verificar se é a 1ª parcela (sem parcela anterior paga ou pendente)
        $stmtMin = $this->pdo->prepare("
            SELECT MIN(data_vencimento) FROM pagamentos_plano
            WHERE tenant_id = ? AND matricula_id = ? AND status_pagamento_id != 4
        ");
        $stmtMin->execute([$tenantId, $matriculaId]);
        $minVenc = $stmtMin->fetchColumn();

        $stmtUpd = $this->pdo->prepare("
            UPDATE pagamentos_plano
            SET valor = ?, valor_original = ?, desconto = ?, motivo_desconto = ?, updated_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ");

        $stmtDelPivot = $this->pdo->prepare("
            DELETE FROM pagamento_desconto_aplicado WHERE pagamento_plano_id = ?
        ");

        $atualizados = 0;

        foreach ($pendentes as $pag) {
            $pagId = (int) $pag['id'];
            // Valor base: usar valor_original se disponível, senão reconstruir
            $valorBase = $pag['valor_original']
                ? (float) $pag['valor_original']
                : (float) $pag['valor'] + (float) ($pag['desconto'] ?? 0);

            $isPrimeira = ($pag['data_vencimento'] === $minVenc);

            $descontosAplicaveis = $this->buscarAplicaveis($tenantId, $matriculaId, $pag['data_vencimento'], $isPrimeira);
            $info = $this->calcularDesconto($valorBase, $descontosAplicaveis);

            $valorFinal = max(0, $valorBase - $info['desconto_total']);

            // Atualizar pagamento
            $stmtUpd->execute([
                $valorFinal,
                $valorBase,
                $info['desconto_total'],
                $info['motivos'] ?: null,
                $pagId,
                $tenantId
            ]);

            // Atualizar pivot
            $stmtDelPivot->execute([$pagId]);
            if (!empty($info['detalhes'])) {
                $this->salvarDescontosAplicados($pagId, $info['detalhes']);
            }

            $atualizados++;
        }

        return $atualizados;
    }
}
