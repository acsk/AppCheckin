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
                'alunos_registrados' => $horario['alunos_registrados'] ?? 0,
                'vagas_disponiveis' => $horario['vagas_disponiveis'] ?? $horario['limite_alunos'],
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

    /**
     * POST /admin/dias/desativar
     * Desativa dia(s) - útil para feriados ou dias sem aula
     * 
     * Body options:
     * 1. Desativar um dia específico:
     *    { "dia_id": 17 }
     * 
     * 2. Desativar próxima semana (mesmo dia da semana):
     *    { "dia_id": 17, "periodo": "proxima_semana" }
     * 
     * 3. Desativar mês inteiro (todos os dias):
     *    { "dia_id": 17, "periodo": "mes_todo", "mes": "2026-01" }
     * 
     * 4. Desativar dias específicos da semana:
     *    { "dia_id": 17, "periodo": "custom", "dias_semana": [1], "mes": "2026-01" }
     *    (ex: desativar todos os domingos)
     */
    public function desativarDias(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $data = $request->getParsedBody();
        
        if (!isset($data['dia_id'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'dia_id é obrigatório'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
        }

        $diaId = (int) $data['dia_id'];
        $periodo = $data['periodo'] ?? 'apenas_este';
        $mes = $data['mes'] ?? date('Y-m');
        $diasSemana = [];

        try {
            // Buscar dia origem
            $diaOrigem = $this->diaModel->buscarPorId($diaId, $tenantId);
            if (!$diaOrigem) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Dia não encontrado'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
            }

            $totalDesativados = 0;
            $detalhes = [];
            $diasParaDesativar = [];

            if ($periodo === 'apenas_este') {
                // Desativar apenas este dia
                $diasParaDesativar = [$diaId];
                
            } elseif ($periodo === 'proxima_semana') {
                // Próxima semana (mesmo dia da semana)
                $diaSemanaNum = (int) date('N', strtotime($diaOrigem['data']));
                $diaSemanaNum = $diaSemanaNum === 7 ? 1 : $diaSemanaNum + 1;
                $diasSemana = [$diaSemanaNum];
                
                $dataProxSemana = new \DateTime($diaOrigem['data']);
                $dataProxSemana->add(new \DateInterval('P7D'));
                $mes = $dataProxSemana->format('Y-m');
                
            } elseif ($periodo === 'mes_todo') {
                // Mês inteiro (todos os dias)
                $diasSemana = [1, 2, 3, 4, 5, 6, 7];
                
            } elseif ($periodo === 'custom') {
                if (!isset($data['dias_semana'])) {
                    $response->getBody()->write(json_encode([
                        'type' => 'error',
                        'message' => 'Para periodo=custom, dias_semana é obrigatório'
                    ], JSON_UNESCAPED_UNICODE));
                    return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
                }
                $diasSemana = (array) $data['dias_semana'];
            } else {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'periodo deve ser: apenas_este, proxima_semana, mes_todo ou custom'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
            }

            // Validar dias_semana
            if (!empty($diasSemana)) {
                foreach ($diasSemana as $dia) {
                    if (!is_numeric($dia) || $dia < 1 || $dia > 7) {
                        $response->getBody()->write(json_encode([
                            'type' => 'error',
                            'message' => 'dias_semana deve conter valores entre 1 e 7'
                        ], JSON_UNESCAPED_UNICODE));
                        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
                    }
                }

                // Buscar dias para desativar
                $diasSemanaStr = implode(',', $diasSemana);
                $sql = "SELECT id, data FROM dias 
                        WHERE tenant_id = ? 
                        AND YEAR(data) = ? 
                        AND MONTH(data) = ? 
                        AND DAYOFWEEK(data) IN ($diasSemanaStr)
                        ORDER BY data ASC";

                $db = require __DIR__ . '/../../config/database.php';
                $stmt = $db->prepare($sql);
                $stmt->execute([$tenantId, substr($mes, 0, 4), substr($mes, 5, 2)]);
                
                $diasEncontrados = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $diasParaDesativar = array_column($diasEncontrados, 'id');
                
                foreach ($diasEncontrados as $dia) {
                    $detalhes[] = [
                        'dia_id' => $dia['id'],
                        'data' => $dia['data']
                    ];
                }
            }

            // Desativar os dias
            if (!empty($diasParaDesativar)) {
                foreach ($diasParaDesativar as $id) {
                    $sql = "UPDATE dias SET ativo = 0 WHERE id = ? AND tenant_id = ?";
                    $db = require __DIR__ . '/../../config/database.php';
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$id, $tenantId]);
                    $totalDesativados++;
                }
            }

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => "Total de $totalDesativados dia(s) desativado(s) com sucesso",
                'summary' => [
                    'total_desativados' => $totalDesativados
                ],
                'detalhes' => $detalhes
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao desativar dias: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Deletar todas as turmas de um dia específico
     * DELETE /admin/dias/{id}/horarios
     */
    public function deletarHorariosDoDia(Request $request, Response $response, array $args): Response
    {
        $diaId = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId');

        try {
            $db = require __DIR__ . '/../../config/database.php';

            // Verificar se o dia existe e pertence ao tenant
            $dia = $this->diaModel->findById($diaId, $tenantId);

            if (!$dia) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Dia não encontrado'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
            }

            // Buscar turmas do dia para retornar informações
            $stmt = $db->prepare("
                SELECT t.id, t.nome, t.horario_inicio, t.horario_fim, t.limite_alunos
                FROM turmas t
                WHERE t.dia_id = :dia_id AND t.tenant_id = :tenant_id
            ");
            $stmt->execute(['dia_id' => $diaId, 'tenant_id' => $tenantId]);
            $turmasParaDeletar = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $totalTurmas = count($turmasParaDeletar);

            if ($totalTurmas === 0) {
                $response->getBody()->write(json_encode([
                    'type' => 'info',
                    'message' => 'Nenhuma turma encontrada para este dia'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
            }

            // Primeiro, deletar os checkins associados às turmas
            $turmasIds = array_column($turmasParaDeletar, 'id');
            $placeholders = implode(',', array_fill(0, count($turmasIds), '?'));
            
            $stmt = $db->prepare("DELETE FROM checkins WHERE turma_id IN ($placeholders)");
            $stmt->execute($turmasIds);
            $checkinsRemovidos = $stmt->rowCount();

            // Depois, deletar as turmas do dia
            $stmt = $db->prepare("DELETE FROM turmas WHERE dia_id = :dia_id AND tenant_id = :tenant_id");
            $stmt->execute(['dia_id' => $diaId, 'tenant_id' => $tenantId]);

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => "$totalTurmas turma(s) removida(s) com sucesso do dia {$dia['data']}",
                'data' => [
                    'dia_id' => $diaId,
                    'data' => $dia['data'],
                    'turmas_removidas' => $totalTurmas,
                    'checkins_removidos' => $checkinsRemovidos,
                    'detalhes' => $turmasParaDeletar
                ]
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao deletar turmas: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }
}

