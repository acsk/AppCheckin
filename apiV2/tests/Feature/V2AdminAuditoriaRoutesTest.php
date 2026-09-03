<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;

class V2AdminAuditoriaRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_auditoria_requires_jwt(): void
    {
        $this->getJson('/v2/admin/auditoria/pagamentos-duplicados')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_auditoria_rejects_aluno(): void
    {
        config(['appcheckin.jwt_secret' => 'test-secret-key-with-enough-length-for-hs256-algorithm']);

        $usuarios = Mockery::mock(\App\Repositories\UsuarioRepository::class);
        $usuarios->shouldReceive('findAuthContext')
            ->once()
            ->with(10)
            ->andReturn([
                'id' => 10,
                'nome' => 'Aluno',
                'email' => 'aluno@example.com',
                'tenant_id' => 3,
                'papel_id' => 1,
            ]);
        $this->app->instance(\App\Repositories\UsuarioRepository::class, $usuarios);

        $token = app(\App\Services\JwtService::class)->encode([
            'user_id' => 10,
            'email' => 'aluno@example.com',
            'tenant_id' => 3,
        ]);

        $this->getJson('/v2/admin/auditoria/pagamentos-duplicados', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertForbidden()
            ->assertJsonPath('erro', 'Acesso negado. Apenas administradores podem acessar este recurso.');
    }

    public function test_auditoria_pagamentos_duplicados_ok_for_admin(): void
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

        $service = Mockery::mock(\App\Services\Admin\AdminAuditoriaService::class);
        $service->shouldReceive('pagamentosDuplicados')
            ->once()
            ->with(3)
            ->andReturn([
                'status' => 200,
                'body' => [
                    'resumo' => [
                        'total_grupos_duplicados' => 0,
                        'total_pagamentos_envolvidos' => 0,
                    ],
                    'grupos' => [],
                ],
            ]);
        $this->app->instance(\App\Services\Admin\AdminAuditoriaService::class, $service);

        $token = app(\App\Services\JwtService::class)->encode([
            'user_id' => 5,
            'email' => 'admin@example.com',
            'tenant_id' => 3,
        ]);

        $this->getJson('/v2/admin/auditoria/pagamentos-duplicados', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('resumo.total_grupos_duplicados', 0)
            ->assertJsonPath('grupos', []);
    }

    public function test_auditoria_credito_migracao_ok_for_admin(): void
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

        $service = Mockery::mock(\App\Services\Admin\AdminAuditoriaService::class);
        $service->shouldReceive('creditoMigracaoPlano')
            ->once()
            ->with(3)
            ->andReturn([
                'status' => 200,
                'body' => [
                    'resumo' => ['total_matriculas' => 0, 'revisao_manual' => 0, 'informativo' => 0],
                    'registros' => [],
                ],
            ]);
        $this->app->instance(\App\Services\Admin\AdminAuditoriaService::class, $service);

        $token = app(\App\Services\JwtService::class)->encode([
            'user_id' => 5,
            'email' => 'admin@example.com',
            'tenant_id' => 3,
        ]);

        $this->getJson('/v2/admin/auditoria/credito-migracao-plano', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('resumo.total_matriculas', 0);
    }

    public function test_auditoria_reparar_vencimento_requires_jwt(): void
    {
        $this->postJson('/v2/admin/auditoria/reparar-vencimento-matricula/1')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }
}
