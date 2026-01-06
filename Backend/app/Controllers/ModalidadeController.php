<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Modalidade;

class ModalidadeController
{
    private Modalidade $modalidadeModel;

    public function __construct()
    {
        $db = require __DIR__ . '/../../config/database.php';
        $this->modalidadeModel = new Modalidade($db);
    }

    /**
     * Listar modalidades do tenant
     * GET /admin/modalidades
     */
    public function index(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $queryParams = $request->getQueryParams();
        $apenasAtivas = isset($queryParams['apenas_ativas']) && $queryParams['apenas_ativas'] === 'true';
        
        $modalidades = $this->modalidadeModel->listarPorTenant($tenantId, $apenasAtivas);
        
        $response->getBody()->write(json_encode([
            'modalidades' => $modalidades
        ], JSON_UNESCAPED_UNICODE));
        
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Buscar modalidade por ID
     * GET /admin/modalidades/{id}
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $tenantId = $request->getAttribute('tenant_id');
        
        $modalidade = $this->modalidadeModel->buscarPorId($id);
        
        if (!$modalidade) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Modalidade não encontrada'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }
        
        // Verificar se a modalidade pertence ao tenant
        if ($modalidade['tenant_id'] !== $tenantId) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Acesso negado'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(403);
        }
        
        $response->getBody()->write(json_encode([
            'modalidade' => $modalidade
        ], JSON_UNESCAPED_UNICODE));
        
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Criar nova modalidade
     * POST /admin/modalidades
     */
    public function create(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $data = $request->getParsedBody();
        
        // Validações
        $errors = [];
        if (empty($data['nome'])) {
            $errors[] = 'Nome é obrigatório';
        }
        if (isset($data['valor_mensalidade']) && !is_numeric($data['valor_mensalidade'])) {
            $errors[] = 'Valor da mensalidade inválido';
        }
        
        if (!empty($errors)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => implode(', ', $errors)
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(422);
        }
        
        // Verificar se nome já existe
        if ($this->modalidadeModel->nomeExiste($tenantId, $data['nome'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Já existe uma modalidade com este nome'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(422);
        }
        
        try {
            $data['tenant_id'] = $tenantId;
            $id = $this->modalidadeModel->criar($data);
            $modalidade = $this->modalidadeModel->buscarPorId($id);
            
            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Modalidade criada com sucesso',
                'modalidade' => $modalidade
            ], JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(201);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao criar modalidade: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Atualizar modalidade
     * PUT /admin/modalidades/{id}
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $tenantId = $request->getAttribute('tenant_id');
        $data = $request->getParsedBody();
        
        $modalidade = $this->modalidadeModel->buscarPorId($id);
        
        if (!$modalidade) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Modalidade não encontrada'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }
        
        // Verificar se a modalidade pertence ao tenant
        if ($modalidade['tenant_id'] !== $tenantId) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Acesso negado'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(403);
        }
        
        // Validações
        $errors = [];
        if (empty($data['nome'])) {
            $errors[] = 'Nome é obrigatório';
        }
        if (isset($data['valor_mensalidade']) && !is_numeric($data['valor_mensalidade'])) {
            $errors[] = 'Valor da mensalidade inválido';
        }
        
        if (!empty($errors)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => implode(', ', $errors)
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(422);
        }
        
        // Verificar se nome já existe (exceto para própria modalidade)
        if ($this->modalidadeModel->nomeExiste($tenantId, $data['nome'], $id)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Já existe uma modalidade com este nome'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(422);
        }
        
        try {
            $this->modalidadeModel->atualizar($id, $data);
            $modalidade = $this->modalidadeModel->buscarPorId($id);
            
            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Modalidade atualizada com sucesso',
                'modalidade' => $modalidade
            ], JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao atualizar modalidade: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Excluir modalidade
     * DELETE /admin/modalidades/{id}
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $tenantId = $request->getAttribute('tenant_id');
        
        $modalidade = $this->modalidadeModel->buscarPorId($id);
        
        if (!$modalidade) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Modalidade não encontrada'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
        }
        
        // Verificar se a modalidade pertence ao tenant
        if ($modalidade['tenant_id'] !== $tenantId) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Acesso negado'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(403);
        }
        
        try {
            $this->modalidadeModel->excluir($id);
            
            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Modalidade desativada com sucesso'
            ], JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao excluir modalidade: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }
}
