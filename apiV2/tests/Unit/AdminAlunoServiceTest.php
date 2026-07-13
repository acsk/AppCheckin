<?php

namespace Tests\Unit;

use App\Repositories\AdminAlunoRepository;
use App\Repositories\UsuarioRepository;
use App\Services\Admin\AdminAlunoService;
use Mockery;
use Tests\TestCase;

class AdminAlunoServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_create_requires_nome_email_senha(): void
    {
        $service = new AdminAlunoService(
            Mockery::mock(AdminAlunoRepository::class),
            Mockery::mock(UsuarioRepository::class),
        );

        $result = $service->create(3, []);

        $this->assertSame(422, $result['status']);
        $this->assertSame('Erro de validação', $result['body']['message']);
        $this->assertContains('Nome é obrigatório', $result['body']['errors']);
        $this->assertContains('Email é obrigatório', $result['body']['errors']);
        $this->assertContains('Senha é obrigatória', $result['body']['errors']);
    }

    public function test_show_not_found(): void
    {
        $repo = Mockery::mock(AdminAlunoRepository::class);
        $repo->shouldReceive('findById')->once()->with(99, 3)->andReturn(null);

        $service = new AdminAlunoService($repo, Mockery::mock(UsuarioRepository::class));
        $result = $service->show(99, 3);

        $this->assertSame(404, $result['status']);
        $this->assertSame('Aluno não encontrado', $result['body']['message']);
    }

    public function test_buscar_por_cpf_invalid_length(): void
    {
        $service = new AdminAlunoService(
            Mockery::mock(AdminAlunoRepository::class),
            Mockery::mock(UsuarioRepository::class),
        );

        $result = $service->buscarPorCpf('123', 3);

        $this->assertSame(400, $result['status']);
        $this->assertFalse($result['body']['success']);
        $this->assertSame('CPF deve conter 11 dígitos', $result['body']['error']);
    }

    public function test_associar_requires_aluno_id(): void
    {
        $service = new AdminAlunoService(
            Mockery::mock(AdminAlunoRepository::class),
            Mockery::mock(UsuarioRepository::class),
        );

        $result = $service->associar(3, []);

        $this->assertSame(400, $result['status']);
        $this->assertSame('ID do aluno é obrigatório', $result['body']['error']);
    }

    public function test_listar_basico(): void
    {
        $repo = Mockery::mock(AdminAlunoRepository::class);
        $repo->shouldReceive('listarBasico')->once()->with(3)->andReturn([
            ['id' => 1, 'nome' => 'ANA', 'email' => 'ana@x.com', 'usuario_id' => 10],
        ]);

        $service = new AdminAlunoService($repo, Mockery::mock(UsuarioRepository::class));
        $result = $service->listarBasico(3);

        $this->assertSame(200, $result['status']);
        $this->assertSame(1, $result['body']['total']);
        $this->assertSame('ANA', $result['body']['alunos'][0]['nome']);
    }
}
