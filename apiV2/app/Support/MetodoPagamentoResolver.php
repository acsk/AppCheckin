<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class MetodoPagamentoResolver
{
    /**
     * Resolve metodo_pagamento.id by codigo. Returns $fallback when the row is missing
     * (Slim uses 1 for credit_card on comprar-plano; pix may stay unset).
     */
    public static function resolve(string $codigo, ?int $fallback = null): ?int
    {
        $id = DB::table('metodos_pagamento')->where('codigo', $codigo)->value('id');

        return $id !== null ? (int) $id : $fallback;
    }
}
