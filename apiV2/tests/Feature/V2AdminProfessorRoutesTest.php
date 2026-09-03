<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;

class V2AdminProfessorRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_professores_requires_jwt(): void
    {
        $this->getJson('/v2/admin/professores')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_professores_rejects_non_admin(): void
    {
        $token = $this->tokenParaPapel(1);

        $this->getJson('/v2/admin/professores', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertForbidden()
            ->assertJsonPath('erro', 'Acesso negado. Apenas administradores podem acessar este recurso.');
    }

    public function test_index_repassa_filtro_apenas_ativos(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminProfessorService::class);
        $service->shouldReceive('index')
            ->once()
            ->with(3, true)
            ->andReturn([
                'status' => 200,
                'body' => ['professores' => [['id' => 1, 'nome' => 'CARLOS MENDES']]],
            ]);
        $this->app->instance(\App\Services\Admin\AdminProfessorService::class, $service);

        $this->getJson('/v2/admin/professores?apenas_ativos=true', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('professores.0.nome', 'CARLOS MENDES');
    }

    public function test_show_retorna_404_com_contrato_slim(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminProfessorService::class);
        $service->shouldReceive('show')
            ->once()
            ->with(99, 3)
            ->andReturn([
                'status' => 404,
                'body' => ['type' => 'error', 'message' => 'Professor não encontrado'],
            ]);
        $this->app->instance(\App\Services\Admin\AdminProfessorService::class, $service);

        $this->getJson('/v2/admin/professores/99', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertNotFound()
            ->assertJsonPath('message', 'Professor não encontrado');
    }

    public function test_busca_por_cpf_no_tenant(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminProfessorService::class);
        $service->shouldReceive('buscarPorCpf')
            ->once()
            ->with('12345678901', 3)
            ->andReturn([
                'status' => 200,
                'body' => ['professor' => ['id' => 1, 'cpf' => '12345678901']],
            ]);
        $this->app->instance(\App\Services\Admin\AdminProfessorService::class, $service);

        $this->getJson('/v2/admin/professores/cpf/12345678901', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('professor.cpf', '12345678901');
    }

    public function test_busca_global_por_cpf_tem_precedencia_sobre_id(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminProfessorService::class);
        $service->shouldReceive('buscarPorCpfGlobal')
            ->once()
            ->with('11122233344', 3)
            ->andReturn([
                'status' => 200,
                'body' => ['professor' => ['id' => 101, 'vinculado_ao_tenant_atual' => false]],
            ]);
        $this->app->instance(\App\Services\Admin\AdminProfessorService::class, $service);

        $this->getJson('/v2/admin/professores/global/cpf/11122233344', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('professor.vinculado_ao_tenant_atual', false);
    }

    public function test_store_retorna_201(): void
    {
        $token = $this->tokenParaPapel(3);

        $payload = ['nome' => 'João Silva', 'email' => 'joao@x.com', 'cpf' => '12345678901'];

        $service = Mockery::mock(\App\Services\Admin\AdminProfessorService::class);
        $service->shouldReceive('create')
            ->once()
            ->with(3, $payload)
            ->andReturn([
                'status' => 201,
                'body' => [
                    'type' => 'success',
                    'message' => 'Professor criado com sucesso',
                    'professor' => ['id' => 12],
                    'professor_existia' => false,
                ],
            ]);
        $this->app->instance(\App\Services\Admin\AdminProfessorService::class, $service);

        $this->postJson('/v2/admin/professores', $payload, [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertCreated()
            ->assertJsonPath('type', 'success')
            ->assertJsonPath('professor.id', 12);
    }

    public function test_update_e_delete(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminProfessorService::class);
        $service->shouldReceive('update')
            ->once()
            ->with(9, 3, ['nome' => 'Novo Nome'])
            ->andReturn([
                'status' => 200,
                'body' => [
                    'type' => 'success',
                    'message' => 'Professor atualizado com sucesso',
                    'professor' => ['id' => 9],
                ],
            ]);
        $service->shouldReceive('delete')
            ->once()
            ->with(9, 3)
            ->andReturn([
                'status' => 200,
                'body' => ['type' => 'success', 'message' => 'Professor deletado com sucesso'],
            ]);
        $this->app->instance(\App\Services\Admin\AdminProfessorService::class, $service);

        $this->putJson('/v2/admin/professores/9', ['nome' => 'Novo Nome'], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Professor atualizado com sucesso');

        $this->deleteJson('/v2/admin/professores/9', [], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Professor deletado com sucesso');
    }

    public function test_turmas_do_professor(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminProfessorService::class);
        $service->shouldReceive('turmas')
            ->once()
            ->with(9, 3)
            ->andReturn([
                'status' => 200,
                'body' => ['turmas' => [['id' => 1, 'modalidade_nome' => 'CrossFit']]],
            ]);
        $this->app->instance(\App\Services\Admin\AdminProfessorService::class, $service);

        $this->getJson('/v2/admin/professores/9/turmas', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('turmas.0.modalidade_nome', 'CrossFit');
    }

    private function tokenParaPapel(int $papelId): string
    {
        config(['appcheckin.jwt_secret' => 'test-secret-key-with-enough-length-for-hs256-algorithm']);

        $usuarios = Mockery::mock(\App\Repositories\UsuarioRepository::class);
        $usuarios->shouldReceive('findAuthContext')
            ->with(5)
            ->andReturn([
                'id' => 5,
                'nome' => 'Admin',
                'email' => 'admin@example.com',
                'tenant_id' => 3,
                'papel_id' => $papelId,
            ]);
        $this->app->instance(\App\Repositories\UsuarioRepository::class, $usuarios);

        return app(\App\Services\JwtService::class)->encode([
            'user_id' => 5,
            'email' => 'admin@example.com',
            'tenant_id' => 3,
        ]);
    }
}
