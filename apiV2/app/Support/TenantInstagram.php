<?php

namespace App\Support;

final class TenantInstagram
{
    public static function validar(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value) || strlen($value) > 2048) {
            return 'Informe um link válido de perfil do Instagram.';
        }
        $url = trim($value);
        $parts = parse_url($url);
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! is_array($parts)
            || ($parts['scheme'] ?? '') !== 'https'
            || ! in_array(strtolower($parts['host'] ?? ''), ['instagram.com', 'www.instagram.com'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
            || ! preg_match('#^/[A-Za-z0-9_.]{1,30}/?$#', $parts['path'] ?? '')
            || in_array(strtolower(trim($parts['path'] ?? '', '/')), ['p', 'reel', 'reels', 'stories', 'explore', 'accounts', 'direct'], true)) {
            return 'Use o link do perfil: https://www.instagram.com/usuario/';
        }

        return null;
    }

    public static function normalizar(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' && self::validar($value) === null ? trim($value) : null;
    }
}
