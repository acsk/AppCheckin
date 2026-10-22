<?php

namespace Tests\Feature;

use App\Repositories\AdminMatriculaRepository;
use App\Services\Admin\AdminMatriculaService;
use App\Services\MatriculaEncadeamentoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

/**
 * Matrícula com pagamento confirmado não é excluída nem sobrescrita; é vinculada à atual (SQLite).
 */
class MatriculaEncadeamentoTest extends TestCase
{
    use RequiresSqlite;

    private const T = 3;

    private int $natacao;

    private int $musculacao;

    private int $plano3x;

    private int $planoLivre;

    private int $planoMusc;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        $this->requireSqlite();

        Schema::create('planos', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('modalidade_id');
            $t->string('nome');
            $t->decimal('valor', 10, 2)->default(0);
        });
        Schema::create('status_matricula', function (Blueprint $t) {
            $t->increments('id');
            $t->string('codigo');
            $t->string('nome');
        });
        Schema::create('motivo_matricula', function (Blueprint $t) {
            $t->increments('id');
            $t->string('codigo');
            $t->string('nome');
        });
        Schema::create('matriculas', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('aluno_id');
            $t->integer('plano_id');
            $t->decimal('valor', 10, 2)->default(0);
            $t->integer('status_id');
            $t->integer('motivo_id')->default(1);
            $t->integer('matricula_anterior_id')->nullable();
            $t->integer('plano_anterior_id')->nullable();
            $t->date('data_inicio')->nullable();
            $t->date('data_vencimento')->nullable();
            $t->timestamps();
        });
        Schema::create('pagamentos_plano', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('matricula_id');
            $t->decimal('valor', 10, 2);
            $t->integer('status_pagamento_id');
        });
        Schema::create('pagamentos_mercadopago', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('matricula_id');
            $t->string('status');
        });
        foreach (['ativa', 'vencida', 'cancelada', 'finalizada', 'pendente'] as $i => $c) {
            DB::table('status_matricula')->insert(['id' => $i + 1, 'codigo' => $c, 'nome' => ucfirst($c)]);
        }
        foreach (['nova', 'renovacao', 'upgrade', 'downgrade'] as $i => $c) {
            DB::table('motivo_matricula')->insert(['id' => $i + 1, 'codigo' => $c, 'nome' => $c]);
        }
        $this->natacao = 3;
        $this->musculacao = 4;
        $this->plano3x = (int) DB::table('planos')->insertGetId(['tenant_id' => self::T, 'modalidade_id' => $this->natacao, 'nome' => '3x por Semana', 'valor' => 150]);
        $this->planoLivre = (int) DB::table('planos')->insertGetId(['tenant_id' => self::T, 'modalidade_id' => $this->natacao, 'nome' => 'Livre', 'valor' => 220]);
        $this->planoMusc = (int) DB::table('planos')->insertGetId(['tenant_id' => self::T, 'modalidade_id' => $this->musculacao, 'nome' => 'Musculação', 'valor' => 90]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function matricula(int $planoId, string $status, string $criada, int $aluno = 134, ?int $anterior = null): int
    {
        $statusId = (int) DB::table('status_matricula')->where('codigo', $status)->value('id');
        $valor = (float) DB::table('planos')->where('id', $planoId)->value('valor');

        return (int) DB::table('matriculas')->insertGetId([
            'tenant_id' => self::T, 'aluno_id' => $aluno, 'plano_id' => $planoId, 'valor' => $valor, 'status_id' => $statusId,
            'matricula_anterior_id' => $anterior, 'data_inicio' => substr($criada, 0, 10), 'created_at' => $criada, 'updated_at' => $criada,
        ]);
    }

    private function pagar(int $matriculaId, int $status = 2, ?string $mp = 'approved'): void
    {
        DB::table('pagamentos_plano')->insert(['matricula_id' => $matriculaId, 'valor' => 150, 'status_pagamento_id' => $status]);
        if ($mp) {
            DB::table('pagamentos_mercadopago')->insert(['matricula_id' => $matriculaId, 'status' => $mp]);
        }
    }

    private function servico(): MatriculaEncadeamentoService
    {
        return app(MatriculaEncadeamentoService::class);
    }

    private function statusDe(int $id): string
    {
        return DB::table('matriculas as m')->join('status_matricula as s', 's.id', '=', 'm.status_id')->where('m.id', $id)->value('s.codigo');
    }

    // ------------------------------------------------------------------ caso da Leane

    public function test_cancelada_paga_e_vinculada_a_matricula_nova_sem_mover_pagamentos(): void
    {
        $antiga = $this->matricula($this->plano3x, 'cancelada', '2026-03-03 22:42:23');
        $this->pagar($antiga);
        $nova = $this->matricula($this->plano3x, 'ativa', '2026-10-01 10:00:00');

        $this->assertSame($nova, (int) $this->servico()->sugerirAtual($antiga, self::T)['id']);

        $r = $this->servico()->vincularManual($antiga, self::T, null, 1);

        $this->assertSame(200, $r['status']);
        $this->assertSame('renovacao', $r['body']['motivo']);
        $this->assertSame('finalizada', $this->statusDe($antiga));
        $linha = DB::table('matriculas')->find($nova);
        $this->assertSame($antiga, (int) $linha->matricula_anterior_id);
        $this->assertSame($this->plano3x, (int) $linha->plano_anterior_id);
        $this->assertSame($antiga, (int) DB::table('pagamentos_plano')->value('matricula_id'), 'pagamento fica no ciclo pago');
    }

    public function test_sugere_a_proxima_para_formar_cadeia(): void
    {
        $m239 = $this->matricula($this->plano3x, 'cancelada', '2026-06-09 10:00:00');
        $m240 = $this->matricula($this->plano3x, 'cancelada', '2026-07-09 10:00:00');
        $m241 = $this->matricula($this->plano3x, 'ativa', '2026-08-09 10:00:00');

        $this->assertSame($m240, (int) $this->servico()->sugerirAtual($m239, self::T)['id']);
        $this->assertSame(200, $this->servico()->vincularManual($m239, self::T, null, 1)['status']);
        $this->assertSame(200, $this->servico()->vincularManual($m240, self::T, null, 1)['status']);

        $this->assertSame($m239, (int) DB::table('matriculas')->where('id', $m240)->value('matricula_anterior_id'));
        $this->assertSame($m240, (int) DB::table('matriculas')->where('id', $m241)->value('matricula_anterior_id'));
    }

    public function test_resumo_da_lista_indica_pagamento_e_continuacao(): void
    {
        $paga = $this->matricula($this->plano3x, 'cancelada', '2026-03-03 10:00:00');
        $this->pagar($paga, 2, null);
        $nova = $this->matricula($this->plano3x, 'ativa', '2026-10-01 10:00:00');
        $semPagamento = $this->matricula($this->plano3x, 'pendente', '2026-10-02 10:00:00', 135);

        $antes = $this->servico()->resumoParaLista([$paga, $nova, $semPagamento]);
        $this->assertSame(['pagamento_confirmado' => true, 'continuada_por' => null], $antes[$paga]);
        $this->assertFalse($antes[$semPagamento]['pagamento_confirmado']);

        $this->servico()->vincularManual($paga, self::T, null, 1);

        $this->assertSame($nova, $this->servico()->resumoParaLista([$paga])[$paga]['continuada_por']);
        $this->assertSame($nova, $this->servico()->continuadaPor($paga));
    }

    public function test_motivo_upgrade_quando_plano_novo_e_mais_caro(): void
    {
        $antiga = $this->matricula($this->plano3x, 'vencida', '2026-03-03 10:00:00');
        $nova = $this->matricula($this->planoLivre, 'pendente', '2026-10-01 10:00:00');

        $this->assertSame('upgrade', $this->servico()->vincularManual($antiga, self::T, $nova, 1)['body']['motivo']);
    }

    public function test_vinculo_recusa_matricula_de_outra_modalidade_ja_vinculada_ou_ativa(): void
    {
        $antiga = $this->matricula($this->plano3x, 'cancelada', '2026-03-03 10:00:00');
        $musc = $this->matricula($this->planoMusc, 'ativa', '2026-10-01 10:00:00');
        $this->assertSame('MATRICULA_INCOMPATIVEL', $this->servico()->vincularManual($antiga, self::T, $musc, 1)['body']['code']);

        $outra = $this->matricula($this->plano3x, 'cancelada', '2026-02-01 10:00:00');
        $nova = $this->matricula($this->plano3x, 'ativa', '2026-10-02 10:00:00', 134, $outra);
        $this->assertSame('JA_VINCULADA', $this->servico()->vincularManual($antiga, self::T, $nova, 1)['body']['code']);

        $this->assertSame('STATUS_INVALIDO', $this->servico()->vincularManual($nova, self::T, null, 1)['body']['code']);
        $this->assertSame('cancelada', $this->statusDe($antiga), 'nada muda quando recusa');
    }

    public function test_sem_matricula_mais_recente_nao_ha_o_que_vincular(): void
    {
        $antiga = $this->matricula($this->plano3x, 'cancelada', '2026-03-03 10:00:00');
        $this->matricula($this->plano3x, 'ativa', '2026-10-01 10:00:00', 999); // outra aluna

        $this->assertNull($this->servico()->sugerirAtual($antiga, self::T));
        $this->assertSame('SEM_MATRICULA_ATUAL', $this->servico()->vincularManual($antiga, self::T, null, 1)['body']['code']);
    }

    // ------------------------------------------------------------------ exclusão e reaproveitamento

    public function test_so_pagamento_confirmado_bloqueia(): void
    {
        $aguardando = $this->matricula($this->plano3x, 'pendente', '2026-03-03 10:00:00');
        $this->pagar($aguardando, 1, 'pending');
        $paga = $this->matricula($this->plano3x, 'cancelada', '2026-04-03 10:00:00', 135);
        $this->pagar($paga, 2, null);

        $this->assertTrue($this->servico()->podeReaproveitar($aguardando), 'compra abandonada pode ser reaproveitada');
        $this->assertFalse($this->servico()->podeReaproveitar($paga));
        $this->assertSame(['pagamentos_plano' => 1, 'pagamentos_provedor' => 0, 'valor' => 150.0], $this->servico()->pagamentosConfirmados($paga));
    }

    public function test_destroy_recusa_matricula_com_pagamento_e_nao_apaga_nada(): void
    {
        $paga = $this->matricula($this->plano3x, 'cancelada', '2026-03-03 10:00:00');
        $this->pagar($paga);

        $repo = Mockery::mock(AdminMatriculaRepository::class);
        $repo->shouldReceive('findParaHardDelete')->once()->andReturn(['id' => $paga, 'aluno_id' => 134, 'pacote_contrato_id' => null]);
        $repo->shouldNotReceive('deletarPagamentosPlano', 'deletarPagamentosMercadopago', 'deletarMatricula');
        $this->app->instance(AdminMatriculaRepository::class, $repo);

        $r = app(AdminMatriculaService::class)->destroy($paga, self::T);

        $this->assertSame(422, $r['status']);
        $this->assertSame('MATRICULA_COM_PAGAMENTO', $r['body']['code']);
        $this->assertSame(1, DB::table('pagamentos_plano')->count());
    }

    public function test_encerrar_anterior_finaliza_vencida_e_preserva_cancelada(): void
    {
        $vencida = $this->matricula($this->plano3x, 'vencida', '2026-03-03 10:00:00');
        $cancelada = $this->matricula($this->plano3x, 'cancelada', '2026-03-03 10:00:00', 135);

        $this->servico()->encerrarAnterior($vencida);
        $this->servico()->encerrarAnterior($cancelada);

        $this->assertSame('finalizada', $this->statusDe($vencida));
        $this->assertSame('cancelada', $this->statusDe($cancelada));
    }
}
