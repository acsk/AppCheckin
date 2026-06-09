<?php

namespace App\Support;

use App\Models\Parametro;
use PDO;

/**
 * Resolve métodos de pagamento do mobile conforme parâmetros do tenant.
 */
final class MobilePagamentoMetodos
{
    /**
     * @return array{habilitar_pix: bool, habilitar_cartao_credito: bool}
     */
    public static function flags(PDO $db, int $tenantId): array
    {
        $parametro = new Parametro($db);

        return [
            'habilitar_pix' => $parametro->isEnabled($tenantId, 'habilitar_pix'),
            'habilitar_cartao_credito' => $parametro->isEnabled($tenantId, 'habilitar_cartao_credito'),
        ];
    }

    /**
     * @return list<string>
     */
    public static function metodosPagamentoCiclo(
        bool $permiteRecorrencia,
        bool $habilitarCartao,
        bool $habilitarPix,
    ): array {
        if (! $habilitarCartao && ! $habilitarPix) {
            return [];
        }

        if (! $habilitarCartao) {
            return $habilitarPix ? ['pix'] : [];
        }

        if ($permiteRecorrencia) {
            return ['checkout'];
        }

        return $habilitarPix ? ['pix'] : ['checkout'];
    }

    /**
     * Recorrente/preapproval só vale com cartão habilitado no tenant.
     */
    public static function isRecorrenteEfetivo(bool $permiteRecorrenciaCiclo, bool $habilitarCartao): bool
    {
        return $permiteRecorrenciaCiclo && $habilitarCartao;
    }

    /**
     * Normaliza método solicitado pelo app quando checkout não está disponível.
     */
    public static function normalizarMetodo(
        string $metodoPagamento,
        bool $habilitarCartao,
        bool $habilitarPix,
    ): string {
        $metodo = strtolower(trim($metodoPagamento));

        if ($metodo === 'checkout' && ! $habilitarCartao && $habilitarPix) {
            return 'pix';
        }

        return $metodo;
    }
}
