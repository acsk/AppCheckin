<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ConfigController
{
    /**
     * Listar formas de pagamento ativas
     */
    public function listarFormasPagamento(Request $request, Response $response): Response
    {
        $db = require __DIR__ . '/../../config/database.php';
        
        $stmt = $db->prepare("
            SELECT id, nome, percentual_desconto 
            FROM formas_pagamento 
            WHERE ativo = 1
            ORDER BY nome
        ");
        $stmt->execute();
        $formas = $stmt->fetchAll();
        
        $response->getBody()->write(json_encode($formas));
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    /**
     * Listar status de contas
     */
    public function listarStatusConta(Request $request, Response $response): Response
    {
        $db = require __DIR__ . '/../../config/database.php';
        
        $stmt = $db->prepare("
            SELECT id, nome, cor 
            FROM status_conta 
            ORDER BY nome
        ");
        $stmt->execute();
        $status = $stmt->fetchAll();
        
        $response->getBody()->write(json_encode($status));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
