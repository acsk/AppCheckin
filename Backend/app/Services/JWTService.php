<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTService
{
    private string $secret;
    private int $expiration;

    public function __construct(string $secret, int $expiration = 86400)
    {
        $this->secret = $secret;
        $this->expiration = $expiration;
    }

    public function encode(array $payload): string
    {
        $issuedAt = time();
        $expire = $issuedAt + $this->expiration;

        $data = array_merge($payload, [
            'iat' => $issuedAt,
            'exp' => $expire
        ]);

        return JWT::encode($data, $this->secret, 'HS256');
    }

    public function decode(string $token): ?object
    {
        try {
            return JWT::decode($token, new Key($this->secret, 'HS256'));
        } catch (\Exception $e) {
            return null;
        }
    }

    public function verify(string $token): bool
    {
        return $this->decode($token) !== null;
    }
}
