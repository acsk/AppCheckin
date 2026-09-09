<?php

namespace App\Services;

use App\Support\DisplayDateTime;

final class ApplicationErrorAlertMailBuilder
{
    public const SUBJECT_PREFIX = 'AppCheckin [ERRO]';

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $meta
     * @return array{subject: string, html: string, text: string}
     */
    public function build(array $payload, array $meta): array
    {
        $description = (string) ($payload['description'] ?? 'Erro');
        $subject = self::SUBJECT_PREFIX.' '.mb_substr($description, 0, 80);

        return [
            'subject' => $subject,
            'html' => $this->buildHtml($payload, $meta),
            'text' => $this->buildText($payload, $meta),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $meta
     */
    private function buildHtml(array $payload, array $meta): string
    {
        $rows = $this->detailRows($payload, $meta);
        $detailHtml = '';
        foreach ($rows as $label => $value) {
            $detailHtml .= $this->row($label, $value);
        }

        $message = htmlspecialchars((string) ($payload['message'] ?? ''), ENT_QUOTES, 'UTF-8');
        $context = $this->formatContext($payload['context'] ?? []);
        $panelUrl = htmlspecialchars((string) ($meta['panel_url'] ?? ''), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#111827;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f6;padding:24px 12px;">
    <tr><td align="center">
      <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
        <tr>
          <td style="padding:20px 24px;border-bottom:1px solid #e5e7eb;background:#fafafa;">
            <div style="font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;">AppCheckin · API v2</div>
            <div style="font-size:20px;font-weight:600;margin-top:4px;color:#b91c1c;">Erro em produção</div>
          </td>
        </tr>
        <tr>
          <td style="padding:20px 24px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-size:14px;">
              {$detailHtml}
            </table>
            <div style="margin-top:18px;font-size:12px;font-weight:600;color:#374151;">Mensagem / stack</div>
            <pre style="margin:8px 0 0;padding:14px;background:#111827;color:#e5e7eb;border-radius:6px;font-size:12px;line-height:1.45;white-space:pre-wrap;word-break:break-word;">{$message}</pre>
            {$context}
            <p style="margin:18px 0 0;font-size:12px;color:#6b7280;">Painel completo: <a href="{$panelUrl}" style="color:#ea580c;">ver histórico agrupado</a></p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $meta
     */
    private function buildText(array $payload, array $meta): string
    {
        $lines = ['AppCheckin — Erro em produção (API v2)', ''];
        foreach ($this->detailRows($payload, $meta) as $label => $value) {
            $lines[] = "{$label}: {$value}";
        }
        $lines[] = '';
        $lines[] = 'Mensagem / stack:';
        $lines[] = (string) ($payload['message'] ?? '');
        $context = $payload['context'] ?? [];
        if (is_array($context) && $context !== []) {
            $lines[] = '';
            $lines[] = 'Contexto:';
            $lines[] = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        $lines[] = '';
        $lines[] = 'Painel: '.($meta['panel_url'] ?? '');

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $meta
     * @return array<string, string>
     */
    private function detailRows(array $payload, array $meta): array
    {
        return [
            'Horário (BRT)' => DisplayDateTime::label($meta['created_at'] ?? null),
            'ID do log' => (string) ($meta['log_id'] ?? '-'),
            'Nível' => strtoupper((string) ($payload['level'] ?? 'error')),
            'Ambiente' => (string) ($meta['app_env'] ?? 'production'),
            'Descrição' => (string) ($payload['description'] ?? '-'),
            'Request' => trim(((string) ($payload['request_method'] ?? '-')).' '.((string) ($payload['request_path'] ?? ''))),
            'IP' => (string) ($payload['ip'] ?? '-'),
            'Usuário' => isset($payload['user_id']) ? (string) $payload['user_id'] : '-',
            'Tenant' => isset($payload['tenant_id']) ? (string) $payload['tenant_id'] : '-',
            'Exceção' => (string) ($payload['exception_class'] ?? '-'),
            'Origem' => $this->sourceLabel($payload),
            'Ocorrências (janela)' => (string) ($meta['recent_count'] ?? 1).' em '.($meta['throttle_minutes'] ?? 15).' min',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sourceLabel(array $payload): string
    {
        $file = $payload['source_file'] ?? null;
        $line = $payload['source_line'] ?? null;
        if (! $file) {
            return '-';
        }

        return basename((string) $file).($line ? ':'.(string) $line : '');
    }

    private function row(string $label, string $value): string
    {
        $labelEsc = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        $valueEsc = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<tr>
  <td style="padding:8px 0;width:170px;vertical-align:top;color:#6b7280;">{$labelEsc}</td>
  <td style="padding:8px 0;vertical-align:top;color:#111827;word-break:break-word;">{$valueEsc}</td>
</tr>
HTML;
    }

    /**
     * @param  mixed  $context
     */
    private function formatContext($context): string
    {
        if (! is_array($context) || $context === []) {
            return '';
        }

        $json = htmlspecialchars(
            json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}',
            ENT_QUOTES,
            'UTF-8',
        );

        return <<<HTML
<div style="margin-top:18px;font-size:12px;font-weight:600;color:#374151;">Contexto</div>
<pre style="margin:8px 0 0;padding:14px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;font-size:12px;line-height:1.45;white-space:pre-wrap;">{$json}</pre>
HTML;
    }
}
