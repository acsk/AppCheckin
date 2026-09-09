<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;

final class TransactionalMailSender
{
    /**
     * @param  array<string, string>  $headers
     */
    public static function send(
        string $email,
        string $nome,
        string $subject,
        string $html,
        string $text,
        array $headers = [],
        ?string $fromNameOverride = null,
    ): bool {
        $fromAddress = (string) config('mail.from.address');
        $fromName = $fromNameOverride ?? (string) config('mail.from.name');
        $replyTo = (string) config('appcheckin.mail_from_address', $fromAddress);

        $result = Mail::send([], [], function ($message) use ($email, $nome, $subject, $html, $text, $fromAddress, $fromName, $replyTo, $headers): void {
            $message->to($email, $nome)
                ->subject($subject)
                ->html($html)
                ->text($text);

            if ($fromAddress !== '') {
                $message->from($fromAddress, $fromName);
            }

            if ($replyTo !== '') {
                $message->replyTo($replyTo, $fromName);
            }

            foreach ($headers as $name => $value) {
                $message->getHeaders()->addTextHeader($name, $value);
            }
        });

        return $result !== null;
    }

    public static function sendOperationalAlert(
        string $email,
        string $nome,
        string $subject,
        string $html,
        string $text,
    ): bool {
        return self::send(
            $email,
            $nome,
            $subject,
            $html,
            $text,
            headers: [
                'Importance' => 'high',
                'X-Priority' => '1',
                'X-AppCheckin-Mail-Type' => 'operational-alert',
            ],
            fromNameOverride: (string) config('appcheckin.error_alert_from_name', 'AppCheckin Ops'),
        );
    }
}
