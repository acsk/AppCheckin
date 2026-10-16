<?php

namespace App\Support;

/**
 * Contrato oficial Asaas: https://docs.asaas.com/docs/autenticacao-1
 * Sandbox: $aact_hmlg_…  Produção: $aact_prod_…
 * O `$` faz parte da chave (não remover). Não usar Authorization: Bearer.
 */
final class AsaasApiKey
{
    public const PREFIXO_SANDBOX = '$aact_hmlg_';

    public const PREFIXO_PRODUCAO = '$aact_prod_';

    public static function normalizar(string $key): string
    {
        return trim($key);
    }

    public static function prefixo(string $environment): string
    {
        return $environment === 'production' ? self::PREFIXO_PRODUCAO : self::PREFIXO_SANDBOX;
    }

    public static function valida(string $key, string $environment): bool
    {
        $key = self::normalizar($key);

        return str_starts_with($key, self::prefixo($environment));
    }

    public static function mensagemInvalida(string $environment): string
    {
        $prefixo = self::prefixo($environment);
        $onde = $environment === 'production'
            ? 'https://www.asaas.com (Integrações → API Key)'
            : 'https://sandbox.asaas.com (Integrações → API Key)';

        return "A API key do Asaas ({$environment}) deve começar com {$prefixo} (o \$ faz parte da chave). Gere em {$onde}. UUID ou token antigo não autentica. Docs: https://docs.asaas.com/reference/comece-por-aqui";
    }
}
