<?php

namespace App\Console\Commands;

use App\Services\Asaas\AsaasPagamentoSync;
use Illuminate\Console\Command;

class AsaasSincronizarMatriculasCommand extends Command
{
    protected $signature = 'asaas:sincronizar-matriculas
                            {--tenant= : só as cobranças desta academia}
                            {--limite=200 : máximo de cobranças conferidas}
                            {--dias=60 : só cobranças criadas/atualizadas nos últimos N dias}';

    protected $description = 'Confere no Asaas as cobranças atuais de matrículas com parcela em aberto e dá baixa nas que o webhook não trouxe';

    public function handle(AsaasPagamentoSync $sync): int
    {
        $tenant = $this->option('tenant');
        $res = $sync->sincronizarPendentes(
            is_numeric($tenant) ? (int) $tenant : null,
            max(1, (int) $this->option('limite')),
            max(1, (int) $this->option('dias')),
        );

        $this->info(sprintf('%d conferidas, %d pagas, %d com erro', $res['conferidas'], $res['pagas'], $res['erros']));

        return $res['erros'] > 0 && $res['conferidas'] === $res['erros'] ? self::FAILURE : self::SUCCESS;
    }
}
