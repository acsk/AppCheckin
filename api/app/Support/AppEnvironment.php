<?php

namespace App\Support;

/**
 * Utilitários de ambiente da aplicação.
 */
final class AppEnvironment
{
    private const DEVELOPMENT_ENVS = ['local', 'development', 'dev', 'testing'];

    public static function current(): string
    {
        $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV');

        return is_string($env) && $env !== ''
            ? strtolower($env)
            : 'production';
    }

    public static function isDevelopment(): bool
    {
        return in_array(self::current(), self::DEVELOPMENT_ENVS, true);
    }

    public static function isProduction(): bool
    {
        return !self::isDevelopment();
    }

    /**
     * Resposta JSON 404 para rotas disponíveis apenas em desenvolvimento.
     */
    public static function developmentOnlyResponse(\Psr\Http\Message\ResponseInterface $response): \Psr\Http\Message\ResponseInterface
    {
        $response->getBody()->write(json_encode([
            'type' => 'error',
            'code' => 'NOT_FOUND',
            'message' => 'Rota não encontrada',
        ], JSON_UNESCAPED_UNICODE));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(404);
    }
}
