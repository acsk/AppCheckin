<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ParametrosController
{
    public function index(Request $request, Response $response): Response
    {
        $response->getBody()->write(json_encode([
            'message' => 'Parametros controller pronto'
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }
}
