<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cobranças de contrato: cobre webhooks do Asaas que não chegaram (requer o cron do schedule:run)
Schedule::command('asaas:sincronizar-contratos')->hourly()->withoutOverlapping();

// Mensalidades de alunos: baixa as cobranças Asaas pagas cujo webhook não chegou (fila interrompida, 401, 500)
Schedule::command('asaas:sincronizar-matriculas')->everyFifteenMinutes()->withoutOverlapping();
// Planos com fidelização: webhook perdido, fim da fidelização, inadimplência e cancelamentos agendados
Schedule::command('recorrencia:processar')->dailyAt('05:45')->withoutOverlapping();

// Prospecção: oportunidades de recuperação (matrículas canceladas), recuperações e score do dia
Schedule::command('prospeccao:sincronizar')->dailyAt('05:30')->withoutOverlapping();
