<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Dia;
use App\Models\Turma;

class DiaController
{
    private Dia $diaModel;
    private Turma $turmaModel;

    public function __construct()
    {
        $db = require __DIR__ . '/../../config/database.php';
        $this->diaModel = new Dia($db);
        $this->turmaModel = new Turma($db);
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

        // Buscar turmas do dia (antes chamadas de horários)
        $turmas = $this->turmaModel->listarPorDia($tenantId, $diaId);

        // Adicionar informações de disponibilidade para cada turma
        $turmasComDisponibilidade = array_map(function($turma) {
            $alunosRegistrados = (int) ($turma['alunos_count'] ?? 0);
            $vagasDisponiveis = (int) $turma['limite_alunos'] - $alunosRegistrados;
            
            return [
                'id' => $turma['id'],
                'nome' => $turma['nome'],
                'professor_nome' => $turma['professor_nome'],
                'modalidade_nome' => $turma['modalidade_nome'],
                'horario_inicio' => $turma['horario_inicio'],
                'horario_fim' => $turma['horario_fim'],
                'limite_alunos' => (int) $turma['limite_alunos'],
                'alunos_registrados' => $alunosRegistrados,
                'vagas_disponiveis' => $vagasDisponiveis,
                'tolerancia_minutos' => (int) $turma['tolerancia_minutos'],
                'tolerancia_antes_minutos' => (int) $turma['tolerancia_antes_minutos'],
                'ativo' => (bool) $turma['ativo']
            ];
        }, $turmas);

        $response->getBody()->write(json_encode([
            'dia' => $dia,
            'turmas' => $turmasComDisponibilidade
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

        // Buscar turmas do dia
        $turmas = $this->turmaModel->listarPorDia($tenantId, $dia['id']);

        // Adicionar informações completas para cada turma
        $turmasCompletas = array_map(function($turma) {
            $alunosRegistrados = (int) ($turma['alunos_count'] ?? 0);
            $vagasDisponiveis = (int) $turma['limite_alunos'] - $alunosRegistrados;
            
            return [
                'id' => $turma['id'],
                'nome' => $turma['nome'],
                'professor_nome' => $turma['professor_nome'],
                'professor_id' => (int) $turma['professor_id'],
                'modalidade_nome' => $turma['modalidade_nome'],
                'modalidade_icone' => $turma['modalidade_icone'],
                'modalidade_cor' => $turma['modalidade_cor'],
                'horario_inicio' => $turma['horario_inicio'],
                'horario_fim' => $turma['horario_fim'],
                'limite_alunos' => (int) $turma['limite_alunos'],
                'alunos_registrados' => $alunosRegistrados,
                'vagas_disponiveis' => $vagasDisponiveis,
                'percentual_ocupacao' => $turma['limite_alunos'] > 0 
                    ? ($alunosRegistrados / $turma['limite_alunos']) * 100 
                    : 0,
                'tolerancia_minutos' => (int) $turma['tolerancia_minutos'],
                'tolerancia_antes_minutos' => (int) $turma['tolerancia_antes_minutos'],
                'ativo' => (bool) $turma['ativo']
            ];
        }, $turmas);

        $response->getBody()->write(json_encode([
            'dia' => $dia,
            'turmas' => $turmasCompletas
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

    /**
     * Consultar ID do dia pela data
     * GET /dias/por-data?data=2026-01-20
     */
    public function porData(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $data = $queryParams['data'] ?? null;
            
            if (!$data) {
                $response->getBody()->write(json_encode([
                    'type' => 'validation_error',
                    'message' => 'Data é obrigatória',
                    'reason' => 'Forneça a data no parâmetro: ?data=2026-01-20',
                    'code' => 'MISSING_DATA'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
            }
            
            // Validar formato da data
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
                $response->getBody()->write(json_encode([
                    'type' => 'validation_error',
                    'message' => 'Formato de data inválido',
                    'reason' => 'Use o formato YYYY-MM-DD (ex: 2026-01-20)',
                    'code' => 'INVALID_DATE_FORMAT',
                    'provided' => $data
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
            }
            
            // Validar se a data é válida
            $d = \DateTime::createFromFormat('Y-m-d', $data);
            if (!$d || $d->format('Y-m-d') !== $data) {
                $response->getBody()->write(json_encode([
                    'type' => 'validation_error',
                    'message' => 'Data inválida',
                    'reason' => 'A data fornecida não é válida (ex: 2026-02-30)',
                    'code' => 'INVALID_DATE',
                    'provided' => $data
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
            }
            
            // Buscar o dia
            $dia = $this->diaModel->findByData($data);
            
            if (!$dia) {
                $response->getBody()->write(json_encode([
                    'type' => 'not_found_error',
                    'message' => 'Dia não encontrado',
                    'reason' => 'A data fornecida não existe na tabela dias',
                    'code' => 'DAY_NOT_FOUND',
                    'data' => $data
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
            }
            
            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Dia encontrado com sucesso',
                'code' => 'DAY_FOUND',
                'data' => [
                    'id' => (int) $dia['id'],
                    'data' => $dia['data'],
                    'ativo' => (bool) $dia['ativo'],
                    'dia_semana' => $this->getNomeDiaSemana($dia['data']),
                    'created_at' => $dia['created_at'],
                    'updated_at' => $dia['updated_at']
                ]
            ], JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
            
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao consultar dia',
                'reason' => $e->getMessage(),
                'code' => 'INTERNAL_ERROR'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Listar dias por período
     * GET /dias/periodo?data_inicio=2026-01-01&data_fim=2026-01-31
     */
    public function periodo(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $dataInicio = $queryParams['data_inicio'] ?? null;
            $dataFim = $queryParams['data_fim'] ?? null;
            
            if (!$dataInicio || !$dataFim) {
                $response->getBody()->write(json_encode([
                    'type' => 'validation_error',
                    'message' => 'Datas são obrigatórias',
                    'reason' => 'Forneça data_inicio e data_fim',
                    'code' => 'MISSING_DATES'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
            }
            
            // Validar formatos
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicio) || 
                !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim)) {
                $response->getBody()->write(json_encode([
                    'type' => 'validation_error',
                    'message' => 'Formato de data inválido',
                    'reason' => 'Use o formato YYYY-MM-DD',
                    'code' => 'INVALID_DATE_FORMAT'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
            }
            
            // Buscar dias no período
            $db = require __DIR__ . '/../../config/database.php';
            $sql = "SELECT id, data, ativo, created_at, updated_at 
                    FROM dias 
                    WHERE data BETWEEN :data_inicio AND :data_fim 
                    ORDER BY data ASC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim
            ]);
            $dias = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            if (empty($dias)) {
                $response->getBody()->write(json_encode([
                    'type' => 'not_found_error',
                    'message' => 'Nenhum dia encontrado no período',
                    'reason' => 'Não há registros para o período informado',
                    'code' => 'NO_DAYS_FOUND',
                    'periodo' => [
                        'inicio' => $dataInicio,
                        'fim' => $dataFim
                    ]
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
            }
            
            // Formatar resposta
            $diasFormatados = array_map(function($dia) {
                return [
                    'id' => (int) $dia['id'],
                    'data' => $dia['data'],
                    'ativo' => (bool) $dia['ativo'],
                    'dia_semana' => $this->getNomeDiaSemana($dia['data']),
                    'created_at' => $dia['created_at'],
                    'updated_at' => $dia['updated_at']
                ];
            }, $dias);
            
            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Dias encontrados com sucesso',
                'code' => 'DAYS_FOUND',
                'total' => count($diasFormatados),
                'periodo' => [
                    'inicio' => $dataInicio,
                    'fim' => $dataFim
                ],
                'data' => $diasFormatados
            ], JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
            
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao consultar período',
                'reason' => $e->getMessage(),
                'code' => 'INTERNAL_ERROR'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Obter nome do dia da semana
     */
    private function getNomeDiaSemana(string $data): string
    {
        $nomesDia = [
            'Monday' => 'Segunda',
            'Tuesday' => 'Terça',
            'Wednesday' => 'Quarta',
            'Thursday' => 'Quinta',
            'Friday' => 'Sexta',
            'Saturday' => 'Sábado',
            'Sunday' => 'Domingo'
        ];
        
        $dioDaSemana = date('l', strtotime($data));
        return $nomesDia[$dioDaSemana] ?? $dioDaSemana;
    }
}

