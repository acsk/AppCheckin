<?php

namespace Tests\Unit;

use App\Repositories\UsuarioRepository;
use App\Services\AuthService;
use App\Services\JwtService;
use App\Services\PasswordRecoveryMailer;
use Mockery;
use Tests\TestCase;

class AuthServiceSelectTenantTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_select_tenant_requires_tenant_id(): void
    {
        $service = $this->authService(
            Mockery::mock(JwtService::class),
            Mockery::mock(UsuarioRepository::class),
        );

        $response = $service->selectTenant(1, 0);
        $data = $response->getData(true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('MISSING_TENANT_ID', $data['code']);
    }

    public function test_select_tenant_denies_without_access(): void
    {
        $usuarios = Mockery::mock(UsuarioRepository::class);
        $usuarios->shouldReceive('temAcessoTenant')->with(1, 5)->andReturn(false);

        $service = $this->authService(Mockery::mock(JwtService::class), $usuarios);

        $response = $service->selectTenant(1, 5);
        $data = $response->getData(true);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('TENANT_ACCESS_DENIED', $data['code']);
    }

    public function test_select_tenant_public_rejects_unknown_user(): void
    {
        $usuarios = Mockery::mock(UsuarioRepository::class);
        $usuarios->shouldReceive('findById')->with(99999)->andReturn(null);

        $service = $this->authService(Mockery::mock(JwtService::class), $usuarios);

        $response = $service->selectTenantPublic(99999, 'a@b.com', 1);
        $data = $response->getData(true);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('INVALID_USER_DATA', $data['code']);
    }

    public function test_select_tenant_public_requires_fields(): void
    {
        $service = $this->authService(
            Mockery::mock(JwtService::class),
            Mockery::mock(UsuarioRepository::class),
        );

        $response = $service->selectTenantPublic(0, '', 0);
        $data = $response->getData(true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('MISSING_REQUIRED_FIELDS', $data['code']);
    }

    public function test_select_tenant_success_returns_token(): void
    {
        $this->assertSelectTenantResolvesTenant(
            tenantIdInList: 3,
            papelId: 3,
        );
    }

    public function test_select_tenant_resolves_tenant_when_id_is_string_from_db(): void
    {
        $this->assertSelectTenantResolvesTenant(
            tenantIdInList: '3',
            papelId: 1,
            alunoId: 99,
        );
    }

    private function assertSelectTenantResolvesTenant(
        int|string $tenantIdInList,
        int $papelId,
        ?int $alunoId = null,
    ): void {
        $userId = 2;
        $tenantId = 3;

        $usuarios = Mockery::mock(UsuarioRepository::class);
        $usuarios->shouldReceive('temAcessoTenant')->with($userId, $tenantId)->andReturn(true);
        $usuarios->shouldReceive('findById')->with($userId)->andReturn([
            'id' => $userId,
            'nome' => 'Test',
            'email' => 'a@b.com',
            'email_global' => 'a@b.com',
            'papel_id' => $papelId,
            'foto_base64' => null,
        ]);

        if ($alunoId !== null) {
            $usuarios->shouldReceive('findAlunoId')->with($userId)->andReturn($alunoId);
        }

        $usuarios->shouldReceive('getTenantsByUsuario')->with($userId)->andReturn([
            [
                'tenant' => ['id' => $tenantIdInList, 'nome' => 'Academia'],
                'papeis' => [['id' => $papelId, 'nome' => 'aluno']],
            ],
        ]);

        $jwt = Mockery::mock(JwtService::class);
        $jwt->shouldReceive('encode')->once()->andReturn('token-test');

        $service = $this->authService($jwt, $usuarios);
        $response = $service->selectTenant($userId, $tenantId);
        $data = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('token-test', $data['token']);
        $this->assertSame('Academia selecionada com sucesso', $data['message']);
        $this->assertNotNull($data['tenant']);
        $this->assertEquals($tenantId, $data['tenant']['tenant']['id']);
        $this->assertSame('Academia', $data['tenant']['tenant']['nome']);
    }

    private function authService($jwt, $usuarios): AuthService
    {
        return new AuthService(
            $jwt,
            $usuarios,
            Mockery::mock(PasswordRecoveryMailer::class),
        );
    }
}
