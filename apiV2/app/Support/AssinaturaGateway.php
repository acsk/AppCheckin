<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class AssinaturaGateway
{
    public static function id(string $codigo): int
    {
        $id = DB::table('assinatura_gateways')->where('codigo', $codigo)->value('id');
        if ($id) {
            return (int) $id;
        }

        $nomes = [
            'asaas' => 'Asaas',
            'mercadopago' => 'Mercado Pago',
        ];

        return (int) DB::table('assinatura_gateways')->insertGetId([
            'codigo' => $codigo,
            'nome' => $nomes[$codigo] ?? $codigo,
            'ativo' => 1,
        ]);
    }
}
