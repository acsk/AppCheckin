<?php

namespace Tests\Unit;

use App\Services\EmailTemplateService;
use Tests\TestCase;

class EmailTemplateServiceTest extends TestCase
{
    public function test_password_recovery_includes_token_and_preheader(): void
    {
        $html = (new EmailTemplateService)->passwordRecovery('Maria', 'abc-123', 30);

        $this->assertStringContainsString('abc-123', $html);
        $this->assertStringContainsString('Maria', $html);
        $this->assertStringContainsString('30 minutos', $html);
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
    }

    public function test_password_recovery_plain_text_includes_token(): void
    {
        $text = (new EmailTemplateService)->passwordRecoveryPlainText('Maria', 'abc-123', 30);

        $this->assertStringContainsString('abc-123', $text);
        $this->assertStringContainsString('Maria', $text);
    }
}
