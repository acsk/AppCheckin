<?php

namespace Tests\Feature;

use App\Services\Bi\BiExportacaoService;
use App\Services\Bi\BiVisaoGeralService;
use App\Support\Papel;
use Mockery;
use Tests\TestCase;

/** Rotas do BI da academia: autenticação, permissão bi.ver e tenant vindo do token. */
class V2AdminBiRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function tokenParaPapel(int $papelId): string
    {
        config(['appcheckin.jwt_secret' => 'test-secret-key-with-enough-length-for-hs256-algorithm']);

        $usuarios = Mockery::mock(\App\Repositories\UsuarioRepository::class);
        $usuarios->shouldReceive('findAuthContext')->with(5)->andReturn([
            'id' => 5, 'nome' => 'Admin', 'email' => 'admin@example.com', 'tenant_id' => 3, 'papel_id' => $papelId,
        ]);
        $this->app->instance(\App\Repositories\UsuarioRepository::class, $usuarios);

        return app(\App\Services\JwtService::class)->encode(['user_id' => 5, 'email' => 'admin@example.com', 'tenant_id' => 3]);
    }

    public function test_exige_token(): void
    {
        $this->getJson('/v2/admin/bi/visao-geral')->assertUnauthorized();
    }

    public function test_aluno_e_comercial_nao_acessam(): void
    {
        foreach ([Papel::ALUNO, Papel::COMERCIAL] as $papel) {
            $this->getJson('/v2/admin/bi/visao-geral', ['Authorization' => 'Bearer '.$this->tokenParaPapel($papel)])->assertForbidden();
        }
    }

    public function test_usa_o_tenant_do_token_e_ignora_tenant_da_query(): void
    {
        $service = Mockery::mock(BiVisaoGeralService::class);
        $service->shouldReceive('visaoGeral')->once()->with(3, '3m')
            ->andReturn(['status' => 200, 'body' => ['success' => true, 'data' => ['periodo' => ['preset' => '3m']]]]);
        $this->app->instance(BiVisaoGeralService::class, $service);

        $this->getJson('/v2/admin/bi/visao-geral?periodo=3m&tenant_id=99', ['Authorization' => 'Bearer '.$this->tokenParaPapel(Papel::ADMIN)])
            ->assertOk()
            ->assertJsonPath('data.periodo.preset', '3m');
    }

    public function test_detalhe_usa_o_tenant_do_token(): void
    {
        $service = Mockery::mock(BiVisaoGeralService::class);
        $service->shouldReceive('detalhe')->once()->with(3, 'saidas', 'mes')
            ->andReturn(['status' => 200, 'body' => ['success' => true, 'data' => ['quantidade' => 0, 'itens' => []]]]);
        $this->app->instance(BiVisaoGeralService::class, $service);

        $this->getJson('/v2/admin/bi/visao-geral/detalhe?indicador=saidas&periodo=mes&tenant_id=99', ['Authorization' => 'Bearer '.$this->tokenParaPapel(Papel::ADMIN)])
            ->assertOk()
            ->assertJsonPath('data.quantidade', 0);
        $this->getJson('/v2/admin/bi/visao-geral/detalhe?indicador=saidas', ['Authorization' => 'Bearer '.$this->tokenParaPapel(Papel::COMERCIAL)])->assertForbidden();
    }

    public function test_financeiro_acessa(): void
    {
        $service = Mockery::mock(BiVisaoGeralService::class);
        $service->shouldReceive('visaoGeral')->once()->andReturn(['status' => 200, 'body' => ['success' => true, 'data' => []]]);
        $this->app->instance(BiVisaoGeralService::class, $service);

        $this->getJson('/v2/admin/bi/visao-geral', ['Authorization' => 'Bearer '.$this->tokenParaPapel(Papel::FINANCEIRO)])->assertOk();
    }

    public function test_exportar_devolve_csv_do_tenant_do_token(): void
    {
        $service = Mockery::mock(BiExportacaoService::class);
        $service->shouldReceive('exportar')->once()->with(3, 'saidas', null, 'mes')
            ->andReturn(['status' => 200, 'csv' => "\xEF\xBB\xBFAluno\nANA\n", 'arquivo' => 'bi-saidas.csv']);
        $this->app->instance(BiExportacaoService::class, $service);

        $r = $this->get('/v2/admin/bi/exportar?indicador=saidas&periodo=mes&tenant_id=99', ['Authorization' => 'Bearer '.$this->tokenParaPapel(Papel::ADMIN)]);
        $r->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="bi-saidas.csv"');
        $this->assertStringContainsString('ANA', $r->getContent());
        $this->getJson('/v2/admin/bi/exportar?indicador=saidas', ['Authorization' => 'Bearer '.$this->tokenParaPapel(Papel::COMERCIAL)])->assertForbidden();
        $this->getJson('/v2/admin/bi/retencao', ['Authorization' => 'Bearer '.$this->tokenParaPapel(Papel::COMERCIAL)])->assertForbidden();
    }

    public function test_bi_global_so_para_superadmin(): void
    {
        $this->getJson('/v2/superadmin/bi/global', ['Authorization' => 'Bearer '.$this->tokenParaPapel(Papel::ADMIN)])->assertForbidden();
    }
}
