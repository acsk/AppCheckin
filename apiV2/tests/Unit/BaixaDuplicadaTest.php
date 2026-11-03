<?php

namespace Tests\Unit;

use App\Repositories\AdminAssinaturaRepository;
use App\Repositories\AdminMatriculaRepository;
use App\Repositories\MatriculaRepository;
use App\Services\Admin\AdminMatriculaService;
use App\Services\Admin\AdminPacoteService;
use App\Services\Admin\AdminPagamentoPlanoService;
use App\Services\MatriculaMigracaoAptidaoService;
use App\Services\PagamentoPlanoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

/**
 * Caso real (matrícula 164): a 1312 (venc. 01/11) e a 1551 (venc. 03/11) foram baixadas em 40 s; o mesmo mês
 * ficou pago duas vezes e cada baixa gerou uma parcela de 03/12 (1552 e 1553).
 */
class BaixaDuplicadaTest extends TestCase
{
    use RequiresSqlite;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function service(AdminMatriculaRepository $repo, ?PagamentoPlanoService $pagamentos = null): AdminMatriculaService
    {
        return new AdminMatriculaService(
            $repo,
            $pagamentos ?? Mockery::mock(PagamentoPlanoService::class),
            Mockery::mock(MatriculaRepository::class),
            Mockery::mock(AdminPacoteService::class),
            Mockery::mock(AdminPagamentoPlanoService::class),
            Mockery::mock(AdminAssinaturaRepository::class),
            Mockery::mock(MatriculaMigracaoAptidaoService::class),
        );
    }

    private function parcela1551(): array
    {
        return ['id' => 1551, 'status_pagamento_id' => 1, 'matricula_id' => 164, 'tenant_id' => 3, 'aluno_id' => 112, 'plano_id' => 7,
            'valor' => '80.00', 'matricula_valor' => '80.00', 'data_vencimento' => '2026-11-03', 'duracao_dias' => 30,
            'ciclo_meses' => 1, 'pacote_contrato_id' => null];
    }

    public function test_segunda_baixa_do_mesmo_mes_pede_confirmacao_com_resumo(): void
    {
        $paga = ['id' => 1312, 'valor' => '80.00', 'data_vencimento' => '2026-11-01', 'data_pagamento' => '2026-11-03',
            'status_pagamento_id' => 2, 'baixado_por_nome' => 'JOSÉ CARLOS'];
        $aberta = ['id' => 1552, 'valor' => '80.00', 'data_vencimento' => '2026-12-03', 'data_pagamento' => null,
            'status_pagamento_id' => 1, 'baixado_por_nome' => null];
        $repo = Mockery::mock(AdminMatriculaRepository::class);
        $repo->shouldReceive('findPagamentoParaBaixa')->once()->with(1551, 3)->andReturn($this->parcela1551());
        $repo->shouldReceive('parcelasParaConferirBaixa')->once()->with(3, 164, 1551, '2026-10-19', '2026-11-18')
            ->andReturn(['pagas' => [$paga], 'em_aberto' => [$aberta]]);
        $repo->shouldNotReceive('atualizarPagamentoBaixa');

        $r = $this->service($repo)->darBaixaConta(1551, 3, 68, ['data_vencimento' => '2026-11-03', 'data_pagamento' => '2026-11-03']);

        $this->assertSame(409, $r['status']);
        $this->assertSame(AdminMatriculaService::CODIGO_BAIXA_DUPLICADA, $r['body']['code']);
        $this->assertSame(1312, $r['body']['resumo']['pagas'][0]['id']);
        $this->assertSame(1552, $r['body']['resumo']['em_aberto'][0]['id']);
        $this->assertSame(15, $r['body']['resumo']['janela_dias']);
    }

    public function test_confirmada_pelo_admin_a_baixa_segue(): void
    {
        $repo = Mockery::mock(AdminMatriculaRepository::class);
        $repo->shouldReceive('findPagamentoParaBaixa')->with(1551, 3)->andReturn($this->parcela1551());
        $repo->shouldNotReceive('parcelasParaConferirBaixa');
        $repo->shouldReceive('atualizarPagamentoBaixa')->once();
        $repo->shouldReceive('ativarMatriculaSePendente', 'atualizarProximaDataVencimentoSimples');
        $repo->shouldReceive('inserirPagamentoPlano')->andReturn(1553);
        $pagamentos = Mockery::mock(PagamentoPlanoService::class);
        $pagamentos->shouldReceive('atualizarStatusMatricula')->once();

        $r = $this->service($repo, $pagamentos)->darBaixaConta(1551, 3, 68,
            ['data_vencimento' => '2026-11-03', 'data_pagamento' => '2026-11-03', 'confirmar_duplicidade' => true]);

        $this->assertSame(200, $r['status']);
    }

    public function test_mensalidade_seguinte_paga_adiantada_nao_e_duplicidade(): void
    {
        $this->requireSqlite();
        Schema::create('usuarios', fn (Blueprint $t) => [$t->increments('id'), $t->string('nome')]);
        Schema::create('pagamentos_plano', function (Blueprint $t) {
            $t->increments('id');
            foreach (['tenant_id', 'matricula_id', 'status_pagamento_id', 'baixado_por'] as $c) {
                $t->integer($c)->nullable();
            }
            $t->decimal('valor', 10, 2);
            $t->date('data_vencimento');
            $t->date('data_pagamento')->nullable();
            $t->text('observacoes')->nullable();
        });
        DB::table('usuarios')->insert(['id' => 68, 'nome' => 'JOSÉ CARLOS']);
        $p = fn (int $id, int $status, string $venc, ?string $obs = null) => DB::table('pagamentos_plano')->insert([
            'id' => $id, 'tenant_id' => 3, 'matricula_id' => 164, 'status_pagamento_id' => $status, 'baixado_por' => $status === 2 ? 68 : null,
            'valor' => 80, 'data_vencimento' => $venc, 'data_pagamento' => $status === 2 ? $venc : null, 'observacoes' => $obs]);
        $p(1134, 2, '2026-10-01');
        $p(1312, 2, '2026-11-01');
        $p(1400, 2, '2026-11-02', '[MIGRACAO_SIMULADA plano_id=10 plano_ciclo_id=68]');
        $p(1551, 1, '2026-11-03');
        $p(1552, 1, '2026-12-03');

        $repo = app(AdminMatriculaRepository::class);
        $mesmoMes = $repo->parcelasParaConferirBaixa(3, 164, 1551, '2026-10-19', '2026-11-18');
        $this->assertSame([1312], array_column($mesmoMes['pagas'], 'id'), 'a de outubro fica fora; simulação não conta');
        $this->assertSame('JOSÉ CARLOS', $mesmoMes['pagas'][0]['baixado_por_nome']);
        $this->assertSame([1552], array_column($mesmoMes['em_aberto'], 'id'));

        // Dezembro pago em novembro: vencimento um ciclo depois do último pago, sem conflito
        $this->assertSame([], $repo->parcelasParaConferirBaixa(3, 164, 1552, '2026-11-18', '2026-12-18')['pagas']);
    }
}
