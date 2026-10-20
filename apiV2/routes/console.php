<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cobranças de contrato: cobre webhooks do Asaas que não chegaram (requer o cron do schedule:run)
\Illuminate\Support\Facades\Schedule::command('asaas:sincronizar-contratos')->hourly()->withoutOverlapping();
