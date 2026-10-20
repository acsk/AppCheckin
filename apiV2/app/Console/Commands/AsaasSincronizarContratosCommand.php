<?php

namespace App\Console\Commands;

use App\Services\SuperAdmin\CobrancaContratoService;
use Illuminate\Console\Command;

class AsaasSincronizarContratosCommand extends Command
{
    protected $signature = 'asaas:sincronizar-contratos
                            {--tenant= : só as cobranças desta academia}
                            {--limite=200 : máximo de cobranças conferidas}';

    protected $description = 'Confere no Asaas as cobranças de contrato em aberto e aplica pagamentos, exclusões e vencimentos que o webhook não trouxe';

    public function handle(CobrancaContratoService $cobrancas): int
    {
        $tenant = $this->option('tenant');
        $res = $cobrancas->sincronizar(is_numeric($tenant) ? (int) $tenant : null, max(1, (int) $this->option('limite')));

        $this->info(sprintf('%d conferidas, %d alteradas, %d com erro', $res['conferidas'], $res['alteradas'], $res['erros']));

        return $res['erros'] > 0 && $res['conferidas'] === $res['erros'] ? self::FAILURE : self::SUCCESS;
    }
}
