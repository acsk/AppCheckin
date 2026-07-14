<?php

namespace App\Models;

use PDO;

class PagamentoPlano
{
    /** Máximo de linhas por DELETE em lote (proteção ao banco). */
    public const LIMITE_EXCLUSAO_LOTE = 5000;

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Criar novo pagamento
     */
    public function criar(array $dados): int
    {
        // valor = valor cheio ANTES do desconto (será armazenado como valor_original)
        $originalValor = isset($dados['valor']) ? (float)$dados['valor'] : 0.0;
        $desconto = isset($dados['desconto']) ? (float)$dados['desconto'] : 0.0;
        $valorFinal = max(0, $originalValor - $desconto);

        $sql = "INSERT INTO pagamentos_plano 
            (tenant_id, aluno_id, matricula_id, plano_id, valor, valor_original, desconto, motivo_desconto, data_vencimento, 
             data_pagamento, status_pagamento_id, forma_pagamento_id, comprovante, observacoes, criado_por)
            VALUES 
            (:tenant_id, :aluno_id, :matricula_id, :plano_id, :valor, :valor_original, :desconto, :motivo_desconto, :data_vencimento,
             :data_pagamento, :status_pagamento_id, :forma_pagamento_id, :comprovante, :observacoes, :criado_por)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'tenant_id' => $dados['tenant_id'],
            'aluno_id' => $dados['aluno_id'],
            'matricula_id' => $dados['matricula_id'],
            'plano_id' => $dados['plano_id'],
            'valor' => $valorFinal,
            'valor_original' => $originalValor,
            'desconto' => $desconto,
            'motivo_desconto' => $dados['motivo_desconto'] ?? null,
            'data_vencimento' => $dados['data_vencimento'],
            'data_pagamento' => $dados['data_pagamento'] ?? null,
            'status_pagamento_id' => $dados['status_pagamento_id'] ?? 1,
            'forma_pagamento_id' => $dados['forma_pagamento_id'] ?? null,
            'comprovante' => $dados['comprovante'] ?? null,
            'observacoes' => $dados['observacoes'] ?? null,
            'criado_por' => $dados['criado_por'] ?? null
        ]);
        
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Listar pagamentos de uma matrícula
     */
    public function listarPorMatricula(int $tenantId, int $matriculaId): array
    {
        $sql = "SELECT 
                    p.*,
                    sp.nome as status_pagamento_nome,
                    fp.nome as forma_pagamento_nome,
                    a.nome as aluno_nome,
                    pl.nome as plano_nome,
                    criador.nome as criado_por_nome,
                    baixador.nome as baixado_por_nome,
                    tb.nome as tipo_baixa_nome
                FROM pagamentos_plano p
                INNER JOIN status_pagamento sp ON p.status_pagamento_id = sp.id
                LEFT JOIN formas_pagamento fp ON p.forma_pagamento_id = fp.id
                INNER JOIN alunos a ON p.aluno_id = a.id
                INNER JOIN planos pl ON p.plano_id = pl.id
                LEFT JOIN usuarios criador ON p.criado_por = criador.id
                LEFT JOIN usuarios baixador ON p.baixado_por = baixador.id
                LEFT JOIN tipos_baixa tb ON p.tipo_baixa_id = tb.id
                WHERE p.tenant_id = :tenant_id AND p.matricula_id = :matricula_id
                ORDER BY p.data_vencimento ASC, p.id ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'matricula_id' => $matriculaId
        ]);

        return self::anexarNumeroParcela($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Numera parcelas pela ordem de vencimento (lista já ordenada por data_vencimento, id).
     * Independente da ordem de exibição no painel (ex.: ID decrescente).
     */
    public static function anexarNumeroParcela(array $pagamentos): array
    {
        foreach ($pagamentos as $idx => &$pagamento) {
            $pagamento['numero_parcela'] = $idx + 1;
        }
        unset($pagamento);

        return $pagamentos;
    }

    /**
     * Listar pagamentos de um usuário (via aluno_id)
     */
    public function listarPorUsuario(int $tenantId, int $usuarioId, ?array $filtros = []): array
    {
        $sql = "SELECT 
                    p.*,
                    sp.nome as status_nome,
                    fp.nome as forma_pagamento_nome,
                    pl.nome as plano_nome,
                    m.data_inicio as matricula_data_inicio
                FROM pagamentos_plano p
                INNER JOIN status_pagamento sp ON p.status_pagamento_id = sp.id
                LEFT JOIN formas_pagamento fp ON p.forma_pagamento_id = fp.id
                INNER JOIN planos pl ON p.plano_id = pl.id
                INNER JOIN matriculas m ON p.matricula_id = m.id
                INNER JOIN alunos a ON p.aluno_id = a.id
                WHERE p.tenant_id = :tenant_id AND a.usuario_id = :usuario_id";
        
        $params = [
            'tenant_id' => $tenantId,
            'usuario_id' => $usuarioId
        ];
        
        if (!empty($filtros['status_pagamento_id'])) {
            $sql .= " AND p.status_pagamento_id = :status_pagamento_id";
            $params['status_pagamento_id'] = $filtros['status_pagamento_id'];
        }
        
        $sql .= " ORDER BY p.data_vencimento DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Listar todos os pagamentos do tenant (com filtros)
     */
    public function listarTodos(int $tenantId, ?array $filtros = []): array
    {
        $sql = "SELECT 
                    p.*,
                    sp.nome as status_nome,
                    fp.nome as forma_pagamento_nome,
                    a.nome as aluno_nome,
                    pl.nome as plano_nome
                FROM pagamentos_plano p
                INNER JOIN status_pagamento sp ON p.status_pagamento_id = sp.id
                LEFT JOIN formas_pagamento fp ON p.forma_pagamento_id = fp.id
                INNER JOIN alunos a ON p.aluno_id = a.id
                INNER JOIN planos pl ON p.plano_id = pl.id
                WHERE p.tenant_id = :tenant_id";
        
        $params = ['tenant_id' => $tenantId];
        
        if (!empty($filtros['status_pagamento_id'])) {
            $sql .= " AND p.status_pagamento_id = :status_pagamento_id";
            $params['status_pagamento_id'] = $filtros['status_pagamento_id'];
        }
        
        if (!empty($filtros['aluno_id'])) {
            $sql .= " AND p.aluno_id = :aluno_id";
            $params['aluno_id'] = $filtros['aluno_id'];
        }
        
        if (!empty($filtros['data_inicio'])) {
            $sql .= " AND p.data_vencimento >= :data_inicio";
            $params['data_inicio'] = $filtros['data_inicio'];
        }
        
        if (!empty($filtros['data_fim'])) {
            $sql .= " AND p.data_vencimento <= :data_fim";
            $params['data_fim'] = $filtros['data_fim'];
        }
        
        $sql .= " ORDER BY p.data_vencimento DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar pagamento por ID
     */
    public function buscarPorId(int $tenantId, int $id): ?array
    {
        $sql = "SELECT 
                    p.*,
                    sp.nome as status_nome,
                    fp.nome as forma_pagamento_nome,
                    a.nome as aluno_nome,
                    pl.nome as plano_nome,
                    m.data_inicio as matricula_data_inicio,
                    m.data_vencimento as matricula_data_vencimento
                FROM pagamentos_plano p
                INNER JOIN status_pagamento sp ON p.status_pagamento_id = sp.id
                LEFT JOIN formas_pagamento fp ON p.forma_pagamento_id = fp.id
                INNER JOIN alunos a ON p.aluno_id = a.id
                INNER JOIN planos pl ON p.plano_id = pl.id
                INNER JOIN matriculas m ON p.matricula_id = m.id
                WHERE p.tenant_id = :tenant_id AND p.id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'id' => $id
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }

    /**
     * Confirmar pagamento (dar baixa)
     */
    public function confirmarPagamento(
        int $tenantId,
        int $id,
        int $adminId,
        ?string $dataPagamento = null,
        ?int $formaPagamentoId = null,
        ?string $comprovante = null,
        ?string $observacoes = null,
        ?int $tipoBaixaId = 1
    ): bool {
        $sql = "UPDATE pagamentos_plano 
                SET status_pagamento_id = 2,
                    data_pagamento = COALESCE(:data_pagamento, CURDATE()),
                    forma_pagamento_id = COALESCE(:forma_pagamento_id, forma_pagamento_id),
                    comprovante = COALESCE(:comprovante, comprovante),
                    observacoes = COALESCE(:observacoes, observacoes),
                    baixado_por = :baixado_por,
                    tipo_baixa_id = :tipo_baixa_id,
                    updated_at = NOW()
                WHERE tenant_id = :tenant_id AND id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute([
            'tenant_id' => $tenantId,
            'id' => $id,
            'data_pagamento' => $dataPagamento,
            'forma_pagamento_id' => $formaPagamentoId,
            'comprovante' => $comprovante,
            'observacoes' => $observacoes,
            'baixado_por' => $adminId,
            'tipo_baixa_id' => $tipoBaixaId
        ]);
        
        return $result;
    }

    /**
     * Cancelar pagamento
     */
    public function cancelar(int $tenantId, int $id, ?string $observacoes = null): bool
    {
        // Buscar dados do pagamento antes de cancelar
        $sqlBusca = "SELECT matricula_id FROM pagamentos_plano WHERE tenant_id = :tenant_id AND id = :id";
        $stmtBusca = $this->pdo->prepare($sqlBusca);
        $stmtBusca->execute(['tenant_id' => $tenantId, 'id' => $id]);
        $pagamento = $stmtBusca->fetch(\PDO::FETCH_ASSOC);
        
        if (!$pagamento) {
            return false;
        }

        $sql = "UPDATE pagamentos_plano 
                SET status_pagamento_id = 4,
                    observacoes = COALESCE(:observacoes, observacoes),
                    updated_at = NOW()
                WHERE tenant_id = :tenant_id AND id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute([
            'tenant_id' => $tenantId,
            'id' => $id,
            'observacoes' => $observacoes
        ]);

        // Verificar se há pagamentos pendentes para atualizar status da matrícula
        if ($result && $pagamento['matricula_id']) {
            $this->atualizarStatusMatricula($tenantId, $pagamento['matricula_id']);
        }

        return $result;
    }

    /**
     * Cancela parcelas aguardando/atrasadas em aberto (troca de plano, upgrade mobile, etc.).
     */
    public function cancelarParcelasAbertas(
        int $tenantId,
        int $matriculaId,
        string $motivo = 'Cancelado por alteração de plano'
    ): int {
        $stmt = $this->pdo->prepare("
            UPDATE pagamentos_plano
            SET status_pagamento_id = 4,
                observacoes = CONCAT(COALESCE(observacoes, ''), ' [', :motivo, ']'),
                updated_at = NOW()
            WHERE tenant_id = :tenant_id
              AND matricula_id = :matricula_id
              AND status_pagamento_id IN (1, 3)
              AND data_pagamento IS NULL
        ");
        $stmt->execute([
            'motivo' => $motivo,
            'tenant_id' => $tenantId,
            'matricula_id' => $matriculaId,
        ]);

        return $stmt->rowCount();
    }

    /**
     * Após baixar uma parcela, cancela outras abertas duplicadas do mesmo ciclo (mesmo valor e vencimento
     * igual ou anterior ao da parcela paga). Não cancela parcelas futuras legítimas do ciclo recorrente.
     */
    public function cancelarParcelasDuplicadasAposBaixa(
        int $tenantId,
        int $matriculaId,
        int $parcelaPagaId,
        float $valorPago,
        string $motivo = 'Duplicata: outra parcela já quitada com este PIX'
    ): int {
        $stmtVenc = $this->pdo->prepare("
            SELECT data_vencimento FROM pagamentos_plano
            WHERE id = ? AND tenant_id = ? AND matricula_id = ?
            LIMIT 1
        ");
        $stmtVenc->execute([$parcelaPagaId, $tenantId, $matriculaId]);
        $dataVencimentoPaga = $stmtVenc->fetchColumn();
        if (!$dataVencimentoPaga) {
            return 0;
        }

        $stmt = $this->pdo->prepare("
            UPDATE pagamentos_plano
            SET status_pagamento_id = 4,
                observacoes = CONCAT(COALESCE(observacoes, ''), ' [', :motivo, ']'),
                updated_at = NOW()
            WHERE tenant_id = :tenant_id
              AND matricula_id = :matricula_id
              AND id != :parcela_paga_id
              AND status_pagamento_id IN (1, 3)
              AND data_pagamento IS NULL
              AND ABS(valor - :valor) < 0.01
              AND data_vencimento <= :data_vencimento_paga
        ");
        $stmt->execute([
            'motivo' => $motivo,
            'tenant_id' => $tenantId,
            'matricula_id' => $matriculaId,
            'parcela_paga_id' => $parcelaPagaId,
            'valor' => $valorPago,
            'data_vencimento_paga' => $dataVencimentoPaga,
        ]);

        return $stmt->rowCount();
    }

    /**
     * Uma única parcela pendente com o valor atual (evita baixa MP na parcela antiga).
     */
    public function garantirParcelaPendenteUnica(
        int $tenantId,
        int $alunoId,
        int $matriculaId,
        int $planoId,
        float $valor,
        string $dataVencimento,
        int $criadoPor,
        string $observacoes = 'Aguardando pagamento via Mercado Pago'
    ): int {
        $this->cancelarParcelasAbertas($tenantId, $matriculaId, 'Substituída por nova cobrança');

        $stmt = $this->pdo->prepare("
            SELECT id FROM pagamentos_plano
            WHERE tenant_id = :tenant_id AND matricula_id = :matricula_id
              AND status_pagamento_id IN (1, 3) AND data_pagamento IS NULL
              AND valor = :valor
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([
            'tenant_id' => $tenantId,
            'matricula_id' => $matriculaId,
            'valor' => $valor,
        ]);
        $existente = $stmt->fetchColumn();
        if ($existente) {
            $this->pdo->prepare("
                UPDATE pagamentos_plano
                SET data_vencimento = :data_vencimento, updated_at = NOW()
                WHERE id = :id
            ")->execute([
                'data_vencimento' => $dataVencimento,
                'id' => $existente,
            ]);

            return (int) $existente;
        }

        $stmtInsert = $this->pdo->prepare("
            INSERT INTO pagamentos_plano
            (tenant_id, aluno_id, matricula_id, plano_id, valor, data_vencimento,
             status_pagamento_id, observacoes, criado_por, created_at, updated_at)
            VALUES (:tenant_id, :aluno_id, :matricula_id, :plano_id, :valor, :data_vencimento,
                    1, :observacoes, :criado_por, NOW(), NOW())
        ");
        $stmtInsert->execute([
            'tenant_id' => $tenantId,
            'aluno_id' => $alunoId,
            'matricula_id' => $matriculaId,
            'plano_id' => $planoId,
            'valor' => $valor,
            'data_vencimento' => $dataVencimento,
            'observacoes' => $observacoes,
            'criado_por' => $criadoPor,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Atualizar status da matrícula baseado nos pagamentos
     */
    public function atualizarStatusMatricula(int $tenantId, int $matriculaId): void
    {
        $stmtTipo = $this->pdo->prepare("
            SELECT m.tipo_cobranca, m.data_vencimento, sm.codigo AS status_codigo, p.duracao_dias
            FROM matriculas m
            LEFT JOIN status_matricula sm ON sm.id = m.status_id
            LEFT JOIN planos p ON p.id = m.plano_id
            WHERE m.tenant_id = :tenant_id AND m.id = :matricula_id
            LIMIT 1
        ");
        $stmtTipo->execute(['tenant_id' => $tenantId, 'matricula_id' => $matriculaId]);
        $matriculaMeta = $stmtTipo->fetch(\PDO::FETCH_ASSOC) ?: [];
        $ehAvulso = ($matriculaMeta['tipo_cobranca'] ?? '') === 'avulso';
        $ehDiariaAvulsa = $ehAvulso && (int) ($matriculaMeta['duracao_dias'] ?? 0) === 1;
        $statusAtual = (string) ($matriculaMeta['status_codigo'] ?? '');

        // Diária já encerrada (cancelada/concluída): não reativar por recálculo de pagamento.
        if ($ehDiariaAvulsa && in_array($statusAtual, ['cancelada', 'concluida', 'finalizada'], true)) {
            return;
        }

        // Verificar se ainda há pagamentos pendentes ou atrasados
        $sqlVerifica = "
            SELECT 
                MAX(CASE WHEN status_pagamento_id IN (1, 3) AND data_vencimento < CURDATE() THEN DATEDIFF(CURDATE(), data_vencimento) ELSE 0 END) as dias_atraso,
                SUM(CASE WHEN status_pagamento_id IN (1, 3) THEN 1 ELSE 0 END) as pendentes
            FROM pagamentos_plano
            WHERE tenant_id = :tenant_id AND matricula_id = :matricula_id
        ";
        
        $stmtVerifica = $this->pdo->prepare($sqlVerifica);
        $stmtVerifica->execute(['tenant_id' => $tenantId, 'matricula_id' => $matriculaId]);
        $resultado = $stmtVerifica->fetch(\PDO::FETCH_ASSOC);

        $novoStatus = 'ativa';
        $diasAtraso = (int) $resultado['dias_atraso'];
        $pendentes = (int) $resultado['pendentes'];

        // Avulso: acesso = fim do período PAGO (última parcela paga).
        // Parcela futura "Aguardando" é só cobrança — não estende vigência.
        $acessoAte = null;
        if ($ehAvulso) {
            $stmtPago = $this->pdo->prepare(
                'SELECT ' . self::sqlFimPeriodoPago(':tenant_id', ':matricula_id')
            );
            $stmtPago->execute(['tenant_id' => $tenantId, 'matricula_id' => $matriculaId]);
            $acessoAte = $stmtPago->fetchColumn() ?: ($matriculaMeta['data_vencimento'] ?? null);

            if ($acessoAte && $acessoAte < date('Y-m-d')) {
                $diasAtrasoAcesso = (int) ((new \DateTime(date('Y-m-d')))->diff(new \DateTime($acessoAte))->days);
                if ($ehDiariaAvulsa || $diasAtrasoAcesso >= 5) {
                    $novoStatus = 'cancelada';
                } else {
                    $novoStatus = 'vencida';
                }
            }
            // Avulso: status/acesso só pelo período pago. Parcelas pendentes
            // são cobrança futura e não alteram vigência nem status.
        } elseif ($pendentes > 0) {
            if ($diasAtraso >= 5) {
                $novoStatus = 'cancelada';
            } elseif ($diasAtraso >= 1) {
                $novoStatus = 'vencida';
            }
        }

        // Atualizar matrícula
        $sqlUpdate = "
            UPDATE matriculas 
            SET status_id = (SELECT id FROM status_matricula WHERE codigo = :status_codigo LIMIT 1),
                updated_at = NOW()
            WHERE tenant_id = :tenant_id AND id = :matricula_id
        ";
        
        $stmtUpdate = $this->pdo->prepare($sqlUpdate);
        $stmtUpdate->execute([
            'status_codigo' => $novoStatus,
            'tenant_id' => $tenantId,
            'matricula_id' => $matriculaId
        ]);

        if ($ehAvulso) {
            if ($acessoAte) {
                // Sempre alinhar vigência ao período pago (não à parcela futura de cobrança).
                $this->pdo->prepare("
                    UPDATE matriculas
                    SET data_vencimento = ?,
                        proxima_data_vencimento = ?,
                        updated_at = NOW()
                    WHERE tenant_id = ? AND id = ?
                ")->execute([$acessoAte, $acessoAte, $tenantId, $matriculaId]);
            }
            // Avulso sem parcela paga: não usar pendentes para proxima_data_vencimento.
            return;
        }

        if ($novoStatus === 'ativa') {
            $stmtProx = $this->pdo->prepare("
                SELECT MIN(data_vencimento) FROM pagamentos_plano
                WHERE matricula_id = :matricula_id AND status_pagamento_id IN (1, 3)
            ");
            $stmtProx->execute(['matricula_id' => $matriculaId]);
            $proxima = $stmtProx->fetchColumn();

            if ($proxima) {
                $this->pdo->prepare("
                    UPDATE matriculas
                    SET proxima_data_vencimento = ?, updated_at = NOW()
                    WHERE tenant_id = ? AND id = ?
                ")->execute([$proxima, $tenantId, $matriculaId]);
            } else {
                $this->pdo->prepare("
                    UPDATE matriculas
                    SET proxima_data_vencimento = data_vencimento, updated_at = NOW()
                    WHERE tenant_id = ? AND id = ?
                ")->execute([$tenantId, $matriculaId]);
            }
        }
    }

    /**
     * Marcar pagamentos atrasados
     */
    public function marcarAtrasados(int $tenantId): int
    {
        $this->corrigirParcelasFuturasMarcadasAtrasadas($tenantId);

        $sql = "UPDATE pagamentos_plano 
                SET status_pagamento_id = 3,
                    updated_at = NOW()
                WHERE tenant_id = :tenant_id
                AND status_pagamento_id = 1 
                AND data_vencimento < CURDATE()
                AND data_pagamento IS NULL";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['tenant_id' => $tenantId]);
        $marcados = $stmt->rowCount();

        // Avulso: renovação em aberto fica atrasada quando o período PAGO ATUAL expirou
        // (MAX data_vencimento das pagas), não por parcela histórica antiga.
        $sqlAvulso = "
            UPDATE pagamentos_plano pp
            INNER JOIN matriculas m ON m.id = pp.matricula_id AND m.tenant_id = pp.tenant_id
            SET pp.status_pagamento_id = 3, pp.updated_at = NOW()
            WHERE pp.tenant_id = :tenant_id
              AND m.tipo_cobranca = 'avulso'
              AND pp.status_pagamento_id = 1
              AND pp.data_pagamento IS NULL
              AND " . self::sqlAvulsoPeriodoPagoExpirou('pp') . "
        ";
        $stmtAvulso = $this->pdo->prepare($sqlAvulso);
        $stmtAvulso->execute(['tenant_id' => $tenantId]);

        return $marcados + $stmtAvulso->rowCount();
    }

    /**
     * Expressão SQL do fim do período pago (avulso), por parcela paga:
     * - venc ≈ pagamento + ciclo → parcela já representa o FIM do período
     *   (fluxo MP: +1 mês OU +30 dias; tolerância de 2 dias evita #369:
     *    pago 10/07 + 1 mês = 10/08, venc parcela 09/08 → não somar outro mês);
     * - senão → venc é a data devida (início); fim = GREATEST(pago, venc) + ciclo
     *   (pagamento atrasado reinicia o período na data do pagamento).
     * Ex.: #364 manual pagou 04/07 venc 04/07 → fim 04/08.
     *      #69 MP pagou 29/06 venc 29/07 → fim 29/07.
     *      #347 pagou 02/06 venc 02/07 → fim 02/07.
     *      #369 MP pagou 10/07 venc 09/08 → fim 09/08 (não 09/09).
     * Fallback: parcela paga sem data_pagamento usa o próprio vencimento.
     */
    public static function sqlFimPeriodoPago(string $tenantExpr, string $matriculaExpr): string
    {
        return "(
            SELECT MAX(CASE
                WHEN COALESCE(p_acesso.duracao_dias, 0) = 1 THEN
                    CASE
                        WHEN pp_acesso.data_pagamento IS NULL THEN pp_acesso.data_vencimento
                        WHEN pp_acesso.data_vencimento >= DATE_ADD(pp_acesso.data_pagamento, INTERVAL 1 DAY)
                            THEN pp_acesso.data_vencimento
                        ELSE DATE_ADD(GREATEST(pp_acesso.data_pagamento, pp_acesso.data_vencimento), INTERVAL 1 DAY)
                    END
                WHEN pp_acesso.data_pagamento IS NULL THEN pp_acesso.data_vencimento
                WHEN pp_acesso.data_vencimento >= DATE_SUB(
                        DATE_ADD(pp_acesso.data_pagamento, INTERVAL COALESCE(pc_acesso.meses, 1) MONTH),
                        INTERVAL 2 DAY
                    )
                    THEN pp_acesso.data_vencimento
                ELSE DATE_ADD(GREATEST(pp_acesso.data_pagamento, pp_acesso.data_vencimento), INTERVAL COALESCE(pc_acesso.meses, 1) MONTH)
            END)
            FROM pagamentos_plano pp_acesso
            INNER JOIN matriculas m_acesso
                ON m_acesso.id = pp_acesso.matricula_id AND m_acesso.tenant_id = pp_acesso.tenant_id
            INNER JOIN planos p_acesso ON p_acesso.id = m_acesso.plano_id
            LEFT JOIN plano_ciclos pc_acesso ON pc_acesso.id = m_acesso.plano_ciclo_id
            WHERE pp_acesso.tenant_id = {$tenantExpr}
              AND pp_acesso.matricula_id = {$matriculaExpr}
              AND pp_acesso.status_pagamento_id = 2
        )";
    }

    /**
     * Período pago avulso já expirou em relação a hoje.
     */
    private static function sqlAvulsoPeriodoPagoExpirou(string $ppAlias = 'pp'): string
    {
        return 'COALESCE(' . self::sqlFimPeriodoPago("{$ppAlias}.tenant_id", "{$ppAlias}.matricula_id") . ", '1900-01-01') < CURDATE()";
    }

    /**
     * Parcelas com vencimento futuro não podem permanecer como Atrasado (3),
     * exceto renovação avulsa com período pago já expirado.
     */
    public function corrigirParcelasFuturasMarcadasAtrasadas(int $tenantId): int
    {
        $sql = "UPDATE pagamentos_plano pp
                INNER JOIN matriculas m ON m.id = pp.matricula_id AND m.tenant_id = pp.tenant_id
                SET pp.status_pagamento_id = 1, pp.updated_at = NOW()
                WHERE pp.tenant_id = :tenant_id
                AND pp.status_pagamento_id = 3
                AND pp.data_vencimento >= CURDATE()
                AND pp.data_pagamento IS NULL
                AND NOT (
                    m.tipo_cobranca = 'avulso'
                    AND " . self::sqlAvulsoPeriodoPagoExpirou('pp') . "
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['tenant_id' => $tenantId]);

        return $stmt->rowCount();
    }

    /**
     * Verificar se matrícula tem pagamentos pendentes ou atrasados
     */
    public function temPagamentosPendentes(int $tenantId, int $matriculaId): bool
    {
        $sql = "SELECT COUNT(*) as total
                FROM pagamentos_plano
                WHERE tenant_id = :tenant_id
                AND matricula_id = :matricula_id
                AND status_pagamento_id IN (1, 3)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'matricula_id' => $matriculaId
        ]);
        
        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'] > 0;
    }

    /**
     * Resumo financeiro dos pagamentos
     */
    public function resumo(int $tenantId, ?array $filtros = []): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_pagamentos,
                    SUM(CASE WHEN status_pagamento_id = 1 THEN 1 ELSE 0 END) as aguardando,
                    SUM(CASE WHEN status_pagamento_id = 2 THEN 1 ELSE 0 END) as pagos,
                    SUM(CASE WHEN status_pagamento_id = 3 THEN 1 ELSE 0 END) as atrasados,
                    SUM(CASE WHEN status_pagamento_id = 4 THEN 1 ELSE 0 END) as cancelados,
                    SUM(CASE WHEN status_pagamento_id = 2 THEN valor ELSE 0 END) as valor_recebido,
                    SUM(CASE WHEN status_pagamento_id IN (1, 3) THEN valor ELSE 0 END) as valor_pendente,
                    SUM(valor) as valor_total
                FROM pagamentos_plano
                WHERE tenant_id = :tenant_id";
        
        $params = ['tenant_id' => $tenantId];
        
        if (!empty($filtros['data_inicio'])) {
            $sql .= " AND data_vencimento >= :data_inicio";
            $params['data_inicio'] = $filtros['data_inicio'];
        }
        
        if (!empty($filtros['data_fim'])) {
            $sql .= " AND data_vencimento <= :data_fim";
            $params['data_fim'] = $filtros['data_fim'];
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Buscar último pagamento de uma matrícula
     */
    public function buscarUltimoPagamento(int $tenantId, int $matriculaId): ?array
    {
        $sql = "SELECT * FROM pagamentos_plano
                WHERE tenant_id = :tenant_id AND matricula_id = :matricula_id
                ORDER BY data_vencimento DESC
                LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'matricula_id' => $matriculaId
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }

    /**
     * Verificar se já existe pagamento para uma data específica
     */
    public function existePagamentoParaData(int $tenantId, int $matriculaId, string $dataVencimento): bool
    {
        $sql = "SELECT COUNT(*) as total
                FROM pagamentos_plano
                WHERE tenant_id = :tenant_id
                AND matricula_id = :matricula_id
                AND data_vencimento = :data_vencimento";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'matricula_id' => $matriculaId,
            'data_vencimento' => $dataVencimento
        ]);
        
        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'] > 0;
    }

    /**
     * Atualizar pagamento
     */
    public function atualizar(int $tenantId, int $id, array $dados): bool
    {
        $fields = [];
        $params = ['tenant_id' => $tenantId, 'id' => $id];

        // Se desconto foi informado, recalcular o valor armazenado (valor_original - desconto).
        if (array_key_exists('desconto', $dados)) {
            $desconto = (float) $dados['desconto'];
            // SEMPRE buscar valor_original do banco como base para evitar desconto-sobre-desconto
            $stmtCur = $this->pdo->prepare("SELECT valor, valor_original FROM pagamentos_plano WHERE tenant_id = :tenant_id AND id = :id LIMIT 1");
            $stmtCur->execute(['tenant_id' => $tenantId, 'id' => $id]);
            $cur = $stmtCur->fetch(PDO::FETCH_ASSOC);
            $baseValor = $cur && $cur['valor_original']
                ? (float) $cur['valor_original']
                : ($cur ? (float) $cur['valor'] + $desconto : 0.0);
            $novoValor = max(0, $baseValor - $desconto);
            $dados['valor'] = $novoValor;
        }

        if (isset($dados['valor'])) {
            $fields[] = 'valor = :valor';
            $params['valor'] = $dados['valor'];
        }
        if (isset($dados['data_vencimento'])) {
            $fields[] = 'data_vencimento = :data_vencimento';
            $params['data_vencimento'] = $dados['data_vencimento'];
        }
        if (array_key_exists('data_pagamento', $dados)) {
            $fields[] = 'data_pagamento = :data_pagamento';
            $params['data_pagamento'] = $dados['data_pagamento'];
        }
        if (isset($dados['status_pagamento_id'])) {
            $fields[] = 'status_pagamento_id = :status_pagamento_id';
            $params['status_pagamento_id'] = $dados['status_pagamento_id'];
        }
        if (isset($dados['forma_pagamento_id'])) {
            $fields[] = 'forma_pagamento_id = :forma_pagamento_id';
            $params['forma_pagamento_id'] = $dados['forma_pagamento_id'];
        }
        if (isset($dados['comprovante'])) {
            $fields[] = 'comprovante = :comprovante';
            $params['comprovante'] = $dados['comprovante'];
        }
        if (isset($dados['desconto'])) {
            $fields[] = 'desconto = :desconto';
            $params['desconto'] = $dados['desconto'];
        }
        if (array_key_exists('motivo_desconto', $dados)) {
            $fields[] = 'motivo_desconto = :motivo_desconto';
            $params['motivo_desconto'] = $dados['motivo_desconto'];
        }
        if (isset($dados['observacoes'])) {
            $fields[] = 'observacoes = :observacoes';
            $params['observacoes'] = $dados['observacoes'];
        }

        if (empty($fields)) return false;

        $sql = "UPDATE pagamentos_plano SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE tenant_id = :tenant_id AND id = :id";
        $stmt = $this->pdo->prepare($sql);
        return (bool) $stmt->execute($params);
    }

    /**
     * Excluir (remover) pagamento fisicamente
     */
    public function excluir(int $tenantId, int $id): bool
    {
        $sql = "DELETE FROM pagamentos_plano WHERE tenant_id = :tenant_id AND id = :id";
        $stmt = $this->pdo->prepare($sql);
        return (bool) $stmt->execute(['tenant_id' => $tenantId, 'id' => $id]);
    }

    /**
     * Conta cobranças canceladas elegíveis para exclusão física.
     */
    public function contarCanceladosParaExcluir(int $tenantId, int $diasRetencao = 7): int
    {
        $sql = "SELECT COUNT(*) FROM pagamentos_plano
                WHERE tenant_id = :tenant_id
                  AND status_pagamento_id = 4
                  AND data_pagamento IS NULL
                  AND updated_at < DATE_SUB(NOW(), INTERVAL :dias DAY)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->bindValue('dias', max(0, $diasRetencao), PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Exclui fisicamente cobranças canceladas (status 4) sem pagamento,
     * após período de retenção desde o cancelamento (updated_at).
     */
    public function excluirCanceladosAntigos(int $tenantId, int $diasRetencao = 7, int $limit = 1000): int
    {
        $limit = max(1, min($limit, self::LIMITE_EXCLUSAO_LOTE));
        $diasRetencao = max(0, $diasRetencao);

        $sql = "DELETE FROM pagamentos_plano
                WHERE tenant_id = :tenant_id
                  AND status_pagamento_id = 4
                  AND data_pagamento IS NULL
                  AND updated_at < DATE_SUB(NOW(), INTERVAL :dias DAY)
                LIMIT {$limit}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->bindValue('dias', $diasRetencao, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Gerar próximo pagamento pendente após confirmação automática (polling/webhook).
     *
     * Replica a lógica de MatriculaController::darBaixaConta() para criação da
     * próxima parcela, garantindo que o ciclo de cobrança não seja interrompido
     * quando o pagamento é confirmado automaticamente pelo Mercado Pago.
     *
     * @return array|null  Dados do pagamento criado ou null se não foi necessário
     */
    public function gerarProximoPagamentoAutomatico(int $matriculaId, ?string $dataPagamento = null): ?array
    {
        try {
            // Buscar informações da matrícula, plano e ciclo
            $stmt = $this->pdo->prepare("
                SELECT m.id, m.tenant_id, m.aluno_id, m.plano_id, m.plano_ciclo_id, m.tipo_cobranca, m.valor,
                       m.data_vencimento,
                       p.duracao_dias, pc.meses as ciclo_meses, af.meses as frequencia_meses
                FROM matriculas m
                INNER JOIN planos p ON p.id = m.plano_id
                LEFT JOIN plano_ciclos pc ON pc.id = m.plano_ciclo_id
                LEFT JOIN assinatura_frequencias af ON af.id = pc.assinatura_frequencia_id
                WHERE m.id = ?
            ");
            $stmt->execute([$matriculaId]);
            $matricula = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$matricula) {
                error_log("[gerarProximoPagamento] Matrícula #{$matriculaId} não encontrada");
                return null;
            }

            $ehDiariaAvulsa = ($matricula['tipo_cobranca'] ?? '') === 'avulso'
                && (int) ($matricula['duracao_dias'] ?? 0) === 1;

            if ($ehDiariaAvulsa) {
                error_log("[gerarProximoPagamento] Matrícula #{$matriculaId} é diária avulsa — próxima parcela não gerada");
                return null;
            }

            $valorParcela = (float) ($matricula['valor'] ?? 0);
            if ($valorParcela < 0.01) {
                error_log("[gerarProximoPagamento] Matrícula #{$matriculaId} valor zero — próxima parcela não gerada");
                return null;
            }

            // Verificar se já existe algum pagamento pendente para esta matrícula (Aguardando ou Atrasado)
            $stmtPendente = $this->pdo->prepare("
                SELECT id, data_vencimento FROM pagamentos_plano
                WHERE matricula_id = ? AND status_pagamento_id IN (1, 3)
                ORDER BY data_vencimento ASC
                LIMIT 1
            ");
            $stmtPendente->execute([$matriculaId]);
            $pendente = $stmtPendente->fetch(\PDO::FETCH_ASSOC);

            $ehAvulso = ($matricula['tipo_cobranca'] ?? '') === 'avulso';

            if ($pendente) {
                error_log("[gerarProximoPagamento] Matrícula #{$matriculaId}: já existe pagamento pendente #{$pendente['id']} para {$pendente['data_vencimento']}");
                // Verificar se proxima_data_vencimento já foi atualizada para data mais recente (ex: por ativarMatricula)
                // Se sim, corrigir o pagamento pendente em vez de sobrescrever a data já correta
                $stmtAtual = $this->pdo->prepare("SELECT proxima_data_vencimento FROM matriculas WHERE id = ?");
                $stmtAtual->execute([$matriculaId]);
                $proxAtual = $stmtAtual->fetchColumn();

                if ($proxAtual && $proxAtual > $pendente['data_vencimento']) {
                    // proxima_data_vencimento já aponta para data mais nova — corrigir o pagamento pendente
                    $stmtFixPendente = $this->pdo->prepare("
                        UPDATE pagamentos_plano
                        SET data_vencimento = ?,
                            status_pagamento_id = CASE WHEN ? >= CURDATE() THEN 1 ELSE status_pagamento_id END,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmtFixPendente->execute([$proxAtual, $proxAtual, $pendente['id']]);
                    error_log("[gerarProximoPagamento] Matrícula #{$matriculaId}: corrigida data_vencimento do pendente #{$pendente['id']} de {$pendente['data_vencimento']} para {$proxAtual}");
                } elseif (!$ehAvulso) {
                    // Recorrente: sincronizar acesso com a próxima cobrança.
                    // Avulso: NÃO — parcela futura não paga não estende vigência.
                    $stmtSync = $this->pdo->prepare("
                        UPDATE matriculas SET proxima_data_vencimento = ?, updated_at = NOW() WHERE id = ? AND (proxima_data_vencimento IS NULL OR proxima_data_vencimento != ?)
                    ");
                    $stmtSync->execute([$pendente['data_vencimento'], $matriculaId, $pendente['data_vencimento']]);
                }
                return null;
            }

            // Buscar data_vencimento do último pagamento pago para calcular base
            $stmtUltimoPago = $this->pdo->prepare("
                SELECT data_vencimento, data_pagamento FROM pagamentos_plano
                WHERE matricula_id = ? AND status_pagamento_id = 2
                ORDER BY data_vencimento DESC
                LIMIT 1
            ");
            $stmtUltimoPago->execute([$matriculaId]);
            $ultimoPago = $stmtUltimoPago->fetch(\PDO::FETCH_ASSOC);

            $dataVencPago = $ultimoPago['data_vencimento'] ?? null;
            $dataPag = $dataPagamento ? date('Y-m-d', strtotime($dataPagamento)) : ($ultimoPago['data_pagamento'] ?? null);
            if ($dataPag) {
                $dataPag = date('Y-m-d', strtotime($dataPag));
            }

            $dateVenc = $dataVencPago ? new \DateTime($dataVencPago) : new \DateTime();
            $datePag = $dataPag ? new \DateTime($dataPag) : new \DateTime();

            // Próxima cobrança vence no FIM do período pago (mesma regra de sqlFimPeriodoPago):
            // - parcela paga sem data_pagamento → o venc já é o fim do período;
            // - venc >= pago + ciclo (padrão do fluxo MP, que grava venc = pagamento + ciclo)
            //   → o venc já é o fim, usar o próprio venc;
            // - senão, venc é a data devida (início) → fim = MAX(pago, venc) + ciclo.
            $mesesCiclo = (int) ($matricula['ciclo_meses'] ?? $matricula['frequencia_meses'] ?? 0);
            $baseDate = ($datePag > $dateVenc) ? $datePag : $dateVenc;

            if ($dataPag === null && $dataVencPago) {
                $proximoVencimento = clone $dateVenc;
            } elseif ($mesesCiclo > 0) {
                $vencJaEhFim = clone $datePag;
                $vencJaEhFim->modify("+{$mesesCiclo} months");
                if ($dataVencPago && $dateVenc >= $vencJaEhFim) {
                    $proximoVencimento = clone $dateVenc;
                } else {
                    $proximoVencimento = clone $baseDate;
                    $proximoVencimento->modify("+{$mesesCiclo} months");
                }
            } else {
                $duracaoDias = max(1, (int) ($matricula['duracao_dias'] ?? 30));
                $vencJaEhFim = clone $datePag;
                $vencJaEhFim->add(new \DateInterval("P{$duracaoDias}D"));
                if ($dataVencPago && $dateVenc >= $vencJaEhFim) {
                    $proximoVencimento = clone $dateVenc;
                } else {
                    $proximoVencimento = clone $baseDate;
                    $proximoVencimento->add(new \DateInterval("P{$duracaoDias}D"));
                }
            }

            $proximoVencimentoStr = $proximoVencimento->format('Y-m-d');

            // Aplicar descontos recorrentes à próxima parcela
            $descontoModel = new \App\Models\MatriculaDesconto($this->pdo);
            $descontosAplicaveis = $descontoModel->buscarAplicaveis(
                (int) $matricula['tenant_id'], $matriculaId, $proximoVencimentoStr, false
            );
            $infoDesconto = $descontoModel->calcularDesconto($valorParcela, $descontosAplicaveis);
            $valorComDesconto = max(0, $valorParcela - $infoDesconto['desconto_total']);

            // Inserir próximo pagamento pendente (INSERT direto, sem usar criar() para evitar double-subtraction)
            $stmtInsert = $this->pdo->prepare("
                INSERT INTO pagamentos_plano (
                    tenant_id, aluno_id, matricula_id, plano_id,
                    valor, valor_original, desconto, motivo_desconto, data_vencimento, status_pagamento_id,
                    observacoes, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())
            ");
            $stmtInsert->execute([
                $matricula['tenant_id'],
                $matricula['aluno_id'],
                $matriculaId,
                $matricula['plano_id'],
                $valorComDesconto,
                $valorParcela,
                $infoDesconto['desconto_total'],
                $infoDesconto['motivos'] ?: null,
                $proximoVencimentoStr,
                'Pagamento gerado automaticamente após confirmação MP'
            ]);

            $novoId = (int) $this->pdo->lastInsertId();

            // Salvar descontos aplicados na tabela pivot
            if (!empty($infoDesconto['detalhes'])) {
                $descontoModel->salvarDescontosAplicados($novoId, $infoDesconto['detalhes']);
            }

            // Decrementar parcelas_restantes dos descontos usados
            if (!empty($infoDesconto['ids'])) {
                $descontoModel->decrementarParcelas($infoDesconto['ids']);
            }

            // Recorrente: próxima cobrança = acesso até.
            // Avulso: vigência fica no período já pago; parcela futura é só cobrança.
            if (!$ehAvulso) {
                $stmtUpdMat = $this->pdo->prepare("
                    UPDATE matriculas SET proxima_data_vencimento = ?, updated_at = NOW() WHERE id = ?
                ");
                $stmtUpdMat->execute([$proximoVencimentoStr, $matriculaId]);
            } else {
                // Mesmo fallback de atualizarStatusMatricula: matricula.data_vencimento.
                $acessoPago = $dataVencPago ?: ($dataPag ?: ($matricula['data_vencimento'] ?? null));
                if ($acessoPago) {
                    $stmtUpdMat = $this->pdo->prepare("
                        UPDATE matriculas
                        SET data_vencimento = ?,
                            proxima_data_vencimento = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmtUpdMat->execute([$acessoPago, $acessoPago, $matriculaId]);
                    error_log("[gerarProximoPagamento] Matrícula #{$matriculaId} avulsa: vigência mantida em {$acessoPago}, parcela futura #{$novoId} em {$proximoVencimentoStr}");
                } else {
                    error_log("[gerarProximoPagamento] Matrícula #{$matriculaId} avulsa: sem data de acesso pago para sincronizar; parcela futura #{$novoId} em {$proximoVencimentoStr}");
                }
            }

            error_log("[gerarProximoPagamento] ✅ Matrícula #{$matriculaId}: próximo pagamento #{$novoId} criado para {$proximoVencimentoStr} | Desconto: R$" . $infoDesconto['desconto_total']);

            return [
                'id' => $novoId,
                'data_vencimento' => $proximoVencimentoStr,
                'valor' => $valorComDesconto,
                'desconto' => $infoDesconto['desconto_total'],
            ];
        } catch (\Exception $e) {
            error_log("[gerarProximoPagamento] ❌ Erro matrícula #{$matriculaId}: " . $e->getMessage());
            return null;
        }
    }
}
