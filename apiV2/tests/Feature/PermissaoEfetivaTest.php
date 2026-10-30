<?php

namespace Tests\Feature;

use App\Repositories\TenantRepository;
use App\Services\PermissaoService;
use App\Support\Papel;
use App\Support\Permissao as P;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CnpjCompartilhadoSchema;
use Tests\TestCase;

/**
 * Estrutura de permissões (fase 1): padrão por papel, admin principal e ajustes individuais.
 */
class PermissaoEfetivaTest extends TestCase
{
    use CnpjCompartilhadoSchema;

    private int $escola;

    private int $dono;

    protected function setUp(): void
    {
        parent::setUp();
        $this->criarSchemaCnpj();
        $this->rodarMigrationCnpj();
        (require database_path('migrations/2026_10_22_170000_create_tenant_usuario_permissoes.php'))->up();
        $this->escola = $this->tenant('Escola A');
        $outroAdmin = $this->usuario($this->escola, Papel::ADMIN, 'Admin Antigo');
        $this->dono = $this->usuario($this->escola, Papel::ADMIN, 'Dono');
        DB::table('tenants')->where('id', $this->escola)->update(['responsavel_email' => DB::table('usuarios')->where('id', $this->dono)->value('email')]);
        (require database_path('migrations/2026_10_22_190000_estrutura_permissoes.php'))->up();
        $this->assertNotSame($outroAdmin, $this->principal(), 'principal pelo e-mail do responsável, não pelo mais antigo');
    }

    private function servico(): PermissaoService
    {
        return app(PermissaoService::class);
    }

    private function principal(): ?int
    {
        return $this->servico()->adminPrincipalId($this->escola);
    }

    private function efetivas(int $usuario): array
    {
        return $this->servico()->efetivas($this->escola, $usuario);
    }

    private function ajuste(int $usuario, string $permissao, string $efeito): void
    {
        DB::table('tenant_usuario_permissoes')->insert(['tenant_id' => $this->escola, 'usuario_id' => $usuario, 'permissao' => $permissao, 'efeito' => $efeito]);
    }

    public function test_migracao_cria_papel_financeiro_e_define_o_principal_pelo_responsavel(): void
    {
        $this->assertSame($this->dono, $this->principal());
        $this->assertTrue(DB::table('papeis')->where('id', Papel::FINANCEIRO)->exists());
        $this->assertTrue($this->servico()->ehAdminPrincipal($this->escola, $this->dono));
    }

    public function test_padrao_de_cada_papel(): void
    {
        $admin = $this->usuario($this->escola, Papel::ADMIN, 'Admin');
        $fin = $this->usuario($this->escola, Papel::FINANCEIRO, 'Financeiro');
        $com = $this->usuario($this->escola, Papel::COMERCIAL, 'Comercial');
        $prof = $this->usuario($this->escola, Papel::PROFESSOR, 'Professor');
        $aluno = $this->usuario($this->escola, Papel::ALUNO, 'Aluno');

        $this->assertContains(P::FINANCEIRO_BAIXAR, $this->efetivas($admin));
        $this->assertNotContains(P::EQUIPE_GERENCIAR, $this->efetivas($admin), 'equipe é do principal');
        $this->assertNotContains(P::CONTA_GERENCIAR, $this->efetivas($admin), 'plano e CNPJ são do principal');
        $this->assertSame([P::ALUNOS_VER, P::MATRICULAS_VER, P::FINANCEIRO_VER, P::FINANCEIRO_BAIXAR, P::BI_VER], $this->efetivas($fin));
        $this->assertContains(P::BI_VER, $this->efetivas($admin));
        $this->assertSame([P::PROSPECCAO_USAR], $this->efetivas($com), 'comercial sem valores financeiros');
        $this->assertSame([], $this->efetivas($prof), 'professor só no app');
        $this->assertSame([], $this->efetivas($aluno));
    }

    public function test_principal_tem_tudo_menos_o_sensivel_e_nao_perde_por_ajuste(): void
    {
        $this->ajuste($this->dono, P::FINANCEIRO_VER, PermissaoService::RETIRAR);

        $p = $this->efetivas($this->dono);
        $this->assertContains(P::EQUIPE_GERENCIAR, $p);
        $this->assertContains(P::CONTA_GERENCIAR, $p);
        $this->assertContains(P::FINANCEIRO_VER, $p, 'ninguém retira permissão do principal');
        $this->assertNotContains(P::FINANCEIRO_AVANCADO, $p, 'sensível só por concessão do super admin');
    }

