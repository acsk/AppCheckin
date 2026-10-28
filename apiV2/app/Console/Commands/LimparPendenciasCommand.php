<?php

namespace App\Console\Commands;

use App\Services\LimpezaPendenciasService;
use Illuminate\Console\Command;

/** Encerra checkouts, simulações de troca de plano e assinaturas com fidelidade abandonados (sem pagamento). */
class LimparPendenciasCommand extends Command
{
    protected $signature = 'cobrancas:limpar-pendentes
        {--dias=3 : Idade mínima (dias) de checkouts e simulações não pagos}
        {--dias-fidelidade=7 : Idade mínima (dias) de assinatura com fidelidade não ativada}
        {--tenant= : Só uma academia}
        {--simular : Só lista, sem cancelar nada}';

    protected $description = 'Cancela pendências de pagamento abandonadas no app (confere no Asaas antes)';

    public function handle(LimpezaPendenciasService $service): int
    {
        $simular = (bool) $this->option('simular');
        $r = $service->executar((int) $this->option('dias'), (int) $this->option('dias-fidelidade'),
            $this->option('tenant') !== null ? (int) $this->option('tenant') : null, $simular);
        foreach ($r['itens'] as $item) {
            $this->line(' - '.$item);
        }
        $this->info(sprintf('%s: %d simulações, %d checkouts, %d matrículas, %d assinaturas com fidelidade. %d mantidas (pagas), %d falhas.',
            $simular ? 'Simulação' : 'Limpeza', $r['simulacoes'], $r['checkouts'], $r['matriculas'], $r['assinaturas_fidelidade'], $r['ignoradas_pagas'], $r['falhas']));

        return self::SUCCESS;
    }
}
