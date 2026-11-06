<?php

namespace Tests\Feature;

use Database\Seeders\Tenant5TurmasFixasAlunosSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Concerns\CnpjCompartilhadoSchema;
use Tests\TestCase;

class Tenant5TurmasFixasAlunosSeederTest extends TestCase
{
    use CnpjCompartilhadoSchema;

    private string|false $senhaAnterior;

    protected function setUp(): void
    {
        parent::setUp();
        $this->criarSchemaCnpj();
        Schema::create('alunos', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('usuario_id')->unique();
            $table->string('nome');
            $table->boolean('ativo')->default(true);
        });
        DB::table('tenants')->insert(['id' => 5, 'nome' => 'Tenant de teste', 'ativo' => 1]);
        $this->senhaAnterior = getenv('TURMAS_FIXAS_TEST_PASSWORD');
        putenv('TURMAS_FIXAS_TEST_PASSWORD='.bin2hex(random_bytes(16)));
    }

    protected function tearDown(): void
    {
        putenv($this->senhaAnterior === false ? 'TURMAS_FIXAS_TEST_PASSWORD' : 'TURMAS_FIXAS_TEST_PASSWORD='.$this->senhaAnterior);
        parent::tearDown();
    }

    public function test_cria_tres_alunos_no_tenant_correto_e_reexecucao_preserva_senhas(): void
    {
        $seeder = app(Tenant5TurmasFixasAlunosSeeder::class);
        $seeder->run();
        $usuarios = DB::table('usuarios')->orderBy('id')->get()->all();
        $this->assertCount(3, $usuarios);
        $this->assertSame(3, DB::table('alunos')->count());
        $this->assertSame(3, DB::table('tenant_usuario_papel')->where('tenant_id', 5)->where('papel_id', 1)->where('ativo', 1)->count());
        $this->assertSame(0, DB::table('tenant_usuario_papel')->where('tenant_id', '<>', 5)->count());
        $this->assertTrue(password_verify((string) getenv('TURMAS_FIXAS_TEST_PASSWORD'), $usuarios[0]->senha_hash));
        $this->assertNull($usuarios[0]->cpf);
        $this->assertNull($usuarios[0]->telefone);
        putenv('TURMAS_FIXAS_TEST_PASSWORD='.bin2hex(random_bytes(16)));
        $seeder->run();
        $this->assertEquals($usuarios, DB::table('usuarios')->orderBy('id')->get()->all());
        $this->assertSame(3, DB::table('alunos')->count());
        $this->assertSame(3, DB::table('tenant_usuario_papel')->count());
    }

    public function test_tenant_inexistente_nao_cria_cadastros(): void
    {
        DB::table('tenants')->where('id', 5)->delete();
        try {
            app(Tenant5TurmasFixasAlunosSeeder::class)->run();
            $this->fail('Deveria recusar tenant inexistente.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('tenant 5', $e->getMessage());
        }
        $this->assertSame(0, DB::table('usuarios')->count());
    }

    public function test_colisao_de_email_desfaz_todos_os_novos_cadastros(): void
    {
        DB::table('usuarios')->insert(['nome' => 'CADASTRO EXISTENTE', 'email' => 'appcheckin-tenant5-turmas-02@example.invalid']);
        try {
            app(Tenant5TurmasFixasAlunosSeeder::class)->run();
            $this->fail('Deveria recusar colisão.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Nenhum cadastro será sobrescrito', $e->getMessage());
        }
        $this->assertSame(1, DB::table('usuarios')->count());
        $this->assertSame('CADASTRO EXISTENTE', DB::table('usuarios')->value('nome'));
        $this->assertSame(0, DB::table('alunos')->count());
        $this->assertSame(0, DB::table('tenant_usuario_papel')->count());
    }

    public function test_senha_nao_configurada_impede_execucao(): void
    {
        putenv('TURMAS_FIXAS_TEST_PASSWORD');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TURMAS_FIXAS_TEST_PASSWORD');
        app(Tenant5TurmasFixasAlunosSeeder::class)->run();
    }
}
