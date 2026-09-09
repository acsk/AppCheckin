<?php

namespace App\Support;

final class TenantWhatsappLinks
{
    public const MAX_LINKS = 10;

    /**
     * @return list<array{nome: string, url: string}>
     */
    public static function parse(mixed $value): array
    {
        if (is_array($value)) {
            return self::sanitize($value);
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? self::sanitize($decoded) : [];
    }

    /**
     * @param  list<array<string, mixed>>|mixed  $value
     */
    public static function encode(mixed $value): ?string
    {
        $links = self::parse($value);

        return $links === [] ? null : json_encode($links, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  list<array<string, mixed>>  $links
     * @return list<array{nome: string, url: string}>
     */
    public static function sanitize(array $links): array
    {
        $clean = [];

        foreach ($links as $link) {
            if (! is_array($link)) {
                continue;
            }

            $nome = trim((string) ($link['nome'] ?? ''));
            $url = trim((string) ($link['url'] ?? ''));

            if ($nome === '' || $url === '') {
                continue;
            }

            if (! self::isValidInviteUrl($url)) {
                continue;
            }

            $clean[] = [
                'nome' => mb_substr($nome, 0, 100),
                'url' => $url,
            ];

            if (count($clean) >= self::MAX_LINKS) {
                break;
            }
        }

        return $clean;
    }

    /**
     * @param  list<array<string, mixed>>|mixed  $value
     */
    public static function validateForSave(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        if (! is_array($value)) {
            return 'whatsapp_links deve ser uma lista de objetos { nome, url }';
        }

        if (count($value) > self::MAX_LINKS) {
            return 'Máximo de '.self::MAX_LINKS.' links de WhatsApp por academia';
        }

        foreach ($value as $index => $link) {
            if (! is_array($link)) {
                return 'Link #'.($index + 1).' inválido';
            }

            $nome = trim((string) ($link['nome'] ?? ''));
            $url = trim((string) ($link['url'] ?? ''));

            if ($nome === '') {
                return 'Informe o nome do grupo #'.($index + 1);
            }

            if ($url === '') {
                return 'Informe o link do grupo #'.($index + 1);
            }

            if (! self::isValidInviteUrl($url)) {
                return 'Link inválido em #'.($index + 1).' — use https://chat.whatsapp.com/...';
            }
        }

        return null;
    }

    public static function isValidInviteUrl(string $url): bool
    {
        return (bool) preg_match('#^https://(chat\.)?whatsapp\.com/[A-Za-z0-9_-]+#', $url);
    }

    /**
     * @param  array<string, mixed>  $tenant
     * @return array<string, mixed>
     */
    public static function attachToTenant(array $tenant): array
    {
        $tenant['whatsapp_links'] = self::parse($tenant['whatsapp_links'] ?? null);

        return $tenant;
    }
}
