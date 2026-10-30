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
// Pendências abandonadas no app (checkout, simulação de troca de plano, assinatura não ativada)
Schedule::command('cobrancas:limpar-pendentes')->dailyAt('04:30')->withoutOverlapping();
Schedule::command('recorrencia:processar')->dailyAt('05:45')->withoutOverlapping();

// Status das matrículas (vencida/cancelada/pendente/ativa) sem esperar o aluno abrir o app.
// Desligado até conferir `php artisan matriculas:atualizar-status --simular` (MATRICULAS_STATUS_AUTOMATICO=true)
Schedule::command('matriculas:atualizar-status')->hourlyAt(10)->withoutOverlapping()
    ->when(fn () => (bool) config('appcheckin.matriculas_status_automatico'));

// Prospecção: oportunidades de recuperação (matrículas canceladas), recuperações e score do dia
Schedule::command('prospeccao:sincronizar')->dailyAt('05:30')->withoutOverlapping();
