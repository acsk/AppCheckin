<?php

namespace Tests\Feature;

use App\Repositories\Bi\BiTenantRepository;
use App\Services\Bi\BiGlobalService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BiSchema;
use Tests\TestCase;

/**
 * BI Global: cada academia com os mesmos números do BI dela; totais = soma; tenant do sistema fora.
 */
class BiGlobalTest extends TestCase
{
    use BiSchema;

    private const SISTEMA = 50;

    protected function setUp(): void
    {
        parent::setUp();
        $this->criarCenarioBi();
        config(['appcheckin.sistema_tenant_id' => self::SISTEMA]);
        Schema::create('tenants', function (Blueprint $t) {
            $t->increments('id');
            $t->string('nome');
            $t->boolean('ativo')->default(true);
        });
        DB::table('tenants')->insert([
            ['id' => self::T1, 'nome' => 'Natação Centro', 'ativo' => 1],
            ['id' => self::T2, 'nome' => 'Outra', 'ativo' => 0],
            ['id' => self::SISTEMA, 'nome' => 'Sistema', 'ativo' => 1],
        ]);
        // Aluno da plataforma não conta
        $this->matricula(1, '2026-10-01', '2026-10-01', '2026-10-31', 1, null, null, self::SISTEMA);
    }

    public function test_soma_as_academias_com_os_numeros_do_bi_de_cada_uma(): void
    {
        $r = app(BiGlobalService::class)->global('mes', self::HOJE);
        $this->assertSame(200, $r['status']);
        $d = $r['body']['data'];
        $repo = app(BiTenantRepository::class);

        $this->assertSame([self::T1, self::T2], array_column($d['academias'], 'tenant_id'), 'mais alunos primeiro, sem o sistema');
        $t1 = $d['academias'][0];
        $this->assertSame($repo->alunosAtivosEm(self::T1, self::HOJE), $t1['alunos_ativos']);
        $this->assertSame(250.0, $t1['receita_recebida']);
        $this->assertSame(array_sum($repo->checkinsPorDia(self::T1, '2026-10-01', self::HOJE)), $t1['checkins']);

        $this->assertSame(['atual' => 5, 'anterior' => 3], $d['indicadores']['alunos_ativos']);
        $this->assertSame(5250.0, $d['indicadores']['receita_academias']['atual']);
        $this->assertSame(1, $d['indicadores']['academias_ativas']['atual']);
        $this->assertSame(['mes' => '2026-10', 'alunos_ativos' => 5, 'receita' => 5250.0, 'checkins' => $t1['checkins'] + $d['academias'][1]['checkins']], end($d['mensal']));
    }
}
