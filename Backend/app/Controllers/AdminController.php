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

        $stats = [
            'total_alunos' => (int) $totalAlunos,
            'alunos_ativos' => (int) $statusAlunos['ativos'],
            'alunos_inativos' => (int) $statusAlunos['inativos'],
            'novos_alunos_mes' => (int) $novosAlunos,
            'total_checkins_hoje' => (int) $checkinsHoje,
            'total_checkins_mes' => (int) $checkinsMes,
            'planos_vencendo' => (int) $planosVencendo,
            'receita_mensal' => (float) $receitaMensal
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
                COUNT(c.id) as total_checkins,
                MAX(c.created_at) as ultimo_checkin
            FROM usuarios u
            LEFT JOIN planos p ON u.plano_id = p.id
            LEFT JOIN checkins c ON u.id = c.usuario_id
            WHERE u.tenant_id = ? AND u.role_id = 1
            GROUP BY u.id
            ORDER BY u.nome ASC
        ");
        $stmt->execute([$tenantId]);
        $alunos = $stmt->fetchAll();

        // Estruturar com objeto plano
        foreach ($alunos as &$aluno) {
            if ($aluno['plano_id']) {
                $aluno['plano'] = [
                    'id' => $aluno['plano_id'],
                    'nome' => $aluno['plano_nome'],
                    'valor' => $aluno['plano_valor']
                ];
            } else {
                $aluno['plano'] = null;
            }
            unset($aluno['plano_nome'], $aluno['plano_valor']);
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

        // Definir como aluno e atualizar plano se fornecido
        if ($usuarioId) {
            $updateData = ['role_id' => 1]; // Sempre define como aluno
            
            if (isset($data['plano_id']) && !empty($data['plano_id'])) {
                $updateData['plano_id'] = $data['plano_id'];
                $updateData['data_vencimento_plano'] = $data['data_vencimento_plano'] ?? null;
            }
            
            $this->usuarioModel->update($usuarioId, $updateData);
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
}
