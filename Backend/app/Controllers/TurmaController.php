<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Turma;
use App\Models\Professor;
use App\Models\Modalidade;
use App\Models\Dia;
use PDO;

class TurmaController
{
    private Turma $turmaModel;
    private Professor $professorModel;
    private Modalidade $modalidadeModel;
    private Dia $diaModel;

    public function __construct()
    {
        $db = require __DIR__ . '/../../config/database.php';
        $this->turmaModel = new Turma($db);
        $this->professorModel = new Professor($db);
        $this->modalidadeModel = new Modalidade($db);
        $this->diaModel = new Dia($db);
    }

    /**
     * Listar turmas do tenant
     * GET /admin/turmas
     * Query params: apenas_ativas=true, data=2026-01-10 ou dia_id=18
     */
    public function index(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $queryParams = $request->getQueryParams();
        $apenasAtivas = isset($queryParams['apenas_ativas']) && $queryParams['apenas_ativas'] === 'true';
        
        // Aceitar tanto 'data' quanto 'dia_id'
        $data = isset($queryParams['data']) ? $queryParams['data'] : null;
        $diaId = isset($queryParams['dia_id']) ? (int) $queryParams['dia_id'] : null;
        
        $dia = null;
        $turmas = [];
        
        // Se data foi fornecida, buscar o dia por data
        if ($data) {
            // Validar formato da data
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Formato de data inválido. Use YYYY-MM-DD'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
            }
            
            $dia = $this->diaModel->findByData($data);
            if (!$dia) {
                // Retornar array vazio ao invés de erro
                $dia = null;
                $turmas = [];
            } else {
                $turmas = $this->turmaModel->listarPorDia($tenantId, $dia['id'], $apenasAtivas);
            }
        }
        // Se dia_id foi fornecido, usar esse
        elseif ($diaId) {
            $dia = $this->diaModel->findById($diaId);
            if (!$dia) {
                // Retornar array vazio ao invés de erro
                $dia = null;
                $turmas = [];
            } else {
                $turmas = $this->turmaModel->listarPorDia($tenantId, $diaId, $apenasAtivas);
            }
        }
        // Se nenhum foi fornecido, usar hoje
        else {
            $hoje = date('Y-m-d');
            $dia = $this->diaModel->findByData($hoje);
            
            if (!$dia) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Dia atual não encontrado no sistema'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
            }
            $turmas = $this->turmaModel->listarPorDia($tenantId, $dia['id'], $apenasAtivas);
        }
        
        $response->getBody()->write(json_encode([
            'dia' => $dia,
            'turmas' => $turmas
        ], JSON_UNESCAPED_UNICODE));
        
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Listar turmas de um dia específico (para mobile)
     * GET /admin/turmas/dia/{diaId}
     */
    public function listarPorDia(Request $request, Response $response, array $args): Response
    {
        $diaId = (int) $args['diaId'];
        $tenantId = $request->getAttribute('tenantId');
        
        // Verificar se dia existe
        $dia = $this->diaModel->findById($diaId);
        if (!$dia) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Dia não encontrado'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }
        
        $turmas = $this->turmaModel->listarPorDia($tenantId, $diaId);
        
        $response->getBody()->write(json_encode([
            'dia' => $dia,
            'turmas' => $turmas
        ], JSON_UNESCAPED_UNICODE));
        
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Buscar turma por ID
     * GET /admin/turmas/{id}
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId');
        
        $turma = $this->turmaModel->findById($id, $tenantId);
        
        if (!$turma) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Turma não encontrada'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }
        
        $response->getBody()->write(json_encode([
            'turma' => $turma
        ], JSON_UNESCAPED_UNICODE));
        
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Criar nova turma
     * POST /admin/turmas
     * 
     * Request body:
     * {
     *   "nome": "Turma A",
     *   "professor_id": 1,
     *   "modalidade_id": 1,
     *   "dia_id": 18,
     *   "horario_inicio": "04:00", ou "04:00:00"
     *   "horario_fim": "04:30", ou "04:30:00"
     *   "limite_alunos": 20
     * }
     */
    public function create(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $data = $request->getParsedBody();
        
        // Validações básicas
        if (empty($data['nome'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Nome da turma é obrigatório'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
        }
        
        if (empty($data['professor_id'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Professor é obrigatório'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
        }
        
        if (empty($data['modalidade_id'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Modalidade é obrigatória'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
        }
        
        if (empty($data['dia_id'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Dia é obrigatório'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
        }
        
        if (empty($data['horario_inicio']) || empty($data['horario_fim'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Horário de início e fim são obrigatórios'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
        }
        
        // Validar que horario_fim é maior que horario_inicio
        if ($data['horario_inicio'] >= $data['horario_fim']) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Horário de fim deve ser maior que horário de início'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
        }
        
        // Verificar se professor pertence ao tenant
        if (!$this->professorModel->pertenceAoTenant($data['professor_id'], $tenantId)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Professor não encontrado'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
        }
        
        // Verificar se dia existe
        if (!$this->diaModel->findById($data['dia_id'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Dia não encontrado'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
        }
        
        // Validar limite de alunos
        if (!empty($data['limite_alunos']) && $data['limite_alunos'] < 1) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Limite de alunos deve ser maior que 0'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
        }
        
        // Validar: não pode haver outra turma com horário conflitante
        $turmasComConflito = $this->turmaModel->verificarHorarioOcupado(
            $tenantId, 
            $data['dia_id'], 
            $data['horario_inicio'],
            $data['horario_fim']
        );
        
        if (!empty($turmasComConflito)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Já existe uma turma agendada com horário conflitante neste dia'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
        }
        
        $data['tenant_id'] = $tenantId;
        
        try {
            $id = $this->turmaModel->create($data);
            $turma = $this->turmaModel->findById($id);
            
            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Turma criada com sucesso',
                'turma' => $turma
            ], JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(201);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao criar turma: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Atualizar turma
     * PUT /admin/turmas/{id}
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId');
        $data = $request->getParsedBody();
        
        // Verificar se turma existe e pertence ao tenant
        $turma = $this->turmaModel->findById($id, $tenantId);
        if (!$turma) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Turma não encontrada'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }
        
        // Validações
        if (!empty($data['professor_id'])) {
            if (!$this->professorModel->pertenceAoTenant($data['professor_id'], $tenantId)) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Professor não encontrado'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
            }
        }
        
        if (!empty($data['dia_id'])) {
            if (!$this->diaModel->findById($data['dia_id'])) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Dia não encontrado'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
            }
        }
        
        // Validar horários se estão sendo atualizados
        if (!empty($data['horario_inicio']) && !empty($data['horario_fim'])) {
            if ($data['horario_inicio'] >= $data['horario_fim']) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Horário de fim deve ser maior que horário de início'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
            }
        }
        
        if (!empty($data['limite_alunos']) && $data['limite_alunos'] < 1) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Limite de alunos deve ser maior que 0'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
        }
        
        // Validar: não pode haver outra turma com horário conflitante (se mudando horário ou dia)
        if (!empty($data['horario_inicio']) && !empty($data['horario_fim']) || !empty($data['dia_id'])) {
            $horarioInicio = !empty($data['horario_inicio']) ? $data['horario_inicio'] : $turma['horario_inicio'];
            $horarioFim = !empty($data['horario_fim']) ? $data['horario_fim'] : $turma['horario_fim'];
            $diaId = !empty($data['dia_id']) ? $data['dia_id'] : $turma['dia_id'];
            
            $turmasComConflito = $this->turmaModel->verificarHorarioOcupado(
                $tenantId, 
                $diaId, 
                $horarioInicio,
                $horarioFim,
                $id // Excluir esta turma da verificação
            );
            
            if (!empty($turmasComConflito)) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Já existe outra turma agendada com horário conflitante neste dia'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
            }
        }
        
        try {
            $this->turmaModel->update($id, $data);
            $turmaAtualizada = $this->turmaModel->findById($id);
            
            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Turma atualizada com sucesso',
                'turma' => $turmaAtualizada
            ], JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao atualizar turma: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Deletar turma
     * DELETE /admin/turmas/{id}
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId');
        
        // Verificar se turma existe e pertence ao tenant
        $turma = $this->turmaModel->findById($id, $tenantId);
        if (!$turma) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Turma não encontrada'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }
        
        try {
            $this->turmaModel->delete($id);
            
            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Turma deletada com sucesso'
            ], JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao deletar turma: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Listar turmas de um professor
     * GET /admin/professores/{professorId}/turmas
     */
    public function listarPorProfessor(Request $request, Response $response, array $args): Response
    {
        $professorId = (int) $args['professorId'];
        $tenantId = $request->getAttribute('tenantId');
        
        // Verificar se professor existe
        if (!$this->professorModel->pertenceAoTenant($professorId, $tenantId)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Professor não encontrado'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }
        
        $turmas = $this->turmaModel->listarPorProfessor($professorId, $tenantId);
        
        $response->getBody()->write(json_encode([
            'turmas' => $turmas
        ], JSON_UNESCAPED_UNICODE));
        
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Verificar disponibilidade de vagas
     * GET /admin/turmas/{id}/vagas
     */
    public function verificarVagas(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId');
        
        $turma = $this->turmaModel->findById($id, $tenantId);
        if (!$turma) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Turma não encontrada'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }
        
        $alunos = $this->turmaModel->contarAlunos($id);
        $temVagas = $this->turmaModel->temVagas($id);
        
        $response->getBody()->write(json_encode([
            'turma_id' => $id,
            'limite_alunos' => $turma['limite_alunos'],
            'alunos_inscritos' => $alunos,
            'vagas_disponiveis' => $turma['limite_alunos'] - $alunos,
            'tem_vagas' => $temVagas
        ], JSON_UNESCAPED_UNICODE));
        
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * POST /admin/turmas/replicar
     * Replicas turmas para dias da semana do mês
     * Body: { "dia_id": 17, "dias_semana": [2,3,4,5,6], "mes": "2026-01" }
     * dias_semana: 1=dom, 2=seg, 3=ter, 4=qua, 5=qui, 6=sex, 7=sab
     */
    public function replicarPorDiasSemana(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $data = $request->getParsedBody();
        
        // Validar inputs
        if (!isset($data['dia_id']) || !isset($data['dias_semana'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'dia_id e dias_semana são obrigatórios'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
        }

        $diaOrigemId = (int) $data['dia_id'];
        $diasSemana = (array) $data['dias_semana'];
        $mes = $data['mes'] ?? date('Y-m');

        // Validar dias_semana
        foreach ($diasSemana as $dia) {
            if (!is_numeric($dia) || $dia < 1 || $dia > 7) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'dias_semana deve conter valores entre 1 e 7'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
            }
        }

        try {
            // Buscar turmas do dia origem
            $turmasOrigem = $this->turmaModel->listarPorDia($diaOrigemId, $tenantId);

            if (empty($turmasOrigem)) {
                $response->getBody()->write(json_encode([
                    'type' => 'success',
                    'message' => 'Nenhuma turma encontrada no dia de origem',
                    'summary' => [
                        'total_solicitadas' => 0,
                        'total_criadas' => 0,
                        'total_puladas' => 0
                    ]
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
            }

            // Buscar dias do mês com os dias_semana especificados
            $diasDestino = $this->buscarDiasDoMes($mes, $diasSemana, $tenantId, $diaOrigemId);

            if (empty($diasDestino)) {
                $response->getBody()->write(json_encode([
                    'type' => 'success',
                    'message' => 'Nenhum dia encontrado para replicação no período',
                    'summary' => [
                        'total_solicitadas' => count($turmasOrigem),
                        'total_criadas' => 0,
                        'total_puladas' => 0
                    ]
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
            }

            // Replicar turmas
            $totalCriadas = 0;
            $totalPuladas = 0;
            $detalhes = [];
            $turmasCriadas = [];

            foreach ($turmasOrigem as $turmaOrigem) {
                $detalheTurma = [
                    'turma_original_id' => $turmaOrigem['id'],
                    'professor_id' => $turmaOrigem['professor_id'],
                    'modalidade_id' => $turmaOrigem['modalidade_id'],
                    'horario_inicio' => $turmaOrigem['horario_inicio'],
                    'horario_fim' => $turmaOrigem['horario_fim'],
                    'criadas' => 0,
                    'puladas' => 0,
                    'detalhes_puladas' => []
                ];

                foreach ($diasDestino as $diaDestino) {
                    // Verificar se já existe turma neste horário neste dia
                    $temConflito = $this->turmaModel->verificarHorarioOcupado(
                        $tenantId,
                        $diaDestino['id'],
                        $turmaOrigem['horario_inicio'],
                        $turmaOrigem['horario_fim']
                    );

                    if ($temConflito) {
                        $detalheTurma['puladas']++;
                        $detalheTurma['detalhes_puladas'][] = [
                            'dia_id' => $diaDestino['id'],
                            'data' => $diaDestino['data'],
                            'razao' => 'Horário já ocupado'
                        ];
                        $totalPuladas++;
                        continue;
                    }

                    // Criar nova turma
                    $novaTurma = [
                        'tenant_id' => $tenantId,
                        'professor_id' => (int) $turmaOrigem['professor_id'],
                        'modalidade_id' => (int) $turmaOrigem['modalidade_id'],
                        'dia_id' => (int) $diaDestino['id'],
                        'horario_inicio' => $turmaOrigem['horario_inicio'],
                        'horario_fim' => $turmaOrigem['horario_fim'],
                        'nome' => $turmaOrigem['nome'] ?? '',
                        'limite_alunos' => (int) $turmaOrigem['limite_alunos'],
                        'ativo' => 1
                    ];

                    $idNovoTurma = $this->turmaModel->create($novaTurma);
                    if ($idNovoTurma) {
                        $detalheTurma['criadas']++;
                        $totalCriadas++;
                        
                        // Recuperar turma criada para resposta
                        $turmaCompleta = $this->turmaModel->findById($idNovoTurma, $tenantId);
                        if ($turmaCompleta) {
                            $turmasCriadas[] = $turmaCompleta;
                        }
                    }
                }

                $detalhes[] = $detalheTurma;
            }

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Replicação concluída com sucesso',
                'summary' => [
                    'total_solicitadas' => count($turmasOrigem),
                    'total_criadas' => $totalCriadas,
                    'total_puladas' => $totalPuladas,
                    'dias_destino' => count($diasDestino)
                ],
                'detalhes' => $detalhes,
                'turmas_criadas' => $turmasCriadas
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(201);

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao replicar turmas: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Buscar dias do mês com os dias da semana especificados
     */
    private function buscarDiasDoMes(string $mes, array $diasSemana, int $tenantId, int $diaExcluir = null): array
    {
        $diasSemanaStr = implode(',', $diasSemana);
        
        $sql = "SELECT * FROM dias 
                WHERE DATE_FORMAT(data, '%Y-%m') = ? 
                AND DAYOFWEEK(data) IN ($diasSemanaStr)
                AND ativo = 1";
        
        $params = [$mes];
        
        if ($diaExcluir !== null) {
            $sql .= " AND id != ?";
            $params[] = $diaExcluir;
        }
        
        $sql .= " ORDER BY data ASC";

        $db = $this->getConnection();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
