<?php

namespace App\Console\Commands;

use App\Services\MercadoPago\MercadoPagoService;
use App\Support\MercadoPagoPagamento;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Parcelas baixadas pelo Mercado Pago com a forma errada: o polling gravava Pix como 8 (Boleto) e débito como
 * 10 (Operadora). A forma certa sai de pagamentos_mercadopago (payment_method_id / payment_type_id).
 * Com --liquido também consulta o pagamento no MP (só leitura) e grava valor_liquido e desconto_provedor.
 */
class MercadoPagoCorrigirFormasCommand extends Command
{
    protected $signature = 'mercadopago:corrigir-formas
                            {--tenant= : só este tenant_id}
                            {--liquido : consulta o MP e grava taxa e líquido nas parcelas sem líquido}
                            {--aplicar : grava a correção (sem isso só lista)}';

    protected $description = 'Corrige a forma de pagamento (Pix gravado como Boleto) e o líquido das parcelas pagas pelo Mercado Pago';

    public function handle(): int
    {
        $formas = DB::table('formas_pagamento')->pluck('nome', 'id');
        $temLiquido = Schema::hasColumn('pagamentos_plano', 'valor_liquido');
        $temDesconto = Schema::hasColumn('pagamentos_plano', 'desconto_provedor');

        $query = DB::table('pagamentos_plano')
            ->where('status_pagamento_id', 2)
            ->where('observacoes', 'like', '%Mercado Pago%')
            ->orderBy('id');
        if (is_numeric($this->option('tenant'))) {
            $query->where('tenant_id', (int) $this->option('tenant'));
        }
        $colunas = ['id', 'tenant_id', 'valor', 'forma_pagamento_id', 'observacoes'];
        if ($temLiquido) {
            $colunas[] = 'valor_liquido';
        }

        $servicos = [];
        $total = 0;
        foreach ($query->get($colunas) as $pg) {
            if (! preg_match('/(?:ID: |Payment #)(\d{6,})/', (string) $pg->observacoes, $m)) {
                continue;
            }
            $paymentId = $m[1];
            $mp = DB::table('pagamentos_mercadopago')
                ->where('tenant_id', $pg->tenant_id)
                ->where('payment_id', $paymentId)
                ->first(['payment_method_id', 'payment_type_id']);

            $mudancas = [];
            if ($mp) {
                $forma = MercadoPagoPagamento::formaPagamentoId((array) $mp);
                if ($forma !== (int) $pg->forma_pagamento_id) {
                    $mudancas['forma_pagamento_id'] = $forma;
                }
            }

            if ($this->option('liquido') && $temLiquido && $pg->valor_liquido === null) {
                try {
                    $servicos[$pg->tenant_id] ??= new MercadoPagoService((int) $pg->tenant_id);
                    $raw = $servicos[$pg->tenant_id]->buscarPagamento($paymentId);
                    $raw = is_array($raw['raw'] ?? null) ? $raw['raw'] : $raw;
                    $liquido = MercadoPagoPagamento::liquidoDoPagamento($raw);
                    if ($liquido !== null) {
                        $mudancas['valor_liquido'] = $liquido;
                        if ($temDesconto) {
                            $mudancas['desconto_provedor'] = max(0, round((float) ($raw['transaction_amount'] ?? $pg->valor) - $liquido, 2));
                        }
                    }
                } catch (Throwable $e) {
                    $this->warn("#{$pg->id}: não foi possível consultar o pagamento {$paymentId} no MP ({$e->getMessage()})");
                }
            }

            if ($mudancas === []) {
                continue;
            }

            $total++;
            $partes = [];
            if (isset($mudancas['forma_pagamento_id'])) {
                $partes[] = sprintf('forma %s → %s', $formas[$pg->forma_pagamento_id] ?? '—', $formas[$mudancas['forma_pagamento_id']] ?? $mudancas['forma_pagamento_id']);
            }
            if (isset($mudancas['valor_liquido'])) {
                $partes[] = sprintf('líquido R$ %s (taxa R$ %s)', number_format($mudancas['valor_liquido'], 2, ',', '.'), number_format($mudancas['desconto_provedor'] ?? 0, 2, ',', '.'));
            }
            $this->line("#{$pg->id} (tenant {$pg->tenant_id}, MP {$paymentId}): ".implode(', ', $partes));

            if ($this->option('aplicar')) {
                DB::table('pagamentos_plano')->where('id', $pg->id)->update($mudancas + ['updated_at' => now()]);
            }
        }

        $this->info($this->option('aplicar')
            ? "{$total} parcela(s) corrigida(s)."
            : "{$total} parcela(s) para corrigir. Rode com --aplicar para gravar.");

        return self::SUCCESS;
    }
}
