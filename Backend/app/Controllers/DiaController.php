<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Dia;
use App\Models\Horario;

class DiaController
{
    private Dia $diaModel;
    private Horario $horarioModel;

    public function __construct()
    {
        $db = require __DIR__ . '/../../config/database.php';
        $this->diaModel = new Dia($db);
        $this->horarioModel = new Horario($db);
    }

    public function index(Request $request, Response $response): Response
    {
        $dias = $this->diaModel->getAtivos();

        $response->getBody()->write(json_encode([
            'dias' => $dias
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function horarios(Request $request, Response $response, array $args): Response
    {
        $diaId = (int) $args['id'];

        // Verificar se dia existe
        $dia = $this->diaModel->findById($diaId);

        if (!$dia) {
            $response->getBody()->write(json_encode([
                'error' => 'Dia não encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $horarios = $this->horarioModel->getByDiaId($diaId);

        // Adicionar informações de disponibilidade para cada horário
        $horariosComDisponibilidade = array_map(function($horario) {
            $validacao = $this->horarioModel->podeRealizarCheckin($horario['id']);
            
            return [
                'id' => $horario['id'],
                'hora' => $horario['hora'],
                'horario_inicio' => $horario['horario_inicio'],
                'horario_fim' => $horario['horario_fim'],
                'limite_alunos' => $horario['limite_alunos'],
                'alunos_registrados' => $horario['alunos_registrados'],
                'vagas_disponiveis' => $horario['vagas_disponiveis'],
                'tolerancia_minutos' => $horario['tolerancia_minutos'],
                'pode_fazer_checkin' => $validacao['permitido'],
                'motivo_indisponibilidade' => $validacao['permitido'] ? null : $validacao['motivo'],
                'ativo' => (bool) $horario['ativo']
            ];
        }, $horarios);

        $response->getBody()->write(json_encode([
            'dia' => $dia,
            'horarios' => $horariosComDisponibilidade
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
