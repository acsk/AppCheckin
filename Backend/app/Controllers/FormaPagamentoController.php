<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\FormaPagamento;

class FormaPagamentoController
{
    private FormaPagamento $formaPagamentoModel;

    public function __construct()
    {
        $db = require __DIR__ . '/../../config/database.php';
        $this->formaPagamentoModel = new FormaPagamento($db);
    }

    /**
     * Listar todas as formas de pagamento
     * GET /formas-pagamento
     */
    public function index(Request $request, Response $response): Response
    {
        $formas = $this->formaPagamentoModel->listarTodas();
        
        $response->getBody()->write(json_encode([
            'formas' => $formas
        ], JSON_UNESCAPED_UNICODE));
        
        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    }
}
