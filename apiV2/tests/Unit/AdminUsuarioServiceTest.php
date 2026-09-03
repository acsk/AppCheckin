<?php

namespace Tests\Unit;

use App\Repositories\UsuarioRepository;
use App\Services\Admin\AdminUsuarioService;
use Mockery;
use Tests\TestCase;

class AdminUsuarioServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_index_filtra_admins_para_nao_superadmin(): void
    {
        $repo = Mockery::mock(UsuarioRepository::class);
        $repo->shouldReceive('listarPorTenant')
            ->once()
            ->with(3, true)
            ->andReturn([
                ['id' => 1, 'nome' => 'ALUNO', 'papel_id' => 1],
                ['id' => 2, 'nome' => 'PROFESSOR', 'papel_id' => 2],
                ['id' => 3, 'nome' => 'ADMIN', 'papel_id' => 3],
            ]);

        $service = new AdminUsuarioService($repo);
        $result = $service->index(3, ['ativos' => 'true'], ['papel_id' => 3]);

        $this->assertSame(200, $result['status']);
        $this->assertCount(2, $result['body']);
        $this->assertSame([0, 1], array_keys($result['body']));
        $this->assertSame('PROFESSOR', $result['body'][1]['nome']);
    }

    public function test_index_superadmin_lista_todos_sem_filtro(): void
    {
        $repo = Mockery::mock(UsuarioRepository::class);
        $repo->shouldReceive('listarTodos')
            ->once()
            ->with(true, null, false)
            ->andReturn([
                ['id' => 3, 'nome' => 'ADMIN', 'papel_id' => 3],
            ]);

        $service = new AdminUsuarioService($repo);
        $result = $service->index(3, [], ['papel_id' => 4]);

        $this->assertSame(200, $result['status']);
        $this->assertCount(1, $result['body']);
        $this->assertSame('ADMIN', $result['body'][0]['nome']);
    }

    public function test_show_nao_encontrado(): void
    {
        $repo = Mockery::mock(UsuarioRepository::class);
        $repo->shouldReceive('findById')->once()->with(99, 3)->andReturn(null);

        $service = new AdminUsuarioService($repo);
        $result = $service->show(99, 3, ['papel_id' => 3]);

        $this->assertSame(404, $result['status']);
        $this->assertSame('Usuário não encontrado', $result['body']['error']);
    }

    public function test_show_bloqueia_admin_para_nao_superadmin(): void
    {
        $repo = Mockery::mock(UsuarioRepository::class);
        $repo->shouldReceive('findById')->once()->with(7, 3)->andReturn([
            'id' => 7,
            'nome' => 'OUTRO ADMIN',
            'papel_id' => 3,
        ]);

        $service = new AdminUsuarioService($repo);
        $result = $service->show(7, 3, ['papel_id' => 3]);

        $this->assertSame(403, $result['status']);
        $this->assertSame(
            'Usuários administradores só podem ser visualizados pela tela de Academia',
            $result['body']['error'],
        );
    }

    public function test_create_exige_nome_email_e_senha(): void
    {
        $service = new AdminUsuarioService(Mockery::mock(UsuarioRepository::class));
        $result = $service->create(3, []);

        $this->assertSame(422, $result['status']);
        $this->assertSame('error', $result['body']['type']);
        $this->assertSame(
            'Nome é obrigatório, Email é obrigatório, Senha é obrigatória',
            $result['body']['message'],
        );
    }

    public function test_create_rejeita_email_duplicado(): void
    {
        $repo = Mockery::mock(UsuarioRepository::class);
        $repo->shouldReceive('emailExists')->once()->with('ja@existe.com', null)->andReturn(true);

        $service = new AdminUsuarioService($repo);
        $result = $service->create(3, [
            'nome' => 'Novo',
            'email' => 'ja@existe.com',
            'senha' => 'segredo123',
        ]);

        $this->assertSame(422, $result['status']);
        $this->assertSame('Email já cadastrado', $result['body']['message']);
    }

    public function test_create_ok(): void
    {
        $repo = Mockery::mock(UsuarioRepository::class);
        $repo->shouldReceive('emailExists')->once()->andReturn(false);
        $repo->shouldReceive('criarUsuarioCompleto')->once()->andReturn(42);
        $repo->shouldReceive('findById')->once()->with(42, 3)->andReturn([
            'id' => 42,
            'nome' => 'NOVO',
        ]);

        $service = new AdminUsuarioService($repo);
        $result = $service->create(3, [
            'nome' => 'Novo',
            'email' => 'novo@example.com',
            'senha' => 'segredo123',
        ]);

        $this->assertSame(201, $result['status']);
        $this->assertSame('success', $result['body']['type']);
        $this->assertSame('Usuário criado com sucesso', $result['body']['message']);
        $this->assertSame(42, $result['body']['usuario']['id']);
    }

    public function test_update_retorna_400_quando_nada_foi_atualizado(): void
    {
        $repo = Mockery::mock(UsuarioRepository::class);
        $repo->shouldReceive('findById')->once()->with(5, 3)->andReturn([
            'id' => 5,
            'papel_id' => 1,
        ]);
        $repo->shouldReceive('emailExists')->once()->andReturn(false);
        $repo->shouldReceive('atualizarPerfil')->once()->with(5, Mockery::type('array'))->andReturn(false);

        $service = new AdminUsuarioService($repo);
        $result = $service->update(5, 3, [
            'nome' => 'Aluno',
            'email' => 'aluno@example.com',
        ], ['papel_id' => 3]);

        $this->assertSame(400, $result['status']);
        $this->assertSame('Nenhum dado foi atualizado', $result['body']['message']);
    }

    public function test_delete_superadmin_nao_altera_outro_superadmin(): void
    {
        $repo = Mockery::mock(UsuarioRepository::class);
        $repo->shouldReceive('findById')->once()->with(1, 3)->andReturn(['id' => 1, 'papel_id' => 4]);
        $repo->shouldReceive('findById')->once()->with(9, null)->andReturn(['id' => 9, 'papel_id' => 4]);

        $service = new AdminUsuarioService($repo);
        $result = $service->delete(9, 3, 1);

        $this->assertSame(403, $result['status']);
        $this->assertSame('Não é permitido alterar status de usuários SuperAdmin', $result['body']['message']);
    }

    public function test_delete_alterna_vinculo_do_tenant(): void
    {
        $repo = Mockery::mock(UsuarioRepository::class);
        $repo->shouldReceive('findById')->once()->with(1, 3)->andReturn(['id' => 1, 'papel_id' => 3]);
        $repo->shouldReceive('findById')->once()->with(9, 3)->andReturn([
            'id' => 9,
            'papel_id' => 1,
            'ativo' => true,
        ]);
        $repo->shouldReceive('toggleStatusUsuarioTenant')->once()->with(9, 3)->andReturn(true);

        $service = new AdminUsuarioService($repo);
        $result = $service->delete(9, 3, 1);

        $this->assertSame(200, $result['status']);
        $this->assertSame('success', $result['body']['type']);
        $this->assertSame('Usuário desativado com sucesso', $result['body']['message']);
    }

    public function test_buscar_por_cpf_valida_tamanho(): void
    {
        $service = new AdminUsuarioService(Mockery::mock(UsuarioRepository::class));
        $result = $service->buscarPorCpf('123', 3);

        $this->assertSame(400, $result['status']);
        $this->assertSame('CPF deve conter 11 dígitos', $result['body']['error']);
    }

    public function test_buscar_por_cpf_valida_digitos_verificadores(): void
    {
        $service = new AdminUsuarioService(Mockery::mock(UsuarioRepository::class));
        $result = $service->buscarPorCpf('12345678900', 3);

        $this->assertSame(400, $result['status']);
        $this->assertSame('CPF inválido', $result['body']['error']);
    }

    public function test_buscar_por_cpf_nao_encontrado(): void
    {
        $repo = Mockery::mock(UsuarioRepository::class);
        $repo->shouldReceive('findByCpfGlobal')->once()->with('52998224725')->andReturn(null);

        $service = new AdminUsuarioService($repo);
        $result = $service->buscarPorCpf('529.982.247-25', 3);

        $this->assertSame(200, $result['status']);
        $this->assertFalse($result['body']['found']);
        $this->assertSame(
            'Usuário não encontrado. Você pode cadastrar um novo usuário.',
            $result['body']['message'],
        );
    }

    public function test_buscar_por_cpf_encontrado_em_outro_tenant(): void
    {
        $repo = Mockery::mock(UsuarioRepository::class);
        $repo->shouldReceive('findByCpfGlobal')->once()->andReturn([
            'id' => 11,
            'nome' => 'MARIA',
            'email' => 'maria@example.com',
            'telefone' => '11999998888',
            'cpf' => '52998224725',
            'senha_hash' => 'nao-deve-vazar',
        ]);
        $repo->shouldReceive('isAssociatedWithTenant')->once()->with(11, 3)->andReturn(false);
        $repo->shouldReceive('getTenantsByUsuario')->once()->with(11)->andReturn([
            ['tenant' => ['id' => 7, 'nome' => 'Outra Academia']],
        ]);

        $service = new AdminUsuarioService($repo);
        $result = $service->buscarPorCpf('52998224725', 3);

        $this->assertSame(200, $result['status']);
        $this->assertTrue($result['body']['found']);
        $this->assertFalse($result['body']['ja_associado']);
        $this->assertTrue($result['body']['pode_associar']);
        $this->assertSame(['id', 'nome', 'email', 'telefone', 'cpf'], array_keys($result['body']['usuario']));
        $this->assertSame(7, $result['body']['tenants'][0]['tenant']['id']);
    }

    public function test_associar_exige_usuario_id(): void
    {
        $service = new AdminUsuarioService(Mockery::mock(UsuarioRepository::class));
        $result = $service->associar(3, []);

        $this->assertSame(400, $result['status']);
        $this->assertSame('ID do usuário é obrigatório', $result['body']['error']);
    }

    public function test_associar_conflito_quando_ja_associado(): void
    {
        $repo = Mockery::mock(UsuarioRepository::class);
        $repo->shouldReceive('findById')->once()->with(11, null)->andReturn(['id' => 11]);
        $repo->shouldReceive('isAssociatedWithTenant')->once()->with(11, 3)->andReturn(true);

        $service = new AdminUsuarioService($repo);
        $result = $service->associar(3, ['usuario_id' => 11]);

        $this->assertSame(409, $result['status']);
        $this->assertSame('Usuário já está associado a esta academia', $result['body']['error']);
    }

    public function test_associar_ok(): void
    {
        $repo = Mockery::mock(UsuarioRepository::class);
        $repo->shouldReceive('findById')->once()->with(11, null)->andReturn(['id' => 11]);
        $repo->shouldReceive('isAssociatedWithTenant')->once()->with(11, 3)->andReturn(false);
        $repo->shouldReceive('associateToTenant')->once()->with(11, 3, 'ativo')->andReturn(true);
        $repo->shouldReceive('findById')->once()->with(11, 3)->andReturn(['id' => 11, 'tenant_id' => 3]);

        $service = new AdminUsuarioService($repo);
        $result = $service->associar(3, ['usuario_id' => 11]);

        $this->assertSame(200, $result['status']);
        $this->assertSame('Usuário associado com sucesso', $result['body']['message']);
        $this->assertSame(3, $result['body']['usuario']['tenant_id']);
    }

    public function test_estatisticas_nao_encontrado(): void
    {
        $repo = Mockery::mock(UsuarioRepository::class);
        $repo->shouldReceive('getEstatisticas')->once()->with(99, 3)->andReturn(null);

        $service = new AdminUsuarioService($repo);
        $result = $service->estatisticas(99, 3);

        $this->assertSame(404, $result['status']);
        $this->assertSame('Usuário não encontrado', $result['body']['message']);
    }

    public function test_admins_retorna_lista(): void
    {
        $repo = Mockery::mock(UsuarioRepository::class);
        $repo->shouldReceive('listarAdminsDoTenant')->once()->with(3)->andReturn([
            ['id' => 1, 'nome' => 'ADMIN', 'email' => 'admin@example.com', 'papel' => 'admin'],
        ]);

        $service = new AdminUsuarioService($repo);
        $result = $service->admins(3);

        $this->assertSame(200, $result['status']);
        $this->assertSame('ADMIN', $result['body']['admins'][0]['nome']);
    }
}
