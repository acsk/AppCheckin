<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Checkin;
use App\Models\Turma;
use App\Models\Usuario;

class CheckinController
{
    private Checkin $checkinModel;
    private Turma $turmaModel;
    private Usuario $usuarioModel;

    public function __construct()
    {
        $db = require __DIR__ . '/../../config/database.php';
        $this->checkinModel = new Checkin($db);
        $this->turmaModel = new Turma($db);
        $this->usuarioModel = new Usuario($db);
    }

    public function store(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('userId');
        $data = $request->getParsedBody();

        // Verificar se usuário é admin (admins não podem fazer check-in próprio)
        $usuario = $this->usuarioModel->findById($userId);
        if ($usuario && isset($usuario['papel_id']) && ($usuario['papel_id'] == 2 || $usuario['papel_id'] == 3)) {
            $response->getBody()->write(json_encode([
                'error' => 'Administradores não podem fazer check-in próprio. Use o painel admin para registrar check-ins de alunos.'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Validação - aceita turma_id
        if (empty($data['turma_id'])) {
            $response->getBody()->write(json_encode([
                'error' => 'turma_id é obrigatório'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        $turmaId = (int) $data['turma_id'];

        // ✅ VALIDAR SE MATRÍCULA ESTÁ ATIVA E DENTRO DO PRAZO (proxima_data_vencimento)
        $db = require __DIR__ . '/../../config/database.php';
        $stmtMatricula = $db->prepare("
            SELECT m.id, m.proxima_data_vencimento, m.periodo_teste,
                   sm.codigo as status_codigo, sm.nome as status_nome
            FROM matriculas m
            INNER JOIN alunos a ON a.id = m.aluno_id
            INNER JOIN status_matricula sm ON sm.id = m.status_id
            WHERE a.usuario_id = :usuario_id
            AND m.tenant_id = :tenant_id
            AND sm.codigo = 'ativa'
            ORDER BY m.created_at DESC
            LIMIT 1
        ");
        
        $tenantId = $request->getAttribute('tenantId', 1);
        $stmtMatricula->execute([
            'usuario_id' => $userId,
            'tenant_id' => $tenantId
        ]);
        $matricula = $stmtMatricula->fetch(\PDO::FETCH_ASSOC);
        
        if (!$matricula) {
            $response->getBody()->write(json_encode([
                'error' => 'Você não possui matrícula ativa',
                'codigo' => 'SEM_MATRICULA'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
        
        // Verificar se o acesso ainda está válido (proxima_data_vencimento)
        $hoje = date('Y-m-d');
        if ($matricula['proxima_data_vencimento'] && $matricula['proxima_data_vencimento'] < $hoje) {
            $dataVencimento = date('d/m/Y', strtotime($matricula['proxima_data_vencimento']));
            $response->getBody()->write(json_encode([
                'error' => "Seu acesso expirou em {$dataVencimento}. Por favor, renove sua matrícula.",
                'codigo' => 'MATRICULA_VENCIDA',
                'data_vencimento' => $matricula['proxima_data_vencimento']
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Verificar se usuário já tem check-in nesta turma
        if ($this->checkinModel->usuarioTemCheckin($userId, $turmaId)) {
            $response->getBody()->write(json_encode([
                'error' => 'Você já tem check-in nesta turma'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Buscar dados da turma
        $turma = $this->turmaModel->findById($turmaId);
        
        if (!$turma) {
            $response->getBody()->write(json_encode([
                'error' => 'Turma não encontrada'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Criar check-in com timestamp do momento exato
        $checkinId = $this->checkinModel->create($userId, $turmaId);

        if (!$checkinId) {
            $response->getBody()->write(json_encode([
                'error' => 'Erro ao realizar check-in'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $checkin = $this->checkinModel->findById($checkinId);

        $response->getBody()->write(json_encode([
            'message' => 'Check-in realizado com sucesso',
            'checkin' => $checkin
        ]));

        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    public function myCheckins(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('userId');

        $checkins = $this->checkinModel->getByUsuarioId($userId);

        $response->getBody()->write(json_encode([
            'checkins' => $checkins
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function cancel(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('userId');
        $checkinId = (int) $args['id'];

        // Verificar se check-in existe
        $checkin = $this->checkinModel->findById($checkinId);

        if (!$checkin) {
            $response->getBody()->write(json_encode([
                'error' => 'Check-in não encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Verificar se check-in pertence ao usuário
        if ($checkin['usuario_id'] != $userId) {
            $response->getBody()->write(json_encode([
                'error' => 'Você não tem permissão para cancelar este check-in'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Deletar check-in
        $deleted = $this->checkinModel->delete($checkinId);

        if (!$deleted) {
            $response->getBody()->write(json_encode([
                'error' => 'Erro ao cancelar check-in'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $response->getBody()->write(json_encode([
            'message' => 'Check-in cancelado com sucesso'
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * DELETE /checkin/{id}/desfazer
     * Desfazer check-in com validações de horário
     * Não pode desfazer após o horário + tolerância
     */
    public function desfazer(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('userId');
        $checkinId = (int) $args['id'];

        // Verificar se check-in existe
        $checkin = $this->checkinModel->findById($checkinId);

        if (!$checkin) {
            $response->getBody()->write(json_encode([
                'error' => 'Check-in não encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Verificar se check-in pertence ao usuário
        if ($checkin['usuario_id'] != $userId) {
            $response->getBody()->write(json_encode([
                'error' => 'Você não tem permissão para desfazer este check-in'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Validar se ainda é possível desfazer
        // Se a turma foi deletada ou não tem dados, permitir desfazer
        if ($checkin['turma_id'] && $checkin['horario_inicio'] && $checkin['data']) {
            // Verificar se a aula já começou + tolerância
            $agora = new \DateTime();
            $dataHorarioInicio = new \DateTime($checkin['data'] . ' ' . $checkin['horario_inicio']);
            
            // Tolerar até X minutos após o início da aula
            $tolerancia = (int) ($checkin['tolerancia_minutos'] ?? 10);
            $dataLimiteDesfazer = clone $dataHorarioInicio;
            $dataLimiteDesfazer->modify("+{$tolerancia} minutes");

            // Se já passou do horário + tolerância, não pode desfazer
            if ($agora > $dataLimiteDesfazer) {
                $response->getBody()->write(json_encode([
                    'error' => 'Não é possível desfazer o check-in. O prazo expirou (a aula já começou)',
                    'turma' => [
                        'data' => $checkin['data'],
                        'inicio' => $checkin['horario_inicio'],
                        'tolerancia_minutos' => $tolerancia,
                        'limite_para_desfazer' => $dataLimiteDesfazer->format('Y-m-d H:i:s')
                    ]
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Verificar se a aula ainda está acontecendo
            $dataHorarioFim = new \DateTime($checkin['data'] . ' ' . $checkin['horario_fim']);
            
            if ($agora > $dataHorarioFim) {
                $response->getBody()->write(json_encode([
                    'error' => 'Não é possível desfazer o check-in. A aula já terminou',
                    'turma' => [
                        'data' => $checkin['data'],
                        'inicio' => $checkin['horario_inicio'],
                        'fim' => $checkin['horario_fim']
                    ]
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
        }

        // Desfazer check-in (deletar)
        $deleted = $this->checkinModel->delete($checkinId);

        if (!$deleted) {
            $response->getBody()->write(json_encode([
                'error' => 'Erro ao desfazer check-in'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $response->getBody()->write(json_encode([
            'message' => 'Check-in desfeito com sucesso',
            'checkin_id' => $checkinId,
            'horario' => [
                'data' => $checkin['data'] ?? 'Data não disponível',
                'inicio' => $checkin['horario_inicio'] ?? 'Horário não disponível',
                'fim' => $checkin['horario_fim'] ?? 'Horário não disponível'
            ]
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * POST /admin/checkins/registrar
     * Admin registra check-in para um aluno em qualquer turma
     */
    public function registrarPorAdmin(Request $request, Response $response): Response
    {
        $adminId = $request->getAttribute('userId');
        $data = $request->getParsedBody();

        // Validações
        if (empty($data['usuario_id']) || empty($data['turma_id'])) {
            $response->getBody()->write(json_encode([
                'error' => 'usuario_id e turma_id são obrigatórios'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        $usuarioId = (int) $data['usuario_id'];
        $turmaId = (int) $data['turma_id'];

        // Verificar se aluno existe e é realmente aluno (papel_id = 1)
        $aluno = $this->usuarioModel->findById($usuarioId);
        if (!$aluno) {
            $response->getBody()->write(json_encode([
                'error' => 'Aluno não encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // ✅ VALIDAR SE MATRÍCULA ESTÁ ATIVA E DENTRO DO PRAZO (proxima_data_vencimento)
        $db = require __DIR__ . '/../../config/database.php';
        $tenantId = $request->getAttribute('tenantId', 1);
        
        $stmtMatricula = $db->prepare("
            SELECT m.id, m.proxima_data_vencimento, m.periodo_teste,
                   sm.codigo as status_codigo, sm.nome as status_nome
            FROM matriculas m
            INNER JOIN alunos a ON a.id = m.aluno_id
            INNER JOIN status_matricula sm ON sm.id = m.status_id
            WHERE a.usuario_id = :usuario_id
            AND m.tenant_id = :tenant_id
            AND sm.codigo = 'ativa'
            ORDER BY m.created_at DESC
            LIMIT 1
        ");
        
        $stmtMatricula->execute([
            'usuario_id' => $usuarioId,
            'tenant_id' => $tenantId
        ]);
        $matricula = $stmtMatricula->fetch(\PDO::FETCH_ASSOC);
        
        if (!$matricula) {
            $response->getBody()->write(json_encode([
                'error' => 'Aluno não possui matrícula ativa',
                'codigo' => 'SEM_MATRICULA'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
        
        // Verificar se o acesso ainda está válido (proxima_data_vencimento)
        $hoje = date('Y-m-d');
        if ($matricula['proxima_data_vencimento'] && $matricula['proxima_data_vencimento'] < $hoje) {
            $dataVencimento = date('d/m/Y', strtotime($matricula['proxima_data_vencimento']));
            $response->getBody()->write(json_encode([
                'error' => "Acesso do aluno expirou em {$dataVencimento}",
                'codigo' => 'MATRICULA_VENCIDA',
                'data_vencimento' => $matricula['proxima_data_vencimento']
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        if (($aluno['papel_id'] ?? null) != 1) {
            $response->getBody()->write(json_encode([
                'error' => 'Usuário não é um aluno'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Verificar se aluno já tem check-in nesta turma
        if ($this->checkinModel->usuarioTemCheckin($usuarioId, $turmaId)) {
            $response->getBody()->write(json_encode([
                'error' => 'Aluno já tem check-in nesta turma'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Admin pode registrar em qualquer turma (sem validação de tolerância)
        // Mas ainda validamos se a turma existe e está ativa
        $turma = $this->turmaModel->findById($turmaId);
        if (!$turma || !$turma['ativo']) {
            $response->getBody()->write(json_encode([
                'error' => 'Turma inválida ou inativa'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Criar check-in registrado pelo admin
        $checkinId = $this->checkinModel->createByAdmin($usuarioId, $turmaId, $adminId);

        if (!$checkinId) {
            $response->getBody()->write(json_encode([
                'error' => 'Erro ao registrar check-in'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $checkin = $this->checkinModel->findById($checkinId);

        $response->getBody()->write(json_encode([
            'message' => 'Check-in registrado com sucesso',
            'checkin' => $checkin
        ]));

        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }
}
