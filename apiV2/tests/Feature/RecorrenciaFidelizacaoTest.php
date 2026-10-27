<?php

namespace Tests\Feature;

use App\Services\Asaas\AsaasService;
use App\Services\Recorrencia\AdminRecorrenciaService;
use App\Services\Recorrencia\MobileRecorrenciaService;
use App\Services\Recorrencia\RecorrenciaAcesso;
use App\Services\Recorrencia\RecorrenciaService;
use App\Support\AcademyDateTime;
use App\Support\RecorrenciaCalculo as Calc;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

/**
 * Planos com fidelização: assinatura mensal (nunca venda parcelada), regularização no cancelamento,
 * renovação, falhas, webhook duplicado e isolamento entre academias (SQLite + Asaas falso).
 */
class RecorrenciaFidelizacaoTest extends TestCase
{
    use RequiresSqlite;

    private const T = 3;

    private const OUTRA = 4;

    /** usuario => aluno */
    private const ANA = [10, 100];

    private const BIA = [11, 101];

    /** Asaas falso: guarda o que o sistema pediu ao gateway */
    private object $asaas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireSqlite();
        $this->registrarFuncoesSqlite([
            'NOW' => [fn () => date('Y-m-d H:i:s'), 0],
            'CONCAT' => [fn (...$a) => implode('', $a), -1],
        ]);
        RecorrenciaAcesso::esquecerSchema();

