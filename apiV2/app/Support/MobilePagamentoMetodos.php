<?php

namespace App\Support;

use App\Models\Parametro;
use Illuminate\Support\Facades\DB;

/**
 * Resolve métodos de pagamento do mobile conforme parâmetros do tenant.
 */
final class MobilePagamentoMetodos
{
    /**
     * @return array{habilitar_pix: bool, habilitar_cartao_credito: bool}
     */
    public static function flags(int $tenantId): array
    {
        $pdo = DB::connection()->getPdo();
        require_once base_path('../api/app/Models/Parametro.php');

        $parametro = new Parametro($pdo);

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

    public static function isRecorrenteEfetivo(bool $permiteRecorrenciaCiclo, bool $habilitarCartao): bool
    {
        return $permiteRecorrenciaCiclo && $habilitarCartao;
    }

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
