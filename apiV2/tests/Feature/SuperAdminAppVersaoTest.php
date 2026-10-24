<?php

namespace Tests\Feature;

use App\Repositories\UsuarioRepository;
use App\Services\AppVersionService;
use App\Services\JwtService;
use Mockery;
use Tests\TestCase;

class SuperAdminAppVersaoTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_admin_de_academia_nao_altera(): void
    {
        $this->putJson('/v2/superadmin/app-versao', ['obrigatoria' => false], $this->auth(3))->assertForbidden();
    }

    public function test_super_admin_desliga_atualizacao_obrigatoria(): void
    {
        $service = Mockery::mock(AppVersionService::class);
        $service->shouldReceive('definirObrigatoria')->once()->with('mobile', false)
            ->andReturn(['app' => 'mobile', 'version' => 'v1.3', 'obrigatoria' => false]);
        $this->app->instance(AppVersionService::class, $service);

        $this->putJson('/v2/superadmin/app-versao', ['obrigatoria' => false], $this->auth(4))
            ->assertOk()
            ->assertJsonPath('mobile.obrigatoria', false);
    }

    /** @return array<string, string> */
    private function auth(int $papelId): array
    {
        config(['appcheckin.jwt_secret' => 'test-secret-key-with-enough-length-for-hs256-algorithm']);
        $usuarios = Mockery::mock(UsuarioRepository::class);
        $usuarios->shouldReceive('findAuthContext')->with(5)
            ->andReturn(['id' => 5, 'nome' => 'S', 'email' => 's@example.com', 'tenant_id' => 1, 'papel_id' => $papelId]);
        $this->app->instance(UsuarioRepository::class, $usuarios);

        $token = app(JwtService::class)->encode(['user_id' => 5, 'email' => 's@example.com', 'tenant_id' => 1, 'is_super_admin' => $papelId === 4]);

        return ['Authorization' => 'Bearer '.$token];
    }
}
