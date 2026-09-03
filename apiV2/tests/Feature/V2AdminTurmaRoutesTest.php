<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;

class V2AdminTurmaRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_turmas_requires_jwt(): void
    {
        $this->getJson('/v2/admin/turmas')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_turmas_rejects_non_admin(): void
    {
        $token = $this->tokenParaPapel(1);

        $this->getJson('/v2/admin/turmas', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertForbidden()
            ->assertJsonPath('erro', 'Acesso negado. Apenas administradores podem acessar este recurso.');
    }

    public function test_index_repassa_filtros(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminTurmaService::class);
        $service->shouldReceive('index')
            ->once()
            ->with(3, '2026-01-10', null, true)
            ->andReturn([
                'status' => 200,
                'body' => [
                    'dia' => ['id' => 18, 'data' => '2026-01-10'],
                    'turmas' => [['id' => 1, 'nome' => 'Turma A', 'checkin_bloqueado' => false]],
                ],
            ]);
        $this->app->instance(\App\Services\Admin\AdminTurmaService::class, $service);

        $this->getJson('/v2/admin/turmas?data=2026-01-10&apenas_ativas=true', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('turmas.0.nome', 'Turma A');
    }

    public function test_show_retorna_404_com_contrato_slim(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminTurmaService::class);
        $service->shouldReceive('show')
            ->once()
            ->with(99, 3)
            ->andReturn([
                'status' => 404,
                'body' => ['type' => 'error', 'message' => 'Turma não encontrada'],
            ]);
        $this->app->instance(\App\Services\Admin\AdminTurmaService::class, $service);

        $this->getJson('/v2/admin/turmas/99', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertNotFound()
            ->assertJsonPath('message', 'Turma não encontrada');
    }

    public function test_store_retorna_201(): void
    {
        $token = $this->tokenParaPapel(3);
        $payload = [
            'nome' => 'Turma A',
            'professor_id' => 1,
            'modalidade_id' => 1,
            'dia_id' => 18,
            'horario_inicio' => '06:00',
            'horario_fim' => '07:00',
        ];

        $service = Mockery::mock(\App\Services\Admin\AdminTurmaService::class);
        $service->shouldReceive('create')
            ->once()
            ->with(3, $payload)
            ->andReturn([
                'status' => 201,
                'body' => [
                    'type' => 'success',
                    'message' => 'Turma criada com sucesso',
                    'turma' => ['id' => 12],
                ],
            ]);
        $this->app->instance(\App\Services\Admin\AdminTurmaService::class, $service);

        $this->postJson('/v2/admin/turmas', $payload, [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertCreated()
            ->assertJsonPath('type', 'success')
            ->assertJsonPath('turma.id', 12);
    }

    public function test_rotas_estaticas_tem_precedencia_sobre_id(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminTurmaService::class);
        $service->shouldReceive('replicarPorDiasSemana')
            ->once()
            ->with(3, ['dia_id' => 17, 'periodo' => 'proxima_semana'])
            ->andReturn([
                'status' => 201,
                'body' => ['type' => 'success', 'message' => 'Replicação concluída com sucesso'],
            ]);
        $this->app->instance(\App\Services\Admin\AdminTurmaService::class, $service);

        $this->postJson('/v2/admin/turmas/replicar', [
            'dia_id' => 17,
            'periodo' => 'proxima_semana',
        ], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertCreated()
            ->assertJsonPath('type', 'success');
    }

    public function test_bloquear_checkin(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminTurmaService::class);
        $service->shouldReceive('alterarBloqueioCheckin')
            ->once()
            ->with(5, 3, 5, Mockery::type('array'), true, 'Feriado')
            ->andReturn([
                'status' => 200,
                'body' => [
                    'type' => 'success',
                    'message' => 'Check-in bloqueado para alunos nesta aula',
                    'checkin_bloqueado' => true,
                    'checkins_removidos' => 0,
                ],
            ]);
        $this->app->instance(\App\Services\Admin\AdminTurmaService::class, $service);

        $this->postJson('/v2/admin/turmas/5/bloquear-checkin', ['motivo' => 'Feriado'], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('checkin_bloqueado', true);
    }

    public function test_dias_admin_listagem(): void
    {
        $token = $this->tokenParaPapel(3);

        $diaService = Mockery::mock(\App\Services\Admin\AdminDiaService::class);
        $diaService->shouldReceive('index')
            ->once()
            ->andReturn([
                'status' => 200,
                'body' => ['dias' => [['id' => 1, 'data' => '2026-01-01']]],
            ]);
        $this->app->instance(\App\Services\Admin\AdminDiaService::class, $diaService);

        $this->getJson('/v2/admin/dias', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('dias.0.data', '2026-01-01');
    }

    public function test_shared_dias_horarios_requires_jwt(): void
    {
        $this->getJson('/v2/dias/1/horarios')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_shared_dias_horarios_por_data(): void
    {
        $token = $this->tokenParaPapel(3);

        $diaService = Mockery::mock(\App\Services\Admin\AdminDiaService::class);
        $diaService->shouldReceive('horariosPorData')
            ->once()
            ->with(3, '2026-01-20')
            ->andReturn([
                'status' => 200,
                'body' => [
                    'dia' => ['id' => 20, 'data' => '2026-01-20'],
                    'turmas' => [['id' => 1, 'nome' => 'Morning']],
                ],
            ]);
        $this->app->instance(\App\Services\Admin\AdminDiaService::class, $diaService);

        $this->getJson('/v2/dias/horarios?data=2026-01-20', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('turmas.0.nome', 'Morning');
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
