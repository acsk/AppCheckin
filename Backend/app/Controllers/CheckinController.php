<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Checkin;
use App\Models\Horario;
use App\Models\Usuario;

class CheckinController
{
    private Checkin $checkinModel;
    private Horario $horarioModel;
    private Usuario $usuarioModel;

    public function __construct()
    {
        $db = require __DIR__ . '/../../config/database.php';
        $this->checkinModel = new Checkin($db);
        $this->horarioModel = new Horario($db);
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

        // Validação
        if (empty($data['horario_id'])) {
            $response->getBody()->write(json_encode([
                'error' => 'horario_id é obrigatório'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        $horarioId = (int) $data['horario_id'];

        // Verificar se usuário já tem check-in neste horário
        if ($this->checkinModel->usuarioTemCheckin($userId, $horarioId)) {
            $response->getBody()->write(json_encode([
                'error' => 'Você já tem check-in neste horário'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Verificar se pode realizar check-in (valida horário, tolerância e vagas)
        $validacao = $this->horarioModel->podeRealizarCheckin($horarioId);

        if (!$validacao['permitido']) {
            $response->getBody()->write(json_encode([
                'error' => $validacao['motivo']
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Criar check-in com timestamp do momento exato
        $checkinId = $this->checkinModel->create($userId, $horarioId);

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
     * POST /admin/checkins/registrar
     * Admin registra check-in para um aluno em qualquer horário
     */
    public function registrarPorAdmin(Request $request, Response $response): Response
    {
        $adminId = $request->getAttribute('userId');
        $data = $request->getParsedBody();

        // Validações
        if (empty($data['usuario_id']) || empty($data['horario_id'])) {
            $response->getBody()->write(json_encode([
                'error' => 'usuario_id e horario_id são obrigatórios'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        $usuarioId = (int) $data['usuario_id'];
        $horarioId = (int) $data['horario_id'];

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

        // Verificar se aluno já tem check-in neste horário
        if ($this->checkinModel->usuarioTemCheckin($usuarioId, $horarioId)) {
            $response->getBody()->write(json_encode([
                'error' => 'Aluno já tem check-in neste horário'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Admin pode registrar em qualquer horário (sem validação de tolerância)
        // Mas ainda validamos se o horário existe e está ativo
        $horario = $this->horarioModel->findById($horarioId);
        if (!$horario || !$horario['ativo']) {
            $response->getBody()->write(json_encode([
                'error' => 'Horário inválido ou inativo'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Criar check-in registrado pelo admin
        $checkinId = $this->checkinModel->createByAdmin($usuarioId, $horarioId, $adminId);

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
