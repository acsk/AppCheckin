<?php

namespace Tests\Feature;

use App\Models\Parametro;
use App\Repositories\AlunoRepository;
use App\Repositories\CheckinRepository;
use App\Repositories\MatriculaRepository;
use App\Repositories\TurmaRepository;
use App\Services\AcessoHibridoService;
use App\Services\Admin\AdminTurmaFixaService;
use App\Services\Mobile\MobileHorariosService;
use App\Services\Mobile\MobileProfessorService;
use App\Services\TurmaCheckinBloqueioService;
use App\Support\AcademyDateTime;
use App\Support\CheckinToleranciaAntes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Fixtures\HybridFixture;
use Tests\TestCase;

class GradeIndividualTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        HybridFixture::create();
        HybridFixture::migrate();
        HybridFixture::seed();
        DB::table('planos')->where('id', 1)->update(['checkins_semanais' => 2]);
        (require database_path('migrations/2026_11_06_140000_grade_individual_reposicoes.php'))->up();
        DB::table('turmas_fixas')->insert([
            'id' => 2, 'tenant_id' => 3, 'nome' => 'Quinta tarde', 'modalidade_id' => 1, 'professor_id' => 1,
            'dias_semana' => '[4,6]', 'horario_inicio' => '18:00', 'horario_fim' => '19:00', 'capacidade' => 12,
        ]);
        DB::table('turmas_fixas')->where('id', 1)->update(['dias_semana' => '[2,4]', 'horario_inicio' => '07:00', 'horario_fim' => '08:00']);
    }

    public function test_migracao_preserva_todos_os_dias_valores_e_historico_e_sinaliza_excesso(): void
    {
        $this->assertSame(30, DB::table('matricula_grade')->count());
        $this->assertEquals([1, 3, 5], DB::table('matricula_grade')->where('matricula_id', 1)->orderBy('dia_semana')->pluck('dia_semana')->all());
        $this->assertTrue((bool) DB::table('matriculas')->where('id', 1)->value('grade_revisao'));
        $this->assertEquals(100, DB::table('matriculas')->where('id', 1)->value('valor'));
        $this->assertEquals(1, DB::table('matriculas')->where('id', 1)->value('plano_id'));
        $this->assertEquals(1, DB::table('matriculas')->where('id', 1)->value('turma_fixa_id'));
    }

    public function test_dois_horarios_em_turmas_diferentes_e_dias_individuais(): void
    {
        $grade = [['turma_fixa_id' => 1, 'dia_semana' => 2], ['turma_fixa_id' => 2, 'dia_semana' => 4]];
        app(AdminTurmaFixaService::class)->vincularMatricula(3, 1, ['tipo_acesso' => 'turma_fixa', 'grade' => $grade]);
        $acesso = app(AcessoHibridoService::class);
        $this->assertCount(2, $acesso->grade(3, 1));
        $this->assertEquals(100, DB::table('matriculas')->where('id', 1)->value('valor'));
        $this->assertFalse((bool) DB::table('matriculas')->where('id', 1)->value('grade_revisao'));
        $aula = HybridFixture::aula();
        $aula['dia_data'] = AcademyDateTime::now()->modify('next Tuesday')->format('Y-m-d');
        $this->assertNotNull($acesso->matriculaParaAula(1, 3, $aula));
        $this->assertSame(1, $acesso->vagas($aula, 3)['vagas_fixas']);
        $aula['dia_data'] = (new \DateTimeImmutable($aula['dia_data']))->modify('+2 days')->format('Y-m-d');
        $this->assertNull($acesso->matriculaParaAula(1, 3, $aula));
        $this->assertSame(0, $acesso->vagas($aula, 3)['vagas_fixas']);
        $this->expectException(ValidationException::class);
        app(AdminTurmaFixaService::class)->vincularMatricula(3, 1, ['tipo_acesso' => 'turma_fixa',
            'grade' => [...$grade, ['turma_fixa_id' => 2, 'dia_semana' => 6]]]);
    }

    public function test_mesma_turma_admite_planos_diferentes_sem_pivot(): void
    {
        DB::table('turma_fixa_planos')->delete();
        DB::table('planos')->where('id', 2)->update(['checkins_semanais' => 1]);
        DB::table('matriculas')->where('id', 11)->update(['plano_id' => 2]);
        app(AdminTurmaFixaService::class)->vincularMatricula(3, 11, ['tipo_acesso' => 'turma_fixa',
            'grade' => [['turma_fixa_id' => 1, 'dia_semana' => 2]]]);
        $this->assertEquals(2, DB::table('matriculas')->where('id', 11)->value('plano_id'));
        $this->assertEquals(100, DB::table('matriculas')->where('id', 11)->value('valor'));
        $this->assertCount(1, app(AcessoHibridoService::class)->grade(3, 11));
        DB::table('planos')->where('id', 3)->update(['tenant_id' => 3, 'checkins_semanais' => 3]);
        DB::table('matriculas')->where('id', 12)->update(['plano_id' => 3]);
        app(AdminTurmaFixaService::class)->vincularMatricula(3, 12, ['tipo_acesso' => 'turma_fixa', 'grade' => [
            ['turma_fixa_id' => 1, 'dia_semana' => 2], ['turma_fixa_id' => 1, 'dia_semana' => 4], ['turma_fixa_id' => 2, 'dia_semana' => 6],
        ]]);
        $this->assertCount(3, app(AcessoHibridoService::class)->grade(3, 12));
    }

    public function test_capacidade_e_validada_por_dia_individual_da_turma(): void
    {
        DB::table('turmas_fixas')->where('id', 1)->update(['capacidade' => 1]);
        $service = app(AdminTurmaFixaService::class);
        $service->vincularMatricula(3, 1, ['tipo_acesso' => 'turma_fixa', 'grade' => [['turma_fixa_id' => 1, 'dia_semana' => 2]]]);
        $service->vincularMatricula(3, 11, ['tipo_acesso' => 'turma_fixa', 'grade' => [['turma_fixa_id' => 1, 'dia_semana' => 4]]]);
        $this->assertCount(1, app(AcessoHibridoService::class)->grade(3, 11));
        $this->expectException(ValidationException::class);
        $service->vincularMatricula(3, 12, ['tipo_acesso' => 'turma_fixa', 'grade' => [['turma_fixa_id' => 1, 'dia_semana' => 2]]]);
    }

    public function test_grade_recusa_formato_invalido_dias_e_vinculos_duplicados(): void
    {
        $antes = DB::table('matricula_grade')->get()->toJson();
        foreach ([null, ['dia' => ['turma_fixa_id' => 1, 'dia_semana' => 2]],
            [['turma_fixa_id' => 1, 'dia_semana' => 7]],
            [['turma_fixa_id' => 1, 'dia_semana' => 2], ['turma_fixa_id' => 1, 'dia_semana' => 2]],
            [['turma_fixa_id' => 99, 'dia_semana' => 2]]] as $grade) {
            try {
                app(AdminTurmaFixaService::class)->vincularMatricula(3, 1, ['tipo_acesso' => 'turma_fixa', 'grade' => $grade]);
                $this->fail('Grade inválida aceita.');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
            $this->assertSame($antes, DB::table('matricula_grade')->get()->toJson());
        }
    }

    public function test_reposicao_reserva_ocorrencia_e_autoriza_checkin_sem_alterar_grade(): void
    {
        Schema::table('checkins', function (Blueprint $t) {
            $t->timestamp('created_at')->useCurrent();
            $t->boolean('presente')->nullable();
        });
        Schema::create('tenant_usuario_papel', function (Blueprint $t) {
            $t->integer('usuario_id');
            $t->integer('tenant_id');
            $t->integer('papel_id');
            $t->boolean('ativo');
        });
        Schema::create('professores', function (Blueprint $t) {
            $t->integer('id');
            $t->integer('usuario_id');
        });
        DB::table('tenant_usuario_papel')->insert(['usuario_id' => 90, 'tenant_id' => 3, 'papel_id' => 2, 'ativo' => 1]);
        DB::table('professores')->insert(['id' => 1, 'usuario_id' => 90]);
        $turmas = \Mockery::mock(TurmaRepository::class);
        $turmas->shouldReceive('findById')->andReturnUsing(fn ($id, $tenant) => $tenant === 3 ? HybridFixture::aula($id) : null);
        $alunos = \Mockery::mock(AlunoRepository::class);
        $alunos->shouldReceive('findAlunoNoTenantPorId')->andReturnUsing(fn ($id, $tenant) => $tenant === 3 ? ['aluno_id' => $id, 'usuario_id' => $id] : null);
        $service = new MobileProfessorService($alunos, \Mockery::mock(MatriculaRepository::class), $turmas, \Mockery::mock(CheckinRepository::class));
        DB::table('turmas')->insert(['id' => 2, 'tenant_id' => 3, 'dia_id' => 1,
            'horario_inicio' => AcademyDateTime::now()->format('H:i:s'), 'horario_fim' => '23:59:59', 'checkin_livre' => false]);
        app(AdminTurmaFixaService::class)->vincularMatricula(3, 1, ['tipo_acesso' => 'turma_fixa', 'grade' => [
            ['turma_fixa_id' => 1, 'dia_semana' => 2], ['turma_fixa_id' => 2, 'dia_semana' => 4],
        ]]);
        $antes = DB::table('matricula_grade')->where('matricula_id', 1)->get()->toJson();
        $this->assertSame(201, $service->adicionarReposicao(90, 3, ['aluno_id' => 1, 'turma_id' => 2])['status']);
        $this->assertSame(200, $service->adicionarReposicao(90, 3, ['aluno_id' => 1, 'turma_id' => 2])['status']);
        $this->assertSame(1, DB::table('aula_reposicoes')->count());
        $this->assertSame(0, DB::table('checkins')->count());
        $this->assertSame(201, HybridFixture::checkinService()->registrar(1, 3, ['turma_id' => 2])['status']);
        $historico = app(CheckinRepository::class)->historicoCheckins(1, 10, 0);
        $this->assertSame('Reposição', $historico['checkins'][0]['tipo_presenca']);
        $this->assertSame($antes, DB::table('matricula_grade')->where('matricula_id', 1)->get()->toJson());
        $this->assertEquals(100, DB::table('matriculas')->where('id', 1)->value('valor'));
        $saldo = app(AcessoHibridoService::class)->vagas(HybridFixture::aula(2), 3);
        $this->assertSame(1, $saldo['reposicoes_confirmadas']);
        $this->assertSame(0, $saldo['vagas_livres_ocupadas']);
        DB::table('turmas')->where('id', 2)->update(['limite_alunos' => 1]);
        $this->assertSame(403, $service->adicionarReposicao(90, 3, ['aluno_id' => 2, 'turma_id' => 2])['status']);
        $this->assertSame(403, $service->adicionarReposicao(1, 3, ['aluno_id' => 2, 'turma_id' => 2])['status']);
        $sabado = AcademyDateTime::now()->modify('next Saturday')->format('Y-m-d');
        DB::table('dias')->insert(['id' => 2, 'data' => $sabado]);
        DB::table('turmas')->insert(['id' => 3, 'tenant_id' => 3, 'dia_id' => 2, 'turma_fixa_id' => 2,
            'horario_inicio' => '18:00', 'horario_fim' => '19:00', 'checkin_livre' => false]);
        $this->assertSame(201, $service->adicionarReposicao(90, 3, ['aluno_id' => 1, 'turma_id' => 3])['status']);
        $this->assertTrue(app(AcessoHibridoService::class)->matriculaParaAula(1, 3, HybridFixture::aula(3))['reposicao']);
        $this->assertSame($antes, DB::table('matricula_grade')->where('matricula_id', 1)->get()->toJson());
    }

    public function test_app_exibe_grade_e_proxima_aula_apenas_do_dia_selecionado(): void
    {
        DB::table('turmas')->where('id', 1)->update(['ativo' => false]);
        app(AdminTurmaFixaService::class)->vincularMatricula(3, 1, ['tipo_acesso' => 'turma_fixa', 'grade' => [
            ['turma_fixa_id' => 1, 'dia_semana' => 2], ['turma_fixa_id' => 2, 'dia_semana' => 4],
        ]]);
        $terca = AcademyDateTime::now()->modify('next Tuesday')->format('Y-m-d');
        DB::table('dias')->insert(['id' => 2, 'data' => $terca]);
        DB::table('turmas')->insert(['id' => 2, 'tenant_id' => 3, 'dia_id' => 2, 'turma_fixa_id' => 2,
            'horario_inicio' => '06:00', 'horario_fim' => '07:00']);
        DB::table('turmas')->insert(['id' => 3, 'tenant_id' => 3, 'dia_id' => 2, 'turma_fixa_id' => 1,
            'horario_inicio' => '07:00', 'horario_fim' => '08:00']);
        $service = new MobileHorariosService(\Mockery::mock(TurmaRepository::class),
            \Mockery::mock(TurmaCheckinBloqueioService::class),
            new CheckinToleranciaAntes(\Mockery::mock(Parametro::class)));
        $this->assertCount(2, $service->gradeAluno(3, 1));
        $this->assertEquals(3, $service->proximaAulaFixa(3, 1)['id']);
    }
}
