<?php

namespace Tests\Unit;

use App\Support\MailDiagnostics;
use Tests\TestCase;

class MailDiagnosticsTest extends TestCase
{
    public function test_mask_secret_hides_middle(): void
    {
        $this->assertSame('re_1…abcd', MailDiagnostics::maskSecret('re_123456abcd'));
    }

    public function test_config_snapshot_flags_log_mailer_in_production(): void
    {
        config([
            'app.env' => 'production',
            'mail.default' => 'log',
            'mail.from.address' => 'mail@appcheckin.com.br',
            'services.resend.key' => 're_test_key_1234',
        ]);

        $snapshot = MailDiagnostics::configSnapshot();

        $this->assertFalse($snapshot['ok']);
        $this->assertNotEmpty($snapshot['issues']);
    }
}
