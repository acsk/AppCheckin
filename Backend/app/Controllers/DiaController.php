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
        $tenantId = $request->getAttribute('tenantId', 1);
        $dias = $this->diaModel->getAtivos($tenantId);

        $response->getBody()->write(json_encode([
            'dias' => $dias
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function horarios(Request $request, Response $response, array $args): Response
    {
        $diaId = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId', 1);

        // Verificar se dia existe
        $dia = $this->diaModel->findById($diaId, $tenantId);

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

    /**
     * Retorna dias ao redor de uma data (5 dias: 2 antes, atual, 2 depois)
     */
    public function diasProximos(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId', 1);
        $params = $request->getQueryParams();
        $dataReferencia = $params['data'] ?? null;

        // Retorna 5 dias: 2 antes, atual, 2 depois
        $dias = $this->diaModel->getDiasAoRedor($dataReferencia, 2, 2, $tenantId);

        $response->getBody()->write(json_encode([
            'dias' => $dias,
            'total' => count($dias)
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Retorna horários de um dia específico pela data
     */
    public function horariosPorData(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId', 1);
        $params = $request->getQueryParams();
        $data = $params['data'] ?? null;

        if (!$data) {
            $response->getBody()->write(json_encode([
                'error' => 'Parâmetro data é obrigatório'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Buscar dia pela data
        $dia = $this->diaModel->findByData($data, $tenantId);

        if (!$dia) {
            $response->getBody()->write(json_encode([
                'error' => 'Dia não encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Buscar horários do dia
        $horarios = $this->horarioModel->getByDiaId($dia['id']);

        // Obter usuário logado
        $userId = $request->getAttribute('userId');

        // Adicionar informações completas para cada horário
        $horariosCompletos = array_map(function($horario) use ($userId, $dia) {
            $validacao = $this->horarioModel->podeRealizarCheckin($horario['id']);
            $usuarioRegistrado = false;
            
            if ($userId) {
                // Verifica se o usuário tem check-in registrado nesta turma
                $turmaRegistrada = $this->horarioModel->getTurmaRegistradaHoje($userId, $dia['data']);
                $usuarioRegistrado = ($turmaRegistrada === $horario['id']);
            }
            
            return [
                'id' => $horario['id'],
                'hora' => $horario['hora'],
                'horario_inicio' => $horario['horario_inicio'],
                'horario_fim' => $horario['horario_fim'],
                'limite_alunos' => (int) $horario['limite_alunos'],
                'alunos_registrados' => (int) $horario['alunos_registrados'],
                'vagas_disponiveis' => (int) $horario['vagas_disponiveis'],
                'percentual_ocupacao' => $horario['limite_alunos'] > 0 
                    ? ($horario['alunos_registrados'] / $horario['limite_alunos']) * 100 
                    : 0,
                'tolerancia_minutos' => (int) $horario['tolerancia_minutos'],
                'pode_fazer_checkin' => $validacao['permitido'],
                'motivo_indisponibilidade' => $validacao['permitido'] ? null : $validacao['motivo'],
                'usuario_registrado' => $usuarioRegistrado,
                'ativo' => (bool) $horario['ativo']
            ];
        }, $horarios);

        $response->getBody()->write(json_encode([
            'dia' => $dia,
            'turmas' => $horariosCompletos
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
