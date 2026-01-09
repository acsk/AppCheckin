<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Turma;
use App\Models\Professor;
use App\Models\Modalidade;
use App\Models\Dia;
use App\Models\Horario;
use PDO;

class TurmaController
{
    private Turma $turmaModel;
    private Professor $professorModel;
    private Modalidade $modalidadeModel;
    private Dia $diaModel;
    private Horario $horarioModel;

    public function __construct()
    {
        $db = require __DIR__ . '/../../config/database.php';
        $this->turmaModel = new Turma($db);
        $this->professorModel = new Professor($db);
        $this->modalidadeModel = new Modalidade($db);
        $this->diaModel = new Dia($db);
        $this->horarioModel = new Horario($db);
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
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Dia não encontrado para a data informada'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
            }
            $turmas = $this->turmaModel->listarPorDia($tenantId, $dia['id'], $apenasAtivas);
        }
        // Se dia_id foi fornecido, usar esse
        elseif ($diaId) {
            $dia = $this->diaModel->findById($diaId);
            if (!$dia) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Dia não encontrado'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
            }
            $turmas = $this->turmaModel->listarPorDia($tenantId, $diaId, $apenasAtivas);
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
        
        if (empty($data['horario_id'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Horário é obrigatório'
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
        
        // Verificar se horário existe
        if (!$this->horarioModel->findById($data['horario_id'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Horário não encontrado'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
        }
        
        if (!empty($data['limite_alunos']) && $data['limite_alunos'] < 1) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Limite de alunos deve ser maior que 0'
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
        
        if (!empty($data['horario_id'])) {
            if (!$this->horarioModel->findById($data['horario_id'])) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Horário não encontrado'
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
}
