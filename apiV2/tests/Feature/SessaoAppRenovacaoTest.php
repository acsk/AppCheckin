<?php

namespace Tests\Feature;

use App\Repositories\UsuarioRepository;
use App\Services\JwtService;
use Mockery;
use Tests\TestCase;

class SessaoAppRenovacaoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'appcheckin.jwt_secret' => 'test-secret-key-with-enough-length-for-hs256-algorithm',
            'appcheckin.jwt_app_session_days' => 30,
        ]);

        $usuarios = Mockery::mock(UsuarioRepository::class);
        $usuarios->shouldReceive('findAuthContext')->with(5)->andReturn([
            'id' => 5, 'nome' => 'Aluno', 'email' => 'aluno@example.com', 'tenant_id' => 1, 'papel_id' => 1,
        ]);
        $this->app->instance(UsuarioRepository::class, $usuarios);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_app_recebe_token_renovado_de_30_dias(): void
    {
        $response = $this->getJson('/v2/me', $this->headers($this->token(86400), renovar: true));

        $novo = $response->headers->get('X-Refresh-Token');
        $this->assertNotNull($novo);
        $payload = app(JwtService::class)->decode($novo);
        $this->assertSame(5, $payload->user_id);
        $this->assertSame(1, $payload->tenant_id);
        $this->assertGreaterThan(time() + 29 * 86400, $payload->exp);
    }

    public function test_sem_header_do_app_nao_renova(): void
    {
        $response = $this->getJson('/v2/me', $this->headers($this->token(86400), renovar: false));

        $this->assertNull($response->headers->get('X-Refresh-Token'));
    }

    public function test_token_renovado_hoje_nao_renova_de_novo(): void
    {
        $response = $this->getJson('/v2/me', $this->headers($this->token(30 * 86400), renovar: true));

        $this->assertNull($response->headers->get('X-Refresh-Token'));
    }

    private function token(int $ttl): string
    {
        return app(JwtService::class)->encode(['user_id' => 5, 'email' => 'aluno@example.com', 'tenant_id' => 1], $ttl);
    }

    /** @return array<string, string> */
    private function headers(string $token, bool $renovar): array
    {
        return ['Authorization' => 'Bearer '.$token] + ($renovar ? ['X-Session-Renew' => '1'] : []);
    }
}
