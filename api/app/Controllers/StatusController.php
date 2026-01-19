<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

/**
 * Controller para gerenciar tabelas de status
 * Centraliza o acesso aos diferentes tipos de status do sistema
 */
class StatusController
{
    private $db;
    
    /**
     * Mapeamento de tipos de status para suas tabelas
     */
    private $tabelas = [
        'conta-receber' => 'status_conta_receber',
        'matricula' => 'status_matricula',
        'pagamento' => 'status_pagamento',
        'checkin' => 'status_checkin',
        'usuario' => 'status_usuario',
        'contrato' => 'status_contrato',
    ];
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    /**
     * Listar todos os status de um tipo
     * 
     * @api GET /api/status/{tipo}
     * @param string $tipo Tipo do status (conta-receber, matricula, etc)
     * @return array Lista de status ativos ordenados
     */
    public function listar(Request $request, Response $response, array $args): Response
    {
        $tipo = $args['tipo'] ?? null;
        
        if (!isset($this->tabelas[$tipo])) {
            $response->getBody()->write(json_encode([
                'error' => 'Tipo de status inválido',
                'tipos_validos' => array_keys($this->tabelas)
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $tabela = $this->tabelas[$tipo];
        
        try {
            $sql = "SELECT * FROM {$tabela} WHERE ativo = TRUE ORDER BY ordem, nome";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $status = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response->getBody()->write(json_encode([
                'tipo' => $tipo,
                'status' => $status,
                'total' => count($status)
            ]));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            
        } catch (\PDOException $e) {
            error_log("Erro ao listar status: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'error' => 'Erro ao buscar status'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Buscar status específico por ID
     * 
     * @api GET /api/status/{tipo}/{id}
     */
    public function buscar(Request $request, Response $response, array $args): Response
    {
        $tipo = $args['tipo'] ?? null;
        $id = $args['id'] ?? null;
        
        if (!isset($this->tabelas[$tipo])) {
            $response->getBody()->write(json_encode([
                'error' => 'Tipo de status inválido'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $tabela = $this->tabelas[$tipo];
        
        try {
            $sql = "SELECT * FROM {$tabela} WHERE id = :id AND ativo = TRUE";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);
            $status = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$status) {
                $response->getBody()->write(json_encode([
                    'error' => 'Status não encontrado'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            $response->getBody()->write(json_encode($status));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            
        } catch (\PDOException $e) {
            error_log("Erro ao buscar status: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'error' => 'Erro ao buscar status'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Buscar status por código
     * 
     * @api GET /api/status/{tipo}/codigo/{codigo}
     */
    public function buscarPorCodigo(Request $request, Response $response, array $args): Response
    {
        $tipo = $args['tipo'] ?? null;
        $codigo = $args['codigo'] ?? null;
        
        if (!isset($this->tabelas[$tipo])) {
            $response->getBody()->write(json_encode([
                'error' => 'Tipo de status inválido'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $tabela = $this->tabelas[$tipo];
        
        try {
            $sql = "SELECT * FROM {$tabela} WHERE codigo = :codigo AND ativo = TRUE";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['codigo' => $codigo]);
            $status = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$status) {
                $response->getBody()->write(json_encode([
                    'error' => 'Status não encontrado'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            $response->getBody()->write(json_encode($status));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            
        } catch (\PDOException $e) {
            error_log("Erro ao buscar status: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'error' => 'Erro ao buscar status'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Helper: Obter ID do status pelo código
     * Útil para conversão ENUM → FK
     */
    public function getIdByCodigo(string $tipo, string $codigo): ?int
    {
        if (!isset($this->tabelas[$tipo])) {
            return null;
        }
        
        $tabela = $this->tabelas[$tipo];
        
        try {
            $sql = "SELECT id FROM {$tabela} WHERE codigo = :codigo AND ativo = TRUE";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['codigo' => $codigo]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? (int) $result['id'] : null;
            
        } catch (\PDOException $e) {
            error_log("Erro ao buscar ID do status: " . $e->getMessage());
            return null;
        }
    }
}
