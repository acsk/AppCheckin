<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Usuario;
use App\Models\Plano;

class AdminController
{
    private Usuario $usuarioModel;
    private Plano $planoModel;

    public function __construct()
    {
        $db = require __DIR__ . '/../../config/database.php';
        $tenantId = 1; // TODO: Obter do middleware
        
        $this->usuarioModel = new Usuario($db);
        $this->planoModel = new Plano($db, $tenantId);
    }

    /**
     * Dashboard - Estatísticas gerais
     */
    public function dashboard(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId', 1);
        $db = require __DIR__ . '/../../config/database.php';

        // Total de alunos
        $stmtTotalAlunos = $db->prepare("
            SELECT COUNT(DISTINCT tup.usuario_id) as total 
            FROM tenant_usuario_papel tup
            INNER JOIN usuarios u ON u.id = tup.usuario_id
            WHERE tup.tenant_id = ? AND tup.papel_id = 1 AND tup.ativo = 1
        ");
        $stmtTotalAlunos->execute([$tenantId]);
        $totalAlunos = $stmtTotalAlunos->fetch()['total'];

        // Alunos com status detalhado
        $stmtStatusAlunos = $db->prepare("
            SELECT 
                COUNT(DISTINCT tup.usuario_id) as total,
                SUM(CASE 
                    WHEN tup.ativo = 1
                    THEN 1 ELSE 0 
                END) as ativos,
                SUM(CASE 
                    WHEN tup.ativo = 0
                    THEN 1 ELSE 0 
                END) as inativos
            FROM tenant_usuario_papel tup
            INNER JOIN usuarios u ON u.id = tup.usuario_id
            WHERE tup.tenant_id = ? AND tup.papel_id = 1
        ");
        $stmtStatusAlunos->execute([$tenantId]);
        $statusAlunos = $stmtStatusAlunos->fetch();

        // Check-ins hoje
        $stmtCheckinsHoje = $db->prepare("
            SELECT COUNT(*) as total 
            FROM checkins c
            WHERE c.tenant_id = ? 
            AND DATE(c.created_at) = CURDATE()
        ");
        $stmtCheckinsHoje->execute([$tenantId]);
        $checkinsHoje = $stmtCheckinsHoje->fetch()['total'];

        // Check-ins do mês
        $stmtCheckinsMes = $db->prepare("
            SELECT COUNT(*) as total 
            FROM checkins c
            WHERE c.tenant_id = ? 
            AND YEAR(c.created_at) = YEAR(CURDATE())
            AND MONTH(c.created_at) = MONTH(CURDATE())
        ");
        $stmtCheckinsMes->execute([$tenantId]);
        $checkinsMes = $stmtCheckinsMes->fetch()['total'];

        // Contas a vencer nos próximos 7 dias
        $stmtPlanosVencendo = $db->prepare("
            SELECT COUNT(DISTINCT cr.usuario_id) as total 
            FROM contas_receber cr
            INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = cr.usuario_id AND tup.ativo = 1
            WHERE tup.tenant_id = ? 
            AND cr.data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            AND cr.status IN ('pendente', 'vencido')
        ");
        $stmtPlanosVencendo->execute([$tenantId]);
        $planosVencendo = $stmtPlanosVencendo->fetch()['total'];

        // Receita mensal esperada (soma das contas a receber do mês)
        $stmtReceita = $db->prepare("
            SELECT SUM(cr.valor) as receita
            FROM contas_receber cr
            WHERE cr.tenant_id = ?
            AND cr.referencia_mes = DATE_FORMAT(CURDATE(), '%Y-%m')
        ");
        $stmtReceita->execute([$tenantId]);
        $receitaMensal = $stmtReceita->fetch()['receita'] ?? 0;

        // Novos alunos no mês
        $stmtNovosAlunos = $db->prepare("
            SELECT COUNT(DISTINCT tup.usuario_id) as total 
            FROM tenant_usuario_papel tup
            INNER JOIN usuarios u ON u.id = tup.usuario_id
            WHERE tup.tenant_id = ? 
            AND tup.papel_id = 1
            AND YEAR(tup.created_at) = YEAR(CURDATE())
            AND MONTH(tup.created_at) = MONTH(CURDATE())
        ");
        $stmtNovosAlunos->execute([$tenantId]);
        $novosAlunos = $stmtNovosAlunos->fetch()['total'];

        // Contas a receber - total pendente
        $stmtContasPendentes = $db->prepare("
            SELECT 
                COUNT(*) as quantidade,
                SUM(valor) as total
            FROM contas_receber
            WHERE tenant_id = ? AND status = 'pendente'
        ");
        $stmtContasPendentes->execute([$tenantId]);
        $contasPendentes = $stmtContasPendentes->fetch();

        // Contas vencidas
        $stmtContasVencidas = $db->prepare("
            SELECT 
                COUNT(*) as quantidade,
                SUM(valor) as total
            FROM contas_receber
            WHERE tenant_id = ? 
            AND status IN ('pendente', 'vencido')
            AND data_vencimento < CURDATE()
        ");
        $stmtContasVencidas->execute([$tenantId]);
        $contasVencidas = $stmtContasVencidas->fetch();

        $stats = [
            'total_alunos' => (int) $totalAlunos,
            'alunos_ativos' => (int) $statusAlunos['ativos'],
            'alunos_inativos' => (int) $statusAlunos['inativos'],
            'novos_alunos_mes' => (int) $novosAlunos,
            'total_checkins_hoje' => (int) $checkinsHoje,
            'total_checkins_mes' => (int) $checkinsMes,
            'planos_vencendo' => (int) $planosVencendo,
            'receita_mensal' => (float) $receitaMensal,
            'contas_pendentes_qtd' => (int) ($contasPendentes['quantidade'] ?? 0),
            'contas_pendentes_valor' => (float) ($contasPendentes['total'] ?? 0),
            'contas_vencidas_qtd' => (int) ($contasVencidas['quantidade'] ?? 0),
            'contas_vencidas_valor' => (float) ($contasVencidas['total'] ?? 0)
        ];

        $response->getBody()->write(json_encode($stats));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Listar todos os alunos (apenas papel_id = 1)
     */
    public function listarAlunos(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId', 1);
        $db = require __DIR__ . '/../../config/database.php';

        $stmt = $db->prepare("
            SELECT 
                u.id, u.nome, u.email, tup.papel_id, 
                u.foto_base64, u.created_at, u.updated_at,
                m.plano_id, p.nome as plano_nome, p.valor as plano_valor,
                COUNT(DISTINCT c.id) as total_checkins,
                MAX(c.created_at) as ultimo_checkin,
                
                -- Verifica se tem conta paga no período atual
                CASE 
                    WHEN EXISTS (
                        SELECT 1 FROM contas_receber cr
                        WHERE cr.usuario_id = u.id
                        AND cr.tenant_id = tup.tenant_id
                        AND cr.status = 'pago'
                        AND cr.data_vencimento <= CURDATE()
                        AND DATE_ADD(cr.data_vencimento, INTERVAL COALESCE(cr.intervalo_dias, 30) DAY) >= CURDATE()
                    ) THEN 1
                    ELSE 0
                END as possui_pagamento_ativo,
                
                -- Última conta pendente
                (
                    SELECT cr2.id FROM contas_receber cr2
                    WHERE cr2.usuario_id = u.id
                    AND cr2.tenant_id = tup.tenant_id
                    AND cr2.status = 'pendente'
                    ORDER BY cr2.data_vencimento ASC
                    LIMIT 1
                ) as ultima_conta_pendente_id,
                
                -- Valor da última conta pendente
                (
                    SELECT cr3.valor FROM contas_receber cr3
                    WHERE cr3.usuario_id = u.id
                    AND cr3.tenant_id = tup.tenant_id
                    AND cr3.status = 'pendente'
                    ORDER BY cr3.data_vencimento ASC
                    LIMIT 1
                ) as ultima_conta_pendente_valor
                
            FROM usuarios u
            INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = u.id AND tup.ativo = 1
            LEFT JOIN alunos al ON al.usuario_id = u.id
            LEFT JOIN matriculas m ON m.aluno_id = al.id AND m.status_id = (SELECT id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1)
            LEFT JOIN planos p ON p.id = m.plano_id
            LEFT JOIN checkins c ON al.id = c.aluno_id
            WHERE tup.tenant_id = ? AND tup.papel_id = 1
            GROUP BY u.id
            ORDER BY u.nome ASC
        ");
        $stmt->execute([$tenantId]);
        $alunos = $stmt->fetchAll();

        // Estruturar com objeto plano e status real
        foreach ($alunos as &$aluno) {
            // Define status baseado em pagamento ativo
            $aluno['status_ativo'] = (bool) $aluno['possui_pagamento_ativo'];
            
            if ($aluno['plano_id']) {
                $aluno['plano'] = [
                    'id' => $aluno['plano_id'],
                    'nome' => $aluno['plano_nome'],
                    'valor' => $aluno['plano_valor']
                ];
            } else {
                $aluno['plano'] = null;
            }
            
            // Remove campos auxiliares
            unset(
                $aluno['plano_nome'], 
                $aluno['plano_valor'], 
                $aluno['possui_pagamento_ativo']
            );
        }

        $response->getBody()->write(json_encode([
            'alunos' => $alunos,
            'total' => count($alunos)
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Listar alunos (dados básicos) - nome/email
     */
    public function listarAlunosBasico(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId', 1);
        $db = require __DIR__ . '/../../config/database.php';

        $stmt = $db->prepare("
            SELECT u.id, u.nome, u.email
            FROM usuarios u
            INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = u.id AND tup.ativo = 1
            INNER JOIN alunos al ON al.usuario_id = u.id
            LEFT JOIN matriculas m ON m.aluno_id = al.id AND m.status_id = (SELECT id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1)
            WHERE tup.tenant_id = ? 
            AND tup.papel_id = 1
            AND m.id IS NOT NULL
            ORDER BY u.nome ASC
        ");
        $stmt->execute([$tenantId]);
        $alunos = $stmt->fetchAll();

        $response->getBody()->write(json_encode([
            'alunos' => $alunos,
            'total' => count($alunos)
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Criar novo aluno
     */
    public function criarAluno(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId', 1);
        $adminId = $request->getAttribute('userId', null);
        $data = $request->getParsedBody();

        $errors = [];

        if (empty($data['nome'])) {
            $errors[] = 'Nome é obrigatório';
        }

        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email inválido';
        } elseif ($this->usuarioModel->emailExists($data['email'], null, $tenantId)) {
            $errors[] = 'Email já cadastrado';
        }

        if (empty($data['senha']) || strlen($data['senha']) < 6) {
            $errors[] = 'Senha deve ter no mínimo 6 caracteres';
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode(['errors' => $errors]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        // Criar usuário com papel_id = 1 (aluno)
        $usuarioId = $this->usuarioModel->create($data, $tenantId);

        // Definir papel de aluno em tenant_usuario_papel (já feito pelo create)
        // Não precisa mais atualizar role_id

        $usuario = $this->usuarioModel->findById($usuarioId, $tenantId);

        $response->getBody()->write(json_encode([
            'message' => 'Aluno criado com sucesso',
            'aluno' => $usuario
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    /**
     * Atualizar aluno
     */
    public function atualizarAluno(Request $request, Response $response, array $args): Response
    {
        $alunoId = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId', 1);
        $data = $request->getParsedBody();

        $aluno = $this->usuarioModel->findById($alunoId, $tenantId);

        if (!$aluno) {
            $response->getBody()->write(json_encode(['error' => 'Aluno não encontrado']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Validar email se for alterado
        if (isset($data['email']) && $data['email'] !== $aluno['email']) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $response->getBody()->write(json_encode(['errors' => ['Email inválido']]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
            }
            if ($this->usuarioModel->emailExists($data['email'], $alunoId, $tenantId)) {
                $response->getBody()->write(json_encode(['errors' => ['Email já cadastrado']]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
            }
        }

        // Validar senha se for alterada
        if (isset($data['senha']) && !empty($data['senha']) && strlen($data['senha']) < 6) {
            $response->getBody()->write(json_encode(['errors' => ['Senha deve ter no mínimo 6 caracteres']]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        // Remover campos de plano - agora são gerenciados via matrícula
        unset($data['plano_id']);
        unset($data['data_vencimento_plano']);

        $updated = $this->usuarioModel->update($alunoId, $data);

        if (!$updated) {
            $response->getBody()->write(json_encode(['error' => 'Nenhum dado foi atualizado']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $alunoAtualizado = $this->usuarioModel->findById($alunoId, $tenantId);

        $response->getBody()->write(json_encode([
            'message' => 'Aluno atualizado com sucesso',
            'aluno' => $alunoAtualizado
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Buscar aluno por ID
     */
    public function buscarAluno(Request $request, Response $response, array $args): Response
    {
        $alunoId = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId', 1);

        $aluno = $this->usuarioModel->findById($alunoId, $tenantId);

        if (!$aluno) {
            $response->getBody()->write(json_encode(['error' => 'Aluno não encontrado']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode($aluno));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Desativar/Excluir aluno
     */
    public function desativarAluno(Request $request, Response $response, array $args): Response
    {
        $alunoId = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId', 1);
        $db = require __DIR__ . '/../../config/database.php';

        $aluno = $this->usuarioModel->findById($alunoId, $tenantId);

        if (!$aluno) {
            $response->getBody()->write(json_encode(['error' => 'Aluno não encontrado']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Buscar aluno_id a partir do usuario_id
        $stmtAlunoId = $db->prepare("SELECT id FROM alunos WHERE usuario_id = ?");
        $stmtAlunoId->execute([$alunoId]);
        $alunoRecord = $stmtAlunoId->fetch();
        $realAlunoId = $alunoRecord ? $alunoRecord['id'] : null;

        // Verificar se o aluno tem check-ins
        $stmtCheckins = $db->prepare("SELECT COUNT(*) as total FROM checkins WHERE aluno_id = ?");
        $stmtCheckins->execute([$realAlunoId]);
        $totalCheckins = $stmtCheckins->fetch()['total'];

        if ($totalCheckins > 0) {
            // Apenas desativar (remover plano)
            $this->usuarioModel->update($alunoId, [
                'plano_id' => null,
                'data_vencimento_plano' => null
            ]);

            $response->getBody()->write(json_encode([
                'message' => 'Aluno desativado com sucesso (plano removido)'
            ]));
        } else {
            // Pode deletar completamente - mas precisa verificar tenant via tenant_usuario_papel
            $stmt = $db->prepare("
                DELETE u FROM usuarios u
                INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = u.id
                WHERE u.id = ? AND tup.tenant_id = ?
            ");
            $stmt->execute([$alunoId, $tenantId]);

            $response->getBody()->write(json_encode([
                'message' => 'Aluno excluído com sucesso'
            ]));
        }

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Registrar mudança de plano no histórico
     */
    private function registrarHistoricoPlano(
        int $usuarioId,
        ?int $planoAnteriorId,
        ?int $planoNovoId,
        string $dataInicio,
        ?string $dataVencimento,
        ?int $criadoPor,
        string $motivo
    ): ?int {
        $db = require __DIR__ . '/../../config/database.php';
        
        $valorPago = null;
        if ($planoNovoId) {
            $planoNovo = $this->planoModel->findById($planoNovoId);
            $valorPago = $planoNovo['valor'] ?? null;
        }
        
        $stmt = $db->prepare("
            INSERT INTO historico_planos 
            (usuario_id, plano_anterior_id, plano_novo_id, data_inicio, data_vencimento, valor_pago, motivo, criado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $usuarioId,
            $planoAnteriorId,
            $planoNovoId,
            $dataInicio,
            $dataVencimento,
            $valorPago,
            $motivo,
            $criadoPor
        ]);
        
        return (int) $db->lastInsertId();
    }

    /**
     * Criar conta a receber
     */
    private function criarContaReceber(
        int $tenantId,
        int $usuarioId,
        int $planoId,
        ?int $historicoPlanoId,
        float $valor,
        string $dataVencimento,
        int $intervaloDias,
        ?int $criadoPor
    ): ?int {
        $db = require __DIR__ . '/../../config/database.php';
        
        $referenciaMes = date('Y-m', strtotime($dataVencimento));
        
        $stmt = $db->prepare("
            INSERT INTO contas_receber 
            (tenant_id, usuario_id, plano_id, historico_plano_id, valor, data_vencimento, 
             status, referencia_mes, recorrente, intervalo_dias, criado_por)
            VALUES (?, ?, ?, ?, ?, ?, 'pendente', ?, true, ?, ?)
        ");
        
        $stmt->execute([
            $tenantId,
            $usuarioId,
            $planoId,
            $historicoPlanoId,
            $valor,
            $dataVencimento,
            $referenciaMes,
            $intervaloDias,
            $criadoPor
        ]);
        
        return (int) $db->lastInsertId();
    }

    /**
     * Buscar histórico de planos de um aluno
     */
    public function historicoPlanos(Request $request, Response $response, array $args): Response
    {
        $alunoId = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId', 1);
        $db = require __DIR__ . '/../../config/database.php';

        // Verificar se aluno existe e pertence ao tenant
        $aluno = $this->usuarioModel->findById($alunoId, $tenantId);
        if (!$aluno) {
            $response->getBody()->write(json_encode(['error' => 'Aluno não encontrado']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $stmt = $db->prepare("
            SELECT 
                h.*,
                pa.nome as plano_anterior_nome,
                pa.valor as plano_anterior_valor,
                pn.nome as plano_novo_nome,
                pn.valor as plano_novo_valor,
                u.nome as criado_por_nome
            FROM historico_planos h
            LEFT JOIN planos pa ON h.plano_anterior_id = pa.id
            LEFT JOIN planos pn ON h.plano_novo_id = pn.id
            LEFT JOIN usuarios u ON h.criado_por = u.id
            WHERE h.usuario_id = ?
            ORDER BY h.created_at DESC
        ");
        
        $stmt->execute([$alunoId]);
        $historico = $stmt->fetchAll();

        $response->getBody()->write(json_encode([
            'historico' => $historico,
            'total' => count($historico)
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Listar contratos de pacotes com filtro por status
     * GET /admin/pacote-contratos?status=pendente
     * GET /admin/pacote-contratos?status=ativo
     * Status disponíveis: pendente, ativo, cancelado, expirado
     */
    public function listarContratosPackage(Request $request, Response $response): Response
    {
        try {
            $tenantId = (int) $request->getAttribute('tenantId');
            $db = require __DIR__ . '/../../config/database.php';
            
            $queryParams = $request->getQueryParams();
            $status = strtolower(trim($queryParams['status'] ?? 'pendente'));
            
            // Validar status
            $statusValidos = ['pendente', 'ativo', 'cancelado', 'expirado'];
            if (!in_array($status, $statusValidos, true)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Status inválido. Use: ' . implode(', ', $statusValidos)
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Se pendente, lista com info básica
            if ($status === 'pendente' || $status === 'cancelado' || $status === 'expirado') {
                // Para pendente/cancelado/expirado: info básica
                $stmt = $db->prepare("
                    SELECT 
                        pc.id as contrato_id,
                        pc.status,
                        pc.valor_total,
                        pc.data_inicio,
                        pc.data_fim,
                        pc.created_at,
                        pc.updated_at,
                        p.nome as pacote_nome,
                        p.qtd_beneficiarios,
                        u.id as pagante_usuario_id,
                        u.nome as pagante_nome,
                        u.email as pagante_email,
                        COUNT(DISTINCT pb.id) as beneficiarios_adicionados
                    FROM pacote_contratos pc
                    INNER JOIN pacotes p ON p.id = pc.pacote_id
                    INNER JOIN usuarios u ON u.id = pc.pagante_usuario_id
                    LEFT JOIN pacote_beneficiarios pb ON pb.pacote_contrato_id = pc.id
                    WHERE pc.tenant_id = ? AND pc.status = ?
                    GROUP BY pc.id
                    ORDER BY pc.created_at DESC
                ");
                $stmt->execute([$tenantId, $status]);
                $contratos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'status_filtro' => $status,
                    'contratos' => $contratos,
                    'total' => count($contratos)
                ], JSON_UNESCAPED_UNICODE));
            } elseif ($status === 'ativo') {
                $stmt = $db->prepare("
                    SELECT 
                        pc.id as contrato_id,
                        pc.status,
                        pc.valor_total,
                        pc.data_inicio,
                        pc.data_fim,
                        pc.created_at,
                        pc.updated_at,
                        p.id as pacote_id,
                        p.nome as pacote_nome,
                        p.qtd_beneficiarios,
                        p.plano_id,
                        pl.nome as plano_nome,
                        u.id as pagante_usuario_id,
                        u.nome as pagante_nome,
                        u.email as pagante_email,
                        COUNT(DISTINCT pb.id) as beneficiarios_adicionados
                    FROM pacote_contratos pc
                    INNER JOIN pacotes p ON p.id = pc.pacote_id
                    LEFT JOIN planos pl ON pl.id = p.plano_id
                    INNER JOIN usuarios u ON u.id = pc.pagante_usuario_id
                    LEFT JOIN pacote_beneficiarios pb ON pb.pacote_contrato_id = pc.id
                    WHERE pc.tenant_id = ? AND pc.status = ?
                    GROUP BY pc.id
                    ORDER BY pc.data_inicio DESC
                ");
                $stmt->execute([$tenantId, $status]);
                $contratos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                // Para cada contrato ativo, buscar pagante e beneficiários
                $contratosFormatados = [];
                foreach ($contratos as $contrato) {
                    try {
                        $contratoId = (int) $contrato['contrato_id'];
                        
                        // Buscar matrículas criadas para este contrato
                        $stmtMatriculas = $db->prepare("
                            SELECT 
                                m.id as matricula_id,
                                m.aluno_id,
                                a.nome as aluno_nome,
                                sm.codigo as status_codigo,
                                m.data_inicio,
                                m.data_vencimento
                            FROM matriculas m
                            INNER JOIN alunos a ON a.id = m.aluno_id
                            INNER JOIN status_matricula sm ON sm.id = m.status_id
                            WHERE m.pacote_contrato_id = ?
                            ORDER BY m.created_at ASC
                        ");
                        $stmtMatriculas->execute([$contratoId]);
                        $matriculas = $stmtMatriculas->fetchAll(\PDO::FETCH_ASSOC);

                        // Buscar pagante como aluno (se tiver)
                        $stmtPagante = $db->prepare("
                            SELECT id, nome FROM alunos WHERE usuario_id = ? LIMIT 1
                        ");
                        $stmtPagante->execute([$contrato['pagante_usuario_id']]);
                        $pagante = $stmtPagante->fetch(\PDO::FETCH_ASSOC);

                        // Buscar beneficiários
                        $stmtBenef = $db->prepare("
                            SELECT 
                                pb.id as beneficiario_id,
                                pb.aluno_id,
                                a.nome,
                                a.usuario_id
                            FROM pacote_beneficiarios pb
                            INNER JOIN alunos a ON a.id = pb.aluno_id
                            WHERE pb.pacote_contrato_id = ?
                            ORDER BY pb.created_at ASC
                        ");
                        $stmtBenef->execute([$contratoId]);
                        $beneficiarios = $stmtBenef->fetchAll(\PDO::FETCH_ASSOC);

                        $contratosFormatados[] = [
                            'contrato' => $contrato,
                            'pagante' => $pagante ?: null,
                            'beneficiarios' => $beneficiarios,
                            'matriculas_geradas' => $matriculas,
                            'qtd_pessoas' => count($beneficiarios) + ($pagante ? 1 : 0),
                            'qtd_matriculas_faltando' => max(0, (count($beneficiarios) + ($pagante ? 1 : 0)) - count($matriculas))
                        ];
                    } catch (\Exception $loopError) {
                        error_log("[AdminController::listarContratosPackage] Erro no loop contrato {$contratoId}: " . $loopError->getMessage());
                        error_log("[AdminController::listarContratosPackage] Stack: " . $loopError->getTraceAsString());
                        throw $loopError; // Re-throw para ser capturado pelo catch externo
                    }
                }

                $response->getBody()->write(json_encode([
                    'success' => true,
                    'status_filtro' => $status,
                    'contratos' => $contratosFormatados,
                    'total' => count($contratosFormatados)
                ], JSON_UNESCAPED_UNICODE));
            }
            
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            error_log("[AdminController::listarContratosPackage] Erro: " . $e->getMessage());
            error_log("[AdminController::listarContratosPackage] Stack: " . $e->getTraceAsString());
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Erro ao listar contratos',
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Gerar matrículas para um pacote específico
     * POST /admin/pacote-contratos/{contratoId}/gerar-matriculas
     */
    public function gerarMatriculasPackage(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = (int) $request->getAttribute('tenantId');
            $contratoId = (int) ($args['contratoId'] ?? 0);
            $db = require __DIR__ . '/../../config/database.php';

            if ($contratoId <= 0) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'contratoId inválido'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Buscar contrato
            $stmtContrato = $db->prepare("
                SELECT pc.*, p.plano_id, p.plano_ciclo_id, p.valor_total,
                       COALESCE(pc2.permite_recorrencia, 0) as permite_recorrencia
                FROM pacote_contratos pc
                INNER JOIN pacotes p ON p.id = pc.pacote_id
                LEFT JOIN plano_ciclos pc2 ON pc2.id = p.plano_ciclo_id
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

            if (($contrato['status'] ?? '') !== 'ativo') {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Contrato não está ativo. Status atual: ' . $contrato['status']
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $db->beginTransaction();

            // Buscar PAGANTE
            $pagante_usuario_id = $contrato['pagante_usuario_id'] ?? null;
            $pagante_aluno_id = null;
            
            if ($pagante_usuario_id) {
                $stmtAlunoUsuario = $db->prepare("
                    SELECT id FROM alunos
                    WHERE usuario_id = ?
                    ORDER BY id ASC
                    LIMIT 1
                ");
                $stmtAlunoUsuario->execute([$pagante_usuario_id]);
                $pagante_aluno_id = (int) ($stmtAlunoUsuario->fetchColumn() ?: 0);
            }

            // Buscar BENEFICIÁRIOS
            $stmtBenef = $db->prepare("
                SELECT pb.id, pb.aluno_id
                FROM pacote_beneficiarios pb
                WHERE pb.pacote_contrato_id = ?
            ");
            $stmtBenef->execute([$contratoId]);
            $beneficiarios = $stmtBenef->fetchAll(\PDO::FETCH_ASSOC);

            // Montar lista COMPLETA
            $todasAsMatriculas = [];
            if ($pagante_aluno_id) {
                $todasAsMatriculas[] = [
                    'id' => 'pagante_' . $pagante_usuario_id,
                    'aluno_id' => $pagante_aluno_id,
                    'tipo' => 'pagante'
                ];
            }
            
            foreach ($beneficiarios as $b) {
                $b['tipo'] = 'beneficiario';
                $todasAsMatriculas[] = $b;
            }

            if (empty($todasAsMatriculas)) {
                $db->rollBack();
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Nenhuma matrícula para criar (sem pagante e sem beneficiários)'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $valorTotal = (float) $contrato['valor_total'];
            $valorRateado = $valorTotal / max(1, count($todasAsMatriculas));

            // Data de início/fim
            $dataInicio = $contrato['data_inicio'] ?? date('Y-m-d');
            $dataFim = $contrato['data_fim'];
            
            error_log("[AdminController::gerarMatriculasPackage] Contrato #{$contratoId}: data_inicio={$dataInicio}, data_fim={$dataFim}");
            
            // Se não tem data_fim, calcular baseado no ciclo/duração
            if (!$dataFim) {
                if (!empty($contrato['plano_ciclo_id'])) {
                    $stmtCiclo = $db->prepare("SELECT meses FROM plano_ciclos WHERE id = ? LIMIT 1");
                    $stmtCiclo->execute([(int) $contrato['plano_ciclo_id']]);
                    $meses = (int) ($stmtCiclo->fetchColumn() ?: 1);
                    $dataFim = date('Y-m-d', strtotime("+{$meses} months", strtotime($dataInicio)));
                    error_log("[AdminController::gerarMatriculasPackage] data_fim calculada a partir de ciclo ({$meses} meses): {$dataFim}");
                } else {
                    // Fallback: 30 dias
                    $dataFim = date('Y-m-d', strtotime('+30 days', strtotime($dataInicio)));
                    error_log("[AdminController::gerarMatriculasPackage] data_fim calculada com fallback (30 dias): {$dataFim}");
                }
            }
            
            error_log("[AdminController::gerarMatriculasPackage] Processando {" . count($todasAsMatriculas) . "} pessoas, valor_rateado={$valorRateado}, vencimento={$dataFim}");

            // Buscar status ativa
            $stmtStatusAtiva = $db->prepare("SELECT id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1");
            $stmtStatusAtiva->execute();
            $statusAtivaId = (int) ($stmtStatusAtiva->fetchColumn() ?: 1);

            $stmtMotivo = $db->prepare("SELECT id FROM motivo_matricula WHERE codigo = 'nova' LIMIT 1");
            $stmtMotivo->execute();
            $motivoId = (int) ($stmtMotivo->fetchColumn() ?: 1);

            $matriculasCriadas = [];

            foreach ($todasAsMatriculas as $ben) {
                $ehPagante = ($ben['tipo'] === 'pagante');
                $tipoCobranca = (bool) ($contrato['permite_recorrencia'] ?? false) ? 'recorrente' : 'avulso';
                
                // Verificar se já existe
                $stmtVerificar = $db->prepare("
                    SELECT id, status_id
                    FROM matriculas
                    WHERE aluno_id = ? AND pacote_contrato_id = ? AND tenant_id = ?
                    ORDER BY id DESC LIMIT 1
                ");
                $stmtVerificar->execute([(int) $ben['aluno_id'], $contratoId, $tenantId]);
                $matriculaExistente = $stmtVerificar->fetch(\PDO::FETCH_ASSOC);
                
                if ($matriculaExistente) {
                    // ATUALIZAR
                    $stmtUpdate = $db->prepare("
                        UPDATE matriculas
                        SET status_id = ?, data_vencimento = ?, proxima_data_vencimento = ?,
                            valor = ?, valor_rateado = ?, updated_at = NOW()
                        WHERE id = ? AND tenant_id = ?
                    ");
                    $stmtUpdate->execute([
                        $statusAtivaId,
                        $dataFim,
                        $dataFim,
                        $valorRateado,
                        $valorRateado,
                        $matriculaExistente['id'],
                        $tenantId
                    ]);
                    $matriculaId = (int) $matriculaExistente['id'];
                } else {
                    // CRIAR
                    $stmtMat = $db->prepare("
                        INSERT INTO matriculas
                        (tenant_id, aluno_id, plano_id, plano_ciclo_id, tipo_cobranca,
                         data_matricula, data_inicio, data_vencimento, valor, valor_rateado,
                         status_id, motivo_id, proxima_data_vencimento, pacote_contrato_id, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $stmtMat->execute([
                        $tenantId,
                        (int) $ben['aluno_id'],
                        (int) $contrato['plano_id'],
                        !empty($contrato['plano_ciclo_id']) ? (int) $contrato['plano_ciclo_id'] : null,
                        $tipoCobranca,
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
                    $matriculaId = (int) $db->lastInsertId();
                }

                $matriculasCriadas[] = [
                    'aluno_id' => $ben['aluno_id'],
                    'matricula_id' => $matriculaId,
                    'tipo' => $ben['tipo'],
                    'valor_rateado' => $valorRateado,
                    'vencimento' => $dataFim
                ];

                // Se é pagante e recorrente, criar/atualizar assinatura
                if ($ehPagante && (bool) ($contrato['permite_recorrencia'] ?? false)) {
                    $stmtStatusAssinatura = $db->prepare("SELECT id FROM assinatura_status WHERE codigo = 'ativa' LIMIT 1");
                    $stmtStatusAssinatura->execute();
                    $statusAssinaturaId = $stmtStatusAssinatura->fetchColumn() ?: 1;

                    $stmtAssinComprovacao = $db->prepare("
                        SELECT id FROM assinaturas
                        WHERE matricula_id = ? AND tenant_id = ?
                        LIMIT 1
                    ");
                    $stmtAssinComprovacao->execute([$matriculaId, $tenantId]);
                    $assinaturaExistente = $stmtAssinComprovacao->fetchColumn();

                    if (!$assinaturaExistente) {
                        $stmtGateway = $db->prepare("SELECT id FROM assinatura_gateways WHERE codigo = 'mercadopago' LIMIT 1");
                        $stmtGateway->execute();
                        $gatewayId = (int) ($stmtGateway->fetchColumn() ?: 1);

                        $stmtFreq = $db->prepare("SELECT id FROM assinatura_frequencias WHERE codigo = 'mensal' LIMIT 1");
                        $stmtFreq->execute();
                        $frequenciaId = (int) ($stmtFreq->fetchColumn() ?: 4);

                        $stmtAssinatura = $db->prepare("
                            INSERT INTO assinaturas
                            (tenant_id, matricula_id, aluno_id, plano_id,
                             gateway_id, external_reference, status_id, status_gateway,
                             valor, frequencia_id, dia_cobranca,
                             data_inicio, proxima_cobranca, tipo_cobranca, criado_em, atualizado_em)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'approved', ?, ?, ?, CURDATE(), ?, 'recorrente', NOW(), NOW())
                        ");
                        $stmtAssinatura->execute([
                            $tenantId,
                            $matriculaId,
                            (int) $ben['aluno_id'],
                            (int) $contrato['plano_id'],
                            $gatewayId,
                            'pacote_' . $contratoId . '_' . $matriculaId,
                            $statusAssinaturaId,
                            $valorRateado,
                            $frequenciaId,
                            (int) date('d'),
                            $dataFim
                        ]);
                    }
                }
            }

            $db->commit();

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Matrículas geradas com sucesso',
                'contrato_id' => $contratoId,
                'matriculas_criadas' => count($matriculasCriadas),
                'matriculas' => $matriculasCriadas
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log("[AdminController::gerarMatriculasPackage] Erro: " . $e->getMessage());
            error_log("[AdminController::gerarMatriculasPackage] Stack: " . $e->getTraceAsString());
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Erro ao gerar matrículas',
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}