    public function test_ajustes_individuais_concedem_e_retiram(): void
    {
        $admin = $this->usuario($this->escola, Papel::ADMIN, 'Admin');
        $this->ajuste($admin, P::FINANCEIRO_BAIXAR, PermissaoService::RETIRAR);
        $this->ajuste($admin, P::EQUIPE_GERENCIAR, PermissaoService::CONCEDER);
        $this->ajuste($admin, P::FINANCEIRO_AVANCADO, PermissaoService::CONCEDER);

        $p = $this->efetivas($admin);
        $this->assertNotContains(P::FINANCEIRO_BAIXAR, $p);
        $this->assertContains(P::EQUIPE_GERENCIAR, $p);
        $this->assertContains(P::FINANCEIRO_AVANCADO, $p);
    }

    public function test_quem_sai_da_equipe_perde_tudo_inclusive_concessoes(): void
    {
        $admin = $this->usuario($this->escola, Papel::ADMIN, 'Admin');
        $this->ajuste($admin, P::FINANCEIRO_AVANCADO, PermissaoService::CONCEDER);
        DB::table('tenant_usuario_papel')->where('usuario_id', $admin)->update(['ativo' => 0]);
        DB::table('tenant_usuario_papel')->insert(['tenant_id' => $this->escola, 'usuario_id' => $admin, 'papel_id' => Papel::ALUNO, 'ativo' => 1]);

        $this->assertSame([], $this->efetivas($admin));
    }

    public function test_tela_do_super_admin_so_mexe_nas_sensiveis(): void
    {
        $admin = $this->usuario($this->escola, Papel::ADMIN, 'Admin');
        $this->ajuste($admin, P::EQUIPE_GERENCIAR, PermissaoService::CONCEDER);
        $sensiveis = array_column($this->servico()->catalogo(true), 'codigo');

        $this->servico()->definirConcessoes($this->escola, $admin, [P::FINANCEIRO_AVANCADO, P::CONFIGURACOES_EDITAR], $sensiveis, null);
        $this->servico()->definirConcessoes($this->escola, $admin, [], $sensiveis, null);

        $this->assertSame([P::EQUIPE_GERENCIAR], $this->servico()->concedidas($this->escola, $admin));
    }

    public function test_editar_papeis_do_admin_nao_apaga_comercial_nem_financeiro(): void
    {
        $admin = $this->usuario($this->escola, Papel::ADMIN, 'Admin');
        DB::table('tenant_usuario_papel')->insert([
            ['tenant_id' => $this->escola, 'usuario_id' => $admin, 'papel_id' => Papel::COMERCIAL, 'ativo' => 1],
            ['tenant_id' => $this->escola, 'usuario_id' => $admin, 'papel_id' => Papel::FINANCEIRO, 'ativo' => 1],
        ]);

        app(TenantRepository::class)->substituirPapeis($this->escola, $admin, [Papel::ADMIN, Papel::PROFESSOR]);

        $papeis = $this->servico()->papeisAtivos($this->escola, $admin);
        sort($papeis);
        $this->assertSame([Papel::PROFESSOR, Papel::ADMIN, Papel::COMERCIAL, Papel::FINANCEIRO], $papeis);
    }

    public function test_endpoint_devolve_permissoes_papeis_e_principal(): void
    {
        $this->getJson('/v2/admin/minha-conta/permissoes', $this->auth($this->dono, $this->escola))
            ->assertOk()
            ->assertJsonPath('admin_principal', true)
            ->assertJsonPath('papeis', [Papel::ADMIN])
            ->assertJsonFragment([P::EQUIPE_GERENCIAR]);
    }

    public function test_financeiro_nao_vira_papel_principal_de_quem_e_admin(): void
    {
        $admin = $this->usuario($this->escola, Papel::ADMIN, 'Admin');
        DB::table('tenant_usuario_papel')->insert(['tenant_id' => $this->escola, 'usuario_id' => $admin, 'papel_id' => Papel::FINANCEIRO, 'ativo' => 1]);

        $this->assertSame(Papel::ADMIN, (int) app(\App\Repositories\UsuarioRepository::class)->findAuthContext($admin)['papel_id']);
    }
}
