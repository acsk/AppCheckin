<?php

namespace Tests\Unit;

use App\Services\Admin\AdminMatriculaService;
use Tests\TestCase;

/** Datas em formato inesperado são rejeitadas (422) sem gravar. Crédito com data inválida: MatriculaMigracaoRegrasTest. */
class AdminMatriculaDatasTest extends TestCase
{

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
}
