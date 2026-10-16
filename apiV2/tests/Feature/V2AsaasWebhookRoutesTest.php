<?php

namespace Tests\Feature;

use App\Services\Asaas\AsaasWebhookService;
use Mockery;
use Tests\TestCase;

class V2AsaasWebhookRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_webhook_asaas_is_public(): void
    {
        $service = Mockery::mock(AsaasWebhookService::class);
        $service->shouldReceive('processar')
            ->once()
            ->andReturn([
                'status' => 200,
                'body' => ['success' => true, 'ignored' => 'PAYMENT_CREATED'],
            ]);
        $this->app->instance(AsaasWebhookService::class, $service);

        $this->postJson('/v2/webhooks/asaas', [
            'event' => 'PAYMENT_CREATED',
            'payment' => ['id' => 'pay_test'],
        ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
