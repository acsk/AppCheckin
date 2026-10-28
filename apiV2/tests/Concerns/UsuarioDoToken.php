<?php

namespace Tests\Concerns;

use App\Repositories\UsuarioRepository;
use Mockery;

/**
 * O SQLite de teste não tem o schema base (usuarios, tenants): o JwtAuthenticate busca o
 * usuário do token no UsuarioRepository, então os testes de rota o simulam com este mock.
 */
trait UsuarioDoToken
{
    /** @param array<string, mixed> $usuario */
    protected function mockUsuarioDoToken(array $usuario = []): void
    {
        $usuarios = Mockery::mock(UsuarioRepository::class);
        $usuarios->shouldReceive('findAuthContext')->andReturn($usuario + [
            'id' => 1,
            'nome' => 'Aluno',
            'email' => 'test@example.com',
            'tenant_id' => 1,
            'papel_id' => 1,
        ]);
        $this->app->instance(UsuarioRepository::class, $usuarios);
    }
}
