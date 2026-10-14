<?php

namespace Tests\Unit;

use App\Repositories\TenantRepository;
use App\Repositories\UsuarioRepository;
use App\Services\AuthService;
use App\Services\JwtService;
use App\Services\PasswordRecoveryMailer;
use App\Services\WelcomeAlunoMailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

class AuthServiceRegisterRecoveryTest extends TestCase
{
    use RequiresSqlite;

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

    public function test_register_rejects_invalid_cpf_length(): void
    {
        $usuarios = Mockery::mock(UsuarioRepository::class);
        $usuarios->shouldReceive('isTenantActive')->with(1)->andReturn(true);
        $usuarios->shouldReceive('findByEmailGlobal')->andReturn(null);

        $service = $this->authService(Mockery::mock(JwtService::class), $usuarios);

        $response = $service->register('João', 'joao@b.com', 'senha123', 1, '123');
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('INVALID_CPF', $response->getData(true)['code']);
    }

    public function test_register_rejects_duplicate_cpf(): void
    {
        $usuarios = Mockery::mock(UsuarioRepository::class);
        $usuarios->shouldReceive('isTenantActive')->with(1)->andReturn(true);
        $usuarios->shouldReceive('findByEmailGlobal')->andReturn(null);
        $usuarios->shouldReceive('cpfExists')->with('39053344705')->andReturn(true);

        $service = $this->authService(Mockery::mock(JwtService::class), $usuarios);

        $response = $service->register('João', 'joao@b.com', 'senha123', 1, '390.533.447-05');
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('CPF_ALREADY_EXISTS', $response->getData(true)['code']);
    }

    public function test_password_recovery_requires_email(): void
    {
        $service = $this->authService(
            Mockery::mock(JwtService::class),
            Mockery::mock(UsuarioRepository::class),
        );

        $response = $service->requestPasswordRecovery(Request::create('/', 'POST', ['email' => '']));
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('MISSING_EMAIL', $response->getData(true)['code']);
    }

    public function test_password_recovery_always_returns_generic_message(): void
    {
        $usuarios = Mockery::mock(UsuarioRepository::class);
        $usuarios->shouldReceive('findByEmailGlobal')->andReturn(null);

        $service = $this->authService(Mockery::mock(JwtService::class), $usuarios);

        $response = $service->requestPasswordRecovery(Request::create('/', 'POST', [
            'email' => 'unknown@example.com',
        ]));
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

    public function test_register_mobile_validates_required_fields(): void
    {
        $service = $this->authService(
            Mockery::mock(JwtService::class),
            Mockery::mock(UsuarioRepository::class),
        );

        $request = Request::create('/v2/auth/register-mobile', 'POST', []);
        $response = $service->registerMobile($request);
        $data = $response->getData(true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('VALIDATION_ERROR', $data['code']);
        $this->assertContains('nome é obrigatório', $data['errors']);
    }

    public function test_register_mobile_exige_sexo(): void
    {
        $this->criarTabelaRateLimits();
        $service = $this->authService(Mockery::mock(JwtService::class), Mockery::mock(UsuarioRepository::class));

        $sem = $service->registerMobile(Request::create('/v2/auth/register-mobile', 'POST', $this->dadosMobile(['sexo' => ''])));
        $this->assertSame(422, $sem->getStatusCode());
        $this->assertContains('sexo é obrigatório', $sem->getData(true)['errors']);

        $invalido = $service->registerMobile(Request::create('/v2/auth/register-mobile', 'POST', $this->dadosMobile(['sexo' => 'x'])));
        $this->assertSame(422, $invalido->getStatusCode());
        $this->assertStringStartsWith('sexo inválido', $invalido->getData(true)['errors'][0]);
    }

    public function test_register_mobile_grava_sexo_normalizado(): void
    {
        $this->criarTabelaRateLimits();
        $usuarios = Mockery::mock(UsuarioRepository::class);
        $usuarios->shouldReceive('emailExists')->andReturn(false);
        $usuarios->shouldReceive('cpfExists')->andReturn(false);
        $usuarios->shouldReceive('createMobileAluno')
            ->once()
            ->withArgs(fn (array $payload) => $payload['sexo'] === 'prefiro_nao_informar')
            ->andReturn(10);
        $usuarios->shouldReceive('findAlunoId')->with(10)->andReturn(20);
        $jwt = Mockery::mock(JwtService::class);
        $jwt->shouldReceive('encode')->andReturn('token');
        $mailer = Mockery::mock(WelcomeAlunoMailer::class);
        $mailer->shouldReceive('send');

        $service = new AuthService($jwt, $usuarios, Mockery::mock(TenantRepository::class), Mockery::mock(PasswordRecoveryMailer::class), $mailer);
        $response = $service->registerMobile(Request::create('/v2/auth/register-mobile', 'POST', $this->dadosMobile(['sexo' => 'Prefiro não informar'])));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('prefiro_nao_informar', $response->getData(true)['user']['sexo']);
    }

    private function criarTabelaRateLimits(): void
    {
        $this->requireSqlite();
        config(['appcheckin.rate_limit_register_max' => 1000]);
        DB::statement('CREATE TABLE IF NOT EXISTS rate_limits (id INTEGER PRIMARY KEY, ip TEXT, action TEXT, attempts INTEGER, expires_at TEXT, created_at TEXT, updated_at TEXT)');
    }

    /** @param array<string, mixed> $extra */
    private function dadosMobile(array $extra = []): array
    {
        return $extra + [
            'nome' => 'Maria Teste',
            'email' => 'maria@example.com',
            'cpf' => '52998224725',
            'data_nascimento' => '1990-05-10',
            'tenant_id' => 1,
        ];
    }

    private function authService($jwt, $usuarios): AuthService
    {
        return new AuthService(
            $jwt,
            $usuarios,
            Mockery::mock(TenantRepository::class),
            Mockery::mock(PasswordRecoveryMailer::class),
            Mockery::mock(WelcomeAlunoMailer::class),
        );
    }
}
