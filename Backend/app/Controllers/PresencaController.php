<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Checkin;
use App\Models\Turma;
use PDO;

/**
 * Controller para gerenciamento de presenças pelo professor
 * Permite que professores confirmem a presença dos alunos que fizeram check-in
 */
class PresencaController
{
    private PDO $db;
    private Checkin $checkinModel;
    private Turma $turmaModel;

    public function __construct()
    {
        $this->db = require __DIR__ . '/../../config/database.php';
        $this->checkinModel = new Checkin($this->db);
        $this->turmaModel = new Turma($this->db);
    }

    /**
     * Listar alunos com check-in em uma turma para controle de presença
     * GET /admin/turmas/{turmaId}/presencas
     */
    public function listarPresencas(Request $request, Response $response, array $args): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $turmaId = (int) $args['turmaId'];

        try {
            // Verificar se turma existe e pertence ao tenant
            $turma = $this->turmaModel->findById($turmaId, $tenantId);
            
            if (!$turma) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Turma não encontrada'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
            }

            // Listar check-ins da turma
            $checkins = $this->checkinModel->listarCheckinsTurma($turmaId, $tenantId);

            // Obter estatísticas
            $estatisticas = $this->checkinModel->estatisticasPresencaTurma($turmaId);

            // Formatar resposta
            $checkinsFormatados = array_map(function($c) {
                return [
                    'checkin_id' => (int) $c['id'],
                    'aluno' => [
                        'id' => (int) $c['usuario_id'],
                        'nome' => $c['aluno_nome'],
                        'email' => $c['aluno_email']
                    ],
                    'data_checkin' => $c['data_checkin'],
                    'presenca' => [
                        'status' => $c['presente'] === null ? 'nao_verificado' : ($c['presente'] ? 'presente' : 'falta'),
                        'confirmada_em' => $c['presenca_confirmada_em'],
                        'confirmada_por' => $c['confirmado_por_nome']
                    ]
                ];
            }, $checkins);

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'data' => [
                    'turma' => [
                        'id' => (int) $turma['id'],
                        'nome' => $turma['nome'],
                        'professor' => $turma['professor_nome'],
                        'modalidade' => $turma['modalidade_nome'],
                        'horario_inicio' => $turma['horario_inicio'],
                        'horario_fim' => $turma['horario_fim'],
                        'dia_data' => $turma['dia_data'] ?? null
                    ],
                    'checkins' => $checkinsFormatados,
                    'estatisticas' => [
                        'total_checkins' => (int) $estatisticas['total_checkins'],
                        'presentes' => (int) $estatisticas['presentes'],
                        'faltas' => (int) $estatisticas['faltas'],
                        'nao_verificados' => (int) $estatisticas['nao_verificados'],
                        'percentual_presenca' => $estatisticas['total_checkins'] > 0 
                            ? round(($estatisticas['presentes'] / $estatisticas['total_checkins']) * 100, 1) 
                            : 0
                    ]
                ]
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao listar presenças: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Marcar presença de um aluno específico
     * PATCH /admin/checkins/{checkinId}/presenca
     * Body: { "presente": true/false }
     */
    public function marcarPresenca(Request $request, Response $response, array $args): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $userId = $request->getAttribute('userId');
        $checkinId = (int) $args['checkinId'];
        $body = $request->getParsedBody();

        try {
            // Validar body
            if (!isset($body['presente'])) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Campo "presente" é obrigatório (true/false)'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
            }

            $presente = filter_var($body['presente'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($presente === null) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Campo "presente" deve ser true ou false'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
            }

            // Verificar se check-in existe
            $checkin = $this->checkinModel->findById($checkinId);
            if (!$checkin) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Check-in não encontrado'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
            }

            // Marcar presença
            $result = $this->checkinModel->marcarPresenca($checkinId, $presente, $userId);

            if (!$result) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Erro ao marcar presença'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
            }

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => $presente ? 'Presença confirmada' : 'Falta registrada',
                'data' => [
                    'checkin_id' => $checkinId,
                    'presente' => $presente,
                    'confirmado_em' => date('Y-m-d H:i:s')
                ]
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao marcar presença: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Marcar presença em lote para uma turma
     * POST /admin/turmas/{turmaId}/presencas/lote
     * Body: { "checkin_ids": [1, 2, 3], "presente": true/false }
     * ou { "marcar_todos": true, "presente": true/false }
     */
    public function marcarPresencaLote(Request $request, Response $response, array $args): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $userId = $request->getAttribute('userId');
        $turmaId = (int) $args['turmaId'];
        $body = $request->getParsedBody();

        try {
            // Verificar se turma existe e pertence ao tenant
            $turma = $this->turmaModel->findById($turmaId, $tenantId);
            
            if (!$turma) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Turma não encontrada'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
            }

            // Validar body
            if (!isset($body['presente'])) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Campo "presente" é obrigatório (true/false)'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
            }

            $presente = filter_var($body['presente'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($presente === null) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Campo "presente" deve ser true ou false'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
            }

            $checkinIds = [];

            // Se marcar_todos = true, buscar todos os check-ins da turma
            if (!empty($body['marcar_todos']) && $body['marcar_todos'] === true) {
                $checkins = $this->checkinModel->listarCheckinsTurma($turmaId, $tenantId);
                $checkinIds = array_column($checkins, 'id');
            } 
            // Senão, usar os IDs fornecidos
            elseif (!empty($body['checkin_ids']) && is_array($body['checkin_ids'])) {
                $checkinIds = array_map('intval', $body['checkin_ids']);
            } else {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Forneça "checkin_ids" (array) ou "marcar_todos": true'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
            }

            if (empty($checkinIds)) {
                $response->getBody()->write(json_encode([
                    'type' => 'info',
                    'message' => 'Nenhum check-in para atualizar'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
            }

            // Marcar presença em lote
            $atualizados = $this->checkinModel->marcarPresencaEmLote($checkinIds, $presente, $userId);

            // Obter estatísticas atualizadas
            $estatisticas = $this->checkinModel->estatisticasPresencaTurma($turmaId);

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => "$atualizados presença(s) " . ($presente ? 'confirmada(s)' : 'marcada(s) como falta'),
                'data' => [
                    'turma_id' => $turmaId,
                    'atualizados' => $atualizados,
                    'presente' => $presente,
                    'estatisticas' => [
                        'total_checkins' => (int) $estatisticas['total_checkins'],
                        'presentes' => (int) $estatisticas['presentes'],
                        'faltas' => (int) $estatisticas['faltas'],
                        'nao_verificados' => (int) $estatisticas['nao_verificados']
                    ]
                ]
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao marcar presenças em lote: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }
}
