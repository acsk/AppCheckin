<?php

namespace Tests\Unit;

use App\Support\SexoAluno;
use Database\Seeders\AlunoSexoSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

class SexoAlunoTest extends TestCase
{
    use RequiresSqlite;

    protected function tearDown(): void
    {
        SexoAluno::resetCache();
        parent::tearDown();
    }

    public function test_normaliza_valor_e_rotulo(): void
    {
        $this->assertSame('feminino', SexoAluno::normalizar('feminino'));
        $this->assertSame('masculino', SexoAluno::normalizar(' Masculino '));
        $this->assertSame('prefiro_nao_informar', SexoAluno::normalizar('Prefiro não informar'));
        $this->assertSame('outro', SexoAluno::normalizar('OUTRO'));
        $this->assertNull(SexoAluno::normalizar('f'));
        $this->assertNull(SexoAluno::normalizar(null));
    }

    public function test_lista_do_seed_tem_59_nomes_sem_ids_repetidos(): void
    {
        $lista = AlunoSexoSeeder::lista();
        $this->assertCount(59, $lista);
        $this->assertSame([355, 'CAROLINNE RODRIGUES DOS SANTOS'], $lista[0]);
        $this->assertCount(59, array_unique(array_column($lista, 0)));
    }

    public function test_compara_nomes_sem_acento_e_caixa(): void
    {
        $this->assertTrue(AlunoSexoSeeder::mesmoNome('Leticia  da Silva Nunes', 'LETÍCIA DA SILVA NUNES'));
        $this->assertFalse(AlunoSexoSeeder::mesmoNome('LETÍCIA NUNES', 'LETÍCIA DA SILVA NUNES'));
    }

    public function test_seed_marca_lista_como_feminino_e_restante_do_tenant_como_masculino(): void
    {
        $this->requireSqlite();
        DB::statement('CREATE TABLE alunos (id INTEGER PRIMARY KEY, usuario_id INTEGER, nome TEXT, sexo TEXT NULL)');
        DB::statement('CREATE TABLE tenant_usuario_papel (id INTEGER PRIMARY KEY, tenant_id INTEGER, usuario_id INTEGER, papel_id INTEGER)');
        SexoAluno::resetCache();

        DB::table('alunos')->insert([
            ['id' => 355, 'usuario_id' => 900, 'nome' => 'Carolinne Rodrigues dos Santos', 'sexo' => null], // id confere
            ['id' => 50, 'usuario_id' => 350, 'nome' => 'SARAH BARBOSA', 'sexo' => null],                  // confere pelo usuario_id
            ['id' => 347, 'usuario_id' => 901, 'nome' => 'OUTRA PESSOA', 'sexo' => null],                   // id existe, nome não bate
            ['id' => 60, 'usuario_id' => 902, 'nome' => 'JOÃO SILVA', 'sexo' => null],                      // restante do tenant
            ['id' => 61, 'usuario_id' => 903, 'nome' => 'ALEX', 'sexo' => 'outro'],                         // já informado: mantém
            ['id' => 62, 'usuario_id' => 904, 'nome' => 'DE OUTRO TENANT', 'sexo' => null],                 // outro tenant: mantém
        ]);
        foreach ([[1, 900], [1, 350], [1, 901], [1, 902], [1, 903], [2, 904]] as [$tenant, $usuario]) {
            DB::table('tenant_usuario_papel')->insert(['tenant_id' => $tenant, 'usuario_id' => $usuario, 'papel_id' => 1]);
        }

        $resultado = (new AlunoSexoSeeder)->aplicar();

        $sexo = DB::table('alunos')->pluck('sexo', 'id')->all();
        $this->assertSame('feminino', $sexo[355]);
        $this->assertSame('feminino', $sexo[50]);
        $this->assertSame('masculino', $sexo[347]);
        $this->assertSame('masculino', $sexo[60]);
        $this->assertSame('outro', $sexo[61]);
        $this->assertNull($sexo[62]);
        $this->assertSame([1], $resultado['tenants']);
        $this->assertContains('347;AYLA LIMA PORTO', $resultado['nao_encontrados']);
    }
}
