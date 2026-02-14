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
    private PDO $db;

    public function __construct()
    {
        $this->db = require __DIR__ . '/../../config/database.php';
        $this->turmaModel = new Turma($this->db);
        $this->professorModel = new Professor($this->db);
        $this->modalidadeModel = new Modalidade($this->db);
        $this->diaModel = new Dia($this->db);
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
     *   "limite_alunos": 20,
     *   "tolerancia_minutos": 10,              (opcional, padrão: 10)
     *   "tolerancia_antes_minutos": 480        (opcional, padrão: 480)
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
            $data['horario_fim'],
            null,
            $data['professor_id']
        );
        
        if (!empty($turmasComConflito)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'O professor já possui uma turma agendada neste horário neste dia'
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
            $professorId = !empty($data['professor_id']) ? $data['professor_id'] : $turma['professor_id'];
            
            $turmasComConflito = $this->turmaModel->verificarHorarioOcupado(
                $tenantId, 
                $diaId, 
                $horarioInicio,
                $horarioFim,
                $id, // Excluir esta turma da verificação
                $professorId
            );
            
            if (!empty($turmasComConflito)) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'O professor já possui outra turma agendada neste horário neste dia'
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
     * Deletar turma permanentemente
     * DELETE /admin/turmas/{id}/permanente
     */
    public function deletePermanente(Request $request, Response $response, array $args): Response
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
            $this->turmaModel->deleteHard($id);
            
            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Turma deletada permanentemente'
            ], JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao deletar turma permanentemente: ' . $e->getMessage()
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
     * 
     * Body options:
     * 1. Próxima semana (mesmo dia da semana):
     *    { "dia_id": 17, "periodo": "proxima_semana" }
     * 
     * 2. Mês inteiro (seg-dom):
     *    { "dia_id": 17, "periodo": "mes_todo", "mes": "2026-01" }
     * 
     * 3. Dias customizados:
     *    { "dia_id": 17, "dias_semana": [2,3,4,5,6], "mes": "2026-01" }
     * 
     * dias_semana: 1=dom, 2=seg, 3=ter, 4=qua, 5=qui, 6=sex, 7=sab
     */
    public function replicarPorDiasSemana(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $data = $request->getParsedBody();
        
        // Validar input obrigatório
        if (!isset($data['dia_id'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'dia_id é obrigatório'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
        }

        $diaOrigemId = (int) $data['dia_id'];
        $periodo = $data['periodo'] ?? 'custom';
        $mes = $data['mes'] ?? date('Y-m');
        $diasSemana = [];

        // Processar conforme tipo de período
        if ($periodo === 'proxima_semana') {
            // Buscar dia origem para descobrir qual dia da semana é
            $diaOrigem = $this->diaModel->buscarPorId($diaOrigemId, $tenantId);
            if (!$diaOrigem) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Dia de origem não encontrado'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
            }
            
            // Pegar dia da semana (1=dom, 2=seg, etc)
            $diaSemanaNum = (int) date('N', strtotime($diaOrigem['data']));
            // Converter de N (1=seg, 7=dom) para nosso sistema (1=dom, 7=sab)
            $diaSemanaNum = $diaSemanaNum === 7 ? 1 : $diaSemanaNum + 1;
            $diasSemana = [$diaSemanaNum];
            
            // Mês que vem
            $dataProxSemana = new \DateTime($diaOrigem['data']);
            $dataProxSemana->add(new \DateInterval('P7D'));
            $mes = $dataProxSemana->format('Y-m');
            
        } elseif ($periodo === 'mes_todo') {
            // Replicar para todos os dias da semana (seg-dom)
            $diasSemana = [1, 2, 3, 4, 5, 6, 7];
            
        } elseif ($periodo === 'custom') {
            // Usar dias_semana customizados
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
                'message' => 'periodo deve ser: proxima_semana, mes_todo ou custom'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
        }

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
            $turmasOrigem = $this->turmaModel->listarPorDia($tenantId, $diaOrigemId);

            // Filtrar por modalidade se fornecida
            if (!empty($data['modalidade_id'])) {
                $modalidadeId = (int) $data['modalidade_id'];
                $turmasOrigem = array_filter($turmasOrigem, function($turma) use ($modalidadeId) {
                    return (int) $turma['modalidade_id'] === $modalidadeId;
                });
                $turmasOrigem = array_values($turmasOrigem); // Reindexar array
            }

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
                    // Verificar se já existe turma neste horário para este professor neste dia
                    $temConflito = $this->turmaModel->verificarHorarioOcupado(
                        $tenantId,
                        $diaDestino['id'],
                        $turmaOrigem['horario_inicio'],
                        $turmaOrigem['horario_fim'],
                        null,
                        $turmaOrigem['professor_id']
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
     * Retorna a conexão do banco de dados
     */
    private function getConnection(): PDO
    {
        return $this->db;
    }

    /**
     * Buscar dias do mês com os dias da semana especificados
     */
    private function buscarDiasDoMes(string $mes, array $diasSemana, int $tenantId, int $diaExcluir = null): array
    {
        $diasSemanaStr = implode(',', $diasSemana);
        
        // Converter mês (YYYY-MM) para data inicial e final
        $dataParts = explode('-', $mes);
        if (count($dataParts) !== 2) {
            return [];
        }
        $ano = (int)$dataParts[0];
        $mes_num = (int)$dataParts[1];
        
        $dataInicio = sprintf('%04d-%02d-01', $ano, $mes_num);
        $dataFim = date('Y-m-t', strtotime($dataInicio));
        
        // Usar YEAR() e MONTH() para evitar problemas de collation
        $sql = "SELECT * FROM dias 
                WHERE YEAR(data) = :ano 
                AND MONTH(data) = :mes 
                AND DAYOFWEEK(data) IN ($diasSemanaStr)
                AND ativo = 1";
        
        $params = [
            'ano' => $ano,
            'mes' => $mes_num
        ];
        
        if ($diaExcluir !== null) {
            $sql .= " AND id != :dia_excluir";
            $params['dia_excluir'] = $diaExcluir;
        }
        
        $sql .= " ORDER BY data ASC";

        $db = $this->getConnection();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * POST /admin/turmas/desativar
     * Desativa turmas com opção de replicar para outros dias da semana
     * 
     * Body options:
     * 1. Desativar turma específica:
     *    { "turma_id": 1 }
     * 
     * 2. Desativar em outros dias (próxima semana, mesmo horário):
     *    { "turma_id": 1, "periodo": "proxima_semana" }
     * 
     * 3. Desativar em mês inteiro (mesmo horário, todos os dias):
     *    { "turma_id": 1, "periodo": "mes_todo", "mes": "2026-01" }
     * 
     * 4. Desativar em dias customizados:
     *    { "turma_id": 1, "periodo": "custom", "dias_semana": [2,3,4,5,6], "mes": "2026-01" }
     */
    public function desativarTurma(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $data = $request->getParsedBody();
        
        // Validar input obrigatório
        if (!isset($data['turma_id'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'turma_id é obrigatório'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
        }

        $turmaId = (int) $data['turma_id'];
        $periodo = $data['periodo'] ?? 'apenas_esta';
        $mes = $data['mes'] ?? date('Y-m');
        $diasSemana = [];

        try {
            // Buscar turma origem
            $turmaOrigem = $this->turmaModel->findById($turmaId, $tenantId);
            if (!$turmaOrigem) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Turma não encontrada'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
            }

            // Determinar quais dias devem ser desativados
            if ($periodo === 'apenas_esta') {
                // Desativar apenas esta turma
                $this->turmaModel->desativar($turmaId);
                
                $response->getBody()->write(json_encode([
                    'type' => 'success',
                    'message' => 'Turma desativada com sucesso',
                    'summary' => [
                        'total_desativadas' => 1
                    ]
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
            }

            // Para os outros períodos, precisamos encontrar turmas similares em outros dias
            if ($periodo === 'proxima_semana') {
                // Buscar dia origem
                $diaOrigem = $this->diaModel->buscarPorId($turmaOrigem['dia_id'], $tenantId);
                if (!$diaOrigem) {
                    throw new \Exception('Dia de origem não encontrado');
                }
                
                // Pegar dia da semana
                $diaSemanaNum = (int) date('N', strtotime($diaOrigem['data']));
                $diaSemanaNum = $diaSemanaNum === 7 ? 1 : $diaSemanaNum + 1;
                $diasSemana = [$diaSemanaNum];
                
                // Próxima semana
                $dataProxSemana = new \DateTime($diaOrigem['data']);
                $dataProxSemana->add(new \DateInterval('P7D'));
                $mes = $dataProxSemana->format('Y-m');
                
            } elseif ($periodo === 'mes_todo') {
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
                    'message' => 'periodo deve ser: apenas_esta, proxima_semana, mes_todo ou custom'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
            }

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

            // Desativar turma original
            $this->turmaModel->desativar($turmaId);
            $totalDesativadas = 1;

            // Buscar dias do mês com os dias_semana especificados
            $diasDestino = $this->buscarDiasDoMes($mes, $diasSemana, $tenantId, $turmaOrigem['dia_id']);

            $detalhes = [];

            // Desativar turmas similares (mesmo professor, modalidade, hora) em outros dias
            foreach ($diasDestino as $diaDestino) {
                // Buscar turma com mesmo horário neste dia
                $sql = "SELECT id FROM turmas 
                        WHERE tenant_id = ? 
                        AND dia_id = ? 
                        AND professor_id = ? 
                        AND modalidade_id = ? 
                        AND horario_inicio = ? 
                        AND horario_fim = ?
                        LIMIT 1";
                
                $db = $this->getConnection();
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $tenantId,
                    $diaDestino['id'],
                    $turmaOrigem['professor_id'],
                    $turmaOrigem['modalidade_id'],
                    $turmaOrigem['horario_inicio'],
                    $turmaOrigem['horario_fim']
                ]);
                
                $turmaEncontrada = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if ($turmaEncontrada) {
                    $this->turmaModel->desativar($turmaEncontrada['id']);
                    $totalDesativadas++;
                    $detalhes[] = [
                        'turma_id' => $turmaEncontrada['id'],
                        'dia_id' => $diaDestino['id'],
                        'data' => $diaDestino['data'],
                        'status' => 'desativada'
                    ];
                } else {
                    $detalhes[] = [
                        'dia_id' => $diaDestino['id'],
                        'data' => $diaDestino['data'],
                        'status' => 'nao_encontrada',
                        'motivo' => 'Nenhuma turma com mesmo horário neste dia'
                    ];
                }
            }

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Turmas desativadas com sucesso',
                'summary' => [
                    'total_desativadas' => $totalDesativadas
                ],
                'detalhes' => $detalhes
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao desativar turmas: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }
}
