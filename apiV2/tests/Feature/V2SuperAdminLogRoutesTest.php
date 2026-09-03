<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;

class V2SuperAdminLogRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_logs_requires_jwt(): void
    {
        $this->getJson('/v2/superadmin/logs')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_logs_rejects_admin(): void
    {
        config(['appcheckin.jwt_secret' => 'test-secret-key-with-enough-length-for-hs256-algorithm']);

        $usuarios = Mockery::mock(\App\Repositories\UsuarioRepository::class);
        $usuarios->shouldReceive('findAuthContext')
            ->once()
            ->with(5)
            ->andReturn([
                'id' => 5,
                'nome' => 'Admin',
                'email' => 'admin@example.com',
                'tenant_id' => 3,
                'papel_id' => 3,
            ]);
        $this->app->instance(\App\Repositories\UsuarioRepository::class, $usuarios);

        $token = app(\App\Services\JwtService::class)->encode([
            'user_id' => 5,
            'email' => 'admin@example.com',
            'tenant_id' => 3,
        ]);

        $this->getJson('/v2/superadmin/logs', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertForbidden()
            ->assertJsonPath('erro', 'Acesso negado. Apenas Super Admin pode acessar este recurso.');
    }

    public function test_logs_ok_for_super_admin(): void
    {
        config(['appcheckin.jwt_secret' => 'test-secret-key-with-enough-length-for-hs256-algorithm']);

        $usuarios = Mockery::mock(\App\Repositories\UsuarioRepository::class);
        $usuarios->shouldReceive('findAuthContext')
            ->once()
            ->with(1)
            ->andReturn([
                'id' => 1,
                'nome' => 'SA',
                'email' => 'sa@example.com',
                'tenant_id' => 1,
                'papel_id' => 4,
            ]);
        $this->app->instance(\App\Repositories\UsuarioRepository::class, $usuarios);

        $reader = Mockery::mock(\App\Support\LaravelLogReader::class);
        $reader->shouldReceive('listarArquivos')->once()->andReturn([
            ['nome' => 'laravel.log', 'tamanho_bytes' => 10, 'modificado_em' => '2026-09-02T10:00:00+00:00'],
        ]);
        $reader->shouldReceive('lerFinal')
            ->once()
            ->with('laravel.log', 200, null, null)
            ->andReturn([
                'arquivo' => 'laravel.log',
                'tamanho_bytes' => 10,
                'modificado_em' => '2026-09-02T10:00:00+00:00',
                'total_linhas_retornadas' => 1,
                'linhas' => [['numero' => null, 'texto' => 'test', 'nivel' => 'info', 'timestamp' => null]],
                'truncado' => false,
            ]);
        $this->app->instance(\App\Support\LaravelLogReader::class, $reader);

        $token = app(\App\Services\JwtService::class)->encode([
            'user_id' => 1,
            'email' => 'sa@example.com',
            'tenant_id' => 1,
            'is_super_admin' => true,
        ]);

        $this->getJson('/v2/superadmin/logs', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('leitura.arquivo', 'laravel.log')
            ->assertJsonPath('leitura.linhas.0.texto', 'test');
    }
}
