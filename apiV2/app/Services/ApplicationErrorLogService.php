<?php

namespace App\Services;

use App\Listeners\EnforceAllowedOutboundMail;
use App\Repositories\ApplicationErrorLogRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

final class ApplicationErrorLogService
{
    public function __construct(
        private readonly ApplicationErrorLogRepository $repository,
    ) {}

    public function recordFromLogRecord(LogRecord $record): void
    {
        if (! $this->shouldPersistLevel($record->level)) {
            return;
        }

        $message = $record->message;
        $context = $record->context;
        $exception = $context['exception'] ?? null;
        if ($exception instanceof Throwable) {
            unset($context['exception']);
        }

        $this->record(
            message: $message,
            level: strtolower($record->level->getName()),
            context: $context,
            exception: $exception instanceof Throwable ? $exception : null,
        );
    }

    public function recordException(Throwable $exception): void
    {
        $this->record(
            message: $exception->getMessage(),
            level: 'error',
            context: [],
            exception: $exception,
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function record(
        string $message,
        string $level = 'error',
        array $context = [],
        ?Throwable $exception = null,
    ): ?int {
        if (! in_array($level, ['error', 'critical', 'alert', 'emergency'], true)) {
            return null;
        }

        if (! $this->repository->tableExists()) {
            return null;
        }

        $request = request();
        $description = $this->normalizeDescription($message, $exception);
        $fingerprint = $this->buildFingerprint($description, $exception);
        $payload = [
            'fingerprint' => $fingerprint,
            'description' => mb_substr($description, 0, 500),
            'message' => $this->buildFullMessage($message, $exception),
            'level' => $level,
            'exception_class' => $exception ? $exception::class : ($context['exception_class'] ?? null),
            'source_file' => $exception?->getFile() ?? ($context['file'] ?? null),
            'source_line' => $exception?->getLine() ?? ($context['line'] ?? null),
            'request_method' => $request?->method(),
            'request_path' => $request?->path() ? '/'.$request->path() : null,
            'ip' => $request?->ip(),
            'user_id' => $request?->attributes->get('userId'),
            'tenant_id' => $request?->attributes->get('tenantId'),
            'context' => $this->sanitizeContext($context),
        ];

        try {
            $id = $this->repository->insert($payload);
        } catch (\Throwable $e) {
            Log::warning('Falha ao gravar application_error_logs', ['error' => $e->getMessage()]);

            return null;
        }

        if ($id !== null) {
            $this->maybeSendAlertEmail($payload, (int) $id, force: false);
        }

        return $id;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function groupedForView(int $limit = 100): array
    {
        return $this->repository->listGrouped($limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function detailsForFingerprint(string $fingerprint, int $limit = 50): array
    {
        return $this->repository->listByFingerprint($fingerprint, $limit);
    }

    private function shouldPersistLevel(Level $level): bool
    {
        return $level->value >= Level::Error->value;
    }

    private function normalizeDescription(string $message, ?Throwable $exception): string
    {
        $text = trim($message);
        if ($text === '' && $exception) {
            $text = $exception->getMessage();
        }

        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        $text = preg_replace('/\d+/', '#', $text) ?? $text;

        return $text !== '' ? $text : 'Erro sem mensagem';
    }

    private function buildFingerprint(string $description, ?Throwable $exception): string
    {
        $parts = [$description];
        if ($exception) {
            $parts[] = $exception::class;
            $parts[] = basename($exception->getFile()).':'.$exception->getLine();
        }

        return hash('sha256', implode('|', $parts));
    }

    private function buildFullMessage(string $message, ?Throwable $exception): string
    {
        if (! $exception) {
            return $message;
        }

        $trace = mb_substr($exception->getTraceAsString(), 0, 8000);

        return trim($message."\n\n".$exception::class.': '.$exception->getMessage()."\n\n".$trace);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function sanitizeContext(array $context): array
    {
        $clean = [];
        foreach ($context as $key => $value) {
            if (is_string($key) && in_array($key, ['password', 'senha', 'token', 'authorization'], true)) {
                $clean[$key] = '[redacted]';
                continue;
            }
            if ($value instanceof Throwable) {
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $clean[$key] = $value;
            } elseif (is_array($value)) {
                $clean[$key] = $this->sanitizeContext($value);
            } else {
                $clean[$key] = (string) $value;
            }
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function sendAlertEmailForTest(array $payload, int $logId, bool $force = true): bool
    {
        return $this->maybeSendAlertEmail($payload, $logId, $force);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function maybeSendAlertEmail(array $payload, int $logId, bool $force): bool
    {
        $to = (string) config('appcheckin.error_alert_email', '');
        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Log::warning('Alerta de erro não enviado: ERROR_ALERT_EMAIL inválido ou vazio');

            return false;
        }

        $fingerprint = (string) $payload['fingerprint'];
        $throttleMinutes = max(1, (int) config('appcheckin.error_alert_throttle_minutes', 15));
        $cacheKey = 'error_alert_sent:'.$fingerprint;

        if (! $force && Cache::has($cacheKey)) {
            Log::warning('Alerta de erro suprimido por throttle', [
                'log_id' => $logId,
                'fingerprint' => $fingerprint,
                'throttle_minutes' => $throttleMinutes,
            ]);

            return false;
        }

        $recentCount = $this->repository->countSince($fingerprint, $throttleMinutes);

        try {
            $mail = $this->buildAlertMail($payload, [
                'log_id' => $logId,
                'created_at' => Carbon::now('UTC')->format('Y-m-d H:i:s'),
                'app_env' => (string) config('app.env', 'production'),
                'recent_count' => $recentCount,
                'throttle_minutes' => $throttleMinutes,
                'panel_url' => $this->opsViewUrl(),
            ]);

            $sent = TransactionalMailSender::sendOperationalAlert(
                $to,
                'Admin AppCheckin',
                $mail['subject'],
                $mail['html'],
                $mail['text'],
            );

            if (! $sent) {
                Log::warning('Alerta de erro não enviado (mail guard ou transporte cancelou)', [
                    'log_id' => $logId,
                    'subject' => $mail['subject'],
                    'to' => $to,
                ]);

                return false;
            }

            Cache::put($cacheKey, true, now()->addMinutes($throttleMinutes));
            Log::info('Alerta de erro enviado por email', [
                'log_id' => $logId,
                'subject' => $mail['subject'],
                'to' => $to,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Falha ao enviar alerta de erro por email', [
                'log_id' => $logId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function opsViewUrl(): string
    {
        $base = rtrim((string) config('app.url', ''), '/');
        $token = (string) config('appcheckin.ops_view_token', '');
        $url = $base.'/ops/errors';

        return $token !== '' ? $url.'?token='.urlencode($token) : $url;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $meta
     * @return array{subject: string, html: string, text: string}
     */
    private function buildAlertMail(array $payload, array $meta): array
    {
        if (class_exists(ApplicationErrorAlertMailBuilder::class)) {
            return app(ApplicationErrorAlertMailBuilder::class)->build($payload, $meta);
        }

        $description = (string) ($payload['description'] ?? 'Erro');
        $message = (string) ($payload['message'] ?? $description);

        return [
            'subject' => EnforceAllowedOutboundMail::ERROR_ALERT_SUBJECT_PREFIX.' '.mb_substr($description, 0, 80),
            'html' => '<pre>'.htmlspecialchars($message, ENT_QUOTES, 'UTF-8').'</pre>',
            'text' => $message,
        ];
    }
}
