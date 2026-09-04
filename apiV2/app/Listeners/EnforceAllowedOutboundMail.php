<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;

/**
 * Bloqueia envios fora dos templates oficiais do AppCheckin (defesa em profundidade).
 */
class EnforceAllowedOutboundMail
{
    public function handle(MessageSending $event): bool
    {
        if (! config('appcheckin.mail_guard_enabled', true)) {
            return true;
        }

        $message = $event->message;
        $subject = (string) $message->getSubject();

        /** @var list<string> $allowedSubjects */
        $allowedSubjects = config('appcheckin.mail_allowed_subjects', []);
        if ($allowedSubjects !== [] && ! in_array($subject, $allowedSubjects, true)) {
            Log::warning('Mail guard bloqueou assunto não autorizado', [
                'subject' => $subject,
                'to' => array_map(static fn ($a) => $a->getAddress(), $message->getTo()),
            ]);

            return false;
        }

        $allowedFrom = strtolower(trim((string) config('appcheckin.mail_from_address', '')));
        if ($allowedFrom !== '') {
            $fromList = $message->getFrom();
            foreach ($fromList as $address) {
                if (strtolower($address->getAddress()) !== $allowedFrom) {
                    Log::warning('Mail guard bloqueou remetente não autorizado', [
                        'from' => $address->getAddress(),
                        'subject' => $subject,
                    ]);

                    return false;
                }
            }
        }

        return true;
    }
}
