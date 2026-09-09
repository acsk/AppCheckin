<?php

namespace App\Services;

use App\Repositories\ApplicationErrorLogRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

final class ApplicationErrorLogService
{
    private const ALERT_SUBJECT = '🚨 Alerta de Erro — AppCheckin API v2';

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
            $this->maybeSendAlertEmail($payload, (int) $id);
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
    private function maybeSendAlertEmail(array $payload, int $logId): void
    {
        $to = (string) config('appcheckin.error_alert_email', '');
        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $fingerprint = (string) $payload['fingerprint'];
        $throttleMinutes = max(1, (int) config('appcheckin.error_alert_throttle_minutes', 15));
        $cacheKey = 'error_alert_sent:'.$fingerprint;

        if (Cache::has($cacheKey)) {
            return;
        }

        $recentCount = $this->repository->countSince($fingerprint, $throttleMinutes);

        try {
            $html = $this->buildAlertHtml($payload, $logId, $recentCount, $throttleMinutes);
            $text = $this->buildAlertText($payload, $logId, $recentCount, $throttleMinutes);

            TransactionalMailSender::send(
                $to,
                'Admin AppCheckin',
                self::ALERT_SUBJECT,
                $html,
                $text,
            );

            Cache::put($cacheKey, true, now()->addMinutes($throttleMinutes));
        } catch (\Throwable $e) {
            Log::warning('Falha ao enviar alerta de erro por email', [
                'log_id' => $logId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function buildAlertHtml(array $payload, int $logId, int $recentCount, int $throttleMinutes): string
    {
        $desc = htmlspecialchars((string) $payload['description'], ENT_QUOTES, 'UTF-8');
        $level = htmlspecialchars(strtoupper((string) $payload['level']), ENT_QUOTES, 'UTF-8');
        $path = htmlspecialchars((string) ($payload['request_path'] ?? '-'), ENT_QUOTES, 'UTF-8');
        $method = htmlspecialchars((string) ($payload['request_method'] ?? '-'), ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars(mb_substr((string) $payload['message'], 0, 2000), ENT_QUOTES, 'UTF-8');
        $opsUrl = htmlspecialchars($this->opsViewUrl(), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR"><body style="font-family: Arial, sans-serif; color: #333;">
  <h2 style="color: #c0392b;">Alerta de erro — apiV2</h2>
  <p><strong>ID:</strong> {$logId} &nbsp; <strong>Nível:</strong> {$level}</p>
  <p><strong>Descrição:</strong> {$desc}</p>
  <p><strong>Request:</strong> {$method} {$path}</p>
  <p><strong>Ocorrências recentes ({$throttleMinutes} min):</strong> {$recentCount}</p>
  <pre style="background:#f4f4f4;padding:12px;border-radius:6px;white-space:pre-wrap;font-size:12px;">{$message}</pre>
  <p><a href="{$opsUrl}">Ver painel de erros agrupados</a></p>
</body></html>
HTML;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function buildAlertText(array $payload, int $logId, int $recentCount, int $throttleMinutes): string
    {
        return implode("\n", [
            'Alerta de erro — apiV2',
            "ID: {$logId}",
            'Nível: '.strtoupper((string) $payload['level']),
            'Descrição: '.$payload['description'],
            'Request: '.($payload['request_method'] ?? '-').' '.($payload['request_path'] ?? '-'),
            "Ocorrências recentes ({$throttleMinutes} min): {$recentCount}",
            '',
            mb_substr((string) $payload['message'], 0, 2000),
            '',
            'Painel: '.$this->opsViewUrl(),
        ]);
    }

    private function opsViewUrl(): string
    {
        $base = rtrim((string) config('app.url', ''), '/');
        $token = (string) config('appcheckin.ops_view_token', '');
        $url = $base.'/ops/errors';

        return $token !== '' ? $url.'?token='.urlencode($token) : $url;
    }
}
