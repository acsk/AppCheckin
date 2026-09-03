<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;

class V2AdminPacoteRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_pacotes_requires_jwt(): void
    {
        $this->getJson('/v2/admin/pacotes')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_pacotes_rejects_non_admin(): void
    {
        $token = $this->tokenParaPapel(1);

        $this->getJson('/v2/admin/pacotes', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertForbidden();
    }

    public function test_index_retorna_lista(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminPacoteService::class);
        $service->shouldReceive('listar')
            ->once()
            ->with(3)
            ->andReturn([
                'status' => 200,
                'body' => [
                    'success' => true,
                    'pacotes' => [['id' => 1, 'nome' => 'Família']],
                    'total' => 1,
                ],
            ]);
        $this->app->instance(\App\Services\Admin\AdminPacoteService::class, $service);

        $this->getJson('/v2/admin/pacotes', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('pacotes.0.nome', 'Família');
    }

    public function test_store_cria_pacote(): void
    {
        $token = $this->tokenParaPapel(3);
        $payload = [
            'nome' => 'Família',
            'valor_total' => 300,
            'qtd_beneficiarios' => 2,
            'plano_id' => 5,
        ];

        $service = Mockery::mock(\App\Services\Admin\AdminPacoteService::class);
        $service->shouldReceive('criar')
            ->once()
            ->with(3, Mockery::subset($payload))
            ->andReturn([
                'status' => 201,
                'body' => ['success' => true, 'pacote_id' => 10],
            ]);
        $this->app->instance(\App\Services\Admin\AdminPacoteService::class, $service);

        $this->postJson('/v2/admin/pacotes', $payload, [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertCreated()
            ->assertJsonPath('pacote_id', 10);
    }

    public function test_pacote_contratos_lista_pendentes(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminPacoteService::class);
        $service->shouldReceive('listarContratos')
            ->once()
            ->with(3, 'pendente')
            ->andReturn([
                'status' => 200,
                'body' => [
                    'success' => true,
                    'status_filtro' => 'pendente',
                    'contratos' => [],
                    'total' => 0,
                ],
            ]);
        $this->app->instance(\App\Services\Admin\AdminPacoteService::class, $service);

        $this->getJson('/v2/admin/pacote-contratos?status=pendente', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('status_filtro', 'pendente');
    }

    public function test_contratar_rota_registrada(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminPacoteService::class);
        $service->shouldReceive('contratar')
            ->once()
            ->with(3, 7, Mockery::subset(['pagante_usuario_id' => 12]))
            ->andReturn([
                'status' => 201,
                'body' => ['success' => true, 'pacote_contrato_id' => 99],
            ]);
        $this->app->instance(\App\Services\Admin\AdminPacoteService::class, $service);

        $this->postJson('/v2/admin/pacotes/7/contratar', ['pagante_usuario_id' => 12], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertCreated()
            ->assertJsonPath('pacote_contrato_id', 99);
    }

    public function test_excluir_contrato(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminPacoteService::class);
        $service->shouldReceive('excluirContrato')
            ->once()
            ->with(3, 15)
            ->andReturn([
                'status' => 200,
                'body' => ['success' => true, 'message' => 'Contrato excluído com sucesso'],
            ]);
        $this->app->instance(\App\Services\Admin\AdminPacoteService::class, $service);

        $this->deleteJson('/v2/admin/pacotes/contratos/15', [], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Contrato excluído com sucesso');
    }

    public function test_gerar_matriculas(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminPacoteService::class);
        $service->shouldReceive('gerarMatriculas')
            ->once()
            ->with(3, 20)
            ->andReturn([
                'status' => 200,
                'body' => [
                    'success' => true,
                    'message' => 'Matrículas geradas com sucesso',
                    'matriculas_criadas' => 2,
                ],
            ]);
        $this->app->instance(\App\Services\Admin\AdminPacoteService::class, $service);

        $this->postJson('/v2/admin/pacote-contratos/20/gerar-matriculas', [], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('matriculas_criadas', 2);
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
