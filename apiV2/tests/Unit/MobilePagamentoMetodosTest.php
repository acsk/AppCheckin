<?php

namespace Tests\Unit;

use App\Models\Parametro;
use App\Support\MobilePagamentoMetodos;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

class MobilePagamentoMetodosTest extends TestCase
{
    use RequiresSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireSqlite();

        DB::statement('CREATE TABLE parametros (id INTEGER PRIMARY KEY, codigo TEXT, valor_padrao TEXT, tipo_valor TEXT, ativo INTEGER)');
        DB::statement('CREATE TABLE parametros_tenant (id INTEGER PRIMARY KEY, tenant_id INTEGER, parametro_id INTEGER, valor TEXT, ativo INTEGER)');
        DB::table('parametros')->insert([
            ['id' => 1, 'codigo' => 'habilitar_pix', 'valor_padrao' => 'true', 'tipo_valor' => 'boolean', 'ativo' => 1],
            ['id' => 2, 'codigo' => 'habilitar_cartao_credito', 'valor_padrao' => 'false', 'tipo_valor' => 'boolean', 'ativo' => 1],
        ]);
        // Tenant 99 sobrescreve: cartão ligado.
        DB::table('parametros_tenant')->insert(['tenant_id' => 99, 'parametro_id' => 2, 'valor' => 'true', 'ativo' => 1]);
    }

    public function test_parametro_vem_do_autoload_da_v2(): void
    {
        $arquivo = (new \ReflectionClass(Parametro::class))->getFileName();

        $this->assertSame(realpath(app_path('Models/Parametro.php')), realpath($arquivo));
    }

    public function test_flags_le_parametros_sem_codigo_legado(): void
    {
        $this->assertSame(
            ['habilitar_pix' => true, 'habilitar_cartao_credito' => false],
            MobilePagamentoMetodos::flags(98),
        );
        $this->assertSame(
            ['habilitar_pix' => true, 'habilitar_cartao_credito' => true],
            MobilePagamentoMetodos::flags(99),
        );
    }
}
