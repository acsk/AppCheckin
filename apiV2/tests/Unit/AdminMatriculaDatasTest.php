<?php

namespace Tests\Unit;

use App\Services\Admin\AdminMatriculaService;
use App\Support\AcademyDateTime;
use Tests\TestCase;

/** Datas vindas do banco em formato inesperado não podem gerar exceção (500) nem crédito. */
class AdminMatriculaDatasTest extends TestCase
{

    private function datasCiclo(array $matricula): array
    {
        $service = (new \ReflectionClass(AdminMatriculaService::class))->newInstanceWithoutConstructor();
        $metodo = new \ReflectionMethod($service, 'datasCicloParaCredito');
        $metodo->setAccessible(true);

        return $metodo->invoke($service, $matricula);
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function datasNaoEstritasNoCriar(): array
    {
        return [
            'data_inicio sem zero à esquerda' => [['data_inicio' => '2026-7-13']],
            'data_inicio com hora' => [['data_inicio' => '2026-07-13 10:00']],
            'data_inicio formato BR' => [['data_inicio' => '13/07/2026']],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('datasNaoEstritasNoCriar')]
    public function test_criar_exige_yyyy_mm_dd_estrito(array $datas): void
    {
        $repo = \Mockery::mock(\App\Repositories\AdminMatriculaRepository::class);
        $repo->shouldReceive('findUsuarioIdPorAluno')->with(2)->andReturn(50);
        $repo->shouldReceive('findUsuarioAluno')->with(50)->andReturn(['id' => 50]);
        $repo->shouldReceive('ensureVinculoAlunoTenant')->with(50, 3);
        $repo->shouldReceive('findUsuarioAlunoNoTenant')->with(50, 3)->andReturn(['id' => 50]);
        $repo->shouldReceive('findPlano')->with(10, 3)->andReturn(['id' => 10, 'valor' => 100, 'duracao_dias' => 30]);
        // Nada é gravado quando a data é rejeitada.
        $repo->shouldNotReceive('criarMatricula');

        $service = new AdminMatriculaService(
            $repo,
            \Mockery::mock(\App\Services\PagamentoPlanoService::class),
            \Mockery::mock(\App\Repositories\MatriculaRepository::class),
            \Mockery::mock(\App\Services\Admin\AdminPacoteService::class),
            \Mockery::mock(\App\Services\Admin\AdminPagamentoPlanoService::class),
            \Mockery::mock(\App\Repositories\AdminAssinaturaRepository::class),
            \Mockery::mock(\App\Services\MatriculaMigracaoAptidaoService::class),
        );

        $result = $service->criar(3, 5, ['aluno_id' => 2, 'plano_id' => 10] + $datas);

        $this->assertSame(422, $result['status']);
        $this->assertSame('Formato de data inválido. Use YYYY-MM-DD', $result['body']['error']);
    }

    public function test_datas_validas_com_hora(): void
    {
        [$inicio, $venc, $validas] = $this->datasCiclo([
            'data_inicio' => '2026-09-01 00:00:00',
            'data_vencimento' => '2026-10-01 00:00:00',
        ]);

        $this->assertTrue($validas);
        $this->assertSame('2026-09-01', $inicio->format('Y-m-d'));
        $this->assertSame('2026-10-01', $venc->format('Y-m-d'));
    }

    public function test_usa_fallbacks_created_at_e_proxima_data(): void
    {
        [$inicio, $venc, $validas] = $this->datasCiclo([
            'data_inicio' => null,
            'created_at' => '2026-09-05 14:00:00',
            'data_vencimento' => '0000-00-00',
            'proxima_data_vencimento' => '2026-10-05',
        ]);

        $this->assertTrue($validas);
        $this->assertSame('2026-09-05', $inicio->format('Y-m-d'));
        $this->assertSame('2026-10-05', $venc->format('Y-m-d'));
    }

    public function test_datas_invalidas_nao_lancam_e_bloqueiam_credito(): void
    {
        [$inicio, $venc, $validas] = $this->datasCiclo([
            'data_inicio' => 'lixo',
            'data_vencimento' => '0000-00-00 00:00:00',
        ]);

        $this->assertFalse($validas);
        $this->assertSame(AcademyDateTime::today(), $inicio->format('Y-m-d'));
        $this->assertSame(AcademyDateTime::today(), $venc->format('Y-m-d'));
    }
}
