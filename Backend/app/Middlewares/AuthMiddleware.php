<?php

namespace App\Middlewares;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Response as SlimResponse;
use App\Services\JWTService;

class AuthMiddleware
{
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (!$authHeader) {
            return $this->unauthorizedResponse('Token não fornecido');
        }

        // Formato: Bearer <token>
        $parts = explode(' ', $authHeader);
        
        if (count($parts) !== 2 || $parts[0] !== 'Bearer') {
            return $this->unauthorizedResponse('Formato de token inválido');
        }

        $token = $parts[1];

        // Validar token
        $jwtService = new JWTService($_ENV['JWT_SECRET']);
        $decoded = $jwtService->decode($token);

        if (!$decoded) {
            return $this->unauthorizedResponse('Token inválido ou expirado');
        }

        // Adicionar dados do usuário ao request
        $request = $request->withAttribute('userId', $decoded->user_id);
        $request = $request->withAttribute('userEmail', $decoded->email);

        return $handler->handle($request);
    }

    private function unauthorizedResponse(string $message): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode([
            'error' => $message
        ]));
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(401);
    }
}
