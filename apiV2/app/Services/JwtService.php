<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * JWT compatível com a API Slim (HS256, mesmos claims: user_id, email, tenant_id, aluno_id).
 */
class JwtService
{
    public function __construct(
        private readonly string $secret,
        private readonly int $expiration = 86400,
    ) {}

    public function encode(array $payload): string
    {
        if (strlen($this->secret) < 16) {
            throw new \RuntimeException('JWT_SECRET inválido ou não configurado');
        }

        $issuedAt = time();
        $data = array_merge($payload, [
            'iat' => $issuedAt,
            'exp' => $issuedAt + $this->expiration,
        ]);

        try {
            return JWT::encode($data, $this->secret, 'HS256');
        } catch (\Throwable $e) {
            throw new \RuntimeException('JWT_SECRET inválido ou não configurado', 0, $e);
        }
    }

    public function decode(string $token): ?object
    {
        try {
            return JWT::decode($token, new Key($this->secret, 'HS256'));
        } catch (\Throwable) {
            return null;
        }
    }

    public function verify(string $token): bool
    {
        return $this->decode($token) !== null;
    }
}
