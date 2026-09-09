<?php

namespace App\Support;

use App\Repositories\ApplicationErrorLogRepository;
use Illuminate\Support\Facades\DB;

final class OpsErrorLogDiagnostics
{
    /**
     * @return array<string, mixed>
     */
    public static function snapshot(): array
    {
        $repo = app(ApplicationErrorLogRepository::class);
        $tableExists = $repo->tableExists();

        $recent = [];
        if ($tableExists) {
            try {
                $recent = DB::table('application_error_logs')
                    ->orderByDesc('created_at')
                    ->limit(5)
                    ->get(['id', 'description', 'level', 'created_at'])
                    ->map(fn ($row) => (array) $row)
                    ->all();
            } catch (\Throwable $e) {
                $recent = ['error' => $e->getMessage()];
            }
        }

        $stackChannels = config('logging.channels.stack.channels', []);
        $logStack = is_array($stackChannels)
            ? implode(',', $stackChannels)
            : (string) env('LOG_STACK', 'single,database_errors');

        $issues = [];
        if (! str_contains($logStack, 'database_errors')) {
            $issues[] = 'LOG_STACK não inclui database_errors';
        }
        if (! $tableExists) {
            $issues[] = 'Tabela application_error_logs ausente — rode migrate ou SQL';
        }
        if ((string) config('appcheckin.error_alert_email', '') === '') {
            $issues[] = 'ERROR_ALERT_EMAIL não configurado';
        }
        if ((string) config('appcheckin.ops_view_token', '') === '') {
            $issues[] = 'OPS_VIEW_TOKEN não configurado (painel /ops/errors)';
        }

        $subjectPrefixes = config('appcheckin.mail_allowed_subject_prefixes', []);
        $hints = [];
        if (! is_array($subjectPrefixes) || ! in_array('AppCheckin [ERRO]', $subjectPrefixes, true)) {
            $hints[] = 'Config em cache antiga: rode php artisan config:clear (fallback hardcoded no mail guard)';
        }

        return [
            'ok' => $issues === [],
            'config' => [
                'log_stack' => $logStack,
                'error_alert_email' => config('appcheckin.error_alert_email'),
                'error_alert_throttle_minutes' => config('appcheckin.error_alert_throttle_minutes'),
                'ops_view_token_set' => (string) config('appcheckin.ops_view_token', '') !== '',
                'mail_guard_enabled' => config('appcheckin.mail_guard_enabled', true),
                'mail_allowed_subject_prefixes' => $subjectPrefixes,
            ],
            'table' => [
                'exists' => $tableExists,
                'total_rows' => $tableExists ? $repo->totalCount() : 0,
                'recent' => $recent,
            ],
            'issues' => $issues,
            'hints' => $hints,
            'panel_url' => rtrim((string) config('app.url', ''), '/').'/ops/errors',
        ];
    }
}
