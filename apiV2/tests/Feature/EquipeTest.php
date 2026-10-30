<?php

namespace Tests\Feature;

use App\Services\PermissaoService;
use App\Services\SuperAdmin\UnificacaoUsuarioService;
use App\Support\Papel;
use App\Support\Permissao as P;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CnpjCompartilhadoSchema;
use Tests\TestCase;

/**
 * Fase 2: equipe da academia, admin principal, limite do plano e unificação de cadastros.
 */
class EquipeTest extends TestCase
{
    use CnpjCompartilhadoSchema;

    private int $escola;

    private int $dono;

    private int $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->criarSchemaCnpj();
        $this->rodarMigrationCnpj();
        (require database_path('migrations/2026_10_22_160000_create_acessos_suporte_tables.php'))->up();
        (require database_path('migrations/2026_10_22_210000_create_motivos_acesso_suporte.php'))->up();
        (require database_path('migrations/2026_10_22_170000_create_tenant_usuario_permissoes.php'))->up();
        Schema::create('planos_sistema', function (Blueprint $t) {
            $t->increments('id');
            $t->string('nome');
            $t->integer('max_admins')->nullable();
        });
        Schema::create('tenant_planos_sistema', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('plano_sistema_id');
            $t->integer('status_id');
        });
        Schema::create('alunos', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('usuario_id')->unique();
            $t->string('nome');
            $t->string('cpf')->nullable();
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });
        Schema::create('matriculas', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('aluno_id');
            $t->integer('criado_por')->nullable();
        });
        Schema::create('professores', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('usuario_id')->nullable();
            $t->string('nome');
            $t->string('cpf')->nullable()->unique();
            $t->string('email')->nullable();
            $t->string('foto_url')->nullable();
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });
        Schema::create('tenant_professor', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('professor_id');
        });
        Schema::create('turmas', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('professor_id')->nullable();
        });

        $this->escola = $this->tenant('Escola A');
        $this->dono = $this->usuario($this->escola, Papel::ADMIN, 'Dono');
        $this->admin = $this->usuario($this->escola, Papel::ADMIN, 'Admin Dois');
        (require database_path('migrations/2026_10_22_190000_estrutura_permissoes.php'))->up();
        $this->assertSame($this->dono, app(PermissaoService::class)->adminPrincipalId($this->escola));
    }

    private function plano(?int $max): void
    {
        $id = DB::table('planos_sistema')->insertGetId(['nome' => 'Plano', 'max_admins' => $max]);
        DB::table('tenant_planos_sistema')->insert(['tenant_id' => $this->escola, 'plano_sistema_id' => $id, 'status_id' => 1]);
    }

    private function h(int $usuario): array
    {
        return $this->auth($usuario, $this->escola);
    }

    private function cpf(int $n): string
    {
        // CPFs válidos fixos para os testes
        return ['52998224725', '11144477735', '12345678909', '98765432100'][$n];
    }

    // ------------------------------------------------------------------ acesso

    public function test_so_quem_gerencia_equipe_acessa(): void
    {
        $membros = collect($this->getJson('/v2/admin/equipe', $this->h($this->dono))->assertOk()->assertJsonPath('limite.max', null)->json('membros'))->keyBy('id');
        $this->assertTrue($membros[$this->dono]['admin_principal']);
        $this->assertFalse($membros[$this->dono]['gerenciavel'], 'nem o principal altera a si mesmo');
        $this->assertTrue($membros[$this->admin]['gerenciavel']);
        $this->getJson('/v2/admin/equipe', $this->h($this->admin))->assertForbidden()->assertJsonPath('code', 'PERMISSAO_NEGADA');
    }

    // ------------------------------------------------------------------ adicionar

    public function test_adiciona_pessoa_nova_como_financeiro(): void
    {
        $r = $this->postJson('/v2/admin/equipe', ['nome' => 'Fina', 'email' => 'fina@x.com', 'cpf' => $this->cpf(0), 'senha' => 'segredo1', 'papeis' => [Papel::FINANCEIRO]], $this->h($this->dono))
            ->assertCreated()->assertJsonPath('reaproveitado', false);

        $id = $r->json('usuario_id');
        $this->assertSame([P::ALUNOS_VER, P::MATRICULAS_VER, P::FINANCEIRO_VER, P::FINANCEIRO_BAIXAR, P::BI_VER], app(PermissaoService::class)->efetivas($this->escola, $id));
    }

    public function test_professor_existente_vira_admin_no_mesmo_cadastro_e_sem_ocupar_vaga(): void
    {
        $this->plano(3); // dono + admin + professor = 3
        $prof = $this->usuario($this->escola, Papel::PROFESSOR, 'Prof');
        DB::table('usuarios')->where('id', $prof)->update(['cpf' => $this->cpf(1)]);
        $total = DB::table('usuarios')->count();

        // Mesmo CPF, outro e-mail: antes duplicava o cadastro
        $this->postJson('/v2/admin/equipe', ['cpf' => $this->cpf(1), 'email' => 'outro@x.com', 'papeis' => [Papel::ADMIN]], $this->h($this->dono))
            ->assertCreated()->assertJsonPath('reaproveitado', true)->assertJsonPath('usuario_id', $prof);

        $this->assertSame($total, DB::table('usuarios')->count());
        $this->assertContains(Papel::ADMIN, app(PermissaoService::class)->papeisAtivos($this->escola, $prof));
    }

    public function test_cpf_e_email_de_cadastros_diferentes_sao_recusados(): void
    {
        DB::table('usuarios')->where('id', $this->admin)->update(['cpf' => $this->cpf(2)]);
        $outro = $this->usuario(null, Papel::ALUNO, 'Outro');

        $this->postJson('/v2/admin/equipe', ['cpf' => $this->cpf(2), 'email' => DB::table('usuarios')->where('id', $outro)->value('email'), 'papeis' => [Papel::COMERCIAL]], $this->h($this->dono))
            ->assertStatus(409)->assertJsonPath('code', 'CADASTROS_DIFERENTES');
    }

    public function test_limite_do_plano_conta_toda_a_equipe(): void
    {
        $this->plano(2); // dono + admin já ocupam

        $this->postJson('/v2/admin/equipe', ['nome' => 'Novo', 'email' => 'novo@x.com', 'senha' => 'segredo1', 'papeis' => [Papel::COMERCIAL]], $this->h($this->dono))
            ->assertStatus(422)->assertJsonPath('code', 'LIMITE_EQUIPE');
        // Quem já é da equipe só ganha outro papel
        $this->postJson('/v2/admin/equipe', ['email' => DB::table('usuarios')->where('id', $this->admin)->value('email'), 'papeis' => [Papel::COMERCIAL]], $this->h($this->dono))
            ->assertCreated();
        $this->getJson('/v2/admin/equipe', $this->h($this->dono))->assertJsonPath('limite.usados', 2)->assertJsonPath('limite.disponiveis', 0);
    }

    // ------------------------------------------------------------------ admin principal e regras de edição

    public function test_admin_principal_nao_e_alterado_por_outros_admins_nem_alguem_altera_a_si(): void
    {
        DB::table('tenant_usuario_permissoes')->insert(['tenant_id' => $this->escola, 'usuario_id' => $this->admin, 'permissao' => P::EQUIPE_GERENCIAR, 'efeito' => 'conceder']);

        $this->putJson("/v2/admin/equipe/{$this->dono}", ['papeis' => [Papel::COMERCIAL]], $this->h($this->admin))
            ->assertForbidden()->assertJsonPath('code', 'ADMIN_PRINCIPAL');
        $this->deleteJson("/v2/admin/equipe/{$this->dono}", [], $this->h($this->admin))->assertForbidden();
        $this->putJson("/v2/admin/equipe/{$this->admin}", ['papeis' => [Papel::FINANCEIRO]], $this->h($this->admin))
            ->assertForbidden()->assertJsonPath('code', 'PROPRIO_ACESSO');
        $this->assertContains(Papel::ADMIN, app(PermissaoService::class)->papeisAtivos($this->escola, $this->dono));
    }

    public function test_so_concede_o_que_tem_e_nunca_o_sensivel(): void
    {
        $fin = $this->postJson('/v2/admin/equipe', ['nome' => 'Fina', 'email' => 'fina@x.com', 'senha' => 'segredo1', 'papeis' => [Papel::FINANCEIRO]], $this->h($this->dono))->json('usuario_id');

        $this->putJson("/v2/admin/equipe/{$fin}", ['conceder' => [P::FINANCEIRO_AVANCADO]], $this->h($this->dono))
            ->assertStatus(422)->assertJsonPath('code', 'PERMISSAO_INVALIDA');
        $this->putJson("/v2/admin/equipe/{$fin}", ['conceder' => [P::PROSPECCAO_USAR], 'retirar' => [P::FINANCEIRO_BAIXAR]], $this->h($this->dono))->assertOk();

        $p = app(PermissaoService::class)->efetivas($this->escola, $fin);
        $this->assertContains(P::PROSPECCAO_USAR, $p);
        $this->assertNotContains(P::FINANCEIRO_BAIXAR, $p);
    }

    public function test_remover_tira_todos_os_papeis_da_equipe_mas_mantem_aluno_e_perfil_de_professor(): void
    {
        $this->putJson("/v2/admin/equipe/{$this->admin}", ['papeis' => [Papel::ADMIN, Papel::PROFESSOR]], $this->h($this->dono))->assertOk();
        DB::table('tenant_usuario_papel')->insert(['tenant_id' => $this->escola, 'usuario_id' => $this->admin, 'papel_id' => Papel::ALUNO, 'ativo' => 1]);

        $this->deleteJson("/v2/admin/equipe/{$this->admin}", [], $this->h($this->dono))->assertOk();

        $this->assertSame([Papel::ALUNO], app(PermissaoService::class)->papeisAtivos($this->escola, $this->admin));
        $this->assertTrue(DB::table('professores')->where('usuario_id', $this->admin)->exists(), 'turmas antigas seguem com o professor');
    }

    // ------------------------------------------------------------------ professor como papel da equipe

    public function test_professor_e_papel_gerenciavel_e_ganha_perfil_para_as_aulas(): void
    {
        $this->getJson('/v2/admin/equipe', $this->h($this->dono))->assertJsonPath('papeis_gerenciaveis', [Papel::PROFESSOR, Papel::ADMIN, Papel::FINANCEIRO, Papel::COMERCIAL]);

        $id = $this->postJson('/v2/admin/equipe', ['nome' => 'Prof Novo', 'email' => 'prof@x.com', 'cpf' => $this->cpf(0), 'senha' => 'segredo1', 'papeis' => [Papel::PROFESSOR]], $this->h($this->dono))
            ->assertCreated()->json('usuario_id');

        $perfil = DB::table('professores')->where('usuario_id', $id)->first();
        $this->assertSame('PROF NOVO', $perfil->nome);
        // CPF é da pessoa (usuarios); o perfil de professor não guarda cópia
        $this->assertNull($perfil->cpf);
        $this->assertSame($this->cpf(0), DB::table('usuarios')->where('id', $id)->value('cpf'));
        $this->assertSame([], app(PermissaoService::class)->efetivas($this->escola, $id), 'professor sozinho não entra no painel');

        // Admin que passa a dar aula: mesmo cadastro, um perfil só
        $this->putJson("/v2/admin/equipe/{$this->admin}", ['papeis' => [Papel::ADMIN, Papel::PROFESSOR]], $this->h($this->dono))->assertOk();
        $this->putJson("/v2/admin/equipe/{$this->admin}", ['papeis' => [Papel::ADMIN, Papel::PROFESSOR]], $this->h($this->dono))->assertOk();
        $this->assertSame(1, DB::table('professores')->where('usuario_id', $this->admin)->count());
    }

    public function test_perfil_de_professor_reativa_o_existente_e_adota_o_legado_pelo_cpf(): void
    {
        $inativo = DB::table('professores')->insertGetId(['usuario_id' => $this->admin, 'nome' => 'Admin Dois', 'ativo' => 0]);
        $this->putJson("/v2/admin/equipe/{$this->admin}", ['papeis' => [Papel::PROFESSOR]], $this->h($this->dono))->assertOk();
        $this->assertSame(1, (int) DB::table('professores')->where('id', $inativo)->value('ativo'));

        $legado = DB::table('professores')->insertGetId(['usuario_id' => null, 'nome' => 'Legado', 'cpf' => $this->cpf(1)]);
        $novo = $this->postJson('/v2/admin/equipe', ['nome' => 'Legado', 'email' => 'legado@x.com', 'cpf' => $this->cpf(1), 'senha' => 'segredo1', 'papeis' => [Papel::PROFESSOR]], $this->h($this->dono))->json('usuario_id');
        $this->assertSame($novo, (int) DB::table('professores')->where('id', $legado)->value('usuario_id'));
        $this->assertSame(1, DB::table('professores')->where('cpf', $this->cpf(1))->count());
    }

    public function test_nao_deixa_a_pessoa_sem_papel(): void
    {
        $this->putJson("/v2/admin/equipe/{$this->admin}", ['papeis' => []], $this->h($this->dono))
            ->assertStatus(422)->assertJsonPath('code', 'PAPEL_OBRIGATORIO');
    }

    public function test_migracao_cria_perfil_para_quem_ja_e_professor(): void
    {
        $prof = $this->usuario($this->escola, Papel::PROFESSOR, 'Sem Perfil');

        (require database_path('migrations/2026_10_22_200000_professor_como_papel_da_equipe.php'))->up();

        $this->assertSame(1, DB::table('professores')->where('usuario_id', $prof)->count());
        $this->assertFalse(DB::table('professores')->where('usuario_id', $this->dono)->exists(), 'só quem tem o papel Professor');
        $this->assertTrue(Schema::hasIndex('professores', 'uk_professores_usuario_id'));
    }

    public function test_suporte_pode_alterar_o_principal(): void
    {
        $sa = $this->superAdmin();
        $token = $this->postJson("/v2/superadmin/academias/{$this->escola}/acessar", ['motivo_id' => $this->motivoAcesso(), 'detalhe' => 'Ajustar equipe a pedido'], $this->auth($sa, null))->json('token');

        $this->putJson("/v2/admin/equipe/{$this->dono}", ['papeis' => [Papel::ADMIN, Papel::FINANCEIRO]], ['Authorization' => 'Bearer '.$token])->assertOk();
    }

    // ------------------------------------------------------------------ super admin

    public function test_super_admin_define_principal_e_nao_desativa_o_principal(): void
    {
        $sa = $this->superAdmin();

        $this->deleteJson("/v2/superadmin/academias/{$this->escola}/admins/{$this->dono}", ['papeis' => [Papel::ADMIN]], $this->auth($sa, null))
            ->assertStatus(400);
        $this->postJson("/v2/superadmin/academias/{$this->escola}/admins/{$this->admin}/principal", [], $this->auth($sa, null))->assertOk();

        $this->assertSame($this->admin, app(PermissaoService::class)->adminPrincipalId($this->escola));
        $this->postJson("/v2/superadmin/academias/{$this->escola}/admins/{$this->dono}/principal", [], $this->h($this->dono))->assertForbidden();
    }

    // ------------------------------------------------------------------ unificação

    public function test_unifica_admin_e_professor_duplicados(): void
    {
        $prof = $this->usuario($this->escola, Papel::PROFESSOR, 'Dono Prof');
        DB::table('usuarios')->where('id', $prof)->update(['cpf' => $this->cpf(3)]);
        $alunoDono = DB::table('alunos')->insertGetId(['usuario_id' => $this->dono, 'nome' => 'Dono']);
        DB::table('matriculas')->insert(['aluno_id' => $alunoDono, 'criado_por' => $prof]);
        DB::table('alunos')->insert(['usuario_id' => $prof, 'nome' => 'Dono Prof']); // ficha vazia
        $profId = DB::table('professores')->insertGetId(['usuario_id' => $prof, 'nome' => 'Dono Prof']);
        DB::table('turmas')->insert(['professor_id' => $profId]);

        $previa = app(UnificacaoUsuarioService::class)->previa($prof, $this->dono);
        $this->assertTrue($previa['body']['pode_unificar']);
        $this->assertSame('descartar_ficha_vazia', $previa['body']['ficha_aluno']);

        $sa = $this->superAdmin();
        $this->postJson('/v2/superadmin/usuarios-unificar', ['origem' => $prof, 'destino' => $this->dono], $this->auth($sa, null))->assertOk();

        $papeis = app(PermissaoService::class)->papeisAtivos($this->escola, $this->dono);
        sort($papeis);
        $this->assertSame([Papel::PROFESSOR, Papel::ADMIN], $papeis);
        $this->assertSame($this->dono, (int) DB::table('professores')->where('id', $profId)->value('usuario_id'));
        $this->assertSame(1, DB::table('alunos')->count());
        $this->assertSame($this->dono, (int) DB::table('matriculas')->value('criado_por'));
        $origem = DB::table('usuarios')->find($prof);
        $this->assertSame(0, (int) $origem->ativo);
        $this->assertNull($origem->cpf, 'CPF liberado');
    }

    public function test_unificacao_bloqueada_quando_os_dois_tem_historico_de_aluno(): void
    {
        $outro = $this->usuario($this->escola, Papel::ALUNO, 'Outro');
        foreach ([$this->dono, $outro] as $u) {
            $a = DB::table('alunos')->insertGetId(['usuario_id' => $u, 'nome' => 'X']);
            DB::table('matriculas')->insert(['aluno_id' => $a]);
        }
        $sa = $this->superAdmin();

        $this->postJson('/v2/superadmin/usuarios-unificar', ['origem' => $outro, 'destino' => $this->dono], $this->auth($sa, null))
            ->assertStatus(422)->assertJsonPath('code', 'UNIFICACAO_BLOQUEADA');
        $this->assertSame(1, (int) DB::table('usuarios')->where('id', $outro)->value('ativo'));
    }
}
