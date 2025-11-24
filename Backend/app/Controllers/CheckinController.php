<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Checkin;
use App\Models\Horario;

class CheckinController
{
    private Checkin $checkinModel;
    private Horario $horarioModel;

    public function __construct()
    {
        $db = require __DIR__ . '/../../config/database.php';
        $this->checkinModel = new Checkin($db);
        $this->horarioModel = new Horario($db);
    }

    public function store(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('userId');
        $data = $request->getParsedBody();

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
}
