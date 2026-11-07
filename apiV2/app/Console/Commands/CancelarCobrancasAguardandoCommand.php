<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Muda para Cancelado (4) todas as cobranças Aguardando (1) de uma academia. */
class CancelarCobrancasAguardandoCommand extends Command
{
    protected $signature = 'cobrancas:cancelar-aguardando
        {--tenant= : Academia (obrigatório)}
        {--simular : Só conta e lista, sem gravar}';

    protected $description = 'Cancela todas as cobranças Aguardando (pagamentos_plano) de uma academia';

    public function handle(): int
    {
        $tenant = $this->option('tenant');
        if (! is_numeric($tenant)) {
            $this->error('Informe --tenant=ID.');

            return self::FAILURE;
        }
        $tenantId = (int) $tenant;

        $query = fn () => DB::table('pagamentos_plano')
            ->where('tenant_id', $tenantId)
            ->where('status_pagamento_id', 1)
            ->whereNull('data_pagamento');

        $total = $query()->count();
        $this->line("Academia {$tenantId}: {$total} cobrança(s) Aguardando.");

        if ($this->option('simular') || $total === 0) {
            if ($this->option('simular')) {
                $this->info('Simulação: nada foi gravado.');
            }

            return self::SUCCESS;
        }

        $afetadas = $query()->update([
            'status_pagamento_id' => 4,
            'observacoes' => DB::raw("CONCAT(COALESCE(observacoes, ''), ' [Cancelada em lote em ".date('d/m/Y')."]')"),
            'updated_at' => now(),
        ]);
        $this->info("{$afetadas} cobrança(s) canceladas.");

        return self::SUCCESS;
    }
}
