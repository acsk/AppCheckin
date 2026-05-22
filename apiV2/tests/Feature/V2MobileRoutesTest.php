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

    public function test_perfil_requires_jwt(): void
    {
        $this->getJson('/v2/mobile/perfil')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_acesso_returns_payload_with_tenant_in_jwt(): void
    {
        $this->getJson('/v2/mobile/acesso', [
            'Authorization' => 'Bearer '.$this->bearerToken(),
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('acesso.permitido', true)
            ->assertJsonPath('acesso.bloqueado', false);
    }

    public function test_tenants_requires_jwt(): void
    {
        $this->getJson('/v2/mobile/tenants')
            ->assertUnauthorized();
    }

    public function test_checkins_requires_jwt(): void
    {
        $this->getJson('/v2/mobile/checkins')
            ->assertUnauthorized();
    }

    public function test_wod_hoje_validates_date_format(): void
    {
        $this->getJson('/v2/mobile/wod/hoje?data=invalid', [
            'Authorization' => 'Bearer '.$this->bearerToken(),
        ])
            ->assertStatus(400)
            ->assertJsonPath('error', 'Formato de data inválido. Use YYYY-MM-DD');
    }

    public function test_wods_hoje_requires_jwt(): void
    {
        $this->getJson('/v2/mobile/wods/hoje')
            ->assertUnauthorized();
    }

    public function test_ranking_mensal_returns_success_shape(): void
    {
        $this->getJson('/v2/mobile/ranking/mensal', [
            'Authorization' => 'Bearer '.$this->bearerToken(),
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => ['periodo', 'mes', 'ano', 'ranking'],
            ]);
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

    public function test_planos_disponiveis_requires_jwt(): void
    {
        $this->getJson('/v2/mobile/planos-disponiveis')
            ->assertUnauthorized();
    }

    public function test_comprar_plano_requires_plano_id(): void
    {
        $this->postJson('/v2/mobile/comprar-plano', [], [
            'Authorization' => 'Bearer '.$this->bearerToken(),
        ])
            ->assertStatus(400)
            ->assertJsonPath('code', 'PLANO_OBRIGATORIO');
    }

    public function test_pagamento_pix_requires_matricula_id(): void
    {
        $this->postJson('/v2/mobile/pagamento/pix', [], [
            'Authorization' => 'Bearer '.$this->bearerToken(),
        ])
            ->assertStatus(400)
            ->assertJsonPath('error', 'matricula_id é obrigatório');
    }

    public function test_assinaturas_requires_jwt(): void
    {
        $this->getJson('/v2/mobile/assinaturas')
            ->assertUnauthorized();
    }
}
