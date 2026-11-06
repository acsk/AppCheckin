<?php

namespace Tests\Fixtures;

use App\Models\Parametro;
use App\Repositories\AlunoRepository;
use App\Repositories\CheckinRepository;
use App\Repositories\MatriculaRepository;
use App\Repositories\TurmaRepository;
use App\Repositories\UsuarioRepository;
use App\Services\Comunicacao\PendenciaComunicacaoService;
use App\Services\Mobile\MobileCheckinService;
use App\Services\TurmaCheckinBloqueioService;
use App\Support\AcademyDateTime;
use App\Support\CheckinToleranciaAntes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HybridFixture
{
    public static function create(): void
    {
        Schema::create('status_matricula', function (Blueprint $t) {
            $t->increments('id');
            $t->string('codigo');
            $t->string('nome');
            $t->string('cor')->nullable();
            $t->boolean('permite_checkin');
            $t->boolean('ativo')->default(true);
        });
        Schema::create('planos', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('modalidade_id');
            $t->string('nome');
            $t->decimal('valor', 10, 2);
            $t->boolean('ativo')->default(true);
        });
        Schema::create('alunos', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('usuario_id');
            $t->string('nome');
        });
        Schema::create('matriculas', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('aluno_id');
            $t->integer('plano_id');
            $t->integer('status_id');
            $t->date('data_inicio');
            $t->date('data_vencimento');
            $t->date('proxima_data_vencimento')->nullable();
            $t->decimal('valor', 10, 2);
            $t->timestamps();
            $t->string('observacoes')->nullable();
        });
        Schema::create('dias', function (Blueprint $t) {
            $t->increments('id');
            $t->date('data')->unique();
            $t->boolean('ativo')->default(true);
        });
        Schema::create('turmas', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('dia_id');
            $t->integer('professor_id')->default(1);
            $t->integer('modalidade_id')->default(1);
            $t->string('nome')->default('Aula');
            $t->time('horario_inicio')->default('13:00:00');
            $t->time('horario_fim')->default('14:00:00');
            $t->integer('limite_alunos')->default(12);
            $t->integer('tolerancia_antes_minutos')->default(480);
            $t->integer('tolerancia_minutos')->default(10);
            $t->integer('tolerancia_antes_checkin_minutos')->nullable();
            $t->integer('tolerancia_cancelamento_minutos')->nullable();
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });
        Schema::create('checkins', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('turma_id');
            $t->integer('aluno_id');
            $t->timestamp('data_checkin')->useCurrent();
            $t->boolean('registrado_por_admin')->default(false);
            $t->unique(['tenant_id', 'turma_id', 'aluno_id']);
        });
        Schema::create('tipos_parametro', function (Blueprint $t) {
            $t->increments('id');
            $t->string('codigo')->unique();
            $t->string('nome');
            $t->integer('ordem');
            $t->boolean('ativo');
        });
        Schema::create('parametros', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tipo_parametro_id');
            $t->string('codigo')->unique();
            $t->string('nome');
            $t->string('descricao');
            $t->enum('tipo_valor', ['select', 'boolean']);
            $t->string('valor_padrao');
            $t->json('opcoes_select');
            $t->integer('ordem');
            $t->boolean('ativo');
            $t->boolean('visivel_tenant');
            $t->timestamps();
        });
        DB::table('status_matricula')->insert([
            ['id' => 1, 'codigo' => 'ativa', 'nome' => 'Ativa', 'permite_checkin' => 1],
            ['id' => 2, 'codigo' => 'pendente', 'nome' => 'Pendente', 'permite_checkin' => 0],
            ['id' => 3, 'codigo' => 'cancelada', 'nome' => 'Cancelada', 'permite_checkin' => 0],
        ]);
        DB::table('planos')->insert([
            ['id' => 1, 'tenant_id' => 3, 'modalidade_id' => 1, 'nome' => 'Plano A', 'valor' => 100],
            ['id' => 2, 'tenant_id' => 3, 'modalidade_id' => 1, 'nome' => 'Plano B', 'valor' => 150],
            ['id' => 3, 'tenant_id' => 4, 'modalidade_id' => 1, 'nome' => 'Outro tenant', 'valor' => 200],
        ]);
    }

    public static function migrate(): void
    {
        (require database_path('migrations/2026_11_06_100000_acesso_hibrido_aulas.php'))->up();
    }

    public static function seed(): void
    {
        DB::table('turmas_fixas')->insert([
            'id' => 1, 'tenant_id' => 3, 'nome' => 'Turma Aqua', 'modalidade_id' => 1, 'professor_id' => 1,
            'dias_semana' => '[1,3,5]', 'horario_inicio' => '13:00:00', 'horario_fim' => '14:00:00',
            'capacidade' => 12, 'checkin_livre' => true,
        ]);
        DB::table('turma_fixa_planos')->insert(['turma_fixa_id' => 1, 'plano_id' => 1]);
        DB::table('dias')->insert(['id' => 1, 'data' => AcademyDateTime::today()]);
        DB::table('turmas')->insert(['id' => 1, 'tenant_id' => 3, 'dia_id' => 1, 'turma_fixa_id' => 1]);
        for ($id = 1; $id <= 14; $id++) {
            DB::table('alunos')->insert(['id' => $id, 'usuario_id' => $id, 'nome' => "Aluno {$id}"]);
            DB::table('matriculas')->insert([
                'id' => $id, 'tenant_id' => 3, 'aluno_id' => $id, 'plano_id' => 1, 'status_id' => 1, 'valor' => 100,
                'data_inicio' => AcademyDateTime::today(), 'data_vencimento' => '2099-01-01',
                'tipo_acesso' => $id <= 10 ? 'turma_fixa' : 'horario_livre', 'turma_fixa_id' => $id <= 10 ? 1 : null,
            ]);
        }
    }

    public static function aula(int $id = 1): ?array
    {
        $aula = DB::table('turmas as t')->join('dias as d', 'd.id', '=', 't.dia_id')
            ->where('t.id', $id)->first(['t.*', 'd.data as dia_data']);

        return $aula ? ((array) $aula) + ['professor_nome' => 'Professor', 'modalidade_nome' => 'Natação'] : null;
    }

    public static function checkinService(bool $limiteDiarioReal = false): MobileCheckinService
    {
        $usuarios = \Mockery::mock(UsuarioRepository::class);
        $usuarios->shouldReceive('findById')->andReturn(['nome' => 'Aluno', 'email' => 'aluno@example.test']);
        $alunos = \Mockery::mock(AlunoRepository::class);
        $alunos->shouldReceive('findForTenant')->andReturnUsing(fn ($id) => ['id' => $id]);
        $alunos->shouldReceive('usuarioTemAcessoTenant')->andReturn(true);
        $matriculas = \Mockery::mock(MatriculaRepository::class);
        $matriculas->shouldReceive('atualizarStatusMatriculasVencidas', 'marcarPendenteSeLimiteCicloEsgotado');
        $matriculas->shouldReceive('findElegivelParaCheckin')->andReturnUsing(fn ($id) => (array) DB::table('matriculas')->where('id', $id)->first());
        $turmas = \Mockery::mock(TurmaRepository::class);
        $turmas->shouldReceive('findById')->andReturnUsing(fn ($id, $tenant) => $tenant === 3 ? self::aula($id) : null);
        $turmas->shouldReceive('contarCheckinsNaTurma')->andReturnUsing(fn () => DB::table('checkins')->count());
        $checkins = \Mockery::mock(CheckinRepository::class)->makePartial();
        $checkins->shouldReceive('usuarioTemCheckinNaTurma')->andReturnUsing(fn ($user, $turma) => DB::table('checkins')
            ->where('aluno_id', $user)->where('turma_id', $turma)->lockForUpdate()->exists());
        $checkins->shouldReceive('usuarioTemCheckinNoDiaNaModalidade')->andReturnUsing(function ($user, $dia, $modalidade) use ($limiteDiarioReal) {
            if (! $limiteDiarioReal) {
                return ['total' => 0];
            }
            $ids = DB::table('checkins as c')->join('turmas as t', 't.id', '=', 'c.turma_id')
                ->join('dias as d', 'd.id', '=', 't.dia_id')->where('c.aluno_id', $user)
                ->where('c.tenant_id', 3)->where('d.data', $dia)->where('t.modalidade_id', $modalidade)->pluck('c.id');

            return ['total' => $ids->count(), 'ultimo_checkin_id' => $ids->last()];
        });
        $checkins->shouldReceive('obterLimiteCheckinsPlano')->andReturn(['tem_plano' => true, 'limite' => 0]);
        $bloqueios = \Mockery::mock(TurmaCheckinBloqueioService::class);
        $bloqueios->shouldReceive('isBloqueada')->andReturn(false);
        $parametro = \Mockery::mock(Parametro::class);
        $parametro->shouldReceive('getInt')->andReturn(0);
        $comunicacoes = \Mockery::mock(PendenciaComunicacaoService::class);
        $comunicacoes->shouldReceive('verificarPermissaoCheckin')->andReturn(null);

        return new MobileCheckinService($usuarios, $alunos, $matriculas, $turmas, $checkins, $bloqueios,
            new CheckinToleranciaAntes($parametro), $comunicacoes);
    }

    public static function mysql(): void
    {
        if (getenv('HYBRID_MYSQL_TEST') !== '1' || ! getenv('HYBRID_MYSQL_PASSWORD')) {
            throw new \RuntimeException('MySQL híbrido exige configuração explícita de teste isolado.');
        }
        config(['database.default' => 'mysql', 'database.connections.mysql.host' => '127.0.0.1',
            'database.connections.mysql.database' => 'appcheckin_hybrid_test', 'database.connections.mysql.username' => 'root',
            'database.connections.mysql.password' => getenv('HYBRID_MYSQL_PASSWORD'), 'database.connections.mysql.port' => 3306]);
        DB::purge('mysql');
    }
}
