<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Horario;

class TurmaController
{
    private Horario $horarioModel;

    public function __construct()
    {
        $db = require __DIR__ . '/../../config/database.php';
        $this->horarioModel = new Horario($db);
    }

    /**
     * Lista todos os horários/turmas com estatísticas de alunos
     */
    public function index(Request $request, Response $response): Response
    {
        $horarios = $this->horarioModel->getAllWithStats();

        // Agrupar por data para melhor visualização
        $turmasPorDia = [];
        foreach ($horarios as $horario) {
            $data = $horario['data'];
            
            if (!isset($turmasPorDia[$data])) {
                $turmasPorDia[$data] = [
                    'data' => $data,
                    'dia_ativo' => (bool) $horario['dia_ativo'],
                    'turmas' => []
                ];
            }

            $turmasPorDia[$data]['turmas'][] = [
                'id' => $horario['id'],
                'hora' => $horario['hora'],
                'horario_inicio' => $horario['horario_inicio'],
                'horario_fim' => $horario['horario_fim'],
                'limite_alunos' => $horario['limite_alunos'],
                'alunos_registrados' => $horario['alunos_registrados'],
                'vagas_disponiveis' => $horario['vagas_disponiveis'],
                'percentual_ocupacao' => $horario['limite_alunos'] > 0 
                    ? round(($horario['alunos_registrados'] / $horario['limite_alunos']) * 100, 2)
                    : 0,
                'ativo' => (bool) $horario['ativo']
            ];
        }

        $response->getBody()->write(json_encode([
            'turmas_por_dia' => array_values($turmasPorDia),
            'total_turmas' => count($horarios)
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Lista turmas do dia atual (baseado na data do servidor)
     */
    public function hoje(Request $request, Response $response): Response
    {
        $dataHoje = date('Y-m-d');
        
        // Obter ID do usuário autenticado
        $userId = $request->getAttribute('userId');
        
        $horarios = $this->horarioModel->getAllWithStats();

        // Filtrar apenas turmas do dia atual
        $turmasHoje = array_filter($horarios, function($horario) use ($dataHoje) {
            return $horario['data'] === $dataHoje;
        });

        if (empty($turmasHoje)) {
            $response->getBody()->write(json_encode([
                'message' => 'Não há turmas cadastradas para hoje',
                'data' => $dataHoje,
                'turmas' => []
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        // Verificar em qual turma o usuário está registrado hoje
        $turmaUsuarioId = $this->horarioModel->getTurmaRegistradaHoje($userId, $dataHoje);

        // Formatar turmas
        $turmasFormatadas = array_map(function($horario) use ($turmaUsuarioId) {
            return [
                'id' => $horario['id'],
                'hora' => $horario['hora'],
                'horario_inicio' => $horario['horario_inicio'],
                'horario_fim' => $horario['horario_fim'],
                'limite_alunos' => $horario['limite_alunos'],
                'alunos_registrados' => $horario['alunos_registrados'],
                'vagas_disponiveis' => $horario['vagas_disponiveis'],
                'percentual_ocupacao' => $horario['limite_alunos'] > 0 
                    ? round(($horario['alunos_registrados'] / $horario['limite_alunos']) * 100, 2)
                    : 0,
                'ativo' => (bool) $horario['ativo'],
                'usuario_registrado' => $turmaUsuarioId === $horario['id']
            ];
        }, array_values($turmasHoje));

        $response->getBody()->write(json_encode([
            'data' => $dataHoje,
            'turmas' => $turmasFormatadas,
            'total_turmas' => count($turmasFormatadas)
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Lista alunos de uma turma/horário específico
     */
    public function alunos(Request $request, Response $response, array $args): Response
    {
        $horarioId = (int) $args['id'];

        // Verificar se horário existe
        $horario = $this->horarioModel->findById($horarioId);

        if (!$horario) {
            $response->getBody()->write(json_encode([
                'error' => 'Horário/Turma não encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Buscar alunos
        $alunos = $this->horarioModel->getAlunosByHorarioId($horarioId);

        $response->getBody()->write(json_encode([
            'turma' => [
                'id' => $horario['id'],
                'data' => $horario['data'],
                'hora' => $horario['hora'],
                'horario_inicio' => $horario['horario_inicio'],
                'horario_fim' => $horario['horario_fim'],
                'limite_alunos' => $horario['limite_alunos'],
                'alunos_registrados' => $horario['alunos_registrados'],
                'vagas_disponiveis' => $horario['vagas_disponiveis']
            ],
            'alunos' => $alunos,
            'total_alunos' => count($alunos)
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
