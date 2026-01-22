<?php

use Slim\Factory\AppFactory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/../vendor/autoload.php';

// Carregar variáveis de ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Configurar timezone para Brasil
date_default_timezone_set('America/Sao_Paulo');

$app = AppFactory::create();

// ========================================
// CORS Middleware (PRIMEIRO!)
// ========================================
// Executar ANTES de tudo para interceptar OPTIONS
$app->add(function ($request, $handler) {
    // Interceptar OPTIONS - retornar 200 imediatamente
    if ($request->getMethod() === 'OPTIONS') {
        $response = new \Slim\Psr7\Response();
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
            ->withHeader('Access-Control-Max-Age', '86400')
            ->withStatus(200);
    }
    
    // Para outros métodos, prosseguir normalmente e adicionar headers CORS
    $response = $handler->handle($request);
    
    // Adicionar headers CORS mas preservar Content-Type, Status e Body
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Max-Age', '86400');
});

// Middleware de parsing de JSONN
$app->addBodyParsingMiddleware();

// Middleware de erro
$app->addErrorMiddleware(true, true, true);

// ========================================
// CARREGAR ROTAS
// ========================================
$routes = require __DIR__ . '/../routes/api.php';
$routes($app);

// ========================================
// EXECUTAR APLICAÇÃO
// ========================================
$app->run();
