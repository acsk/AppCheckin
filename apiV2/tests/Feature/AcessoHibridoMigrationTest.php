<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Fixtures\HybridFixture;
use Tests\TestCase;

class AcessoHibridoMigrationTest extends TestCase
{
    public function test_grade_nova_preserva_checkins_e_vinculo_antigo_sem_cortar_dias(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        HybridFixture::create();
        HybridFixture::migrate();
        HybridFixture::seed();
        DB::table('planos')->where('id', 1)->update(['checkins_semanais' => 2]);
        DB::table('checkins')->insert(['tenant_id' => 3, 'turma_id' => 1, 'aluno_id' => 1]);
        $antes = DB::table('checkins')->first();
        (require database_path('migrations/2026_11_06_140000_grade_individual_reposicoes.php'))->up();
        $this->assertEquals($antes, DB::table('checkins')->first());
        $this->assertEquals(1, DB::table('matriculas')->where('id', 1)->value('turma_fixa_id'));
        $this->assertEquals(100, DB::table('matriculas')->where('id', 1)->value('valor'));
        $this->assertEquals(1, DB::table('matriculas')->where('id', 1)->value('plano_id'));
        $this->assertSame(3, DB::table('matricula_grade')->where('matricula_id', 1)->count());
        $this->assertTrue((bool) DB::table('matriculas')->where('id', 1)->value('grade_revisao'));
    }

    public function test_migracao_preserva_registros_e_padrao_livre(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        HybridFixture::create();
        DB::table('dias')->insert(['id' => 1, 'data' => '2026-11-01']);
        DB::table('turmas')->insert(['id' => 1, 'tenant_id' => 3, 'dia_id' => 1]);
        DB::table('matriculas')->insert(['id' => 1, 'tenant_id' => 3, 'aluno_id' => 1, 'plano_id' => 1,
            'status_id' => 1, 'valor' => 75, 'data_inicio' => '2026-10-01', 'data_vencimento' => '2026-12-01']);
        HybridFixture::migrate();
        $m = DB::table('matriculas')->first();
        $this->assertSame('horario_livre', $m->tipo_acesso);
        $this->assertNull($m->turma_fixa_id);
        $this->assertEquals(75, $m->valor);
        $this->assertSame(1, $m->status_id);
        $this->assertEquals(1, DB::table('turmas')->value('checkin_livre'));
        $this->assertSame('horario_livre', DB::table('parametros')->where('codigo', 'tipo_acesso_padrao')->value('valor_padrao'));
        DB::table('parametros')->where('codigo', 'tipo_acesso_padrao')->update(['valor_padrao' => 'turma_fixa']);
        $this->assertSame('horario_livre', DB::table('matriculas')->value('tipo_acesso'));
    }
}
