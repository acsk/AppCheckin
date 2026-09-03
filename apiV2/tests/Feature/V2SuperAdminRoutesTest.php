<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;

class V2SuperAdminRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_academias_requires_jwt(): void
    {
        $this->getJson('/v2/superadmin/academias')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_academias_rejects_non_superadmin(): void
    {
        $token = $this->tokenParaPapel(3);

        $this->getJson('/v2/superadmin/academias', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertForbidden()
            ->assertJsonPath('erro', 'Acesso negado. Apenas Super Admin pode acessar este recurso.');
    }

    public function test_academias_index_ok(): void
    {
        $token = $this->tokenParaPapel(4);

        $service = Mockery::mock(\App\Services\SuperAdmin\SuperAdminAcademiaService::class);
        $service->shouldReceive('listarAcademias')
            ->once()
            ->with([])
            ->andReturn([
                'status' => 200,
                'body' => ['academias' => [['id' => 2, 'nome' => 'Academia Teste']]],
            ]);
        $this->app->instance(\App\Services\SuperAdmin\SuperAdminAcademiaService::class, $service);

        $this->getJson('/v2/superadmin/academias', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('academias.0.nome', 'Academia Teste');
    }

    public function test_papeis_ok(): void
    {
        $token = $this->tokenParaPapel(4);

        $misc = Mockery::mock(\App\Services\SuperAdmin\SuperAdminMiscService::class);
        $misc->shouldReceive('listarPapeis')
            ->once()
            ->andReturn([
                'status' => 200,
                'body' => ['papeis' => [['id' => 3, 'nome' => 'Admin']]],
            ]);
        $this->app->instance(\App\Services\SuperAdmin\SuperAdminMiscService::class, $misc);

        $this->getJson('/v2/superadmin/papeis', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('papeis.0.nome', 'Admin');
    }

    public function test_planos_sistema_index_ok(): void
    {
        $token = $this->tokenParaPapel(4);

        $service = Mockery::mock(\App\Services\SuperAdmin\SuperAdminPlanoSistemaService::class);
        $service->shouldReceive('index')
            ->once()
            ->with([])
            ->andReturn([
                'status' => 200,
                'body' => ['planos' => [['id' => 1, 'nome' => 'Starter']], 'total' => 1],
            ]);
        $this->app->instance(\App\Services\SuperAdmin\SuperAdminPlanoSistemaService::class, $service);

        $this->getJson('/v2/superadmin/planos-sistema', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('planos.0.nome', 'Starter');
    }

    public function test_contratos_index_ok(): void
    {
        $token = $this->tokenParaPapel(4);

        $service = Mockery::mock(\App\Services\SuperAdmin\SuperAdminContratoService::class);
        $service->shouldReceive('index')
            ->once()
            ->andReturn([
                'status' => 200,
                'body' => ['contratos' => [['id' => 5]], 'total' => 1],
            ]);
        $this->app->instance(\App\Services\SuperAdmin\SuperAdminContratoService::class, $service);

        $this->getJson('/v2/superadmin/contratos', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_pagamentos_contrato_index_ok(): void
    {
        $token = $this->tokenParaPapel(4);

        $service = Mockery::mock(\App\Services\SuperAdmin\SuperAdminPagamentoContratoService::class);
        $service->shouldReceive('index')
            ->once()
            ->with([])
            ->andReturn([
                'status' => 200,
                'body' => ['pagamentos' => [['id' => 9]]],
            ]);
        $this->app->instance(\App\Services\SuperAdmin\SuperAdminPagamentoContratoService::class, $service);

        $this->getJson('/v2/superadmin/pagamentos-contrato', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('pagamentos.0.id', 9);
    }

    public function test_usuarios_index_ok(): void
    {
        $token = $this->tokenParaPapel(4);

        $service = Mockery::mock(\App\Services\SuperAdmin\SuperAdminUsuarioService::class);
        $service->shouldReceive('index')
            ->once()
            ->with([])
            ->andReturn([
                'status' => 200,
                'body' => ['total' => 1, 'usuarios' => [['id' => 10, 'nome' => 'João']]],
            ]);
        $this->app->instance(\App\Services\SuperAdmin\SuperAdminUsuarioService::class, $service);

        $this->getJson('/v2/superadmin/usuarios', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('usuarios.0.nome', 'João');
    }

    public function test_assinaturas_global_ok(): void
    {
        $token = $this->tokenParaPapel(4);

        $service = Mockery::mock(\App\Services\SuperAdmin\SuperAdminAssinaturaService::class);
        $service->shouldReceive('listar')
            ->once()
            ->with([])
            ->andReturn([
                'status' => 200,
                'body' => [
                    'success' => true,
                    'total' => 1,
                    'page' => 1,
                    'per_page' => 20,
                    'total_pages' => 1,
                    'assinaturas' => [['id' => 7, 'tenant_nome' => 'Academia A']],
                ],
            ]);
        $this->app->instance(\App\Services\SuperAdmin\SuperAdminAssinaturaService::class, $service);

        $this->getJson('/v2/superadmin/assinaturas', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('assinaturas.0.tenant_nome', 'Academia A');
    }

    public function test_assinaturas_com_filtro_tenant(): void
    {
        $token = $this->tokenParaPapel(4);

        $service = Mockery::mock(\App\Services\SuperAdmin\SuperAdminAssinaturaService::class);
        $service->shouldReceive('listar')
            ->once()
            ->with(['tenant_id' => '3'])
            ->andReturn([
                'status' => 200,
                'body' => ['success' => true, 'total' => 0, 'assinaturas' => []],
            ]);
        $this->app->instance(\App\Services\SuperAdmin\SuperAdminAssinaturaService::class, $service);

        $this->getJson('/v2/superadmin/assinaturas?tenant_id=3', [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();
    }

    public function test_env_ok(): void
    {
        $token = $this->tokenParaPapel(4);

        $misc = Mockery::mock(\App\Services\SuperAdmin\SuperAdminMiscService::class);
        $misc->shouldReceive('getEnvironmentVariables')
            ->once()
            ->andReturn([
                'status' => 200,
                'body' => ['warning' => 'Dados de ambiente do servidor - Proteja este acesso'],
            ]);
        $this->app->instance(\App\Services\SuperAdmin\SuperAdminMiscService::class, $misc);

        $this->getJson('/v2/superadmin/env', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('warning', 'Dados de ambiente do servidor - Proteja este acesso');
    }

    private function tokenParaPapel(int $papelId): string
    {
        config(['appcheckin.jwt_secret' => 'test-secret-key-with-enough-length-for-hs256-algorithm']);

        $usuarios = Mockery::mock(\App\Repositories\UsuarioRepository::class);
        $usuarios->shouldReceive('findAuthContext')
            ->with(5)
            ->andReturn([
                'id' => 5,
                'nome' => 'Super',
                'email' => 'super@example.com',
                'tenant_id' => 1,
                'papel_id' => $papelId,
            ]);
        $this->app->instance(\App\Repositories\UsuarioRepository::class, $usuarios);

        return app(\App\Services\JwtService::class)->encode([
            'user_id' => 5,
            'email' => 'super@example.com',
            'tenant_id' => 1,
            'is_super_admin' => $papelId === 4,
        ]);
    }
}
