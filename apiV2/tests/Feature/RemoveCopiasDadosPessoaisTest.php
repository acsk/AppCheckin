<?php

namespace Tests\Feature;

use App\Services\SuperAdmin\ConsolidacaoPessoasService;
use App\Support\Papel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CnpjCompartilhadoSchema;
use Tests\TestCase;

/**
 * Fase 3: a migration só apaga as cópias depois do consolidar, guarda o que apagou e o down() devolve.
 */
class RemoveCopiasDadosPessoaisTest extends TestCase
{
    use CnpjCompartilhadoSchema;

    private object $migration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->criarSchemaCnpj();
        (require database_path('migrations/2026_10_23_100000_dados_pessoais_em_usuarios.php'))->up();
        Schema::table('usuarios', function (Blueprint $t) {
            if (! Schema::hasColumn('usuarios', 'foto_base64')) {
                $t->longText('foto_base64')->nullable();
            }
            $t->integer('role_id_bkp')->nullable()->index();
            $t->string('reset_token', 100)->nullable();
            $t->dateTime('reset_token_expiry')->nullable();
        });
        Schema::create('alunos', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('usuario_id')->unique();
            $t->string('nome');
            foreach (['telefone', 'whatsapp', 'cpf', 'data_nascimento', 'sexo', 'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'estado', 'foto_url', 'foto_base64', 'foto_caminho'] as $c) {
                $t->string($c)->nullable();
            }
            $t->index('foto_caminho', 'idx_alunos_foto_caminho');
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });
        Schema::create('professores', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('usuario_id')->nullable()->unique();
            $t->string('nome');
            $t->string('cpf')->nullable()->unique();
            foreach (['email', 'telefone', 'whatsapp', 'data_nascimento', 'foto_url'] as $c) {
                $t->string($c)->nullable();
            }
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });
        $this->migration = require database_path('migrations/2026_10_23_110000_remove_copias_de_dados_pessoais.php');
    }

    private function pessoa(array $aluno, array $usuario = []): int
    {
        $id = $this->usuario($this->tenant('Escola '.uniqid()), Papel::ALUNO, 'Maria Souza');
        if ($usuario) {
            DB::table('usuarios')->where('id', $id)->update($usuario);
        }
        DB::table('alunos')->insert(['usuario_id' => $id, 'nome' => 'MARIA SOUZA'] + $aluno);

        return $id;
    }

    public function test_recusa_enquanto_ha_dados_so_nas_copias(): void
    {
        $this->pessoa(['whatsapp' => '82988887777']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('pessoas:consolidar --aplicar');
        $this->migration->up();
    }

    public function test_apaga_copias_guarda_backup_e_o_down_devolve(): void
    {
        $id = $this->pessoa(
            ['whatsapp' => '82988887777', 'telefone' => '8233334444', 'foto_caminho' => '/uploads/fotos/aluno_novo.jpg'],
            ['telefone' => '82955556666', 'foto_caminho' => '/uploads/fotos/usuario_slim.jpg', 'role_id_bkp' => 1],
        );
        DB::table('professores')->insert(['usuario_id' => $id, 'nome' => 'MARIA SOUZA', 'cpf' => '52998224725']);
        app(ConsolidacaoPessoasService::class)->aplicar(app(ConsolidacaoPessoasService::class)->analisar());

        $this->migration->up();

        foreach (['telefone', 'whatsapp', 'cpf', 'foto_caminho', 'foto_base64'] as $c) {
            $this->assertFalse(Schema::hasColumn('alunos', $c), "alunos.{$c}");
        }
        $this->assertFalse(Schema::hasColumn('professores', 'cpf'));
        $this->assertFalse(Schema::hasColumn('usuarios', 'role_id_bkp'));
        $this->assertTrue(Schema::hasColumn('alunos', 'nome'), 'o nome fica (espelho)');

        $u = DB::table('usuarios')->find($id);
        $this->assertSame('/uploads/fotos/aluno_novo.jpg', $u->foto_caminho, 'a foto do aluno é a mais nova');
        $this->assertSame('82955556666', $u->telefone, 'conflito: vale o de usuarios');
        $this->assertSame('82988887777', $u->whatsapp, 'copiado pelo consolidar');
        $this->assertSame('8233334444', DB::table('copias_dados_pessoais_removidas')
            ->where(['tabela' => 'alunos', 'campo' => 'telefone'])->value('valor'), 'o valor em conflito fica no backup');

        $this->migration->down();

        $this->assertSame('8233334444', DB::table('alunos')->where('usuario_id', $id)->value('telefone'));
        $this->assertSame('52998224725', DB::table('professores')->where('usuario_id', $id)->value('cpf'));
        $this->assertSame(1, (int) DB::table('usuarios')->where('id', $id)->value('role_id_bkp'));
        $this->assertFalse(Schema::hasTable('copias_dados_pessoais_removidas'));
    }
}
