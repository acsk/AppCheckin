<?php

namespace Tests\Feature;

use App\Services\Asaas\AsaasService;
use App\Services\LimpezaPendenciasService;
use App\Services\MatriculaEncadeamentoService;
use App\Services\Recorrencia\RecorrenciaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

/** Limpeza de pendências abandonadas: só encerra o que não foi pago (conferido no Asaas). */
class LimpezaPendenciasTest extends TestCase
{
    use RequiresSqlite;

    private const T = 3;

    /** status Asaas por cobrança; cobranças canceladas no Asaas */
    private array $asaasStatus = [];

    private array $canceladasNoAsaas = [];

    private bool $asaasFora = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireSqlite();
        $this->registrarFuncoesSqlite(['CONCAT' => [fn (...$a) => implode('', $a), -1]]);

        Schema::create('status_matricula', fn (Blueprint $t) => [$t->increments('id'), $t->string('codigo')]);
        Schema::create('matriculas', fn (Blueprint $t) => [$t->increments('id'), $t->integer('tenant_id'), $t->integer('status_id'), $t->timestamps()]);
        Schema::create('pagamentos_plano', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('matricula_id');
            $t->decimal('valor', 10, 2);
            $t->integer('status_pagamento_id');
            $t->date('data_pagamento')->nullable();
            $t->text('observacoes')->nullable();
            $t->timestamps();
        });
        Schema::create('assinatura_status', fn (Blueprint $t) => [$t->increments('id'), $t->string('codigo')]);
        Schema::create('assinatura_cancelamento_tipos', fn (Blueprint $t) => [$t->increments('id'), $t->string('codigo')]);
        Schema::create('assinaturas', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('matricula_id')->nullable();
            $t->decimal('valor', 10, 2);
            $t->integer('status_id');
            $t->string('tipo_cobranca')->nullable();
            $t->string('gateway_preference_id')->nullable();
            $t->string('status_gateway')->nullable();
            $t->integer('cancelado_por_id')->nullable();
            $t->text('motivo_cancelamento')->nullable();
            $t->timestamp('criado_em')->nullable();
            $t->timestamp('atualizado_em')->nullable();
        });
        Schema::create('contratos_recorrencia', fn (Blueprint $t) => [$t->increments('id'), $t->integer('tenant_id'), $t->integer('matricula_id'), $t->string('status'), $t->timestamps()]);

        DB::table('status_matricula')->insert([['id' => 1, 'codigo' => 'ativa'], ['id' => 2, 'codigo' => 'vencida'], ['id' => 3, 'codigo' => 'cancelada'], ['id' => 5, 'codigo' => 'pendente']]);
        DB::table('assinatura_status')->insert([['id' => 1, 'codigo' => 'pendente'], ['id' => 4, 'codigo' => 'cancelada'], ['id' => 5, 'codigo' => 'expirada']]);
        DB::table('assinatura_cancelamento_tipos')->insert(['id' => 4, 'codigo' => 'sistema']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function servico(?RecorrenciaService $recorrencia = null): LimpezaPendenciasService
    {
        $fake = new class($this) extends AsaasService
        {
            public function __construct(private $teste) {}

            public function obterPagamento(string $paymentId): array
            {
                if ($this->teste->asaasForaDoAr()) {
                    throw new \RuntimeException('Asaas fora');
                }

                return ['id' => $paymentId, 'status' => $this->teste->statusAsaas($paymentId)];
            }

            public function cancelarCobranca(string $paymentId): bool
            {
                $this->teste->registrarCancelamento($paymentId);

                return true;
            }
        };

        return new LimpezaPendenciasService($recorrencia ?? Mockery::mock(RecorrenciaService::class), app(MatriculaEncadeamentoService::class), fn () => $fake);
    }

    public function asaasForaDoAr(): bool
    {
        return $this->asaasFora;
    }

    public function statusAsaas(string $id): string
    {
        return $this->asaasStatus[$id] ?? 'PENDING';
    }

    public function registrarCancelamento(string $id): void
    {
        $this->canceladasNoAsaas[] = $id;
    }

    private function simulacao(string $paymentId, string $criada = '-10 days', int $matricula = 189): int
    {
        DB::table('matriculas')->insertOrIgnore(['id' => $matricula, 'tenant_id' => self::T, 'status_id' => 1]);

        return DB::table('pagamentos_plano')->insertGetId([
            'tenant_id' => self::T, 'matricula_id' => $matricula, 'valor' => 53.16, 'status_pagamento_id' => 1,
            'observacoes' => "Simulação de migração [MIGRACAO_SIMULADA plano_id=10 plano_ciclo_id=68] [Asaas {$paymentId}]",
            'created_at' => date('Y-m-d H:i:s', strtotime($criada)),
        ]);
    }

    private function checkout(?string $paymentId, int $matricula, int $statusMatricula = 5, string $criado = '-10 days'): int
    {
        DB::table('matriculas')->insertOrIgnore(['id' => $matricula, 'tenant_id' => self::T, 'status_id' => $statusMatricula]);

        return DB::table('assinaturas')->insertGetId([
            'tenant_id' => self::T, 'matricula_id' => $matricula, 'valor' => 120, 'status_id' => 1, 'tipo_cobranca' => 'avulso',
            'gateway_preference_id' => $paymentId, 'criado_em' => date('Y-m-d H:i:s', strtotime($criado)), 'atualizado_em' => date('Y-m-d H:i:s', strtotime($criado)),
        ]);
    }

    public function test_simulacao_de_troca_abandonada_e_cancelada_no_asaas_e_aqui(): void
    {
        $id = $this->simulacao('pay_kcob0f2efbtmlbwb');

        $r = $this->servico()->executar();

        $this->assertSame(1, $r['simulacoes']);
        $this->assertSame(['pay_kcob0f2efbtmlbwb'], $this->canceladasNoAsaas);
        $p = DB::table('pagamentos_plano')->find($id);
        $this->assertSame(4, (int) $p->status_pagamento_id);
        $this->assertStringContainsString('cancelada automaticamente', $p->observacoes);
    }

    public function test_paga_no_asaas_nunca_e_cancelada(): void
    {
        $id = $this->simulacao('pay_pago');
        $this->asaasStatus['pay_pago'] = 'RECEIVED';
        $c = $this->checkout('pay_pago2', 300);
        $this->asaasStatus['pay_pago2'] = 'CONFIRMED';

        $r = $this->servico()->executar();

        $this->assertSame(2, $r['ignoradas_pagas']);
        $this->assertSame([], $this->canceladasNoAsaas);
        $this->assertSame(1, (int) DB::table('pagamentos_plano')->find($id)->status_pagamento_id);
        $this->assertSame(1, (int) DB::table('assinaturas')->find($c)->status_id);
    }

    public function test_recentes_e_asaas_fora_ficam_para_depois(): void
    {
        $recente = $this->simulacao('pay_novo', '-1 day');
        $this->assertSame(0, $this->servico()->executar(3)['simulacoes']);
        $this->assertSame(1, (int) DB::table('pagamentos_plano')->find($recente)->status_pagamento_id);

        $velha = $this->simulacao('pay_velho');
        $this->asaasFora = true;
        $r = $this->servico()->executar();
        $this->assertSame(1, $r['falhas']);
        $this->assertSame(1, (int) DB::table('pagamentos_plano')->find($velha)->status_pagamento_id);
    }

    public function test_checkout_abandonado_expira_e_cancela_a_compra_sem_pagamento(): void
    {
        $abandonado = $this->checkout('pay_ab', 400);
        DB::table('pagamentos_plano')->insert(['tenant_id' => self::T, 'matricula_id' => 400, 'valor' => 120, 'status_pagamento_id' => 1, 'observacoes' => 'Aguardando pagamento via Asaas', 'created_at' => now()]);
        $comPagamento = $this->checkout('pay_cp', 401);
        DB::table('pagamentos_plano')->insert(['tenant_id' => self::T, 'matricula_id' => 401, 'valor' => 120, 'status_pagamento_id' => 2, 'data_pagamento' => '2026-09-01', 'created_at' => now()]);

        $r = $this->servico()->executar();

        $this->assertSame(2, $r['checkouts']);
        $this->assertSame(1, $r['matriculas']);
        $a = DB::table('assinaturas')->find($abandonado);
        $this->assertSame([5, 'expired', 4], [(int) $a->status_id, $a->status_gateway, (int) $a->cancelado_por_id]);
        $this->assertSame(3, (int) DB::table('matriculas')->find(400)->status_id, 'compra abandonada cancelada');
        $this->assertSame(4, (int) DB::table('pagamentos_plano')->where('matricula_id', 400)->value('status_pagamento_id'));
        $this->assertSame(5, (int) DB::table('matriculas')->find(401)->status_id, 'matrícula com pagamento não é mexida');
        $this->assertSame(5, (int) DB::table('assinaturas')->find($comPagamento)->status_id);
    }

    // Uma matrícula por modalidade: a renovação reaproveita a matrícula paga e o checkout fica sem pagar
    public function test_renovacao_abandonada_na_mesma_matricula_volta_a_vencida(): void
    {
        $this->checkout('pay_ren', 402);
        DB::table('pagamentos_plano')->insert([
            ['tenant_id' => self::T, 'matricula_id' => 402, 'valor' => 120, 'status_pagamento_id' => 2, 'data_pagamento' => '2026-08-01', 'observacoes' => null, 'created_at' => date('Y-m-d H:i:s', strtotime('-60 days'))],
            ['tenant_id' => self::T, 'matricula_id' => 402, 'valor' => 120, 'status_pagamento_id' => 1, 'data_pagamento' => null, 'observacoes' => 'Aguardando pagamento via Asaas', 'created_at' => date('Y-m-d H:i:s', strtotime('-10 days'))],
        ]);

        $r = $this->servico()->executar();

        $this->assertSame(1, $r['matriculas']);
        $this->assertSame(2, (int) DB::table('matriculas')->find(402)->status_id, 'vencida, não cancelada');
        $this->assertSame(1, DB::table('pagamentos_plano')->where('matricula_id', 402)->where('status_pagamento_id', 2)->count(), 'ciclo pago intacto');
        $this->assertSame(4, (int) DB::table('pagamentos_plano')->where('matricula_id', 402)->whereNull('data_pagamento')->value('status_pagamento_id'));
    }

    public function test_mercado_pago_so_depois_de_30_dias_e_modo_simular_nao_altera(): void
    {
        $mpRecente = $this->checkout('668038569199', 500, 3, '-10 days');
        $mpVelho = $this->checkout('525033883883', 501, 3, '-40 days');

        $sim = $this->servico()->executar(3, 7, null, true);
        $this->assertSame(1, $sim['checkouts']);
        $this->assertSame(1, (int) DB::table('assinaturas')->find($mpVelho)->status_id, 'simular não muda nada');

        $this->servico()->executar();
        $this->assertSame(1, (int) DB::table('assinaturas')->find($mpRecente)->status_id);
        $this->assertSame(5, (int) DB::table('assinaturas')->find($mpVelho)->status_id);
    }

    public function test_assinatura_com_fidelidade_nao_ativada_e_isolamento_por_academia(): void
    {
        DB::table('contratos_recorrencia')->insert([
            ['id' => 1, 'tenant_id' => self::T, 'matricula_id' => 600, 'status' => 'pendente_ativacao', 'created_at' => now()->subDays(10)],
            ['id' => 2, 'tenant_id' => self::T, 'matricula_id' => 601, 'status' => 'pendente_ativacao', 'created_at' => now()->subDays(2)],
            ['id' => 3, 'tenant_id' => 9, 'matricula_id' => 602, 'status' => 'pendente_ativacao', 'created_at' => now()->subDays(10)],
        ]);
        $recorrencia = Mockery::mock(RecorrenciaService::class);
        $recorrencia->shouldReceive('encerrarNaoAtivado')->once()->with(self::T, 1)->andReturn(true);

        $r = $this->servico($recorrencia)->executar(3, 7, self::T);

        $this->assertSame(1, $r['assinaturas_fidelidade']);
    }
}
