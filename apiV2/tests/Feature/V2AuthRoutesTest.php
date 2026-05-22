<?php

namespace Tests\Feature;

use Tests\TestCase;

class V2AuthRoutesTest extends TestCase
{
    public function test_ping_returns_pong(): void
    {
        $this->getJson('/v2/ping')
            ->assertOk()
            ->assertJsonPath('message', 'pong')
            ->assertJsonPath('api_version', 'v2');
    }

    public function test_select_tenant_requires_jwt(): void
    {
        $this->postJson('/v2/auth/select-tenant', ['tenant_id' => 1])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_select_tenant_validates_tenant_id_with_jwt(): void
    {
        config(['appcheckin.jwt_secret' => 'test-secret-key-with-enough-length-for-hs256-algorithm']);

        $jwt = app(\App\Services\JwtService::class);
        $token = $jwt->encode([
            'user_id' => 1,
            'email' => 'test@example.com',
            'tenant_id' => 1,
        ]);

        $this->postJson('/v2/auth/select-tenant', [], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'MISSING_TENANT_ID');
    }

    public function test_select_tenant_public_validates_required_fields(): void
    {
        $this->postJson('/v2/auth/select-tenant-public', [])
            ->assertStatus(422)
            ->assertJsonPath('code', 'MISSING_REQUIRED_FIELDS');
    }

    public function test_register_validates_required_fields(): void
    {
        $this->postJson('/v2/auth/register', [])
            ->assertStatus(422)
            ->assertJsonPath('code', 'MISSING_FIELDS');
    }

    public function test_password_recovery_request_validates_email(): void
    {
        $this->postJson('/v2/auth/password-recovery/request', [])
            ->assertStatus(422)
            ->assertJsonPath('code', 'MISSING_EMAIL');
    }

    public function test_password_recovery_validate_token_requires_token(): void
    {
        $this->postJson('/v2/auth/password-recovery/validate-token', [])
            ->assertStatus(422)
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }
}
