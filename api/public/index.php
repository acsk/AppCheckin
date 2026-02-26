<?php

use Slim\Factory\AppFactory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Habilitar log de erros da aplicação sem necessidade de sudo
// Escrever em public/php-error.log para facilitar tail
try {
    ini_set('log_errors', 'On');
    ini_set('display_errors', 'Off');
    $logFile = __DIR__ . '/php-error.log';
    ini_set('error_log', $logFile);
    if (!file_exists($logFile)) {
        @touch($logFile);
        @chmod($logFile, 0664);
    }
} catch (\Throwable $e) {
    // silencioso
}

require __DIR__ . '/../vendor/autoload.php';

// Trace simples: registrar última requisição (para diagnóstico)
try {
    $trace = sprintf("[%s] %s %s\n", date('Y-m-d H:i:s'), $_SERVER['REQUEST_METHOD'] ?? '-', $_SERVER['REQUEST_URI'] ?? '-');
    @file_put_contents(__DIR__ . '/last_request.txt', $trace, FILE_APPEND);
} catch (\Throwable $e) {
    // silencioso
}

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
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, Cache-Control, Pragma, Expires, X-Tenant-Id')
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
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, Cache-Control, Pragma, Expires, X-Tenant-Id')
        ->withHeader('Access-Control-Max-Age', '86400');
});

// Middleware de parsing de JSONN
$app->addBodyParsingMiddleware();

// Routing middleware (obrigatório no Slim 4 para resolução de rotas)
$app->addRoutingMiddleware();

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
    error_log('[ErrorMiddleware] ' . get_class($exception) . ': ' . $exception->getMessage());

    // Usar status HTTP correto para exceções HTTP do Slim
    $status = 500;
    $message = 'Erro interno no servidor';
    if ($exception instanceof \Slim\Exception\HttpNotFoundException) {
        $status = 404;
        $message = 'Rota não encontrada';
    } elseif ($exception instanceof \Slim\Exception\HttpMethodNotAllowedException) {
        $status = 405;
        $message = 'Método não permitido';
    } elseif ($exception instanceof \Slim\Exception\HttpUnauthorizedException) {
        $status = 401;
        $message = 'Não autorizado';
    } elseif ($exception instanceof \Slim\Exception\HttpForbiddenException) {
        $status = 403;
        $message = 'Acesso negado';
    } elseif ($exception instanceof \Slim\Exception\HttpBadRequestException) {
        $status = 400;
        $message = 'Requisição inválida';
    } elseif (method_exists($exception, 'getCode') && $exception->getCode() >= 400 && $exception->getCode() < 600) {
        $status = $exception->getCode();
    }

    $payload = [
        'status' => 'error',
        'message' => $message,
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
