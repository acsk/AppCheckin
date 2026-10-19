<?php

namespace Tests\Feature;

use App\Repositories\UsuarioRepository;
use App\Services\JwtService;
use App\Services\SuperAdmin\SuperAdminContratoService;
use Mockery;
use Tests\TestCase;

class V2SuperAdminContratosRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_contratos_requires_jwt(): void
    {
        $this->getJson('/v2/superadmin/contratos')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_index_lista_contratos(): void
    {
        $token = $this->tokenParaPapel(4);

        $service = Mockery::mock(SuperAdminContratoService::class);
        $service->shouldReceive('index')
            ->once()
            ->andReturn([
                'status' => 200,
                'body' => ['contratos' => [['id' => 1]], 'total' => 1],
            ]);
        $this->app->instance(SuperAdminContratoService::class, $service);

        $this->getJson('/v2/superadmin/contratos', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_proximos_vencimento(): void
    {
        $token = $this->tokenParaPapel(4);

        $service = Mockery::mock(SuperAdminContratoService::class);
        $service->shouldReceive('proximosVencimento')
            ->once()
            ->with(['dias' => '7'])
            ->andReturn([
                'status' => 200,
                'body' => ['contratos' => [], 'total' => 0, 'dias' => 7],
            ]);
        $this->app->instance(SuperAdminContratoService::class, $service);

        $this->getJson('/v2/superadmin/contratos/proximos-vencimento?dias=7', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('dias', 7);
    }

    public function test_estimativa_cobranca(): void
    {
        $token = $this->tokenParaPapel(4);

        $service = Mockery::mock(SuperAdminContratoService::class);
        $service->shouldReceive('estimativaCobranca')
            ->once()
            ->with(7)
            ->andReturn([
                'status' => 200,
                'body' => ['contrato' => ['id' => 31], 'estimativa' => ['total_estimado' => 259.0, 'alunos_ativos' => 74]],
            ]);
        $this->app->instance(SuperAdminContratoService::class, $service);

        $this->getJson('/v2/superadmin/academias/7/estimativa-cobranca', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('estimativa.alunos_ativos', 74);
    }

    private function tokenParaPapel(int $papelId): string
    {
        config(['appcheckin.jwt_secret' => 'test-secret-key-with-enough-length-for-hs256-algorithm']);

        $usuarios = Mockery::mock(UsuarioRepository::class);
        $usuarios->shouldReceive('findAuthContext')
            ->with(5)
            ->andReturn([
                'id' => 5,
                'nome' => 'Super',
                'email' => 'super@example.com',
                'tenant_id' => 1,
                'papel_id' => $papelId,
            ]);
        $this->app->instance(UsuarioRepository::class, $usuarios);

        return app(JwtService::class)->encode([
            'user_id' => 5,
            'email' => 'super@example.com',
            'tenant_id' => 1,
            'is_super_admin' => $papelId === 4,
        ]);
    }
}
