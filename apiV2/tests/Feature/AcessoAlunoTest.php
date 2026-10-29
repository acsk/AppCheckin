<?php

namespace Tests\Feature;

use App\Support\Papel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CnpjCompartilhadoSchema;
use Tests\TestCase;

/** "Ver app como aluno": admin/super admin abre o app como o aluno, só leitura. */
class AcessoAlunoTest extends TestCase
{
    use CnpjCompartilhadoSchema;

    private int $escola;

    private int $admin;

    private int $alunoUsuario;

    private int $alunoId;

    private const MOTIVO = 'Aluno relatou que o plano não aparece';

    protected function setUp(): void
    {
        parent::setUp();
        $this->criarSchemaCnpj();
        $this->rodarMigrationCnpj();
        (require database_path('migrations/2026_10_22_160000_create_acessos_suporte_tables.php'))->up();
        (require database_path('migrations/2026_10_22_210000_create_motivos_acesso_suporte.php'))->up();
        (require database_path('migrations/2026_11_02_100000_create_acessos_aluno_table.php'))->up();
        Schema::create('alunos', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('usuario_id');
        });
        $this->escola = $this->tenant('Escola A');
        $this->admin = $this->usuario($this->escola, Papel::ADMIN, 'Admin A');
        $this->alunoUsuario = $this->usuario($this->escola, Papel::ALUNO, 'Aluna');
        // Também professora: no acesso o app recebe só o papel de aluno
        DB::table('tenant_usuario_papel')->insert(['tenant_id' => $this->escola, 'usuario_id' => $this->alunoUsuario, 'papel_id' => Papel::PROFESSOR, 'ativo' => 1]);
        $this->alunoId = (int) DB::table('alunos')->insertGetId(['usuario_id' => $this->alunoUsuario]);
    }

    private function abrir(array $headers, string $motivo = self::MOTIVO, ?int $alunoId = null)
    {
        return $this->postJson('/v2/admin/acesso-aluno/'.($alunoId ?? $this->alunoId), ['motivo' => $motivo], $headers);
    }

    public function test_admin_abre_o_app_como_aluno_so_com_papel_de_aluno_e_registro(): void
    {
        $r = $this->abrir($this->auth($this->admin, $this->escola))->assertCreated()
            ->assertJsonPath('user.id', $this->alunoUsuario)
            ->assertJsonPath('user.papel_id', Papel::ALUNO)
            ->assertJsonPath('tenants.0.tenant.id', $this->escola)
            ->assertJsonPath('acesso.somente_leitura', true);
        $this->assertSame([Papel::ALUNO], array_column($r->json('tenants.0.papeis'), 'id'));
        $this->assertDatabaseHas('acessos_aluno', ['aluno_id' => $this->alunoId, 'operador_usuario_id' => $this->admin, 'motivo' => self::MOTIVO, 'encerrado_em' => null]);
    }

    public function test_token_do_acesso_le_mas_nao_escreve_e_encerrar_corta_na_hora(): void
    {
        $auth = ['Authorization' => 'Bearer '.$this->abrir($this->auth($this->admin, $this->escola))->json('token')];

        $this->getJson('/v2/auth/tenants', $auth)->assertOk();
        $this->postJson('/v2/auth/select-tenant', ['tenant_id' => $this->escola], $auth)
            ->assertForbidden()->assertJsonPath('code', 'MODO_VISUALIZACAO');

        $this->postJson('/v2/suporte/aluno/encerrar', [], $auth)->assertOk();
        $this->getJson('/v2/auth/tenants', $auth)->assertUnauthorized()->assertJsonPath('code', 'ACESSO_ALUNO_ENCERRADO');
    }

    public function test_acesso_cai_quando_o_operador_deixa_de_ser_admin(): void
    {
        $auth = ['Authorization' => 'Bearer '.$this->abrir($this->auth($this->admin, $this->escola))->json('token')];
        DB::table('tenant_usuario_papel')->where('usuario_id', $this->admin)->update(['ativo' => 0]);

        $this->getJson('/v2/auth/tenants', $auth)->assertUnauthorized()->assertJsonPath('code', 'ACESSO_ALUNO_ENCERRADO');
    }

    public function test_exige_motivo_admin_e_aluno_da_propria_academia(): void
    {
        $this->abrir($this->auth($this->admin, $this->escola), 'curto')->assertUnprocessable()->assertJsonPath('code', 'MOTIVO_OBRIGATORIO');

        $professor = $this->usuario($this->escola, Papel::PROFESSOR, 'Prof');
        $this->abrir($this->auth($professor, $this->escola))->assertForbidden();

        $outra = $this->tenant('Escola B');
        $adminB = $this->usuario($outra, Papel::ADMIN, 'Admin B');
        $this->abrir($this->auth($adminB, $outra))->assertNotFound()->assertJsonPath('code', 'ALUNO_NAO_ENCONTRADO');
    }

    public function test_super_admin_abre_pelo_acessar_como_academia_e_cai_junto_com_ele(): void
    {
        $sa = $this->superAdmin();
        $suporte = $this->postJson("/v2/superadmin/academias/{$this->escola}/acessar", ['motivo_id' => $this->motivoAcesso(), 'detalhe' => 'Conferir o app da aluna'], $this->auth($sa, null))->json('token');
        $auth = ['Authorization' => 'Bearer '.$this->abrir(['Authorization' => 'Bearer '.$suporte])->assertCreated()->json('token')];
        $this->getJson('/v2/auth/tenants', $auth)->assertOk();

        $this->postJson('/v2/suporte/acesso/encerrar', [], ['Authorization' => 'Bearer '.$suporte])->assertOk();
        $this->getJson('/v2/auth/tenants', $auth)->assertUnauthorized()->assertJsonPath('code', 'ACESSO_ALUNO_ENCERRADO');
    }
}
