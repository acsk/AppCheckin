<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;

final class TransactionalMailSender
{
    public static function send(string $email, string $nome, string $subject, string $html, string $text): void
    {
        $fromAddress = (string) config('mail.from.address');
        $fromName = (string) config('mail.from.name');
        $replyTo = (string) config('appcheckin.mail_from_address', $fromAddress);

        Mail::send([], [], function ($message) use ($email, $nome, $subject, $html, $text, $fromAddress, $fromName, $replyTo): void {
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
        });
    }
}
