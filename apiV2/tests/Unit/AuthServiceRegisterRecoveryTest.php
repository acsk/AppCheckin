<?php

namespace Tests\Unit;

use App\Repositories\UsuarioRepository;
use App\Services\AuthService;
use App\Services\JwtService;
use App\Services\PasswordRecoveryMailer;
use Mockery;
use Tests\TestCase;

class AuthServiceRegisterRecoveryTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_register_requires_fields(): void
    {
        $service = $this->authService(
            Mockery::mock(JwtService::class),
            Mockery::mock(UsuarioRepository::class),
        );

        $response = $service->register('', '', '', 0);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('MISSING_FIELDS', $response->getData(true)['code']);
    }

    public function test_register_includes_aluno_id_in_jwt(): void
    {
        $usuarios = Mockery::mock(UsuarioRepository::class);
        $usuarios->shouldReceive('isTenantActive')->with(1)->andReturn(true);
        $usuarios->shouldReceive('findByEmailGlobal')->andReturn(null);
        $usuarios->shouldReceive('createUsuario')->andReturn(10);
        $usuarios->shouldReceive('findById')->with(10, 1)->andReturn([
            'id' => 10,
            'nome' => 'JOÃO',
            'email' => 'joao@b.com',
            'papel_id' => 1,
        ]);
        $usuarios->shouldReceive('findAlunoId')->with(10)->andReturn(55);

        $jwt = Mockery::mock(JwtService::class);
        $jwt->shouldReceive('encode')
            ->once()
            ->with(Mockery::on(function (array $payload): bool {
                return $payload['user_id'] === 10
                    && $payload['tenant_id'] === 1
                    && $payload['aluno_id'] === 55;
            }))
            ->andReturn('jwt-with-aluno');

        $service = $this->authService($jwt, $usuarios);

        $response = $service->register('João', 'joao@b.com', 'senha123', 1);
        $data = $response->getData(true);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('jwt-with-aluno', $data['token']);
    }

    public function test_register_rejects_invalid_tenant(): void
    {
        $usuarios = Mockery::mock(UsuarioRepository::class);
        $usuarios->shouldReceive('isTenantActive')->with(9)->andReturn(false);

        $service = $this->authService(Mockery::mock(JwtService::class), $usuarios);

        $response = $service->register('João', 'a@b.com', 'senha123', 9);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('INVALID_TENANT', $response->getData(true)['code']);
    }

    public function test_password_recovery_requires_email(): void
    {
        $service = $this->authService(
            Mockery::mock(JwtService::class),
            Mockery::mock(UsuarioRepository::class),
        );

        $response = $service->requestPasswordRecovery('');
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('MISSING_EMAIL', $response->getData(true)['code']);
    }

    public function test_password_recovery_always_returns_generic_message(): void
    {
        $usuarios = Mockery::mock(UsuarioRepository::class);
        $usuarios->shouldReceive('findByEmailGlobal')->andReturn(null);

        $service = $this->authService(Mockery::mock(JwtService::class), $usuarios);

        $response = $service->requestPasswordRecovery('unknown@example.com');
        $data = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('base de dados', $data['message']);
    }

    public function test_reset_password_validation_error(): void
    {
        $service = $this->authService(
            Mockery::mock(JwtService::class),
            Mockery::mock(UsuarioRepository::class),
        );

        $response = $service->resetPassword('', '123', '456');
        $data = $response->getData(true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('error', $data['type']);
        $this->assertSame('VALIDATION_ERROR', $data['code']);
        $this->assertSame('Erro de validação', $data['message']);
        $this->assertNotEmpty($data['errors']);
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
