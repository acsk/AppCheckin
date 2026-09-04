<?php

namespace Tests\Unit;

use App\Listeners\EnforceAllowedOutboundMail;
use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class EnforceAllowedOutboundMailTest extends TestCase
{
    public function test_blocks_unauthorized_subject(): void
    {
        config([
            'appcheckin.mail_guard_enabled' => true,
            'appcheckin.mail_allowed_subjects' => ['Assunto permitido'],
            'appcheckin.mail_from_address' => 'mail@appcheckin.com.br',
        ]);

        $message = (new Email())
            ->from('mail@appcheckin.com.br')
            ->to('user@example.com')
            ->subject('RingGo parking scam')
            ->html('phishing');

        $listener = new EnforceAllowedOutboundMail;
        $result = $listener->handle(new MessageSending($message, []));

        $this->assertFalse($result);
    }

    public function test_allows_official_password_recovery_subject(): void
    {
        config([
            'appcheckin.mail_guard_enabled' => true,
            'appcheckin.mail_allowed_subjects' => [
                '🔐 Código de Recuperação de Senha - App Check-in',
            ],
            'appcheckin.mail_from_address' => 'mail@appcheckin.com.br',
        ]);

        $message = (new Email())
            ->from('mail@appcheckin.com.br')
            ->to('user@example.com')
            ->subject('🔐 Código de Recuperação de Senha - App Check-in')
            ->html('<p>ok</p>');

        $listener = new EnforceAllowedOutboundMail;
        $result = $listener->handle(new MessageSending($message, []));

        $this->assertTrue($result);
    }
}
