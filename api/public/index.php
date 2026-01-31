<?php

use Slim\Factory\AppFactory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/../vendor/autoload.php';

// Carregar variáveis de ambiente
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
} catch (\Throwable $e) {
    // Em produção, pode não existir .env; seguir com variáveis do ambiente
    error_log('[index.php] .ENV não carregado: ' . $e->getMessage());
}

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
// Middleware de erro com resposta JSON
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorMiddleware->setDefaultErrorHandler(function (
    Request $request,
    \Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails
) use ($app) {
    // Log básico
    error_log('[ErrorMiddleware] ' . $exception->getMessage());

    $status = 500;
    $payload = [
        'status' => 'error',
        'message' => 'Erro interno no servidor',
    ];

    // Quando possível, retornar detalhes úteis (apenas se habilitado)
    if ($displayErrorDetails) {
        $payload['details'] = [
            'type' => get_class($exception),
            'error' => $exception->getMessage(),
        ];
    }

    $response = $app->getResponseFactory()->createResponse($status);
    $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json');
});

// ========================================
// CARREGAR ROTAS
// ========================================
$routes = require __DIR__ . '/../routes/api.php';
$routes($app);

// ========================================
// EXECUTAR APLICAÇÃO
// ========================================
$app->run();
