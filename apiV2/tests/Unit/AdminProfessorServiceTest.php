<?php

namespace Tests\Unit;

use App\Repositories\AdminProfessorRepository;
use App\Repositories\UsuarioRepository;
use App\Services\Admin\AdminProfessorService;
use Mockery;
use Tests\TestCase;

class AdminProfessorServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function service(?AdminProfessorRepository $professores = null, ?UsuarioRepository $usuarios = null): AdminProfessorService
    {
        return new AdminProfessorService(
            $professores ?? Mockery::mock(AdminProfessorRepository::class),
            $usuarios ?? Mockery::mock(UsuarioRepository::class),
        );
    }

    public function test_index_returns_professores(): void
    {
        $repo = Mockery::mock(AdminProfessorRepository::class);
        $repo->shouldReceive('listarPorTenant')
            ->once()
            ->with(3, true)
            ->andReturn([['id' => 1, 'nome' => 'CARLOS MENDES', 'turmas_count' => 2]]);

        $result = $this->service($repo)->index(3, true);

        $this->assertSame(200, $result['status']);
        $this->assertSame('CARLOS MENDES', $result['body']['professores'][0]['nome']);
    }

    public function test_show_not_found(): void
    {
        $repo = Mockery::mock(AdminProfessorRepository::class);
        $repo->shouldReceive('findById')->once()->with(99, 3)->andReturn(null);

        $result = $this->service($repo)->show(99, 3);

        $this->assertSame(404, $result['status']);
        $this->assertSame('error', $result['body']['type']);
        $this->assertSame('Professor não encontrado', $result['body']['message']);
    }

    public function test_buscar_por_cpf_valida_tamanho(): void
    {
        $result = $this->service()->buscarPorCpf('123', 3);

        $this->assertSame(400, $result['status']);
        $this->assertSame('CPF inválido. Deve conter 11 dígitos.', $result['body']['message']);
    }

    public function test_buscar_por_cpf_aceita_formatacao_e_retorna_404(): void
    {
        $repo = Mockery::mock(AdminProfessorRepository::class);
        $repo->shouldReceive('findByCpf')->once()->with('12345678901', 3)->andReturn(null);

        $result = $this->service($repo)->buscarPorCpf('123.456.789-01', 3);

        $this->assertSame(404, $result['status']);
        $this->assertSame('Professor não encontrado com este CPF', $result['body']['message']);
    }

    public function test_buscar_por_cpf_global_marca_vinculo_ao_tenant(): void
    {
        $repo = Mockery::mock(AdminProfessorRepository::class);
        $repo->shouldReceive('findByCpfGlobal')->once()->with('11122233344')->andReturn([
            'id' => 101,
            'nome' => 'Maria Oliveira',
            'cpf' => '11122233344',
            'usuario_id' => 101,
        ]);
        $repo->shouldReceive('pertenceAoTenant')->once()->with(101, 3)->andReturn(false);

        $result = $this->service($repo)->buscarPorCpfGlobal('111.222.333-44', 3);

        $this->assertSame(200, $result['status']);
        $this->assertFalse($result['body']['professor']['vinculado_ao_tenant_atual']);
    }

    public function test_buscar_por_cpf_global_not_found(): void
    {
        $repo = Mockery::mock(AdminProfessorRepository::class);
        $repo->shouldReceive('findByCpfGlobal')->once()->with('11122233344')->andReturn(null);

        $result = $this->service($repo)->buscarPorCpfGlobal('11122233344', 3);

        $this->assertSame(404, $result['status']);
        $this->assertSame('Professor não encontrado no sistema', $result['body']['message']);
    }

    public function test_create_valida_campos_obrigatorios(): void
    {
        $service = $this->service();

        $this->assertSame('Nome do professor é obrigatório', $service->create(3, [])['body']['message']);
        $this->assertSame(
            'Email é obrigatório para criar professor',
            $service->create(3, ['nome' => 'João'])['body']['message'],
        );
        $this->assertSame(
            'CPF é obrigatório para criar professor',
            $service->create(3, ['nome' => 'João', 'email' => 'j@x.com'])['body']['message'],
        );

        $cpfInvalido = $service->create(3, ['nome' => 'João', 'email' => 'j@x.com', 'cpf' => '123']);
        $this->assertSame(400, $cpfInvalido['status']);
        $this->assertSame('CPF inválido. Deve conter 11 dígitos', $cpfInvalido['body']['message']);
    }

    public function test_create_rejeita_cpf_de_outro_usuario(): void
    {
        $usuarios = Mockery::mock(UsuarioRepository::class);
        $usuarios->shouldReceive('findByEmailGlobal')->once()->with('novo@x.com')->andReturn(null);

        $repo = Mockery::mock(AdminProfessorRepository::class);
        $repo->shouldReceive('findUsuarioByCpfGlobal')->once()->with('12345678901')->andReturn(['id' => 7]);

        $result = $this->service($repo, $usuarios)->create(3, [
            'nome' => 'João',
            'email' => 'novo@x.com',
            'cpf' => '123.456.789-01',
        ]);

        $this->assertSame(409, $result['status']);
        $this->assertSame('CPF já cadastrado para outro usuário no sistema', $result['body']['message']);
    }

    public function test_create_rejeita_professor_ja_vinculado(): void
    {
        $usuarios = Mockery::mock(UsuarioRepository::class);
        $usuarios->shouldReceive('findByEmailGlobal')->once()->with('prof@x.com')->andReturn((object) ['id' => 50]);

        $repo = Mockery::mock(AdminProfessorRepository::class);
        $repo->shouldReceive('findByUsuarioId')->once()->with(50)->andReturn(['id' => 9, 'usuario_id' => 50]);
        $repo->shouldReceive('pertenceAoTenant')->once()->with(9, 3)->andReturn(true);

        $result = $this->service($repo, $usuarios)->create(3, [
            'nome' => 'João',
            'email' => 'prof@x.com',
            'cpf' => '12345678901',
        ]);

        $this->assertSame(409, $result['status']);
        $this->assertSame('Professor já está vinculado a este tenant', $result['body']['message']);
    }

    public function test_create_associa_professor_global_existente(): void
    {
        $usuarios = Mockery::mock(UsuarioRepository::class);
        $usuarios->shouldReceive('findByEmailGlobal')->once()->with('prof@x.com')->andReturn((object) ['id' => 50]);

        $repo = Mockery::mock(AdminProfessorRepository::class);
        $repo->shouldReceive('findByUsuarioId')->once()->with(50)->andReturn(['id' => 9, 'usuario_id' => 50]);
        $repo->shouldReceive('pertenceAoTenant')->once()->with(9, 3)->andReturn(false);
        $repo->shouldReceive('associarAoTenant')->once()->with(9, 3)->andReturn(true);
        $repo->shouldReceive('findById')->once()->with(9, 3)->andReturn(['id' => 9, 'nome' => 'João', 'vinculo_ativo' => 1]);

        $result = $this->service($repo, $usuarios)->create(3, [
            'nome' => 'João',
            'email' => 'prof@x.com',
            'cpf' => '12345678901',
        ]);

        $this->assertSame(201, $result['status']);
        $this->assertSame('Professor existente associado ao tenant com sucesso', $result['body']['message']);
        $this->assertTrue($result['body']['professor_existia']);
        $this->assertFalse($result['body']['usuario']['criado']);
        $this->assertArrayNotHasKey('credenciais', $result['body']);
    }

    public function test_create_cria_usuario_e_devolve_credenciais(): void
    {
        $usuarios = Mockery::mock(UsuarioRepository::class);
        $usuarios->shouldReceive('findByEmailGlobal')->once()->with('novo@x.com')->andReturn(null);
        $usuarios->shouldReceive('createUsuario')
            ->once()
            ->withArgs(function (array $data, int $tenantId, int $papelId) {
                return $tenantId === 3
                    && $papelId === 1
                    && $data['cpf'] === '12345678901'
                    && is_string($data['senha'])
                    && strlen($data['senha']) === 8;
            })
            ->andReturn(77);

        $repo = Mockery::mock(AdminProfessorRepository::class);
        $repo->shouldReceive('findUsuarioByCpfGlobal')->once()->with('12345678901')->andReturn(null);
        $repo->shouldReceive('criar')
            ->once()
            ->withArgs(fn (array $data) => $data['usuario_id'] === 77 && $data['cpf'] === '12345678901')
            ->andReturn(12);
        $repo->shouldReceive('associarAoTenant')->once()->with(12, 3)->andReturn(true);
        $repo->shouldReceive('findById')->once()->with(12, 3)->andReturn(['id' => 12, 'nome' => 'João']);

        $result = $this->service($repo, $usuarios)->create(3, [
            'nome' => 'João',
            'email' => 'novo@x.com',
            'cpf' => '12345678901',
            'telefone' => '11999998888',
        ]);

        $this->assertSame(201, $result['status']);
        $this->assertSame('Professor criado com sucesso', $result['body']['message']);
        $this->assertFalse($result['body']['professor_existia']);
        $this->assertTrue($result['body']['usuario']['criado']);
        $this->assertSame(77, $result['body']['usuario']['id']);
        $this->assertSame('professor', $result['body']['usuario']['papel']);
        $this->assertSame('novo@x.com', $result['body']['credenciais']['email']);
        $this->assertSame(8, strlen($result['body']['credenciais']['senha_temporaria']));
    }

    public function test_update_not_found(): void
    {
        $repo = Mockery::mock(AdminProfessorRepository::class);
        $repo->shouldReceive('findById')->once()->with(99, 3)->andReturn(null);

        $result = $this->service($repo)->update(99, 3, ['nome' => 'Novo']);

        $this->assertSame(404, $result['status']);
        $this->assertSame('Professor não encontrado', $result['body']['message']);
    }

    public function test_update_rejeita_email_duplicado(): void
    {
        $repo = Mockery::mock(AdminProfessorRepository::class);
        $repo->shouldReceive('findById')->once()->with(9, 3)->andReturn(['id' => 9, 'usuario_id' => 50]);
        $repo->shouldReceive('emailEmUsoPorOutroUsuario')->once()->with('dup@x.com', 50)->andReturn(true);

        $result = $this->service($repo)->update(9, 3, ['email' => 'dup@x.com']);

        $this->assertSame(422, $result['status']);
        $this->assertSame('Email já está em uso por outro usuário', $result['body']['message']);
    }

    public function test_update_atualiza_professor_e_usuario(): void
    {
        $repo = Mockery::mock(AdminProfessorRepository::class);
        $repo->shouldReceive('findById')->once()->with(9, 3)->andReturn(['id' => 9, 'usuario_id' => 50]);
        $repo->shouldReceive('emailEmUsoPorOutroUsuario')->once()->with('novo@x.com', 50)->andReturn(false);
        $repo->shouldReceive('atualizar')->once()->with(9, Mockery::type('array'))->andReturn(true);
        $repo->shouldReceive('findById')->once()->with(9, 3)->andReturn(['id' => 9, 'email' => 'novo@x.com']);

        $usuarios = Mockery::mock(UsuarioRepository::class);
        $usuarios->shouldReceive('updateAuthFields')
            ->once()
            ->with(50, ['email' => 'novo@x.com', 'senha' => 'segredo123'])
            ->andReturnNull();

        $result = $this->service($repo, $usuarios)->update(9, 3, [
            'email' => 'novo@x.com',
            'senha' => 'segredo123',
        ]);

        $this->assertSame(200, $result['status']);
        $this->assertSame('Professor atualizado com sucesso', $result['body']['message']);
        $this->assertSame('novo@x.com', $result['body']['professor']['email']);
    }

    public function test_delete_desativa_professor(): void
    {
        $repo = Mockery::mock(AdminProfessorRepository::class);
        $repo->shouldReceive('findById')->once()->with(9, 3)->andReturn(['id' => 9, 'usuario_id' => 50]);
        $repo->shouldReceive('softDelete')->once()->with(9)->andReturn(true);

        $result = $this->service($repo)->delete(9, 3);

        $this->assertSame(200, $result['status']);
        $this->assertSame('success', $result['body']['type']);
        $this->assertSame('Professor deletado com sucesso', $result['body']['message']);
    }

    public function test_turmas_exige_vinculo_com_tenant(): void
    {
        $repo = Mockery::mock(AdminProfessorRepository::class);
        $repo->shouldReceive('pertenceAoTenant')->once()->with(9, 3)->andReturn(false);

        $result = $this->service($repo)->turmas(9, 3);

        $this->assertSame(404, $result['status']);
        $this->assertSame('Professor não encontrado', $result['body']['message']);
    }

    public function test_turmas_retorna_lista(): void
    {
        $repo = Mockery::mock(AdminProfessorRepository::class);
        $repo->shouldReceive('pertenceAoTenant')->once()->with(9, 3)->andReturn(true);
        $repo->shouldReceive('listarTurmas')->once()->with(9, 3)->andReturn([
            ['id' => 1, 'modalidade_nome' => 'CrossFit', 'alunos_count' => 5],
        ]);

        $result = $this->service($repo)->turmas(9, 3);

        $this->assertSame(200, $result['status']);
        $this->assertSame('CrossFit', $result['body']['turmas'][0]['modalidade_nome']);
    }
}
