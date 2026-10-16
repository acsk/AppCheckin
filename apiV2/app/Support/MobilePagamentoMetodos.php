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
     * @return array{habilitar_pix: bool, habilitar_cartao_credito: bool, habilitar_boleto: bool}
     */
    public static function flags(int $tenantId): array
    {
        $parametro = new Parametro(DB::connection()->getPdo());

        return [
            'habilitar_pix' => $parametro->isEnabled($tenantId, 'habilitar_pix'),
            'habilitar_cartao_credito' => $parametro->isEnabled($tenantId, 'habilitar_cartao_credito'),
            'habilitar_boleto' => $parametro->isEnabled($tenantId, 'habilitar_boleto'),
        ];
    }

    /**
     * @return list<string>
     */
    public static function metodosPagamentoCiclo(
        bool $permiteRecorrencia,
        bool $habilitarCartao,
        bool $habilitarPix,
        bool $habilitarBoleto = false,
    ): array {
        $checkout = $habilitarCartao || $habilitarBoleto;
        if (! $checkout && ! $habilitarPix) {
            return [];
        }

        if ($permiteRecorrencia && $habilitarCartao) {
            return ['checkout'];
        }

        $metodos = [];
        if ($habilitarPix) {
            $metodos[] = 'pix';
        }
        if ($checkout) {
            $metodos[] = 'checkout';
        }

        return $metodos;
    }

    public static function isRecorrenteEfetivo(bool $permiteRecorrenciaCiclo, bool $habilitarCartao): bool
    {
        return $permiteRecorrenciaCiclo && $habilitarCartao;
    }

    public static function normalizarMetodo(
        string $metodoPagamento,
        bool $habilitarCartao,
        bool $habilitarPix,
        bool $habilitarBoleto = false,
    ): string {
        $metodo = strtolower(trim($metodoPagamento));

        if ($metodo === 'checkout' && ! $habilitarCartao && ! $habilitarBoleto && $habilitarPix) {
            return 'pix';
        }

        return $metodo;
    }
}
