<?php

use App\Services\Asaas\AsaasWebhookService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Parcelas pagas pelo Asaas ficaram com "Aguardando pagamento via Mercado Pago [Asaas pay_...]":
 * a compra pelo app gravava o texto do Mercado Pago e a baixa só acrescentava o id do Asaas.
 * Troca o começo por "Pago via Asaas (forma)", como a baixa passa a fazer.
 */
return new class extends Migration
{
    private const BILLING = ['Cartão' => 'CREDIT_CARD', 'Pix' => 'PIX', 'Boleto' => 'BOLETO'];

    public function up(): void
    {
        $rows = DB::table('pagamentos_plano as p')
            ->leftJoin('formas_pagamento as fp', 'fp.id', '=', 'p.forma_pagamento_id')
            ->where('p.status_pagamento_id', 2)
            ->where('p.observacoes', 'like', 'Aguardando pagamento%')
            ->where('p.observacoes', 'like', '%[Asaas pay_%')
            ->get(['p.id', 'p.observacoes', 'fp.nome as forma']);

        $antes = [];
        foreach ($rows as $r) {
            preg_match('/\[Asaas (pay_[A-Za-z0-9]+)\]/', (string) $r->observacoes, $m);
            $billing = null;
            foreach (self::BILLING as $nome => $tipo) {
                if (stripos((string) $r->forma, $nome) !== false || stripos((string) $r->forma, strtolower($nome)) !== false) {
                    $billing = $tipo;
                }
            }
            $nova = AsaasWebhookService::observacaoPagoAsaas((string) $r->observacoes, $m[1] ?? '', ['billingType' => $billing]);
            if ($nova !== $r->observacoes) {
                DB::table('pagamentos_plano')->where('id', $r->id)->update(['observacoes' => $nova]);
                $antes[$r->id] = $r->observacoes;
            }
        }

        if ($antes !== []) {
            Log::info('Parcelas pagas pelo Asaas: observação corrigida', ['total' => count($antes), 'anteriores' => $antes]);
        }
    }

    public function down(): void
    {
        // Sem volta automática: os textos anteriores ficam no laravel.log.
    }
};
