<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PacoteController
{
    private $db;

    public function __construct()
    {
        $this->db = require __DIR__ . '/../../config/database.php';
    }

    /**
     * Listar pacotes (admin)
     * GET /admin/pacotes
     */
    public function listar(Request $request, Response $response): Response
    {
        try {
            $tenantId = (int) $request->getAttribute('tenantId');
            $stmt = $this->db->prepare("
                SELECT p.*
                FROM pacotes p
                WHERE p.tenant_id = ?
                ORDER BY p.created_at DESC
            ");
            $stmt->execute([$tenantId]);
            $pacotes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode([
                'success' => true,
                'pacotes' => $pacotes,
                'total' => count($pacotes)
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            error_log("[PacoteController::listar] Erro: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Erro ao listar pacotes'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Criar pacote (admin)
     * POST /admin/pacotes
     */
    public function criar(Request $request, Response $response): Response
    {
        try {
            $tenantId = (int) $request->getAttribute('tenantId');
            $data = $request->getParsedBody() ?? [];

            $required = ['nome', 'valor_total', 'qtd_beneficiarios', 'plano_id'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    $response->getBody()->write(json_encode([
                        'success' => false,
                        'message' => "{$field} é obrigatório"
                    ], JSON_UNESCAPED_UNICODE));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
                }
            }

            $stmt = $this->db->prepare("
                INSERT INTO pacotes
                (tenant_id, nome, descricao, valor_total, qtd_beneficiarios, plano_id, plano_ciclo_id, ativo)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([
                $tenantId,
                $data['nome'],
                $data['descricao'] ?? null,
                (float) $data['valor_total'],
                (int) $data['qtd_beneficiarios'],
                (int) $data['plano_id'],
                !empty($data['plano_ciclo_id']) ? (int) $data['plano_ciclo_id'] : null
            ]);

            $id = (int) $this->db->lastInsertId();
            $response->getBody()->write(json_encode([
                'success' => true,
                'pacote_id' => $id
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (\Exception $e) {
            error_log("[PacoteController::criar] Erro: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Erro ao criar pacote'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Atualizar pacote (admin)
     * PUT /admin/pacotes/{id}
     */
    public function atualizar(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = (int) $request->getAttribute('tenantId');
            $pacoteId = (int) ($args['id'] ?? 0);
            $data = $request->getParsedBody() ?? [];

            if ($pacoteId <= 0) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'id inválido'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $stmtCheck = $this->db->prepare("SELECT id FROM pacotes WHERE id = ? AND tenant_id = ? LIMIT 1");
            $stmtCheck->execute([$pacoteId, $tenantId]);
            if (!$stmtCheck->fetchColumn()) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Pacote não encontrado'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $stmt = $this->db->prepare("
                UPDATE pacotes
                SET nome = ?,
                    descricao = ?,
                    valor_total = ?,
                    qtd_beneficiarios = ?,
                    plano_id = ?,
                    plano_ciclo_id = ?,
                    ativo = ?,
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([
                $data['nome'] ?? '',
                $data['descricao'] ?? null,
                (float) ($data['valor_total'] ?? 0),
                (int) ($data['qtd_beneficiarios'] ?? 1),
                (int) ($data['plano_id'] ?? 0),
                !empty($data['plano_ciclo_id']) ? (int) $data['plano_ciclo_id'] : null,
                isset($data['ativo']) ? (int) $data['ativo'] : 1,
                $pacoteId,
                $tenantId
            ]);

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Pacote atualizado'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            error_log("[PacoteController::atualizar] Erro: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Erro ao atualizar pacote'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Contratar pacote (admin)
     * POST /admin/pacotes/{pacoteId}/contratar
     */
    public function contratar(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = (int) $request->getAttribute('tenantId');
            $pacoteId = (int) ($args['pacoteId'] ?? 0);
            $data = $request->getParsedBody() ?? [];

            if ($pacoteId <= 0) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'pacoteId inválido'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            if (empty($data['pagante_usuario_id'])) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'pagante_usuario_id é obrigatório'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $stmtPacote = $this->db->prepare("
                SELECT p.*, pl.duracao_dias
                FROM pacotes p
                INNER JOIN planos pl ON pl.id = p.plano_id
                WHERE p.id = ? AND p.tenant_id = ? AND p.ativo = 1
                LIMIT 1
            ");
            $stmtPacote->execute([$pacoteId, $tenantId]);
            $pacote = $stmtPacote->fetch(\PDO::FETCH_ASSOC);

            if (!$pacote) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Pacote não encontrado'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $beneficiarios = isset($data['beneficiarios']) ? (array) $data['beneficiarios'] : [];
            if (count($beneficiarios) > (int) $pacote['qtd_beneficiarios']) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Quantidade de beneficiários excede o limite do pacote'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $this->db->beginTransaction();

            $stmtContrato = $this->db->prepare("
                INSERT INTO pacote_contratos
                (tenant_id, pacote_id, pagante_usuario_id, status, valor_total)
                VALUES (?, ?, ?, 'pendente', ?)
            ");
            $stmtContrato->execute([
                $tenantId,
                $pacoteId,
                (int) $data['pagante_usuario_id'],
                (float) $pacote['valor_total']
            ]);
            $contratoId = (int) $this->db->lastInsertId();

            if (!empty($beneficiarios)) {
                $stmtInsBen = $this->db->prepare("
                    INSERT INTO pacote_beneficiarios
                    (tenant_id, pacote_contrato_id, aluno_id, status)
                    VALUES (?, ?, ?, 'pendente')
                ");
                foreach ($beneficiarios as $alunoId) {
                    $stmtInsBen->execute([$tenantId, $contratoId, (int) $alunoId]);
                }
            }

            $this->db->commit();

            $response->getBody()->write(json_encode([
                'success' => true,
                'pacote_contrato_id' => $contratoId
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("[PacoteController::contratar] Erro: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Erro ao contratar pacote'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Definir beneficiários (admin)
     * POST /admin/pacotes/contratos/{contratoId}/beneficiarios
     */
    public function definirBeneficiarios(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = (int) $request->getAttribute('tenantId');
            $contratoId = (int) ($args['contratoId'] ?? 0);
            $data = $request->getParsedBody() ?? [];
            $beneficiarios = isset($data['beneficiarios']) ? (array) $data['beneficiarios'] : [];

            if ($contratoId <= 0) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'contratoId inválido'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $stmtContrato = $this->db->prepare("
                SELECT pc.id, p.qtd_beneficiarios
                FROM pacote_contratos pc
                INNER JOIN pacotes p ON p.id = pc.pacote_id
                WHERE pc.id = ? AND pc.tenant_id = ?
                LIMIT 1
            ");
            $stmtContrato->execute([$contratoId, $tenantId]);
            $contrato = $stmtContrato->fetch(\PDO::FETCH_ASSOC);
            if (!$contrato) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Contrato não encontrado'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            if (count($beneficiarios) > (int) $contrato['qtd_beneficiarios']) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Quantidade de beneficiários excede o limite do pacote'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $this->db->beginTransaction();
            $stmtDel = $this->db->prepare("DELETE FROM pacote_beneficiarios WHERE pacote_contrato_id = ? AND tenant_id = ?");
            $stmtDel->execute([$contratoId, $tenantId]);

            if (!empty($beneficiarios)) {
                $stmtIns = $this->db->prepare("
                    INSERT INTO pacote_beneficiarios
                    (tenant_id, pacote_contrato_id, aluno_id, status)
                    VALUES (?, ?, ?, 'pendente')
                ");
                foreach ($beneficiarios as $alunoId) {
                    $stmtIns->execute([$tenantId, $contratoId, (int) $alunoId]);
                }
            }

            $this->db->commit();

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Beneficiários atualizados'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("[PacoteController::definirBeneficiarios] Erro: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Erro ao atualizar beneficiários'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Confirmar pagamento do pacote (admin)
     * POST /admin/pacotes/contratos/{contratoId}/confirmar-pagamento
     */
    public function confirmarPagamento(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = (int) $request->getAttribute('tenantId');
            $contratoId = (int) ($args['contratoId'] ?? 0);
            $data = $request->getParsedBody() ?? [];

            if ($contratoId <= 0) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'contratoId inválido'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $stmtContrato = $this->db->prepare("
                SELECT pc.*, p.plano_id, p.plano_ciclo_id, p.valor_total, p.qtd_beneficiarios
                FROM pacote_contratos pc
                INNER JOIN pacotes p ON p.id = pc.pacote_id
                WHERE pc.id = ? AND pc.tenant_id = ?
                LIMIT 1
            ");
            $stmtContrato->execute([$contratoId, $tenantId]);
            $contrato = $stmtContrato->fetch(\PDO::FETCH_ASSOC);
            if (!$contrato) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Contrato não encontrado'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $stmtBenef = $this->db->prepare("
                SELECT pb.id, pb.aluno_id
                FROM pacote_beneficiarios pb
                WHERE pb.pacote_contrato_id = ? AND pb.tenant_id = ?
            ");
            $stmtBenef->execute([$contratoId, $tenantId]);
            $beneficiarios = $stmtBenef->fetchAll(\PDO::FETCH_ASSOC);
            if (empty($beneficiarios)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Nenhum beneficiário definido'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $valorTotal = (float) $contrato['valor_total'];
            $valorRateado = $valorTotal / max(1, count($beneficiarios));

            // Calcular datas pelo ciclo do pacote
            $dataInicio = date('Y-m-d');
            $dataFim = null;
            if (!empty($contrato['plano_ciclo_id'])) {
                $stmtCiclo = $this->db->prepare("SELECT meses FROM plano_ciclos WHERE id = ? AND tenant_id = ? LIMIT 1");
                $stmtCiclo->execute([(int)$contrato['plano_ciclo_id'], $tenantId]);
                $meses = (int) ($stmtCiclo->fetchColumn() ?: 0);
                if ($meses > 0) {
                    $dataFim = date('Y-m-d', strtotime("+{$meses} months"));
                }
            }
            if (!$dataFim) {
                $stmtPlano = $this->db->prepare("SELECT duracao_dias FROM planos WHERE id = ? AND tenant_id = ? LIMIT 1");
                $stmtPlano->execute([(int)$contrato['plano_id'], $tenantId]);
                $duracaoDias = (int) ($stmtPlano->fetchColumn() ?: 30);
                $dataFim = date('Y-m-d', strtotime("+{$duracaoDias} days"));
            }

            $this->db->beginTransaction();

            $stmtUpdContrato = $this->db->prepare("
                UPDATE pacote_contratos
                SET status = 'ativo',
                    pagamento_id = ?,
                    data_inicio = ?,
                    data_fim = ?,
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmtUpdContrato->execute([
                $data['pagamento_id'] ?? null,
                $dataInicio,
                $dataFim,
                $contratoId,
                $tenantId
            ]);

            $stmtStatusAtiva = $this->db->prepare("SELECT id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1");
            $stmtStatusAtiva->execute();
            $statusAtivaId = (int) ($stmtStatusAtiva->fetchColumn() ?: 1);

            $stmtMotivo = $this->db->prepare("SELECT id FROM motivo_matricula WHERE codigo = 'nova' LIMIT 1");
            $stmtMotivo->execute();
            $motivoId = (int) ($stmtMotivo->fetchColumn() ?: 1);

            foreach ($beneficiarios as $ben) {
                $stmtMat = $this->db->prepare("
                    INSERT INTO matriculas
                    (tenant_id, aluno_id, plano_id, plano_ciclo_id, tipo_cobranca,
                     data_matricula, data_inicio, data_vencimento, valor, valor_rateado,
                     status_id, motivo_id, proxima_data_vencimento, pacote_contrato_id, created_at, updated_at)
                    VALUES (?, ?, ?, ?, 'avulso', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmtMat->execute([
                    $tenantId,
                    (int) $ben['aluno_id'],
                    (int) $contrato['plano_id'],
                    !empty($contrato['plano_ciclo_id']) ? (int) $contrato['plano_ciclo_id'] : null,
                    $dataInicio,
                    $dataInicio,
                    $dataFim,
                    $valorRateado,
                    $valorRateado,
                    $statusAtivaId,
                    $motivoId,
                    $dataFim,
                    $contratoId
                ]);
                $matriculaId = (int) $this->db->lastInsertId();

                $stmtUpdBen = $this->db->prepare("
                    UPDATE pacote_beneficiarios
                    SET matricula_id = ?, valor_rateado = ?, status = 'ativo', updated_at = NOW()
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmtUpdBen->execute([
                    $matriculaId,
                    $valorRateado,
                    (int) $ben['id'],
                    $tenantId
                ]);

                $stmtPag = $this->db->prepare("
                    INSERT INTO pagamentos_plano
                    (tenant_id, aluno_id, matricula_id, plano_id, valor, data_vencimento,
                     status_pagamento_id, pacote_contrato_id, observacoes, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, (SELECT id FROM status_pagamento WHERE codigo = 'aprovado' LIMIT 1), ?, 'Pacote rateado', NOW(), NOW())
                ");
                $stmtPag->execute([
                    $tenantId,
                    (int) $ben['aluno_id'],
                    $matriculaId,
                    (int) $contrato['plano_id'],
                    $valorRateado,
                    $dataInicio,
                    $contratoId
                ]);
            }

            $this->db->commit();

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Pacote ativado e matrículas criadas'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("[PacoteController::confirmarPagamento] Erro: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Erro ao confirmar pagamento do pacote'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Excluir contrato de pacote com deleção em cascata (admin)
     * DELETE /admin/pacotes/contratos/{contratoId}
     *
     * Remove em ordem:
     *   1. pagamentos_plano vinculados ao contrato
     *   2. matriculas vinculadas ao contrato (CASCADE remove pagamentos_mercadopago, assinaturas, matriculas_historico)
     *   3. pacote_beneficiarios do contrato
     *   4. pacote_contratos (o próprio contrato)
     */
    public function excluirContrato(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = (int) $request->getAttribute('tenantId');
            $contratoId = (int) ($args['contratoId'] ?? 0);

            if ($contratoId <= 0) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'contratoId inválido'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Verificar se o contrato existe e pertence ao tenant
            $stmtContrato = $this->db->prepare("
                SELECT pc.id, pc.status, pc.pagante_usuario_id,
                       p.nome as pacote_nome
                FROM pacote_contratos pc
                INNER JOIN pacotes p ON p.id = pc.pacote_id
                WHERE pc.id = ? AND pc.tenant_id = ?
                LIMIT 1
            ");
            $stmtContrato->execute([$contratoId, $tenantId]);
            $contrato = $stmtContrato->fetch(\PDO::FETCH_ASSOC);

            if (!$contrato) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Contrato não encontrado'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $this->db->beginTransaction();

            // 1. Deletar pagamentos_plano vinculados ao contrato
            $stmtDelPag = $this->db->prepare("
                DELETE FROM pagamentos_plano
                WHERE pacote_contrato_id = ? AND tenant_id = ?
            ");
            $stmtDelPag->execute([$contratoId, $tenantId]);
            $pagamentosRemovidos = $stmtDelPag->rowCount();

            // 2. Deletar matrículas vinculadas ao contrato
            //    (ON DELETE CASCADE remove automaticamente: pagamentos_mercadopago, assinaturas, matriculas_historico)
            $stmtDelMat = $this->db->prepare("
                DELETE FROM matriculas
                WHERE pacote_contrato_id = ? AND tenant_id = ?
            ");
            $stmtDelMat->execute([$contratoId, $tenantId]);
            $matriculasRemovidas = $stmtDelMat->rowCount();

            // 3. Deletar beneficiários do contrato
            $stmtDelBen = $this->db->prepare("
                DELETE FROM pacote_beneficiarios
                WHERE pacote_contrato_id = ? AND tenant_id = ?
            ");
            $stmtDelBen->execute([$contratoId, $tenantId]);
            $beneficiariosRemovidos = $stmtDelBen->rowCount();

            // 4. Deletar o próprio contrato
            $stmtDelContrato = $this->db->prepare("
                DELETE FROM pacote_contratos
                WHERE id = ? AND tenant_id = ?
            ");
            $stmtDelContrato->execute([$contratoId, $tenantId]);

            $this->db->commit();

            error_log("[PacoteController::excluirContrato] Contrato #{$contratoId} excluído em cascata: "
                . "{$pagamentosRemovidos} pagamentos, {$matriculasRemovidas} matrículas, "
                . "{$beneficiariosRemovidos} beneficiários removidos");

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Contrato excluído com sucesso',
                'detalhes' => [
                    'contrato_id' => $contratoId,
                    'pacote_nome' => $contrato['pacote_nome'],
                    'pagamentos_removidos' => $pagamentosRemovidos,
                    'matriculas_removidas' => $matriculasRemovidas,
                    'beneficiarios_removidos' => $beneficiariosRemovidos,
                ]
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("[PacoteController::excluirContrato] Erro: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Erro ao excluir contrato do pacote'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}
