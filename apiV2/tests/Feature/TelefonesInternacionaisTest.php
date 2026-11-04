<?php

namespace Tests\Feature;

use App\Http\Middleware\NormalizarTelefones;
use App\Support\Telefone;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

class TelefonesInternacionaisTest extends TestCase
{
    use RequiresSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireSqlite();
        Schema::create('usuarios', function (Blueprint $t) {
            $t->id();
            $t->string('telefone', 32)->nullable();
            $t->string('whatsapp', 32)->nullable();
        });
        (require database_path('migrations/2026_11_03_200000_create_telefone_normalizacoes_table.php'))->up();
    }

    public function test_http_normaliza_campos_e_responsavel_antes_de_gravar(): void
    {
        Route::post('/_test/telefones', function (Request $request) {
            DB::table('usuarios')->insert($request->only(['telefone', 'whatsapp']));

            return response()->json($request->all());
        })->middleware(NormalizarTelefones::class);

        $this->postJson('/_test/telefones', [
            'telefone' => '+49 162 3329708', 'whatsapp' => '(82) 99999-9999',
            'responsavel' => ['telefone' => '+351 912 345 678'],
        ])->assertOk()->assertJsonPath('telefone', '+491623329708')
            ->assertJsonPath('whatsapp', '+5582999999999')
            ->assertJsonPath('responsavel.telefone', '+351912345678');
        $this->assertDatabaseHas('usuarios', ['telefone' => '+491623329708']);
        $this->postJson('/_test/telefones', ['telefone' => '+49 12'])->assertUnprocessable()->assertJsonValidationErrors('telefone');
        $this->assertDatabaseCount('usuarios', 1);
    }

    public function test_auditoria_nao_altera_e_aplicacao_preserva_originais_e_e_idempotente(): void
    {
        DB::table('usuarios')->insert([
            ['id' => 1, 'telefone' => '+49 162 3329708', 'whatsapp' => null],
            ['id' => 2, 'telefone' => '82999999999', 'whatsapp' => '+351 912 345 678'],
            ['id' => 3, 'telefone' => '+49 12', 'whatsapp' => null],
        ]);
        $this->artisan('telefones:normalizar')->assertSuccessful();
        $this->assertDatabaseHas('usuarios', ['id' => 1, 'telefone' => '+49 162 3329708']);
        $this->assertDatabaseCount('telefone_normalizacoes', 0);

        $this->artisan('telefones:normalizar', ['--aplicar' => true])->assertSuccessful();
        $this->assertDatabaseHas('usuarios', ['id' => 1, 'telefone' => '+491623329708']);
        $this->assertDatabaseHas('usuarios', ['id' => 2, 'telefone' => '82999999999', 'whatsapp' => '+351912345678']);
        $this->assertDatabaseHas('usuarios', ['id' => 3, 'telefone' => '+49 12']);
        $this->assertDatabaseHas('telefone_normalizacoes', ['registro_id' => 1, 'valor_original' => '+49 162 3329708']);
        $this->assertDatabaseCount('telefone_normalizacoes', 2);
        $this->artisan('telefones:normalizar', ['--aplicar' => true])->assertSuccessful();
        $this->assertDatabaseCount('telefone_normalizacoes', 2);

        $this->artisan('telefones:normalizar', ['--aplicar' => true, '--pais-legado' => 'BR', '--tabela' => 'usuarios', '--id' => '2'])->assertSuccessful();
        $this->assertDatabaseHas('usuarios', ['id' => 2, 'telefone' => '+5582999999999']);
    }

    public function test_colisao_de_indice_unico_e_preservada_sem_perder_registros(): void
    {
        Schema::table('usuarios', fn (Blueprint $t) => $t->unique('telefone'));
        DB::table('usuarios')->insert([
            ['id' => 1, 'telefone' => '+49 162 3329708'],
            ['id' => 2, 'telefone' => '+491623329708'],
        ]);
        $this->artisan('telefones:normalizar', ['--aplicar' => true])->assertSuccessful();
        $this->assertDatabaseHas('usuarios', ['id' => 1, 'telefone' => '+49 162 3329708']);
        $this->assertDatabaseCount('telefone_normalizacoes', 0);
    }

    public function test_numero_compartilhado_sem_indice_unico_nao_e_unificado(): void
    {
        DB::table('usuarios')->insert([
            ['id' => 1, 'telefone' => '+49 162 3329708'],
            ['id' => 2, 'telefone' => '+491623329708'],
        ]);
        $this->artisan('telefones:normalizar', ['--aplicar' => true])->assertSuccessful();
        $this->assertSame(2, DB::table('usuarios')->where('telefone', '+491623329708')->count());
    }

    public function test_busca_encontra_numeros_normalizados_e_legados_com_mascara(): void
    {
        DB::table('usuarios')->insert([
            ['id' => 1, 'telefone' => '(82) 99999-9999'],
            ['id' => 2, 'telefone' => '+5582999999999'],
            ['id' => 3, 'telefone' => '+49 162 3329708'],
        ]);
        $query = DB::table('usuarios');
        Telefone::buscar($query, 'telefone', '+55 82 99999-9999', 'and');
        $this->assertSame([1, 2], $query->orderBy('id')->pluck('id')->all());
        $query = DB::table('usuarios');
        Telefone::buscar($query, 'telefone', '+491623329708', 'and');
        $this->assertSame([3], $query->pluck('id')->all());
    }

    public function test_migracao_preserva_indice_e_recusa_truncamento(): void
    {
        Schema::table('usuarios', fn (Blueprint $t) => $t->unique('telefone'));
        $migracao = require database_path('migrations/2026_11_03_201000_telefones_e164_varchar20.php');
        $migracao->up();
        $this->assertTrue(collect(Schema::getIndexes('usuarios'))->contains(fn ($i) => $i['unique'] && $i['columns'] === ['telefone']));
        DB::table('usuarios')->insert(['telefone' => str_repeat('1', 21)]);
        $this->expectException(\RuntimeException::class);
        $migracao->up();
    }
}
