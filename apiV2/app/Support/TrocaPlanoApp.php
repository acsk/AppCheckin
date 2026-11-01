<?php

namespace App\Support;

use App\Services\AppConfigService;

/**
 * Parâmetro troca_plano_app: com ele desligado (padrão), migrar de plano e cancelar o plano pelo app ficam
 * bloqueados. O aluno pede o cancelamento à academia, o admin cancela no painel (com ou sem crédito) e o
 * aluno contrata o plano novo pelo app, com o crédito abatido.
 */
final class TrocaPlanoApp
{
    public const CODIGO = 'TROCA_PLANO_PELA_ACADEMIA';

    public const MENSAGEM = 'A troca de plano é feita pela academia. Solicite o cancelamento do plano atual na recepção; '
        .'depois é só contratar o novo plano pelo app, com o crédito gerado (se houver) já abatido.';

    public static function permitida(?int $tenantId): bool
    {
        return app(AppConfigService::class)->parametro('troca_plano_app', $tenantId) === true;
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public static function bloqueio(array $extra = []): array
    {
        return ['status' => 403, 'body' => ['success' => false, 'type' => 'info', 'code' => self::CODIGO, 'message' => self::MENSAGEM, 'error' => self::MENSAGEM] + $extra];
    }
}
