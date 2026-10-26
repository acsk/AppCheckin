<?php

namespace Tests\Feature;

use App\Repositories\AdminPacoteRepository;
use App\Repositories\UsuarioRepository;
use App\Services\Admin\AdminPacoteLiberacaoService;
use App\Services\Admin\PacoteDescontoService;
use App\Services\CobrancaAnteriorService;
use App\Services\Mobile\MobilePacoteVendaService;
use App\Services\Pacote\PacoteConciliacaoService;
use App\Services\PagamentoPlanoService;
use App\Services\Payment\PaymentGatewayFactory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

class PacoteVendaAppTest extends TestCase
{
    use RequiresSqlite;

    private const T = 3;

    /** usuario => aluno */
    private const PAGANTE = [10, 100];

    private const FILHO = [11, 101];

    private const OUTRO = [12, 102];

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireSqlite();
        $this->registrarFuncoesSqlite(['NOW' => [fn () => date('Y-m-d H:i:s'), 0]]);

        Schema::create('tenants', fn (Blueprint $t) => [$t->increments('id'), $t->string('nome')]);
        Schema::create('usuarios', function (Blueprint $t) {
            $t->increments('id');
            $t->string('nome');
            $t->string('email')->nullable();
            $t->string('cpf')->nullable();
            $t->string('telefone')->nullable();
        });
        Schema::create('alunos', fn (Blueprint $t) => [$t->increments('id'), $t->integer('usuario_id')]);
        Schema::create('tenant_usuario_papel', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('usuario_id');
            $t->integer('papel_id');
            $t->boolean('ativo')->default(true);
        });
        Schema::create('modalidades', fn (Blueprint $t) => [$t->increments('id'), $t->string('nome')]);
        Schema::create('planos', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->string('nome');
            $t->integer('modalidade_id')->nullable();
            $t->integer('duracao_dias')->default(30);
            $t->decimal('valor', 10, 2)->default(0);
        });
        Schema::create('plano_ciclos', fn (Blueprint $t) => [$t->increments('id'), $t->integer('tenant_id'), $t->integer('meses'), $t->decimal('valor', 10, 2)]);
        Schema::create('pacotes', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->string('nome');
            $t->text('descricao')->nullable();
            $t->decimal('valor_total', 10, 2);
            $t->integer('qtd_beneficiarios');
            $t->integer('plano_id');
            $t->integer('plano_ciclo_id')->nullable();
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });
        Schema::create('pacote_contratos', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('pacote_id');
            $t->integer('assinatura_id')->nullable();
            $t->integer('pagante_usuario_id');
            $t->string('payment_url')->nullable();
            $t->string('payment_preference_id')->nullable();
            $t->string('status')->default('pendente');
            $t->decimal('valor_total', 10, 2)->default(0);
            $t->date('data_inicio')->nullable();
            $t->date('data_fim')->nullable();
            $t->timestamps();
        });
        Schema::create('status_matricula', fn (Blueprint $t) => [$t->increments('id'), $t->string('codigo')]);
        Schema::create('matriculas', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('aluno_id');
            $t->integer('pacote_contrato_id')->nullable();
            $t->integer('status_id');
            $t->date('data_vencimento')->nullable();
            $t->timestamps();
        });
        Schema::create('pagamentos_plano', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('aluno_id');
            $t->integer('matricula_id');
            $t->integer('pacote_contrato_id')->nullable();
            $t->decimal('valor', 10, 2);
            $t->date('data_vencimento');
            $t->date('data_pagamento')->nullable();
            $t->integer('status_pagamento_id')->default(1);
            $t->integer('forma_pagamento_id')->nullable();
            $t->integer('tipo_baixa_id')->nullable();
            $t->text('observacoes')->nullable();
            $t->timestamps();
        });
        Schema::create('pagamentos_pix', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('matricula_id');
            $t->string('payment_id');
            $t->string('ticket_url')->nullable();
            $t->text('qr_code')->nullable();
            $t->text('qr_code_base64')->nullable();
            $t->dateTime('expires_at')->nullable();
            $t->string('status')->nullable();
        });
        Schema::create('assinatura_status', fn (Blueprint $t) => [$t->increments('id'), $t->string('codigo')]);
        Schema::create('assinatura_gateways', fn (Blueprint $t) => [$t->increments('id'), $t->string('codigo')]);
        Schema::create('metodos_pagamento', fn (Blueprint $t) => [$t->increments('id'), $t->string('codigo')]);
        Schema::create('assinaturas', function (Blueprint $t) {
            $t->increments('id');
            foreach (['tenant_id', 'matricula_id', 'aluno_id', 'plano_id', 'gateway_id', 'status_id', 'frequencia_id', 'dia_cobranca', 'metodo_pagamento_id', 'pacote_contrato_id'] as $c) {
                $t->integer($c)->nullable();
            }
            foreach (['gateway_preference_id', 'external_reference', 'payment_url', 'status_gateway', 'tipo_cobranca'] as $c) {
                $t->string($c)->nullable();
            }
            $t->decimal('valor', 10, 2)->nullable();
            $t->date('data_inicio')->nullable();
            $t->timestamp('criado_em')->nullable();
            $t->timestamp('atualizado_em')->nullable();
        });
        (require database_path('migrations/2026_10_26_120000_pacote_venda_app.php'))->up();

        DB::table('tenants')->insert(['id' => self::T, 'nome' => 'Cia']);
        DB::table('status_matricula')->insert([['id' => 1, 'codigo' => 'ativa'], ['id' => 5, 'codigo' => 'pendente'], ['id' => 6, 'codigo' => 'cancelada']]);
        DB::table('assinatura_status')->insert(['id' => 1, 'codigo' => 'pendente']);
        DB::table('metodos_pagamento')->insert([['id' => 1, 'codigo' => 'pix'], ['id' => 2, 'codigo' => 'cartao']]);
        DB::table('assinatura_gateways')->insert([['id' => 1, 'codigo' => 'mercadopago'], ['id' => 2, 'codigo' => 'asaas']]);
        foreach ([self::PAGANTE, self::FILHO, self::OUTRO] as [$u, $a]) {
            DB::table('usuarios')->insert(['id' => $u, 'nome' => "U{$u}", 'email' => "u{$u}@x.com", 'cpf' => '52998224725']);
            DB::table('alunos')->insert(['id' => $a, 'usuario_id' => $u]);
            DB::table('tenant_usuario_papel')->insert(['tenant_id' => self::T, 'usuario_id' => $u, 'papel_id' => 1]);
        }
        DB::table('modalidades')->insert(['id' => 1, 'nome' => 'Natação']);
        DB::table('planos')->insert(['id' => 7, 'tenant_id' => self::T, 'nome' => 'Mensal', 'modalidade_id' => 1, 'valor' => 200]);
        DB::table('pacotes')->insert([
            ['id' => 1, 'tenant_id' => self::T, 'nome' => 'Família', 'valor_total' => 300, 'qtd_beneficiarios' => 2, 'plano_id' => 7, 'venda_app' => 'todos'],
            ['id' => 2, 'tenant_id' => self::T, 'nome' => 'Negociado', 'valor_total' => 250, 'qtd_beneficiarios' => 1, 'plano_id' => 7, 'venda_app' => 'liberados'],
            ['id' => 3, 'tenant_id' => self::T, 'nome' => 'Só balcão', 'valor_total' => 400, 'qtd_beneficiarios' => 3, 'plano_id' => 7, 'venda_app' => 'nao'],
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** Contrato ativo do pacote 1: pagante + filho, cada um com uma parcela. */
    private function contrato(string $status = 'ativo', ?string $fim = null): array
    {
        $c = DB::table('pacote_contratos')->insertGetId(['tenant_id' => self::T, 'pacote_id' => 1, 'pagante_usuario_id' => self::PAGANTE[0], 'status' => $status, 'valor_total' => 300, 'data_fim' => $fim]);
        $mp = DB::table('matriculas')->insertGetId(['tenant_id' => self::T, 'aluno_id' => self::PAGANTE[1], 'pacote_contrato_id' => $c, 'status_id' => $status === 'ativo' ? 1 : 5, 'data_vencimento' => $fim]);
        $mf = DB::table('matriculas')->insertGetId(['tenant_id' => self::T, 'aluno_id' => self::FILHO[1], 'pacote_contrato_id' => $c, 'status_id' => $status === 'ativo' ? 1 : 5, 'data_vencimento' => $fim]);

        return [$c, $mp, $mf];
    }

    private function parcela(int $matriculaId, int $alunoId, float $valor, string $venc, int $status = 1, ?int $contrato = null): int
    {
        return DB::table('pagamentos_plano')->insertGetId([
            'tenant_id' => self::T, 'aluno_id' => $alunoId, 'matricula_id' => $matriculaId, 'pacote_contrato_id' => $contrato,
            'valor' => $valor, 'data_vencimento' => $venc, 'status_pagamento_id' => $status,
            'data_pagamento' => $status === 2 ? '2026-10-20' : null, 'forma_pagamento_id' => $status === 2 ? 3 : null, 'observacoes' => 'Pagamento do pacote',
        ]);
    }

    private function conciliacao(): PacoteConciliacaoService
    {
        $repo = Mockery::mock(AdminPacoteRepository::class)->makePartial();
        // UPDATE … JOIN só existe no MySQL: aqui basta saber que o contrato foi ativado
        $repo->shouldReceive('ativarContratoComVigenciaDasMatriculas')->andReturnUsing(fn ($c) => DB::table('pacote_contratos')->where('id', $c)->update(['status' => 'ativo']));
        $repo->shouldReceive('ativarBeneficiariosDoContrato');
        $pp = Mockery::mock(PagamentoPlanoService::class);
        $pp->shouldReceive('atualizarStatusMatricula');

        return new PacoteConciliacaoService($repo, $pp);
    }

    public function test_pagamento_do_pacote_quita_o_grupo_e_ativa_o_contrato(): void
    {
        [$c, $mp, $mf] = $this->contrato('pendente');
        $pagante = $this->parcela($mp, self::PAGANTE[1], 150, '2026-11-26', 2, $c);
        $filho = $this->parcela($mf, self::FILHO[1], 150, '2026-11-26', 1, $c);
        $outroPeriodo = $this->parcela($mf, self::FILHO[1], 150, '2026-12-26', 1, $c);
        DB::table('pacote_cobrancas')->insert(['tenant_id' => self::T, 'pacote_contrato_id' => $c, 'pagamento_plano_id' => $pagante, 'valor' => 300]);

        $this->assertTrue($this->conciliacao()->conciliar(self::T, $c));

        $f = DB::table('pagamentos_plano')->find($filho);
        $this->assertSame(2, (int) $f->status_pagamento_id);
        $this->assertSame('2026-10-20', $f->data_pagamento);
        $this->assertSame(3, (int) $f->forma_pagamento_id);
        $this->assertSame(1, (int) DB::table('pagamentos_plano')->find($outroPeriodo)->status_pagamento_id, 'outro vencimento não é quitado');
        $this->assertSame('ativo', DB::table('pacote_contratos')->find($c)->status);
        $this->assertSame(1, (int) DB::table('matriculas')->find($mf)->status_id);
        $this->assertFalse($this->conciliacao()->conciliar(self::T, $c), 'idempotente');
    }

    public function test_baixa_comum_da_parte_do_pagante_nao_quita_os_demais(): void
    {
        [$c, $mp, $mf] = $this->contrato('pendente');
        $this->parcela($mp, self::PAGANTE[1], 150, '2026-11-26', 2, $c); // sem pacote_cobrancas
        $filho = $this->parcela($mf, self::FILHO[1], 150, '2026-11-26', 1, $c);

        $this->assertFalse($this->conciliacao()->conciliar(self::T, $c));
        $this->assertSame(1, (int) DB::table('pagamentos_plano')->find($filho)->status_pagamento_id);
        $this->assertSame('pendente', DB::table('pacote_contratos')->find($c)->status);
    }

    private function venda(?PaymentGatewayFactory $gateways = null, ?PacoteDescontoService $descontos = null): MobilePacoteVendaService
    {
        $descontos ??= Mockery::mock(PacoteDescontoService::class);
        $cobrancas = Mockery::mock(CobrancaAnteriorService::class);
        $cobrancas->shouldReceive('idsEmAberto')->andReturn([]);
        $usuarios = Mockery::mock(UsuarioRepository::class);
        $usuarios->shouldReceive('findById')->andReturnUsing(fn ($id) => (array) DB::table('usuarios')->find($id));

        return new MobilePacoteVendaService(
            app(AdminPacoteRepository::class),
            Mockery::mock(\App\Services\Admin\AdminPacoteService::class),
            $descontos,
            $this->conciliacao(),
            $gateways ?? Mockery::mock(PaymentGatewayFactory::class),
            $usuarios,
            $cobrancas,
        );
    }

    public function test_vitrine_mostra_para_todos_e_liberado_so_para_quem_tem_liberacao_valida(): void
    {
        $nomes = fn (int $u) => array_column($this->venda()->disponiveis(self::T, $u)['body']['data']['pacotes'], 'nome');
        $this->assertSame(['Família'], $nomes(self::PAGANTE[0]));

        $lib = app(AdminPacoteLiberacaoService::class);
        $this->assertSame(200, $lib->liberar(self::T, 2, ['aluno_id' => self::PAGANTE[1], 'observacao' => 'Negociado no balcão'], 1)['status']);
        $this->assertSame(['Negociado', 'Família'], $nomes(self::PAGANTE[0]));
        $this->assertSame(['Família'], $nomes(self::OUTRO[0]), 'liberação é só do aluno');

        DB::table('pacote_liberacoes')->update(['valida_ate' => now()->subDay()->toDateString()]);
        $this->assertSame(['Família'], $nomes(self::PAGANTE[0]), 'liberação vencida some');

        $this->assertSame(422, $lib->liberar(self::T, 2, ['aluno_id' => self::PAGANTE[1], 'valida_ate' => '2000-01-01'], 1)['status']);
        $this->assertSame(404, $lib->liberar(self::T, 2, ['aluno_id' => 999], 1)['status']);
        $this->assertSame(404, $this->venda()->contratar(self::T, self::PAGANTE[0], 3)['status'], 'pacote só de balcão');
    }

    public function test_renovacao_so_na_janela_e_gera_parcelas_do_grupo_uma_vez(): void
    {
        $longe = now()->addDays(20)->toDateString();
        [$c] = $this->contrato('ativo', $longe);
        $this->assertSame('RENOVACAO_AINDA_NAO_LIBERADA', $this->venda()->renovar(self::T, self::PAGANTE[0], $c)['body']['code']);
        $this->assertSame(404, $this->venda()->renovar(self::T, self::FILHO[0], $c)['status'], 'só o pagante renova');

        $perto = now()->addDays(3)->toDateString();
        DB::table('pacote_contratos')->where('id', $c)->update(['data_fim' => $perto]);
        $descontos = Mockery::mock(PacoteDescontoService::class);
        $descontos->shouldReceive('resolverValorCheio')->andReturn(200.0);
        $descontos->shouldReceive('criarPagamentoPacote')->twice()->andReturnUsing(
            fn ($t, $aluno, $mat, $plano, $contrato, $nome, $cheio, $valor, $venc) => ['id' => $this->parcela($mat, $aluno, $valor, $venc, 1, $contrato)]
        );

        $r = $this->venda(null, $descontos)->renovar(self::T, self::PAGANTE[0], $c);
        $this->assertSame(201, $r['status'], json_encode($r['body']));
        $this->assertSame($perto, $r['body']['data']['vencimento']);
        $this->assertSame(300.0, round((float) DB::table('pagamentos_plano')->sum('valor'), 2));

        $de_novo = $this->venda(null, $descontos)->renovar(self::T, self::PAGANTE[0], $c);
        $this->assertTrue($de_novo['body']['data']['ja_gerada']);
    }

    public function test_cobranca_soma_o_periodo_e_registra_a_parcela_do_pagante(): void
    {
        [$c, $mp, $mf] = $this->contrato('pendente');
        $pagante = $this->parcela($mp, self::PAGANTE[1], 150, '2026-11-26', 1, $c);
        $this->parcela($mf, self::FILHO[1], 150, '2026-11-26', 1, $c);

        $pix = new class
        {
            public array $dados = [];

            public function criarPagamentoPix(array $d): array
            {
                $this->dados = $d;

                return ['id' => 'pay_pac1', 'ticket_url' => 'https://asaas/i/1', 'qr_code' => '000201', 'status' => 'pending', 'external_reference' => 'MAT-'.$d['matricula_id'].'-x'];
            }
        };
        $gateways = Mockery::mock(PaymentGatewayFactory::class);
        $gateways->shouldReceive('resolverPix')->andReturn(['ok' => true, 'provider' => 'asaas', 'instance' => $pix]);

        $r = $this->venda($gateways)->cobrar(self::T, self::PAGANTE[0], $c, ['metodo_pagamento' => 'pix']);

        $this->assertSame(200, $r['status'], json_encode($r['body']));
        $this->assertSame(300.0, $pix->dados['valor']);
        $this->assertSame($mp, $pix->dados['matricula_id']);
        $this->assertSame('000201', $r['body']['data']['pix']['qr_code']);
        $cob = DB::table('pacote_cobrancas')->first();
        $this->assertSame($pagante, (int) $cob->pagamento_plano_id);
        $this->assertSame('pay_pac1', $cob->payment_id);
        $this->assertSame('pay_pac1', DB::table('pagamentos_pix')->value('payment_id'));
        $this->assertSame('pay_pac1', DB::table('assinaturas')->value('gateway_preference_id'));
        $this->assertSame(404, $this->venda($gateways)->cobrar(self::T, self::FILHO[0], $c, [])['status'], 'só o pagante paga');
    }
}
