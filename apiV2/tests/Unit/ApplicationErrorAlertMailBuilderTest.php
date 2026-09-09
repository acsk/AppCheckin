<?php

namespace Tests\Unit;

use App\Services\ApplicationErrorAlertMailBuilder;
use Tests\TestCase;

class ApplicationErrorAlertMailBuilderTest extends TestCase
{
    public function test_builds_subject_without_emoji_and_includes_details(): void
    {
        config(['app.timezone' => 'UTC']);

        $builder = new ApplicationErrorAlertMailBuilder;
        $mail = $builder->build(
            [
                'description' => 'Teste de alerta ops',
                'level' => 'error',
                'message' => "Stack trace line 1\nStack trace line 2",
                'request_method' => 'GET',
                'request_path' => '/v2/test',
                'ip' => '127.0.0.1',
                'user_id' => 10,
                'tenant_id' => 5,
                'exception_class' => 'RuntimeException',
                'source_file' => '/app/Services/Foo.php',
                'source_line' => 99,
                'context' => ['foo' => 'bar'],
            ],
            [
                'log_id' => 1,
                'created_at' => '2026-09-09 11:39:31',
                'app_env' => 'production',
                'recent_count' => 2,
                'throttle_minutes' => 15,
                'panel_url' => 'https://example.test/ops/errors',
            ],
        );

        $this->assertStringStartsWith('AppCheckin [ERRO]', $mail['subject']);
        $this->assertStringContainsString('Teste de alerta ops', $mail['subject']);
        $this->assertStringContainsString('Stack trace line 1', $mail['html']);
        $this->assertStringContainsString('"foo": "bar"', $mail['html']);
        $this->assertStringContainsString('09/09/2026 08:39:31 (BRT)', $mail['html']);
        $this->assertStringContainsString('09/09/2026 08:39:31 (BRT)', $mail['text']);
    }
}
