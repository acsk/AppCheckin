<?php

namespace App\Console\Commands;

use App\Services\Asaas\AsaasWebhookService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AsaasCorrigirObservacoesCommand extends Command
{
    protected $signature = 'asaas:corrigir-observacoes
                            {--tenant= : só este tenant_id}
                            {--aplicar : grava a correção (sem isso só lista)}';

    protected $description = 'Troca "Aguardando pagamento via Mercado Pago" por "Pago via Asaas" nas parcelas pagas pelo Asaas';

    public function handle(): int
    {
        $formas = DB::table('formas_pagamento')->pluck('nome', 'id');

        $query = DB::table('pagamentos_plano')
            ->where('status_pagamento_id', 2)
            ->where('observacoes', 'like', '%[Asaas pay_%')
            ->where('observacoes', 'like', 'Aguardando pagamento%')
            ->orderBy('id');
        if (is_numeric($this->option('tenant'))) {
            $query->where('tenant_id', (int) $this->option('tenant'));
        }

        $total = 0;
        foreach ($query->get(['id', 'tenant_id', 'forma_pagamento_id', 'observacoes']) as $pg) {
            $obs = (string) $pg->observacoes;
            preg_match('/\[Asaas (pay_[A-Za-z0-9]+)\]/', $obs, $m);
            $nova = AsaasWebhookService::observacaoPagoAsaas($obs, $m[1] ?? '', [
                'billingType' => self::billingType((string) ($formas[$pg->forma_pagamento_id] ?? '')),
            ]);
            if ($nova === $obs) {
                continue;
            }

            $total++;
            $this->line("#{$pg->id} (tenant {$pg->tenant_id})");
            $this->line("  antes:  {$obs}");
            $this->line("  depois: {$nova}");

            if ($this->option('aplicar')) {
                DB::table('pagamentos_plano')->where('id', $pg->id)->update(['observacoes' => $nova, 'updated_at' => now()]);
            }
        }

        $this->info($this->option('aplicar')
            ? "{$total} parcela(s) corrigida(s)."
            : "{$total} parcela(s) para corrigir. Rode com --aplicar para gravar.");

        return self::SUCCESS;
    }

    private static function billingType(string $forma): string
    {
        return match (true) {
            stripos($forma, 'cart') !== false || stripos($forma, 'créd') !== false => 'CREDIT_CARD',
            stripos($forma, 'pix') !== false => 'PIX',
            stripos($forma, 'boleto') !== false => 'BOLETO',
            default => '',
        };
    }
}
