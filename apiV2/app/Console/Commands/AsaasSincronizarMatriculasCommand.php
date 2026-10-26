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

        $this->info(sprintf('%d conferidas, %d pagas, %d removidas no Asaas, %d com erro', $res['conferidas'], $res['pagas'], $res['removidas'], $res['erros']));
        foreach ($res['mensagens'] as $mensagem) {
            $this->error($mensagem);
        }

        if ($res['erros'] > 0 && $res['conferidas'] === $res['erros']) {
            // O alerta do schedule só diz "exit code 1": registra o motivo para aparecer no monitor de erros
            report(new \RuntimeException(sprintf('asaas:sincronizar-matriculas: as %d cobranças conferidas falharam. %s', $res['erros'], implode(' | ', $res['mensagens']))));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
