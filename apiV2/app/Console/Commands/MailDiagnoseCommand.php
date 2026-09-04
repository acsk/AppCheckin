<?php

namespace App\Console\Commands;

use App\Support\MailDiagnostics;
use Illuminate\Console\Command;

class MailDiagnoseCommand extends Command
{
    protected $signature = 'mail:diagnose {--send-test= : Envia e-mail de teste para este endereço}';

    protected $description = 'Diagnostica configuração Resend/Laravel Mail';

    public function handle(): int
    {
        $sendTest = $this->option('send-test');
        $sendTest = is_string($sendTest) && $sendTest !== '' ? $sendTest : null;

        $report = MailDiagnostics::run($sendTest);

        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return ($report['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
