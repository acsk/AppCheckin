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
        if ($usuario && isset($usuario['role_id']) && ($usuario['role_id'] == 2 || $usuario['role_id'] == 3)) {
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
        // Se a turma foi deletada, permitir desfazer (pois não é mais uma aula ativa)
        if ($checkin['turma_id'] && $checkin['turma_id'] > 0) {
            // Buscar dados da turma
            $turma = $this->turmaModel->findById($checkin['turma_id']);

            if ($turma) {
                // Precisamos da data do check-in para calcular os horários
                // Como a turma tem dia_id, precisamos buscar o dia
                $db = require __DIR__ . '/../../config/database.php';
                $stmt = $db->prepare("SELECT data FROM dias WHERE id = ?");
                $stmt->execute([$turma['dia_id']]);
                $dia = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if ($dia) {
                    // Verificar se a aula já começou + tolerância
                    $agora = new \DateTime();
                    $dataHorarioInicio = new \DateTime($dia['data'] . ' ' . $turma['horario_inicio']);
                    
                    // Tolerar até X minutos após o início da aula
                    $tolerancia = (int) $turma['tolerancia_minutos'] ?? 10;
                    $dataLimiteDesfazer = clone $dataHorarioInicio;
                    $dataLimiteDesfazer->modify("+{$tolerancia} minutes");

                    // Se já passou do horário + tolerância, não pode desfazer
                    if ($agora > $dataLimiteDesfazer) {
                        return $response->withHeader('Content-Type', 'application/json')
                            ->write(json_encode([
                                'error' => 'Não é possível desfazer o check-in. O prazo expirou (a aula já começou)',
                                'turma' => [
                                    'data' => $dia['data'],
                                    'inicio' => $turma['horario_inicio'],
                                    'tolerancia_minutos' => $tolerancia,
                                    'limite_para_desfazer' => $dataLimiteDesfazer->format('Y-m-d H:i:s')
                                ]
                            ], JSON_UNESCAPED_UNICODE))
                            ->withStatus(400);
                    }

                    // Verificar se a aula ainda está acontecendo
                    $dataHorarioFim = new \DateTime($dia['data'] . ' ' . $turma['horario_fim']);
                    
                    if ($agora > $dataHorarioFim) {
                        return $response->withHeader('Content-Type', 'application/json')
                            ->write(json_encode([
                                'error' => 'Não é possível desfazer o check-in. A aula já terminou',
                                'turma' => [
                                    'data' => $dia['data'],
                                    'inicio' => $turma['horario_inicio'],
                                    'fim' => $turma['horario_fim']
                                ]
                            ], JSON_UNESCAPED_UNICODE))
                            ->withStatus(400);
                    }
                }
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

        // Verificar se aluno existe e é realmente aluno (role_id = 1)
        $aluno = $this->usuarioModel->findById($usuarioId);
        if (!$aluno) {
            $response->getBody()->write(json_encode([
                'error' => 'Aluno não encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        if ($aluno['role_id'] != 1) {
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
