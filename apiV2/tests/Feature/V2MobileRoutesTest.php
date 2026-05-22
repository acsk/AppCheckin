<?php

namespace Tests\Feature;

use Tests\TestCase;

class V2MobileRoutesTest extends TestCase
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

    public function test_horarios_disponiveis_requires_jwt(): void
    {
        $this->getJson('/v2/mobile/horarios-disponiveis')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_horarios_disponiveis_validates_date_format(): void
    {
        $this->getJson('/v2/mobile/horarios-disponiveis?data=invalid', [
            'Authorization' => 'Bearer '.$this->bearerToken(),
        ])
            ->assertBadRequest()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'Formato de data inválido. Use YYYY-MM-DD');
    }

    public function test_registrar_checkin_returns_mobile_error_shape(): void
    {
        $response = $this->postJson('/v2/mobile/checkin', [], [
            'Authorization' => 'Bearer '.$this->bearerToken(),
        ]);

        $response->assertJsonPath('success', false);
        $this->assertContains($response->status(), [400, 403, 404, 500]);
    }

    public function test_desfazer_checkin_requires_valid_id(): void
    {
        $this->deleteJson('/v2/mobile/checkin/0/desfazer', [], [
            'Authorization' => 'Bearer '.$this->bearerToken(),
        ])
            ->assertStatus(400)
            ->assertJsonPath('error', 'checkinId é obrigatório');
    }
}
