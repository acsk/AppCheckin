<?php

namespace App\Console\Commands;

use App\Support\OpsErrorAlertTestRunner;
use Illuminate\Console\Command;

class OpsErrorAlertTestCommand extends Command
{
    protected $signature = 'ops:error-alert-test {--force : Ignora throttle de 15 minutos} {--clear-throttle : Limpa cache de throttle antes do teste}';

    protected $description = 'Dispara log de erro de teste e tenta enviar o e-mail de alerta ops';

    public function handle(): int
    {
        $report = OpsErrorAlertTestRunner::run(
            force: (bool) $this->option('force'),
            clearThrottle: (bool) $this->option('clear-throttle'),
        );

        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return ($report['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
