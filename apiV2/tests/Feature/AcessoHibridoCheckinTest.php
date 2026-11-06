<?php

namespace Tests\Feature;

use App\Support\AcademyDateTime;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\HybridFixture;
use Tests\TestCase;

class AcessoHibridoCheckinTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        HybridFixture::create();
        HybridFixture::migrate();
        HybridFixture::seed();
        DB::table('turmas')->update(['horario_inicio' => AcademyDateTime::now()->format('H:i:s')]);
    }

    public function test_mistos_na_mesma_aula_e_repeticao_nao_duplica_presenca(): void
    {
        $service = HybridFixture::checkinService();
        foreach ([11, 12, 1] as $aluno) {
            $this->assertSame(201, $service->registrar($aluno, 3, ['turma_id' => 1])['status']);
        }
        $this->assertSame(403, $service->registrar(13, 3, ['turma_id' => 1])['status']);
        $this->assertSame(400, $service->registrar(1, 3, ['turma_id' => 1])['status']);
        $this->assertSame(3, DB::table('checkins')->count());
    }

    public function test_recusa_fixo_de_outra_turma_e_fora_da_janela(): void
    {
        $service = HybridFixture::checkinService();
        DB::table('turmas')->update(['turma_fixa_id' => null]);
        $this->assertSame(403, $service->registrar(1, 3, ['turma_id' => 1])['status']);
        DB::table('turmas')->update(['turma_fixa_id' => 1, 'horario_inicio' => '00:00:00', 'tolerancia_minutos' => 0]);
        $result = $service->registrar(1, 3, ['turma_id' => 1]);
        $this->assertSame('CHECKIN_ENCERRADO', $result['body']['code']);
        $this->assertSame(0, DB::table('checkins')->count());
    }
}