        Schema::create('tenants', fn (Blueprint $t) => [$t->increments('id'), $t->string('nome')]);
        Schema::create('usuarios', function (Blueprint $t) {
            $t->increments('id');
            $t->string('nome');
            $t->string('email')->nullable();
            $t->string('telefone')->nullable();
            $t->string('cpf')->nullable();
            $t->boolean('ativo')->default(true);
        });
        Schema::create('alunos', fn (Blueprint $t) => [$t->increments('id'), $t->integer('usuario_id')]);
        Schema::create('tenant_usuario_papel', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('usuario_id');
            $t->integer('papel_id');
            $t->boolean('ativo')->default(true);
        });
        Schema::create('modalidades', fn (Blueprint $t) => [$t->increments('id'), $t->integer('tenant_id'), $t->string('nome')]);
        Schema::create('planos', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('modalidade_id');
            $t->string('nome');
            $t->decimal('valor', 10, 2);
            $t->boolean('ativo')->default(true);
        });
        Schema::create('assinatura_frequencias', fn (Blueprint $t) => [$t->increments('id'), $t->string('nome'), $t->integer('meses')]);
        Schema::create('plano_ciclos', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('plano_id');
            $t->integer('assinatura_frequencia_id');
            $t->integer('meses');
            $t->decimal('valor', 10, 2);
            $t->boolean('permite_recorrencia')->default(false);
            $t->boolean('permite_reposicao')->default(true);
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });
        Schema::create('status_matricula', fn (Blueprint $t) => [$t->increments('id'), $t->string('codigo')]);
        Schema::create('motivo_matricula', fn (Blueprint $t) => [$t->increments('id'), $t->string('codigo')]);
        Schema::create('matriculas', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('aluno_id');
            $t->integer('plano_id');
            $t->integer('plano_ciclo_id')->nullable();
            $t->string('tipo_cobranca')->nullable();
            $t->decimal('valor', 10, 2)->default(0);
            $t->integer('status_id');
            $t->integer('motivo_id')->default(1);
            $t->integer('matricula_anterior_id')->nullable();
            $t->integer('plano_anterior_id')->nullable();
            $t->date('data_matricula')->nullable();
            $t->date('data_inicio')->nullable();
            $t->date('data_vencimento')->nullable();
            $t->date('proxima_data_vencimento')->nullable();
            $t->integer('dia_vencimento')->nullable();
            $t->integer('periodo_teste')->default(0);
            $t->integer('criado_por')->nullable();
            $t->timestamps();
        });
        Schema::create('pagamentos_plano', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('matricula_id');
            $t->integer('aluno_id')->nullable();
            $t->integer('plano_id');
            $t->decimal('valor', 10, 2);
            $t->decimal('valor_original', 10, 2)->nullable();
            $t->decimal('desconto', 10, 2)->default(0);
            $t->string('motivo_desconto')->nullable();
            $t->date('data_vencimento');
            $t->date('data_pagamento')->nullable();
            $t->integer('status_pagamento_id')->default(1);
            $t->integer('forma_pagamento_id')->nullable();
            $t->integer('tipo_baixa_id')->nullable();
            $t->integer('criado_por')->nullable();
            $t->text('observacoes')->nullable();
            $t->timestamps();
        });
        Schema::create('formas_pagamento', fn (Blueprint $t) => [$t->increments('id'), $t->string('nome')]);
        Schema::create('tipos_baixa', fn (Blueprint $t) => [$t->increments('id'), $t->string('nome')]);
        Schema::create('creditos_aluno', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('aluno_id');
            $t->decimal('valor', 10, 2);
            $t->decimal('valor_utilizado', 10, 2)->default(0);
            $t->integer('status_credito_id');
        });
        Schema::create('status_pagamento', fn (Blueprint $t) => [$t->increments('id'), $t->string('nome')]);
        (require database_path('migrations/2026_10_28_120000_recorrencia_fidelizacao.php'))->up();

        DB::table('tenants')->insert([['id' => self::T, 'nome' => 'Cia'], ['id' => self::OUTRA, 'nome' => 'Outra']]);
        foreach (['ativa', 'vencida', 'cancelada', 'finalizada', 'pendente'] as $i => $c) {
            DB::table('status_matricula')->insert(['id' => $i + 1, 'codigo' => $c]);
        }
        DB::table('motivo_matricula')->insert([['id' => 1, 'codigo' => 'nova'], ['id' => 2, 'codigo' => 'renovacao'], ['id' => 3, 'codigo' => 'upgrade'], ['id' => 4, 'codigo' => 'downgrade']]);
        DB::table('formas_pagamento')->insert([['id' => 2, 'nome' => 'pix'], ['id' => 9, 'nome' => 'cartão']]);
        DB::table('tipos_baixa')->insert(['id' => 4, 'nome' => 'Integração']);
        DB::table('status_pagamento')->insert([['id' => 1, 'nome' => 'Aguardando'], ['id' => 2, 'nome' => 'Pago'], ['id' => 3, 'nome' => 'Atrasado'], ['id' => 4, 'nome' => 'Cancelado']]);
        foreach ([self::ANA, self::BIA] as [$u, $a]) {
            DB::table('usuarios')->insert(['id' => $u, 'nome' => "Aluna {$u}", 'email' => "u{$u}@x.com", 'cpf' => '52998224725']);
            DB::table('alunos')->insert(['id' => $a, 'usuario_id' => $u]);
            DB::table('tenant_usuario_papel')->insert(['tenant_id' => self::T, 'usuario_id' => $u, 'papel_id' => 1]);
        }
        DB::table('modalidades')->insert(['id' => 1, 'tenant_id' => self::T, 'nome' => 'Natação']);
        DB::table('planos')->insert(['id' => 7, 'tenant_id' => self::T, 'modalidade_id' => 1, 'nome' => 'Natação 2x', 'valor' => '120.00']);
        DB::table('assinatura_frequencias')->insert([
            ['id' => 4, 'nome' => 'Mensal', 'meses' => 1], ['id' => 5, 'nome' => 'Bimestral', 'meses' => 2], ['id' => 6, 'nome' => 'Trimestral', 'meses' => 3],
            ['id' => 7, 'nome' => 'Semestral', 'meses' => 6], ['id' => 8, 'nome' => 'Anual', 'meses' => 12],
        ]);
        DB::table('plano_ciclos')->insert([
            ['id' => 40, 'tenant_id' => self::T, 'plano_id' => 7, 'assinatura_frequencia_id' => 4, 'meses' => 1, 'valor' => '120.00', 'cobranca_mensal' => 0, 'regularizacao_ativa' => 0, 'politica_renovacao' => 'mensal'],
            ['id' => 50, 'tenant_id' => self::T, 'plano_id' => 7, 'assinatura_frequencia_id' => 5, 'meses' => 2, 'valor' => '200.00', 'cobranca_mensal' => 1, 'regularizacao_ativa' => 1, 'politica_renovacao' => 'mensal'],
            ['id' => 60, 'tenant_id' => self::T, 'plano_id' => 7, 'assinatura_frequencia_id' => 6, 'meses' => 3, 'valor' => '285.00', 'cobranca_mensal' => 1, 'regularizacao_ativa' => 1, 'politica_renovacao' => 'promocional'],
            ['id' => 70, 'tenant_id' => self::T, 'plano_id' => 7, 'assinatura_frequencia_id' => 7, 'meses' => 6, 'valor' => '540.00', 'cobranca_mensal' => 1, 'regularizacao_ativa' => 1, 'politica_renovacao' => 'encerrar'],
            ['id' => 80, 'tenant_id' => self::T, 'plano_id' => 7, 'assinatura_frequencia_id' => 8, 'meses' => 12, 'valor' => '960.00', 'cobranca_mensal' => 1, 'regularizacao_ativa' => 0, 'politica_renovacao' => 'mensal'],
        ]);

        $this->asaas = new class extends AsaasService
        {
            public array $assinaturas = [];

            public array $pagamentos = [];

            public array $chamadas = [];

            public function __construct() {}

            public function criarAssinatura(array $data): array
            {
                $id = 'sub_'.(count($this->assinaturas) + 1);
                $this->assinaturas[$id] = $data;
                // O Asaas gera a 1ª mensalidade na criação
                $this->pagamentos[$id] = [['id' => "pay_{$id}_1", 'subscription' => $id, 'dueDate' => $data['proximo_vencimento'], 'value' => (float) $data['valor'],
                    'status' => 'PENDING', 'billingType' => $data['billing_type'], 'invoiceUrl' => "https://asaas/i/{$id}_1"]];

                return ['id' => $id, 'customer' => 'cus_1', 'external_reference' => 'REC-'.$data['contrato_id'].'-x'];
            }

            public function pagamentosDaAssinatura(string $subscriptionId): array
            {
                return $this->pagamentos[$subscriptionId] ?? [];
            }

            public function obterPagamento(string $paymentId): array
            {
                foreach ($this->pagamentos as $lista) {
                    foreach ($lista as $p) {
                        if ($p['id'] === $paymentId) {
                            return $p;
                        }
                    }
                }

                return [];
            }

            public function atualizarValorAssinatura(string $subscriptionId, string $valor): void
            {
                $this->chamadas[] = ['atualizar', $subscriptionId, $valor];
            }

            public function cancelarAssinatura(string $subscriptionId): void
            {
                $this->chamadas[] = ['cancelar', $subscriptionId];
            }

            public function cancelarCobranca(string $paymentId): bool
            {
                $this->chamadas[] = ['cancelar_cobranca', $paymentId];

                return true;
            }

            public function pixDaCobranca(string $paymentId): array
            {
                return ['payload' => '000201', 'qr_code' => null];
            }
        };
        $this->app->bind(AsaasService::class, fn () => $this->asaas);
    }

    // ------------------------------------------------------------------ apoio

    private function svc(): RecorrenciaService
    {
        return app(RecorrenciaService::class);
    }

    /** Contrata pelo app (aceitando os termos exatos da oferta). */
    private function contratar(int $ciclo, array $usuario = self::ANA, string $metodo = 'cartao'): int
    {
        $oferta = collect($this->svc()->ofertas(self::T, 7))->firstWhere('plano_ciclo_id', $ciclo);
        $r = $this->svc()->contratar(self::T, $usuario[0], ['plano_ciclo_id' => $ciclo, 'metodo' => $metodo, 'termos_hash' => $oferta['termos_hash']], ['ip' => '10.0.0.1', 'user_agent' => 'teste']);
        $this->assertSame(201, $r['status'], json_encode($r['body'], JSON_UNESCAPED_UNICODE));

        return (int) $r['body']['data']['contrato_id'];
    }

    /** Volta o contrato N dias (início, aceite e fim da fidelização), para simular meses já vividos. */
    private function recuar(int $contratoId, int $dias): void
    {
        $c = DB::table('contratos_recorrencia')->find($contratoId);
        $inicio = date('Y-m-d', strtotime(AcademyDateTime::today()." -{$dias} days"));
        DB::table('contratos_recorrencia')->where('id', $contratoId)->update([
            'inicio_em' => $inicio, 'aceite_em' => $inicio.' 10:00:00', 'fim_fidelizacao_em' => Calc::maisMeses($inicio, (int) $c->fidelizacao_meses),
        ]);
        DB::table('recorrencia_cobrancas')->where('contrato_id', $contratoId)->where('numero', 1)->update(['vencimento' => $inicio]);
        $sub = $c->assinatura_gateway_id;
        foreach ($this->asaas->pagamentos[$sub] as $i => $p) {
            $this->asaas->pagamentos[$sub][$i]['dueDate'] = Calc::maisMeses($inicio, $i);
        }
    }

    /** Webhook do Asaas para a mensalidade N (cria se ainda não existe). */
    private function webhook(int $contratoId, int $numero, string $status = 'RECEIVED', string $evento = 'PAYMENT_RECEIVED'): ?string
    {
        $c = DB::table('contratos_recorrencia')->find($contratoId);
        $sub = $c->assinatura_gateway_id;
        $id = "pay_{$sub}_{$numero}";
        $p = ['id' => $id, 'subscription' => $sub, 'dueDate' => Calc::maisMeses((string) $c->inicio_em, $numero - 1), 'value' => (float) $c->valor_mensal_vigente,
            'status' => $status, 'billingType' => 'CREDIT_CARD', 'paymentDate' => in_array($status, ['RECEIVED', 'CONFIRMED'], true) ? AcademyDateTime::today() : null];
        $existe = false;
        foreach ($this->asaas->pagamentos[$sub] as $i => $x) {
            if ($x['id'] === $id) {
                $this->asaas->pagamentos[$sub][$i] = $p;
                $existe = true;
            }
        }
        if (! $existe) {
            $this->asaas->pagamentos[$sub][] = $p;
        }

        return $this->svc()->processarPagamento(self::T, $contratoId, ['id' => $id, 'subscription' => $sub], $evento);
    }

    /** Decimal lido do banco no formato "0.00" (a SQLite devolve "120" onde o MySQL devolve "120.00"). */
    private function v(mixed $valor): string
    {
        return Calc::reais(Calc::centavos((string) $valor));
    }

    private function contrato(int $id): object
    {
        return DB::table('contratos_recorrencia')->find($id);
    }

    // ------------------------------------------------------------------ cenários

    public function test_mensal_sem_fidelizacao_segue_o_fluxo_atual(): void
    {
        $ofertas = $this->svc()->ofertas(self::T, 7);
        $this->assertNotContains(40, array_column($ofertas, 'plano_ciclo_id'), 'ciclo mensal à vista não vira assinatura');
        DB::table('matriculas')->insert(['id' => 900, 'tenant_id' => self::T, 'aluno_id' => self::ANA[1], 'plano_id' => 7, 'plano_ciclo_id' => 40, 'status_id' => 1]);
        $this->assertFalse(app(RecorrenciaAcesso::class)->atualizar(self::T, 900), 'matrícula sem contrato usa a regra antiga');
        $r = $this->svc()->contratar(self::T, self::ANA[0], ['plano_ciclo_id' => 40, 'metodo' => 'cartao', 'termos_hash' => 'x']);
        $this->assertSame(404, $r['status']);
    }

    public function test_bimestral_cobra_100_por_mes_e_nunca_200_parcelado(): void
    {
        $id = $this->contratar(50);

        $sub = $this->asaas->assinaturas['sub_1'];
        $this->assertSame('100.00', $sub['valor']);
        $this->assertNull($sub['max_pagamentos'], 'política mensal: continua depois da fidelização');
        $this->assertArrayNotHasKey('installment_count', $sub);
        $c = $this->contrato($id);
        $this->assertSame(['120.00', '100.00', 2, 'pendente_ativacao'], [$this->v($c->valor_mensal_referencia), $this->v($c->valor_mensal_contratado), (int) $c->fidelizacao_meses, $c->status]);
        $this->assertSame('recorrente', DB::table('matriculas')->find($c->matricula_id)->tipo_cobranca);

        $this->assertSame('paga', $this->webhook($id, 1));
        $this->assertSame('ativo', $this->contrato($id)->status);
        $m = DB::table('matriculas')->find($c->matricula_id);
        $this->assertSame(1, (int) $m->status_id, 'matrícula ativa');
        $this->assertSame(Calc::maisUmMes((string) $c->inicio_em), $m->proxima_data_vencimento, 'uma mensalidade = um mês de acesso');

        $this->webhook($id, 2);
        $this->assertSame(['100.00', '100.00'], DB::table('pagamentos_plano')->where('matricula_id', $c->matricula_id)->orderBy('id')->pluck('valor')->map(fn ($v) => $this->v($v))->all());
        $this->assertSame(0, DB::table('pagamentos_plano')->where('valor', '200.00')->count());
    }

    public function test_trimestral_com_desconto_cancelado_apos_dois_meses_regulariza_50(): void
    {
        $id = $this->contratar(60);
        $this->recuar($id, 70);
        $this->webhook($id, 1);
        $this->webhook($id, 2);

        $sim = $this->svc()->simularCancelamento(self::T, $id);

        $this->assertSame(['25.00', 2, '50.00', '50.00', 'antecipado'], [$sim['desconto_mensal'], $sim['meses_usufruidos'], $sim['desconto_acumulado'], $sim['valor_regularizacao'], $sim['tipo']]);
        $this->assertContains('Meses futuros não entram no cálculo.', $sim['memoria']);
    }

    public function test_cancelamento_apos_um_mes_no_bimestral_regulariza_20(): void
    {
        $id = $this->contratar(50);
        $this->recuar($id, 40);
        $this->webhook($id, 1);

        $r = app(MobileRecorrenciaService::class)->cancelar(self::T, self::ANA[0], $id, 'mudei de cidade');

        $this->assertSame(200, $r['status']);
        $this->assertSame('solicitado', $r['body']['data']['status'], 'com valor a cobrar, a academia confirma');
        $this->assertSame('20.00', $r['body']['data']['simulacao']['valor_regularizacao']);
        $this->assertSame('cancelamento_solicitado', $this->contrato($id)->status);
        $this->assertContains(['cancelar', 'sub_1'], $this->asaas->chamadas, 'as cobranças futuras param na hora do pedido');

        $k = DB::table('cancelamentos_recorrencia')->first();
        $conf = $this->svc()->confirmarCancelamento(self::T, (int) $k->id, 1);
        $this->assertSame('20.00', $conf['body']['data']['valor_regularizacao']);
        $this->assertSame('20.00', $this->v(DB::table('pagamentos_plano')->find(DB::table('cancelamentos_recorrencia')->value('pagamento_plano_id'))->valor));
        $this->assertSame('cancelado', $this->contrato($id)->status, 'acesso pago já tinha acabado');
    }

    public function test_semestral_quatro_meses_regulariza_120(): void
    {
        $id = $this->contratar(70);
        $this->assertSame(6, $this->asaas->assinaturas['sub_1']['max_pagamentos'], 'política encerrar: o Asaas para na 6ª');
        $this->recuar($id, 130);
        foreach ([1, 2, 3, 4] as $n) {
            $this->webhook($id, $n);
        }

        $sim = $this->svc()->simularCancelamento(self::T, $id);

        $this->assertSame(['30.00', 4, '120.00'], [$sim['desconto_mensal'], $sim['meses_usufruidos'], $sim['valor_regularizacao']]);
    }

    public function test_cumprir_a_fidelizacao_zera_a_regularizacao_e_aplica_politica_mensal(): void
    {
        $id = $this->contratar(50);
        $this->recuar($id, 70);
        $this->webhook($id, 1);
        $this->webhook($id, 2);

        $c = $this->contrato($id);
        $this->assertSame(0, (int) $c->em_fidelizacao);
        $this->assertSame('120.00', $this->v($c->valor_mensal_vigente), 'política mensal aceita: passa ao valor de referência');
        $this->assertSame('100.00', $this->v($c->valor_mensal_contratado), 'o contratado fica no histórico');
        $this->assertContains(['atualizar', 'sub_1', '120.00'], $this->asaas->chamadas);
        $sim = $this->svc()->simularCancelamento(self::T, $id);
        $this->assertSame(['0.00', 'fim_prazo'], [$sim['valor_regularizacao'], $sim['tipo']]);
    }

    public function test_renovacao_sem_nova_fidelizacao_mantem_valor_sem_compromisso(): void
    {
        $id = $this->contratar(60);
        $this->recuar($id, 100);
        foreach ([1, 2, 3] as $n) {
            $this->webhook($id, $n);
        }

        $c = $this->contrato($id);
        $this->assertSame([0, '95.00'], [(int) $c->em_fidelizacao, $this->v($c->valor_mensal_vigente)]);
        $this->assertSame([], array_filter($this->asaas->chamadas, fn ($x) => $x[0] === 'atualizar'), 'promocional: nada muda no gateway');
        $this->webhook($id, 4);
        $this->assertSame('0.00', $this->svc()->simularCancelamento(self::T, $id)['valor_regularizacao'], 'depois do prazo, nada de penalidade');
    }

    public function test_cancelamento_com_mensalidade_vencida_fica_a_parte(): void
    {
        $id = $this->contratar(60);
        $this->recuar($id, 70);
        $this->webhook($id, 1);
        $this->webhook($id, 2, 'OVERDUE', 'PAYMENT_OVERDUE');

        $sim = $this->svc()->simularCancelamento(self::T, $id);

        $this->assertSame(1, $sim['meses_usufruidos'], 'mensalidade não paga não conta como desconto usufruído');
        $this->assertSame('25.00', $sim['valor_regularizacao']);
        $this->assertSame('95.00', $sim['valor_em_aberto']);
        $this->assertSame('120.00', $sim['total_a_pagar']);
        $this->assertSame('pagamento_pendente', $this->contrato($id)->status);
    }

    public function test_isencao_administrativa_total_e_parcial(): void
    {
        $id = $this->contratar(50);
        $this->recuar($id, 40);
        $this->webhook($id, 1);
        $semMotivo = $this->svc()->solicitarCancelamento(self::T, $id, 'admin', 1, ['isencao' => '20']);
        $this->assertSame(422, $semMotivo['status'], 'isenção pede justificativa');

        DB::table('contratos_recorrencia')->where('id', $id)->update(['status' => 'ativo']);
        DB::table('cancelamentos_recorrencia')->delete();
        $r = $this->svc()->solicitarCancelamento(self::T, $id, 'admin', 1, ['isencao' => '15', 'isencao_motivo' => 'Lesão comprovada', 'motivo' => 'saúde']);

        $this->assertSame('5.00', $r['body']['data']['valor_regularizacao']);
        $k = DB::table('cancelamentos_recorrencia')->first();
        $this->assertSame(['20.00', '15.00', 'Lesão comprovada'], [$this->v($k->valor_regularizacao), $this->v($k->valor_isencao), $k->isencao_motivo]);
    }

    public function test_falha_na_cobranca_e_inadimplencia(): void
    {
        $id = $this->contratar(50);
        $this->recuar($id, 40);
        $this->webhook($id, 1);
        $this->webhook($id, 2, 'PENDING', 'PAYMENT_CREATED');

        $this->assertSame('recusado', $this->webhook($id, 2, 'PENDING', 'PAYMENT_CREDIT_CARD_CAPTURE_REFUSED'));
        $this->assertSame(1, (int) DB::table('recorrencia_cobrancas')->where('numero', 2)->value('tentativas'));
        $this->assertSame('pagamento_pendente', $this->contrato($id)->status);

        DB::table('recorrencia_cobrancas')->where('numero', 2)->update(['vencimento' => date('Y-m-d', strtotime('-10 days'))]);
        $this->svc()->rotina(self::T);
        $this->assertSame('inadimplente', $this->contrato($id)->status);

        $this->webhook($id, 2);
        $this->assertSame('ativo', $this->contrato($id)->status, 'pagou: volta a ativo');
    }

    public function test_webhook_duplicado_nao_baixa_duas_vezes(): void
    {
        $id = $this->contratar(50);
        $this->assertSame('paga', $this->webhook($id, 1));
        $this->assertSame('ja_paga', $this->webhook($id, 1));
        $this->assertSame('ja_paga', $this->webhook($id, 1, 'CONFIRMED', 'PAYMENT_CONFIRMED'));
        $this->svc()->sincronizar(self::T, $id);

        $this->assertSame(1, DB::table('pagamentos_plano')->count());
        $this->assertSame(1, DB::table('recorrencia_cobrancas')->count());
        $this->assertSame(1, DB::table('recorrencia_eventos')->where('tipo', 'mensalidade_paga')->count());
    }

    public function test_preco_alterado_depois_nao_muda_o_contrato_e_exige_novo_aceite(): void
    {
        $antiga = collect($this->svc()->ofertas(self::T, 7))->firstWhere('plano_ciclo_id', 50);
        $id = $this->contratar(50);
        DB::table('plano_ciclos')->where('id', 50)->update(['valor' => '220.00', 'termos_versao' => 2]);
        DB::table('planos')->where('id', 7)->update(['valor' => '130.00']);

        $c = $this->contrato($id);
        $this->assertSame(['120.00', '100.00'], [$this->v($c->valor_mensal_referencia), $this->v($c->valor_mensal_contratado)]);
        $this->webhook($id, 1);
        $this->assertSame('100.00', $this->v(DB::table('pagamentos_plano')->value('valor')));

        $r = $this->svc()->contratar(self::T, self::BIA[0], ['plano_ciclo_id' => 50, 'metodo' => 'pix', 'termos_hash' => $antiga['termos_hash']]);
        $this->assertSame(409, $r['status']);
        $this->assertSame('TERMOS_DESATUALIZADOS', $r['body']['code']);
        $this->assertSame('110.00', $r['body']['oferta']['valor_mensal']);
    }

    public function test_troca_de_plano_com_assinatura_ativa_e_recusada(): void
    {
        $id = $this->contratar(50);
        $this->webhook($id, 1);
        $oferta = collect($this->svc()->ofertas(self::T, 7))->firstWhere('plano_ciclo_id', 60);

        $r = $this->svc()->contratar(self::T, self::ANA[0], ['plano_ciclo_id' => 60, 'metodo' => 'cartao', 'termos_hash' => $oferta['termos_hash']]);

        $this->assertSame(409, $r['status']);
        $this->assertSame('ASSINATURA_EXISTENTE', $r['body']['code']);
        $this->assertCount(1, $this->asaas->assinaturas);
    }

    public function test_cobrancas_fora_de_ordem_e_duas_no_mesmo_mes_nao_se_misturam(): void
    {
        $id = $this->contratar(50);
        $this->recuar($id, 40);
        $this->webhook($id, 2, 'PENDING', 'PAYMENT_CREATED'); // chega antes da 1ª ser paga
        $this->webhook($id, 1);

        $this->assertSame([1, 2], DB::table('recorrencia_cobrancas')->orderBy('numero')->pluck('numero')->map(fn ($n) => (int) $n)->all());
        $c = $this->contrato($id);
        $outra = ['id' => 'pay_extra', 'subscription' => $c->assinatura_gateway_id, 'dueDate' => (string) $c->inicio_em, 'value' => 100.0, 'status' => 'PENDING', 'billingType' => 'PIX'];
        $this->asaas->pagamentos[$c->assinatura_gateway_id][] = $outra;
        $this->svc()->processarPagamento(self::T, $id, ['id' => 'pay_extra'], 'PAYMENT_CREATED');

        $this->assertSame(3, (int) DB::table('recorrencia_cobrancas')->where('gateway_payment_id', 'pay_extra')->value('numero'), 'não sobrescreve a mensalidade 1');
        $this->assertSame('paga', DB::table('recorrencia_cobrancas')->where('numero', 1)->value('status'));
    }

    public function test_isolamento_entre_academias(): void
    {
        $id = $this->contratar(50);
        DB::table('usuarios')->insert(['id' => 30, 'nome' => 'De fora', 'cpf' => '52998224725']);
        DB::table('alunos')->insert(['id' => 300, 'usuario_id' => 30]);
        DB::table('tenant_usuario_papel')->insert(['tenant_id' => self::OUTRA, 'usuario_id' => 30, 'papel_id' => 1]);

        $this->assertSame(404, app(AdminRecorrenciaService::class)->detalhe(self::OUTRA, $id)['status']);
        $this->assertNull($this->svc()->simularCancelamento(self::OUTRA, $id));
        $this->assertSame(404, $this->svc()->solicitarCancelamento(self::OUTRA, $id, 'admin', 1)['status']);
        $this->assertSame(404, app(MobileRecorrenciaService::class)->cancelar(self::T, self::BIA[0], $id, null)['status'], 'outra aluna não cancela');
        $this->assertSame([], app(MobileRecorrenciaService::class)->contratos(self::OUTRA, self::ANA[0])['body']['data']['contratos']);
        $this->assertSame(0, app(AdminRecorrenciaService::class)->indicadores(self::OUTRA)['body']['contratos_ativos']);
        $this->assertSame(404, $this->svc()->contratar(self::OUTRA, self::ANA[0], ['plano_ciclo_id' => 50, 'metodo' => 'cartao', 'termos_hash' => 'x'])['status']);
    }

    public function test_cancelamento_no_prazo_de_arrependimento_sem_regularizacao(): void
    {
        $id = $this->contratar(50);
        $this->webhook($id, 1);

        $r = app(MobileRecorrenciaService::class)->cancelar(self::T, self::ANA[0], $id, 'desisti');

        $this->assertSame('confirmado', $r['body']['data']['status'], 'nada a cobrar: confirma sozinho');
        $this->assertSame('0.00', $r['body']['data']['valor_regularizacao']);
        $k = DB::table('cancelamentos_recorrencia')->first();
        $this->assertSame('arrependimento', $k->tipo);
        $this->assertSame('cancelamento_agendado', $this->contrato($id)->status, 'acesso vai até o fim do mês pago');
        $this->assertNull($k->pagamento_plano_id);
    }

    public function test_nao_duplicidade_financeira_no_cancelamento(): void
    {
        $id = $this->contratar(50);
        $this->recuar($id, 40);
        $this->webhook($id, 1);
        $this->svc()->solicitarCancelamento(self::T, $id, 'admin', 1, ['motivo' => 'x']);
        $de_novo = $this->svc()->solicitarCancelamento(self::T, $id, 'admin', 1, ['motivo' => 'x']);
        $k = (int) DB::table('cancelamentos_recorrencia')->value('id');
        $this->svc()->confirmarCancelamento(self::T, $k, 1);

        $this->assertTrue($de_novo['body']['data']['ja_cancelada']);
        $this->assertSame(1, DB::table('cancelamentos_recorrencia')->count());
        $this->assertSame(1, DB::table('pagamentos_plano')->where('observacoes', 'like', 'Regularização%')->count());
    }

    public function test_politica_desligada_so_informa_o_valor(): void
    {
        $id = $this->contratar(80); // anual sem regularização
        $this->recuar($id, 70);
        $this->webhook($id, 1);
        $this->webhook($id, 2);

        $sim = $this->svc()->simularCancelamento(self::T, $id);

        $this->assertSame(['80.00', '0.00', false], [$sim['desconto_acumulado'], $sim['valor_regularizacao'], $sim['politica_cobra']]);
        $this->assertStringContainsString('não cobra regularização', implode(' ', $sim['memoria']));
    }

    public function test_calculo_em_centavos(): void
    {
        $this->assertSame(12345, Calc::centavos('123.45'));
        $this->assertSame(12340, Calc::centavos('123,4'));
        $this->assertSame('0.10', Calc::reais(Calc::centavos('0.1')));
        $this->assertSame('2026-02-28', Calc::maisUmMes('2026-01-31'));
        $this->assertSame('2026-03-31', Calc::maisMeses('2026-01-31', 2));
    }
}
