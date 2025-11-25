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
            SELECT COUNT(*) as total 
            FROM usuarios 
            WHERE tenant_id = ? AND role_id = 1
        ");
        $stmtTotalAlunos->execute([$tenantId]);
        $totalAlunos = $stmtTotalAlunos->fetch()['total'];

        // Alunos com status detalhado
        $stmtStatusAlunos = $db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE 
                    WHEN plano_id IS NOT NULL 
                    AND (data_vencimento_plano IS NULL OR data_vencimento_plano >= CURDATE())
                    THEN 1 ELSE 0 
                END) as ativos,
                SUM(CASE 
                    WHEN plano_id IS NULL 
                    OR (data_vencimento_plano IS NOT NULL AND data_vencimento_plano < CURDATE())
                    THEN 1 ELSE 0 
                END) as inativos
            FROM usuarios 
            WHERE tenant_id = ? AND role_id = 1
        ");
        $stmtStatusAlunos->execute([$tenantId]);
        $statusAlunos = $stmtStatusAlunos->fetch();

        // Check-ins hoje
        $stmtCheckinsHoje = $db->prepare("
            SELECT COUNT(*) as total 
            FROM checkins c
            INNER JOIN usuarios u ON c.usuario_id = u.id
            WHERE u.tenant_id = ? 
            AND DATE(c.created_at) = CURDATE()
        ");
        $stmtCheckinsHoje->execute([$tenantId]);
        $checkinsHoje = $stmtCheckinsHoje->fetch()['total'];

        // Check-ins do mês
        $stmtCheckinsMes = $db->prepare("
            SELECT COUNT(*) as total 
            FROM checkins c
            INNER JOIN usuarios u ON c.usuario_id = u.id
            WHERE u.tenant_id = ? 
            AND YEAR(c.created_at) = YEAR(CURDATE())
            AND MONTH(c.created_at) = MONTH(CURDATE())
        ");
        $stmtCheckinsMes->execute([$tenantId]);
        $checkinsMes = $stmtCheckinsMes->fetch()['total'];

        // Planos vencendo nos próximos 7 dias
        $stmtPlanosVencendo = $db->prepare("
            SELECT COUNT(*) as total 
            FROM usuarios 
            WHERE tenant_id = ? 
            AND role_id = 1
            AND data_vencimento_plano BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ");
        $stmtPlanosVencendo->execute([$tenantId]);
        $planosVencendo = $stmtPlanosVencendo->fetch()['total'];

        // Receita mensal (soma dos valores dos planos ativos)
        $stmtReceita = $db->prepare("
            SELECT SUM(p.valor) as receita
            FROM usuarios u
            INNER JOIN planos p ON u.plano_id = p.id
            WHERE u.tenant_id = ?
            AND u.role_id = 1
            AND (u.data_vencimento_plano IS NULL OR u.data_vencimento_plano >= CURDATE())
        ");
        $stmtReceita->execute([$tenantId]);
        $receitaMensal = $stmtReceita->fetch()['receita'] ?? 0;

        // Novos alunos no mês
        $stmtNovosAlunos = $db->prepare("
            SELECT COUNT(*) as total 
            FROM usuarios 
            WHERE tenant_id = ? 
            AND role_id = 1
            AND YEAR(created_at) = YEAR(CURDATE())
            AND MONTH(created_at) = MONTH(CURDATE())
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
     * Listar todos os alunos (apenas role_id = 1)
     */
    public function listarAlunos(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId', 1);
        $db = require __DIR__ . '/../../config/database.php';

        $stmt = $db->prepare("
            SELECT 
                u.id, u.nome, u.email, u.role_id, u.plano_id, 
                u.data_vencimento_plano, u.foto_base64, u.created_at, u.updated_at,
                p.nome as plano_nome, p.valor as plano_valor,
                COUNT(DISTINCT c.id) as total_checkins,
                MAX(c.created_at) as ultimo_checkin,
                
                -- Verifica se tem conta paga no período atual
                CASE 
                    WHEN EXISTS (
                        SELECT 1 FROM contas_receber cr
                        WHERE cr.usuario_id = u.id
                        AND cr.tenant_id = u.tenant_id
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
                    AND cr2.tenant_id = u.tenant_id
                    AND cr2.status = 'pendente'
                    ORDER BY cr2.data_vencimento ASC
                    LIMIT 1
                ) as ultima_conta_pendente_id,
                
                -- Valor da última conta pendente
                (
                    SELECT cr3.valor FROM contas_receber cr3
                    WHERE cr3.usuario_id = u.id
                    AND cr3.tenant_id = u.tenant_id
                    AND cr3.status = 'pendente'
                    ORDER BY cr3.data_vencimento ASC
                    LIMIT 1
                ) as ultima_conta_pendente_valor
                
            FROM usuarios u
            LEFT JOIN planos p ON u.plano_id = p.id
            LEFT JOIN checkins c ON u.id = c.usuario_id
            WHERE u.tenant_id = ? AND u.role_id = 1
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

        // Criar usuário com role_id = 1 (aluno)
        $usuarioId = $this->usuarioModel->create($data, $tenantId);

        // Definir como aluno
        if ($usuarioId) {
            $this->usuarioModel->update($usuarioId, ['role_id' => 1]);
        }

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

        // Verificar se o aluno tem check-ins
        $stmtCheckins = $db->prepare("SELECT COUNT(*) as total FROM checkins WHERE usuario_id = ?");
        $stmtCheckins->execute([$alunoId]);
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
            // Pode deletar completamente
            $stmt = $db->prepare("DELETE FROM usuarios WHERE id = ? AND tenant_id = ?");
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
}
