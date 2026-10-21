<?php

namespace App\Console\Commands;

use App\Repositories\ProspeccaoRepository;
use App\Services\Admin\Prospeccao\ProspeccaoSincronizacaoService;
use Illuminate\Console\Command;

class ProspeccaoSincronizarCommand extends Command
{
    protected $signature = 'prospeccao:sincronizar
                            {--tenant= : só esta academia}';

    protected $description = 'Gera oportunidades de recuperação a partir das matrículas canceladas, marca recuperações e atualiza o score';

    public function handle(ProspeccaoSincronizacaoService $sync, ProspeccaoRepository $repo): int
    {
        $tenant = $this->option('tenant');
        $tenants = is_numeric($tenant) ? [(int) $tenant] : $repo->tenantsAtivos();
        $falhas = 0;

        foreach ($tenants as $tenantId) {
            try {
                $r = $sync->sincronizar($tenantId);
                $this->line(sprintf('tenant %d: %d criadas, %d atualizadas, %d recuperadas, %d scores', $tenantId, $r['criadas'], $r['atualizadas'], $r['recuperadas'], $r['scores']));
            } catch (\Throwable $e) {
                $falhas++;
                report($e);
                $this->error(sprintf('tenant %d: %s', $tenantId, $e->getMessage()));
            }
        }

        return $falhas > 0 && $falhas === count($tenants) ? self::FAILURE : self::SUCCESS;
    }
}
