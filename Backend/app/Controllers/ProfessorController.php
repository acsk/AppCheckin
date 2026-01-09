<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Professor;
use App\Models\Tenant;
use PDO;

class ProfessorController
{
    private Professor $professorModel;
    private Tenant $tenantModel;

    public function __construct()
    {
        $db = require __DIR__ . '/../../config/database.php';
        $this->professorModel = new Professor($db);
        $this->tenantModel = new Tenant($db);
    }

    /**
     * Listar professores do tenant
     * GET /admin/professores
     */
    public function index(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $queryParams = $request->getQueryParams();
        $apenasAtivos = isset($queryParams['apenas_ativos']) && $queryParams['apenas_ativos'] === 'true';
        
        $professores = $this->professorModel->listarPorTenant($tenantId, $apenasAtivos);
        
        $response->getBody()->write(json_encode([
            'professores' => $professores
        ], JSON_UNESCAPED_UNICODE));
        
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Buscar professor por ID
     * GET /admin/professores/{id}
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId');
        
        $professor = $this->professorModel->findById($id, $tenantId);
        
        if (!$professor) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Professor não encontrado'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }
        
        $response->getBody()->write(json_encode([
            'professor' => $professor
        ], JSON_UNESCAPED_UNICODE));
        
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Criar novo professor
     * POST /admin/professores
     */
    public function create(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $data = $request->getParsedBody();
        
        // Validações básicas
        if (empty($data['nome'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Nome do professor é obrigatório'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
        }
        
        // Verificar email único por tenant se informado
        if (!empty($data['email'])) {
            $existente = $this->professorModel->findByEmail($data['email'], $tenantId);
            if ($existente) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Email já cadastrado para outro professor'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
            }
        }
        
        $data['tenant_id'] = $tenantId;
        
        try {
            $id = $this->professorModel->create($data);
            $professor = $this->professorModel->findById($id);
            
            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Professor criado com sucesso',
                'professor' => $professor
            ], JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(201);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao criar professor: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Atualizar professor
     * PUT /admin/professores/{id}
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId');
        $data = $request->getParsedBody();
        
        // Verificar se professor existe e pertence ao tenant
        $professor = $this->professorModel->findById($id, $tenantId);
        if (!$professor) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Professor não encontrado'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }
        
        // Se alterar email, verificar se é único
        if (!empty($data['email']) && $data['email'] !== $professor['email']) {
            $existente = $this->professorModel->findByEmail($data['email'], $tenantId);
            if ($existente) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Email já cadastrado para outro professor'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
            }
        }
        
        try {
            $this->professorModel->update($id, $data);
            $professorAtualizado = $this->professorModel->findById($id);
            
            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Professor atualizado com sucesso',
                'professor' => $professorAtualizado
            ], JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao atualizar professor: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Deletar professor
     * DELETE /admin/professores/{id}
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId');
        
        // Verificar se professor existe e pertence ao tenant
        $professor = $this->professorModel->findById($id, $tenantId);
        if (!$professor) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Professor não encontrado'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }
        
        try {
            $this->professorModel->delete($id);
            
            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Professor deletado com sucesso'
            ], JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao deletar professor: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }
}
