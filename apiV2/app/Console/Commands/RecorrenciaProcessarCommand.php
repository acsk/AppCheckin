<?php

namespace App\Console\Commands;

use App\Services\Recorrencia\RecorrenciaService;
use Illuminate\Console\Command;

/** Rotina dos planos com fidelização: conferência no Asaas, fim da fidelização, inadimplência e cancelamentos agendados. */
class RecorrenciaProcessarCommand extends Command
{
    protected $signature = 'recorrencia:processar {--tenant= : Só uma academia}';

    protected $description = 'Sincroniza assinaturas mensais com o Asaas e aplica as regras de fidelização';

    public function handle(RecorrenciaService $service): int
    {
        $tenant = $this->option('tenant') !== null ? (int) $this->option('tenant') : null;
        $r = $service->rotina($tenant);
        $this->info(sprintf(
            'Recorrência: %d sincronizados, %d fidelizações conferidas, %d encerrados, %d inadimplentes, %d cancelamentos efetivados.',
            $r['sincronizados'], $r['fidelizacoes'], $r['encerrados'], $r['inadimplentes'], $r['agendados_cancelados'],
        ));

        return self::SUCCESS;
    }
}
