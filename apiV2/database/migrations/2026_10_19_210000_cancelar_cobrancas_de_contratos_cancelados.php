<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Antes da correção da troca de plano, o contrato substituído era cancelado mas a cobrança "Aguardando"
 * dele ficava em aberto (e podia ser paga pelo painel da academia). Cancela essas sobras.
 * Cobranças atrasadas continuam: são de período já usado.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sobras = DB::table('pagamentos_contrato as p')
            ->join('tenant_planos_sistema as c', 'c.id', '=', 'p.tenant_plano_id')
            ->where('c.status_id', 3)
            ->where('p.status_pagamento_id', 1)
            ->get(['p.id', 'p.gateway_payment_id']);

        if ($sobras->isEmpty()) {
            return;
        }

        DB::table('pagamentos_contrato')
            ->whereIn('id', $sobras->pluck('id')->all())
            ->update([
                'status_pagamento_id' => 4,
                'observacoes' => DB::raw("CONCAT(COALESCE(observacoes, ''), ' [Cancelada: contrato substituído na troca de plano]')"),
                'updated_at' => DB::raw('NOW()'),
            ]);

        // Cobranças que já tinham link no Asaas precisam ser canceladas lá também (painel do Asaas)
        $noGateway = $sobras->whereNotNull('gateway_payment_id')->pluck('gateway_payment_id')->all();
        if ($noGateway !== []) {
            Log::warning('Cobranças de contrato canceladas com link ainda ativo no Asaas', ['gateway_payment_ids' => $noGateway]);
        }
    }

    public function down(): void
    {
        // Sem volta: não há como saber quais cobranças canceladas estavam em aberto antes.
    }
};
