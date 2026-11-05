<?php

namespace Tests\Feature;

use App\Support\PerfilAluno;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

/** Usuário 69 (Tiago): papel Aluno marcado sem registro em alunos não via os pacotes no app. */
class PerfilAlunoTest extends TestCase
{
    use RequiresSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireSqlite();

        Schema::create('usuarios', function (Blueprint $t) {
            $t->increments('id');
            $t->string('nome');
        });
        Schema::create('alunos', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('usuario_id');
            $t->string('nome')->default('');
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });
        Schema::create('tenant_usuario_papel', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('usuario_id');
            $t->integer('papel_id');
            $t->boolean('ativo')->default(true);
        });
        DB::table('usuarios')->insert([['id' => 69, 'nome' => 'TIAGO RENAN'], ['id' => 70, 'nome' => 'SO ADMIN']]);
    }

    public function test_papel_aluno_cria_o_registro_de_aluno(): void
    {
        PerfilAluno::garantirSeAluno(69, [3, 2, 1]);

        $this->assertSame('TIAGO RENAN', DB::table('alunos')->where('usuario_id', 69)->value('nome'));
    }

    public function test_sem_papel_aluno_nao_cria(): void
    {
        PerfilAluno::garantirSeAluno(70, [3, 2]);

        $this->assertFalse(DB::table('alunos')->where('usuario_id', 70)->exists());
    }

    public function test_registro_existente_e_reativado_sem_duplicar(): void
    {
        $id = DB::table('alunos')->insertGetId(['usuario_id' => 69, 'nome' => 'TIAGO RENAN', 'ativo' => 0]);

        $this->assertSame($id, PerfilAluno::garantir(69));
        $this->assertSame(1, DB::table('alunos')->where('usuario_id', 69)->count());
        $this->assertSame(1, (int) DB::table('alunos')->where('id', $id)->value('ativo'));
    }

    public function test_migration_cria_so_para_quem_tem_papel_aluno_sem_registro(): void
    {
        DB::table('tenant_usuario_papel')->insert([
            ['tenant_id' => 3, 'usuario_id' => 69, 'papel_id' => 1, 'ativo' => 1],
            ['tenant_id' => 3, 'usuario_id' => 69, 'papel_id' => 3, 'ativo' => 1],
            ['tenant_id' => 3, 'usuario_id' => 70, 'papel_id' => 3, 'ativo' => 1],
        ]);

        $migration = require database_path('migrations/2026_11_05_120000_alunos_para_papel_aluno_sem_registro.php');
        $migration->up();
        $migration->up(); // idempotente

        $this->assertSame(1, DB::table('alunos')->where('usuario_id', 69)->count());
        $this->assertFalse(DB::table('alunos')->where('usuario_id', 70)->exists());
    }
}
