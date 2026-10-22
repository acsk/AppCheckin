<?php

namespace Tests\Feature;

use App\Services\JwtService;
use App\Support\Papel;
use Database\Seeders\MotivoAcessoSuporteSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CnpjCompartilhadoSchema;
use Tests\TestCase;

/**
 * "Acessar como academia" pelo super admin (SQLite).
 */
class AcessoSuporteTest extends TestCase
{
    use CnpjCompartilhadoSchema;

    private int $escolaA;

    private int $escolaB;

    private int $adminA;

    private int $sa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->criarSchemaCnpj();
        $this->rodarMigrationCnpj();
        (require database_path('migrations/2026_10_22_160000_create_acessos_suporte_tables.php'))->up();
        (require database_path('migrations/2026_10_22_210000_create_motivos_acesso_suporte.php'))->up();
        $this->escolaA = $this->tenant('Escola A');
        $this->escolaB = $this->tenant('Escola B');
        $this->adminA = $this->usuario($this->escolaA, Papel::ADMIN, 'Admin A');
        $this->sa = $this->superAdmin();
        // Admin A vira o admin principal (só ele vê os acessos do suporte)
        (require database_path('migrations/2026_10_22_170000_create_tenant_usuario_permissoes.php'))->up();
        (require database_path('migrations/2026_10_22_190000_estrutura_permissoes.php'))->up();
    }

    private function iniciar(int $tenantId, string $motivo = 'Ajuste de plano ou cobrança a pedido da academia', ?string $detalhe = null): TestResponse
    {
        return $this->postJson("/v2/superadmin/academias/{$tenantId}/acessar", ['motivo_id' => $this->motivoAcesso($motivo), 'detalhe' => $detalhe], $this->auth($this->sa, null));
    }

    /** @return array<string, string> */
    private function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    public function test_so_super_admin_inicia_e_motivo_e_obrigatorio(): void
    {
        $this->postJson("/v2/superadmin/academias/{$this->escolaA}/acessar", ['motivo_id' => $this->motivoAcesso()], $this->auth($this->adminA, $this->escolaA))
            ->assertForbidden();
        $this->postJson("/v2/superadmin/academias/{$this->escolaA}/acessar", ['detalhe' => 'Sem motivo escolhido'], $this->auth($this->sa, null))
            ->assertStatus(422)->assertJsonPath('code', 'MOTIVO_OBRIGATORIO');
        DB::table('motivos_acesso_suporte')->where('nome', 'Configuração inicial da academia')->update(['ativo' => 0]);
        $this->iniciar($this->escolaA, 'Configuração inicial da academia')->assertStatus(422)->assertJsonPath('code', 'MOTIVO_OBRIGATORIO');
        // "Outro" exige o detalhe
        $this->iniciar($this->escolaA, 'Outro', 'curto')->assertStatus(422)->assertJsonPath('code', 'DETALHE_OBRIGATORIO');
        $this->iniciar(1)->assertStatus(422);
        $this->assertSame(0, DB::table('acessos_suporte')->count());
    }

    public function test_motivos_vem_da_tabela_e_cada_acesso_guarda_so_a_referencia(): void
    {
        $motivos = $this->getJson('/v2/superadmin/academias/acesso-motivos', $this->auth($this->sa, null))->assertOk()->json('motivos');
        $this->assertSame('Outro', end($motivos)['nome']);
        $this->assertTrue(end($motivos)['exige_detalhe']);
        $this->getJson('/v2/superadmin/academias/acesso-motivos', $this->auth($this->adminA, $this->escolaA))->assertForbidden();

        $this->iniciar($this->escolaA)->assertCreated();
        $this->iniciar($this->escolaB, 'Outro', 'Conferir importação de alunos')->assertCreated();

        $this->assertSame(1, DB::table('acessos_suporte')->where('motivo_id', $this->motivoAcesso())->count());
        $this->assertNull(DB::table('acessos_suporte')->where('tenant_id', $this->escolaA)->value('detalhe'));
        $this->assertSame('Conferir importação de alunos', DB::table('acessos_suporte')->where('tenant_id', $this->escolaB)->value('detalhe'));
    }

    public function test_migracao_converte_motivo_em_texto_livre_para_outro_com_detalhe(): void
    {
        $migracao = require database_path('migrations/2026_10_22_210000_create_motivos_acesso_suporte.php');
        $migracao->down();
        DB::table('acessos_suporte')->insert([
            'tenant_id' => $this->escolaA, 'usuario_id' => $this->sa, 'motivo' => 'Texto livre antigo do suporte',
            'iniciado_em' => now(), 'expira_em' => now()->addHour(),
        ]);

        $migracao->up();

        $a = DB::table('acessos_suporte')->first();
        $this->assertSame($this->motivoAcesso('Outro'), (int) $a->motivo_id);
        $this->assertSame('Texto livre antigo do suporte', $a->detalhe);
        $this->assertFalse(Schema::hasColumn('acessos_suporte', 'motivo'));
    }

    public function test_seeder_e_idempotente(): void
    {
        $total = DB::table('motivos_acesso_suporte')->count();
        (new MotivoAcessoSuporteSeeder)->run();
        $this->assertSame($total, DB::table('motivos_acesso_suporte')->count());
    }

    public function test_acesso_age_como_admin_so_da_academia_escolhida(): void
    {
        $token = $this->iniciar($this->escolaA)->assertCreated()->assertJsonPath('academia.nome', 'Escola A')->json('token');

        $this->getJson('/v2/admin/minha-conta/escola', $this->bearer($token))
            ->assertOk()->assertJsonPath('escola.nome', 'Escola A');
        // O token não dá poderes de super admin
        $this->getJson('/v2/superadmin/cnpj', $this->bearer($token))->assertForbidden();
    }

    public function test_token_nao_serve_para_outra_academia(): void
    {
        $acessoId = $this->iniciar($this->escolaA)->json('acesso.id');
        // Mesmo assinado, um token com o acesso de A apontando para B é recusado
        $forjado = app(JwtService::class)->encode(['user_id' => $this->sa, 'email' => 'x', 'tenant_id' => $this->escolaB, 'acesso_suporte_id' => $acessoId]);

        $this->getJson('/v2/admin/minha-conta/escola', $this->bearer($forjado))
            ->assertUnauthorized()->assertJsonPath('code', 'ACESSO_SUPORTE_ENCERRADO');
    }

    public function test_alteracoes_ficam_registradas_e_a_academia_ve_o_acesso(): void
    {
        $token = $this->iniciar($this->escolaA)->json('token');

        $this->putJson('/v2/admin/minha-conta/escola', ['nome' => 'Escola A Centro', 'email' => 'a@a.com'], $this->bearer($token))->assertOk();

        $this->getJson('/v2/admin/minha-conta/acessos-suporte', $this->auth($this->adminA, $this->escolaA))
            ->assertOk()
            ->assertJsonPath('acessos.0.usuario_nome', 'Super')
            ->assertJsonPath('acessos.0.motivo', 'Ajuste de plano ou cobrança a pedido da academia')
            ->assertJsonPath('acessos.0.detalhe', null)
            ->assertJsonPath('acessos.0.alteracoes', 1)
            ->assertJsonPath('acessos.0.em_andamento', true);
        $this->assertSame('/v2/admin/minha-conta/escola', DB::table('acessos_suporte_acoes')->value('rota'));
    }

    public function test_encerrar_corta_o_acesso_na_hora(): void
    {
        $token = $this->iniciar($this->escolaA)->json('token');

        $this->postJson('/v2/suporte/acesso/encerrar', [], $this->bearer($token))->assertOk();
        $this->assertSame(0, DB::table('acessos_suporte_acoes')->count(), 'encerrar não conta como alteração');

        $this->getJson('/v2/admin/minha-conta/escola', $this->bearer($token))
            ->assertUnauthorized()->assertJsonPath('code', 'ACESSO_SUPORTE_ENCERRADO');
        $this->getJson('/v2/admin/minha-conta/acessos-suporte', $this->auth($this->adminA, $this->escolaA))
            ->assertJsonPath('acessos.0.em_andamento', false);
    }

    public function test_acesso_expirado_ou_de_quem_deixou_de_ser_super_admin_e_recusado(): void
    {
        $token = $this->iniciar($this->escolaA)->json('token');
        DB::table('acessos_suporte')->update(['expira_em' => now()->subMinute()]);
        $this->getJson('/v2/admin/minha-conta/escola', $this->bearer($token))->assertUnauthorized();

        $token = $this->iniciar($this->escolaA)->json('token');
        DB::table('tenant_usuario_papel')->where('usuario_id', $this->sa)->update(['ativo' => 0]);
        $this->getJson('/v2/admin/minha-conta/escola', $this->bearer($token))->assertUnauthorized();
    }

    public function test_novo_acesso_encerra_o_anterior(): void
    {
        $tokenA = $this->iniciar($this->escolaA)->json('token');
        $this->iniciar($this->escolaB)->assertCreated();

        $this->getJson('/v2/admin/minha-conta/escola', $this->bearer($tokenA))->assertUnauthorized();
    }

    public function test_perfil_e_senha_ficam_bloqueados_no_acesso(): void
    {
        $token = $this->iniciar($this->escolaA)->json('token');

        $this->putJson('/v2/admin/minha-conta/perfil', ['nome' => 'X', 'email' => 'x@x.com'], $this->bearer($token))->assertForbidden();
        $this->putJson('/v2/admin/minha-conta/senha', ['senha_atual' => 'senha-atual', 'nova_senha' => 'nova123', 'confirmacao_senha' => 'nova123'], $this->bearer($token))->assertForbidden();
    }
}
