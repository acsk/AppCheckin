<?php

namespace Tests\Feature;

use App\Services\Admin\AdminMatriculaService;
use App\Support\Papel;
use App\Support\Permissao;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Concerns\CnpjCompartilhadoSchema;
use Tests\TestCase;

/**
 * Permissões pontuais por usuário/academia (tenant_usuario_permissoes + middleware `permissao:`).
 */
class PermissaoTest extends TestCase
{
    use CnpjCompartilhadoSchema;

    private int $escola;

    private int $outraEscola;

    private int $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->criarSchemaCnpj();
        $this->rodarMigrationCnpj();
        (require database_path('migrations/2026_10_22_160000_create_acessos_suporte_tables.php'))->up();
        $this->escola = $this->tenant('Escola A');
        $this->outraEscola = $this->tenant('Escola B');
        $this->admin = $this->usuario($this->escola, Papel::ADMIN, 'Admin A');
        // Ocupa os ids 1–2 para o usuário 3 da migration existir de propósito abaixo
        (require database_path('migrations/2026_10_22_170000_create_tenant_usuario_permissoes.php'))->up();
        (require database_path('migrations/2026_10_22_190000_estrutura_permissoes.php'))->up();

        $service = Mockery::mock(AdminMatriculaService::class);
        $service->shouldReceive('destroy')->andReturn(['status' => 200, 'body' => ['message' => 'ok']]);
        $this->app->instance(AdminMatriculaService::class, $service);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function conceder(int $usuarioId, int $tenantId): void
    {
        DB::table('tenant_usuario_permissoes')->insert(['tenant_id' => $tenantId, 'usuario_id' => $usuarioId, 'permissao' => Permissao::FINANCEIRO_AVANCADO]);
    }

    public function test_admin_sem_permissao_e_barrado_na_api(): void
    {
        $this->deleteJson('/v2/admin/matriculas/10', [], $this->auth($this->admin, $this->escola))
            ->assertForbidden()->assertJsonPath('code', 'PERMISSAO_NEGADA');
        $this->putJson('/v2/admin/pagamentos-plano/10', [], $this->auth($this->admin, $this->escola))->assertForbidden();
        $this->deleteJson('/v2/admin/pagamentos-plano/10/excluir', [], $this->auth($this->admin, $this->escola))->assertForbidden();
        $this->postJson('/v2/admin/matriculas/10/vincular-atual', [], $this->auth($this->admin, $this->escola))->assertForbidden();
    }

    public function test_admin_com_permissao_passa_e_ve_a_permissao(): void
    {
        $this->conceder($this->admin, $this->escola);

        $this->deleteJson('/v2/admin/matriculas/10', [], $this->auth($this->admin, $this->escola))->assertOk();
        $this->assertContains(Permissao::FINANCEIRO_AVANCADO,
            $this->getJson('/v2/admin/minha-conta/permissoes', $this->auth($this->admin, $this->escola))->assertOk()->json('permissoes'));
    }

    public function test_permissao_vale_so_na_academia_concedida_e_enquanto_for_admin(): void
    {
        $this->conceder($this->admin, $this->escola);
        DB::table('tenant_usuario_papel')->insert(['tenant_id' => $this->outraEscola, 'usuario_id' => $this->admin, 'papel_id' => Papel::ADMIN, 'ativo' => 1]);

        $this->deleteJson('/v2/admin/matriculas/10', [], $this->auth($this->admin, $this->outraEscola))->assertForbidden();

        DB::table('tenant_usuario_papel')->where('tenant_id', $this->escola)->where('usuario_id', $this->admin)->update(['ativo' => 0]);
        DB::table('tenant_usuario_papel')->insert(['tenant_id' => $this->escola, 'usuario_id' => $this->admin, 'papel_id' => Papel::ALUNO, 'ativo' => 1]);
        $this->deleteJson('/v2/admin/matriculas/10', [], $this->auth($this->admin, $this->escola))->assertForbidden();
    }

    public function test_super_admin_no_acesso_de_suporte_tem_a_permissao(): void
    {
        $sa = $this->superAdmin();
        $token = $this->postJson("/v2/superadmin/academias/{$this->escola}/acessar", ['motivo' => 'Vincular matrícula da aluna'], $this->auth($sa, null))->json('token');

        $this->deleteJson('/v2/admin/matriculas/10', [], ['Authorization' => 'Bearer '.$token])->assertOk();
        $this->assertSame(Permissao::todas(),
            $this->getJson('/v2/admin/minha-conta/permissoes', ['Authorization' => 'Bearer '.$token])->json('permissoes'));
    }

    public function test_so_super_admin_concede_e_codigo_desconhecido_e_ignorado(): void
    {
        $sa = $this->superAdmin();
        $url = "/v2/superadmin/academias/{$this->escola}/admins/{$this->admin}";

        $this->putJson($url, ['permissoes' => [Permissao::FINANCEIRO_AVANCADO]], $this->auth($this->admin, $this->escola))->assertForbidden();
        $this->assertSame(0, DB::table('tenant_usuario_permissoes')->where('usuario_id', $this->admin)->count());

        $this->putJson($url, ['permissoes' => [Permissao::FINANCEIRO_AVANCADO, 'inventada']], $this->auth($sa, null))
            ->assertOk()->assertJsonPath('admin.permissoes', [Permissao::FINANCEIRO_AVANCADO]);
        $this->getJson("/v2/superadmin/academias/{$this->escola}/admins", $this->auth($sa, null))
            ->assertOk()->assertJsonPath('permissoes_disponiveis.0.codigo', Permissao::FINANCEIRO_AVANCADO);

        $this->putJson($url, ['permissoes' => []], $this->auth($sa, null))->assertOk();
        $this->assertSame(0, DB::table('tenant_usuario_permissoes')->where('usuario_id', $this->admin)->count());
    }
}
