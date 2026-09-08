<?php

namespace Tests\Feature;

use Tests\TestCase;

class V2NotificationRoutesTest extends TestCase
{
    private function bearerToken(array $claims = []): string
    {
        config(['appcheckin.jwt_secret' => 'test-secret-key-with-enough-length-for-hs256-algorithm']);

        $jwt = app(\App\Services\JwtService::class);

        return $jwt->encode(array_merge([
            'user_id' => 1,
            'email' => 'test@example.com',
            'tenant_id' => 1,
        ], $claims));
    }

    public function test_notificacoes_unread_requires_jwt(): void
    {
        $this->getJson('/v2/notificacoes/unread')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_notificacoes_unread_without_tenant_in_jwt_may_resolve_from_user(): void
    {
        config(['appcheckin.jwt_secret' => 'test-secret-key-with-enough-length-for-hs256-algorithm']);
        $jwt = app(\App\Services\JwtService::class);
        $token = $jwt->encode([
            'user_id' => 1,
            'email' => 'test@example.com',
        ]);

        $response = $this->getJson('/v2/notificacoes/unread', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $this->assertContains($response->status(), [200, 400]);
        if ($response->status() === 200) {
            $response->assertJsonPath('success', true);
        } else {
            $response->assertJsonPath('success', false);
        }
    }

    public function test_notificacoes_unread_returns_success_shape(): void
    {
        $this->getJson('/v2/notificacoes/unread', [
            'Authorization' => 'Bearer '.$this->bearerToken(),
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data',
                'total',
            ]);
    }
}
