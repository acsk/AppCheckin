<?php

namespace Tests\Feature;

use App\Services\AppConfigService;
use App\Services\Mobile\MobileCheckinAcessoService;
use App\Support\AcademyDateTime;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

class MobileCheckinAcessoTest extends TestCase
{
    use RequiresSqlite;

    private bool $exigir = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireSqlite();
        Schema::create('usuarios', function (Blueprint $t) {
            $t->integer('id');
            $t->boolean('ativo');
        });
        Schema::create('tenant_usuario_papel', function (Blueprint $t) {
            $t->integer('usuario_id');
            $t->integer('tenant_id');
            $t->integer('papel_id');
            $t->boolean('ativo');
        });
        Schema::create('alunos', function (Blueprint $t) {
            $t->integer('id');
            $t->integer('usuario_id');
        });
        Schema::create('planos', function (Blueprint $t) {
            $t->integer('id');
            $t->integer('tenant_id');
        });
        Schema::create('status_matricula', function (Blueprint $t) {
            $t->integer('id');
            $t->string('codigo');
        });
        Schema::create('matriculas', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('aluno_id');
            $t->integer('tenant_id');
            $t->integer('plano_id')->nullable();
            $t->integer('status_id');
            $t->date('data_vencimento')->nullable();
            $t->date('proxima_data_vencimento')->nullable();
        });
        DB::table('usuarios')->insert(['id' => 7, 'ativo' => true]);
        DB::table('tenant_usuario_papel')->insert(['usuario_id' => 7, 'tenant_id' => 1, 'papel_id' => 1, 'ativo' => true]);
        DB::table('alunos')->insert(['id' => 11, 'usuario_id' => 7]);
        DB::table('planos')->insert(['id' => 100, 'tenant_id' => 1]);
        foreach (['ativa', 'vencida', 'cancelada', 'pendente', 'bloqueada'] as $id => $codigo) {
            DB::table('status_matricula')->insert(['id' => $id + 1, 'codigo' => $codigo]);
        }
        $config = Mockery::mock(AppConfigService::class);
        $config->shouldReceive('parametro')->with('checkin_exigir_matricula_ativa', 1)->andReturnUsing(fn () => $this->exigir);
        $this->app->instance(AppConfigService::class, $config);
    }

    private function acesso(?int $tenantId = 1): bool
    {
        return app(MobileCheckinAcessoService::class)->consultar(7, $tenantId)['permitido'];
    }

    private function matricula(array $valores = []): void
    {
        DB::table('matriculas')->insert($valores + ['aluno_id' => 11, 'tenant_id' => 1, 'plano_id' => 100, 'status_id' => 1, 'data_vencimento' => AcademyDateTime::today()]);
    }

    public function test_aluno_sem_matricula_ou_sem_plano_nao_acessa(): void
    {
        $this->assertFalse($this->acesso());
        $this->matricula(['plano_id' => null]);
        $this->assertFalse($this->acesso());
        DB::table('matriculas')->update(['plano_id' => 999]);
        $this->assertFalse($this->acesso());
        DB::table('matriculas')->update(['plano_id' => 100]);
        $this->assertTrue($this->acesso());
    }

    public function test_matricula_precisa_ser_ativa_vigente_e_da_academia(): void
    {
        $this->matricula();
        foreach ([2, 3, 4, 5] as $status) {
            DB::table('matriculas')->update(['status_id' => $status]);
            $this->assertFalse($this->acesso());
        }
        DB::table('matriculas')->update(['status_id' => 1, 'data_vencimento' => '2000-01-01']);
        $this->assertFalse($this->acesso());
        DB::table('matriculas')->update(['proxima_data_vencimento' => '2099-01-01']);
        $this->assertTrue($this->acesso());
        DB::table('matriculas')->update(['tenant_id' => 2]);
        $this->assertFalse($this->acesso());
        DB::table('matriculas')->update(['tenant_id' => 1]);
        DB::table('planos')->update(['tenant_id' => 2]);
        $this->assertFalse($this->acesso());
    }

    public function test_parametro_desligado_libera_tela_sem_matricula(): void
    {
        $this->exigir = false;
        $this->assertTrue($this->acesso());
        DB::table('tenant_usuario_papel')->update(['ativo' => false]);
        $this->assertFalse($this->acesso());
        $this->assertFalse($this->acesso(null));
        $this->assertFalse($this->acesso(2));
    }

    public function test_equipe_nao_precisa_de_plano_para_acompanhar_turmas(): void
    {
        foreach ([2, 3, 4] as $papel) {
            DB::table('tenant_usuario_papel')->update(['papel_id' => $papel]);
            $this->assertTrue($this->acesso());
        }
    }

    public function test_endpoint_exige_autenticacao(): void
    {
        $this->getJson('/v2/mobile/checkin/acesso')->assertUnauthorized();
    }
}
