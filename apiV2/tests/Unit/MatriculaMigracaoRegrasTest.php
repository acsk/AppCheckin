<?php

namespace Tests\Unit;

use App\Repositories\UsuarioRepository;
use App\Services\MatriculaMigracaoAptidaoService;
use App\Services\MatriculaMigracaoService;
use App\Services\MercadoPagoService;
use App\Services\PagamentoPlanoService;
use App\Support\AcademyDateTime;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

/**
 * Regras de crédito e ramos de migrar() sobre SQLite em memória.
 * Aptidão, usuário, parcelas abertas e Mercado Pago são mockados.
 */
class MatriculaMigracaoRegrasTest extends TestCase
{
    use RequiresSqlite;

    private const TENANT = 1;

    private const USER = 10;

    private const ALUNO = 20;

    private const MATRICULA = 30;

    /** @var MatriculaMigracaoAptidaoService&MockInterface */
    private MockInterface $aptidao;

    /** @var PagamentoPlanoService&MockInterface */
    private MockInterface $pagamentosPlano;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireSqlite();
        $this->criarSchema();

        $this->aptidao = Mockery::mock(MatriculaMigracaoAptidaoService::class);
        $this->pagamentosPlano = Mockery::mock(PagamentoPlanoService::class);
        $this->pagamentosPlano->shouldReceive('cancelarParcelasAbertas')->andReturn(0)->byDefault();
        $this->aptidaoApta();
    }


    // ---------------------------------------------------------------- calcularCreditoMigracao

    public function test_upgrade_com_ciclo_vigente_gera_credito_valor_cheio_do_plano(): void
    {
        $this->pagamentoPago(501);

        $credito = $this->service()->calcularCreditoMigracao($this->matricula(), self::TENANT, self::MATRICULA, 500.0);

        $this->assertSame('valor_cheio_plano', $credito['tipo_credito']);
        $this->assertTrue($credito['tem_credito']);
        $this->assertSame(300.0, $credito['credito']);
        $this->assertSame(0.0, $credito['valor_consumido']);
        $this->assertSame(501, $credito['pagamento_origem_id']);
    }

    public function test_downgrade_gera_credito_proporcional_aos_dias_restantes(): void
    {
        $credito = $this->service()->calcularCreditoMigracao($this->matricula(), self::TENANT, self::MATRICULA, 100.0);

        $this->assertSame('proporcional', $credito['tipo_credito']);
        $this->assertTrue($credito['tem_credito']);
        $this->assertSame(30, $credito['dias_totais']);
        $this->assertSame(20, $credito['dias_restantes']);
        $this->assertSame(200.0, $credito['credito']);
        $this->assertNull($credito['pagamento_origem_id']);
    }

    public function test_ciclo_vencido_retorna_sem_credito(): void
    {
        $matricula = $this->matricula(['data_inicio' => $this->dia(-31), 'data_vencimento' => $this->dia(-1)]);

        $credito = $this->service()->calcularCreditoMigracao($matricula, self::TENANT, self::MATRICULA, 500.0);

        $this->assertSame('sem_credito', $credito['tipo_credito']);
        $this->assertFalse($credito['tem_credito']);
        $this->assertSame(0.0, $credito['credito']);
        $this->assertSame(300.0, $credito['valor_consumido']);
    }

    public function test_matricula_sem_aptidao_para_credito_retorna_sem_credito(): void
    {
        $this->aptidaoApta(geraCredito: false);

        $credito = $this->service()->calcularCreditoMigracao($this->matricula(), self::TENANT, self::MATRICULA, 500.0);

        $this->assertSame('sem_credito', $credito['tipo_credito']);
        $this->assertSame(0.0, $credito['credito']);
    }

    public function test_vencimento_antes_do_inicio_nao_gera_credito(): void
    {
        // Vencimento ainda no futuro, mas antes do início: sem a validação, diff()->days (absoluto)
        // daria 5 dias totais e 5 restantes → crédito de 100% do valor.
        $r = MatriculaMigracaoService::calcularCreditoProporcional(300.0, $this->dia(10), $this->dia(5), AcademyDateTime::today());

        $this->assertSame('sem_credito', $r['tipo_credito']);
        $this->assertFalse($r['tem_credito']);
        $this->assertTrue($r['ciclo_invalido']);
        $this->assertSame(0.0, $r['credito']);
        $this->assertSame(0, $r['dias_restantes']);
    }

    public function test_vencimento_datetime_no_dia_de_hoje_nao_gera_credito(): void
    {
        // Banco devolvendo DATETIME: como string, "Y-m-d 00:00:00" <= "Y-m-d" seria false.
        $matricula = $this->matricula([
            'data_inicio' => $this->dia(-30).' 00:00:00',
            'data_vencimento' => AcademyDateTime::today().' 00:00:00',
        ]);

        $credito = $this->service()->calcularCreditoMigracao($matricula, self::TENANT, self::MATRICULA, 100.0);

        $this->assertSame('sem_credito', $credito['tipo_credito']);
        $this->assertSame(0.0, $credito['credito']);
    }

    /**
     * @return array<string, array{string, string, ?string}>
     */
    public static function datasInvalidas(): array
    {
        return [
            'início inválido' => ['lixo', '2026-10-01', '2026-09-15'],
            'vencimento inválido' => ['2026-09-01', '2026-13-45', '2026-09-15'],
            'hoje inválido' => ['2026-09-01', '2026-10-01', 'ontem à noite'],
            'vencimento só com ano' => ['2026-09-01', '2026', '2026-09-15'],
            'vencimento relativo' => ['2026-09-01', 'tomorrow', '2026-09-15'],
            'início relativo' => ['+1 week', '2026-10-01', '2026-09-15'],
            'sem zero à esquerda' => ['2026-9-1', '2026-10-01', '2026-09-15'],
            'hora inválida' => ['2026-09-01 25:00:00', '2026-10-01', '2026-09-15'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('datasInvalidas')]
    public function test_datas_invalidas_nao_lancam_e_retornam_ciclo_invalido(string $inicio, string $venc, ?string $hoje): void
    {
        $r = MatriculaMigracaoService::calcularCreditoProporcional(300.0, $inicio, $venc, $hoje);

        $this->assertSame('sem_credito', $r['tipo_credito']);
        $this->assertFalse($r['tem_credito']);
        $this->assertTrue($r['ciclo_invalido']);
        $this->assertSame(0.0, $r['credito']);
        $this->assertSame(300.0, $r['valor_consumido']);
    }

    public function test_datas_datetime_do_banco_sao_aceitas(): void
    {
        $r = MatriculaMigracaoService::calcularCreditoProporcional(300.0, '2026-09-01 00:00:00', '2026-10-01 00:00:00', '2026-09-16');

        $this->assertFalse($r['ciclo_invalido']);
        $this->assertSame(150.0, $r['credito']);
    }

    public function test_valor_do_ciclo_zero_retorna_sem_credito_mesmo_com_dias_restantes(): void
    {
        $r = MatriculaMigracaoService::calcularCreditoProporcional(0.0, $this->dia(-10), $this->dia(20), AcademyDateTime::today());

        $this->assertSame(20, $r['dias_restantes']);
        $this->assertSame(0.0, $r['credito']);
        $this->assertSame('sem_credito', $r['tipo_credito']);
        $this->assertFalse($r['tem_credito']);
    }

    public function test_ciclo_futuro_nao_gera_credito_maior_que_o_valor(): void
    {
        // Ciclo de 30 dias que começa daqui a 10: sem o ajuste, restariam 40 dias (> 30).
        $r = MatriculaMigracaoService::calcularCreditoProporcional(300.0, $this->dia(10), $this->dia(40), AcademyDateTime::today());

        $this->assertSame(30, $r['dias_totais']);
        $this->assertSame(30, $r['dias_restantes']);
        $this->assertSame(0, $r['dias_usados']);
        $this->assertSame(300.0, $r['credito']);
    }

    public function test_aptidao_trata_vencimento_datetime_como_data(): void
    {
        $matriculas = Mockery::mock(\App\Repositories\MatriculaRepository::class);
        $matriculas->shouldReceive('avaliarLimiteMensalPorMatricula')->andReturn(null);
        $aptidao = new MatriculaMigracaoAptidaoService($matriculas);

        $resultado = $aptidao->avaliarAptidaoMigracao($this->matricula([
            'data_vencimento' => AcademyDateTime::today().' 00:00:00',
            'proxima_data_vencimento' => AcademyDateTime::today().' 00:00:00',
        ]), self::TENANT);

        $this->assertTrue($resultado['apto']);
        $this->assertFalse($resultado['gera_credito']);
        $this->assertSame('CICLO_ENCERRADO', $resultado['code']);
    }

    public function test_aptidao_aplica_fallback_apos_normalizar_datas(): void
    {
        $matriculas = Mockery::mock(\App\Repositories\MatriculaRepository::class);
        $matriculas->shouldReceive('avaliarLimiteMensalPorMatricula')->andReturn(null);
        $aptidao = new MatriculaMigracaoAptidaoService($matriculas);

        $acessoVencido = $aptidao->avaliarAptidaoMigracao($this->matricula([
            'data_vencimento' => $this->dia(-1),
            'proxima_data_vencimento' => '0000-00-00',
        ]), self::TENANT);
        $this->assertSame('MATRICULA_VENCIDA', $acessoVencido['code']);

        $cicloEncerrado = $aptidao->avaliarAptidaoMigracao($this->matricula([
            'id' => 0,
            'data_vencimento' => 'data-invalida',
            'proxima_data_vencimento' => AcademyDateTime::today(),
        ]), self::TENANT);
        $this->assertSame('CICLO_ENCERRADO', $cicloEncerrado['code']);
    }

    // ---------------------------------------------------------------- migrar()

    public function test_migrar_com_parcela_zero_ativa_direto_sem_mercado_pago(): void
    {
        $this->inserirMatricula();
        $this->inserirPlano(2, 100.0);
        $this->mercadoPagoNaoUsado();
        $this->pagamentosPlano->shouldReceive('cancelarParcelasAbertas')->once()
            ->with(self::TENANT, self::MATRICULA, Mockery::type('string'))->andReturn(0);

        $result = $this->service()->migrar(self::USER, self::TENANT, ['plano_id' => 2, 'metodo_pagamento' => 'checkout']);

        $this->assertSame(200, $result['status'], json_encode($result['body']));
        $this->assertSame('ativa', $result['body']['data']['status']);

        $matricula = $this->row('matriculas', self::MATRICULA);
        $this->assertSame(2, (int) $matricula['plano_id']);
        $this->assertSame(1, (int) $matricula['plano_anterior_id']);
        $this->assertSame(11, (int) $matricula['status_id']); // ativa
        $this->assertSame(AcademyDateTime::today(), $matricula['data_inicio']);

        // Crédito proporcional 200; aplicado 100 (valor do novo plano) → saldo 100, status ativo.
        $credito = DB::table('creditos_aluno')->first();
        $this->assertEquals(200.0, $credito->valor);
        $this->assertEquals(100.0, $credito->valor_utilizado);
        $this->assertSame(1, (int) $credito->status_credito_id);

        $pagamento = DB::table('pagamentos_plano')->where('matricula_id', self::MATRICULA)->first();
        $this->assertEquals(0.0, $pagamento->valor);
        // Crédito de 200 cobrindo plano de 100: aplicado é 100 (o saldo de 100 fica no crédito).
        $this->assertEquals(100.0, $pagamento->credito_aplicado);

        // Histórico registra o que foi cobrado (nada) e o abatimento na observação.
        $historico = DB::table('historico_planos')->first();
        $this->assertEquals(0.0, $historico->valor_pago);
        $this->assertSame('Migração de plano via app mobile (plano R$ 100,00, crédito aplicado R$ 100,00)', $historico->observacoes);
        $this->assertSame(2, (int) $pagamento->status_pagamento_id);
        $this->assertSame(0, DB::table('assinaturas')->count());
    }

    public function test_migrar_upgrade_com_parcela_gera_pendente_e_checkout(): void
    {
        $this->inserirMatricula();
        $this->inserirPlano(2, 500.0);
        $this->pagamentoPago(501);

        $mp = $this->mercadoPago();
        $mp->shouldReceive('criarPreferenciaPagamento')->once()
            ->with(Mockery::on(fn ($d) => $d['valor'] === 200.0 && $d['matricula_id'] === self::MATRICULA))
            ->andReturn(['id' => 'pref-1', 'init_point' => 'https://mp/checkout', 'external_reference' => 'MAT-30-x']);

        $result = $this->service()->migrar(self::USER, self::TENANT, ['plano_id' => 2, 'metodo_pagamento' => 'checkout']);

        $this->assertSame(200, $result['status'], json_encode($result['body']));
        $this->assertSame('pendente', $result['body']['data']['status']);
        $this->assertSame('https://mp/checkout', $result['body']['data']['payment_url']);
        $this->assertSame('pagamento_unico', $result['body']['data']['tipo_pagamento']);

        $matricula = $this->row('matriculas', self::MATRICULA);
        $this->assertSame(12, (int) $matricula['status_id']); // pendente
        // Pendente mantém a vigência atual até o pagamento confirmar.
        $this->assertSame($this->dia(-10), $matricula['data_inicio']);

        // Valor cheio do plano atual vira crédito e é todo aplicado; pagamento de origem é cancelado.
        $credito = DB::table('creditos_aluno')->first();
        $this->assertEquals(300.0, $credito->valor);
        $this->assertSame(2, (int) $credito->status_credito_id);
        $this->assertSame(4, (int) DB::table('pagamentos_plano')->where('id', 501)->value('status_pagamento_id'));

        $novo = DB::table('pagamentos_plano')->where('status_pagamento_id', 1)->first();
        $this->assertEquals(200.0, $novo->valor);
        $this->assertEquals(200.0, DB::table('historico_planos')->value('valor_pago'));
        $this->assertEquals(300.0, $novo->credito_aplicado);

        $assinatura = DB::table('assinaturas')->first();
        $this->assertSame('avulso', $assinatura->tipo_cobranca);
        $this->assertSame('pref-1', $assinatura->gateway_preference_id);
        $this->assertNull($assinatura->proxima_cobranca);
    }

    public function test_migrar_recorrente_trimestral_agenda_proxima_cobranca_pelo_ciclo(): void
    {
        $this->inserirMatricula();
        $this->inserirPlano(2, 500.0);
        DB::table('assinatura_frequencias')->insert(['id' => 3, 'nome' => 'Trimestral', 'codigo' => 'trimestral', 'meses' => 3]);
        DB::table('plano_ciclos')->insert([
            'id' => 7, 'plano_id' => 2, 'tenant_id' => self::TENANT, 'ativo' => 1, 'valor' => 900.0,
            'meses' => 3, 'permite_recorrencia' => 1, 'assinatura_frequencia_id' => 3,
        ]);

        $mp = $this->mercadoPago();
        $mp->shouldReceive('criarPreferenciaAssinatura')->once()
            ->with(Mockery::type('array'), 3)
            ->andReturn(['id' => 'sub-1', 'init_point' => 'https://mp/assinatura']);

        $result = $this->service()->migrar(self::USER, self::TENANT, [
            'plano_id' => 2, 'plano_ciclo_id' => 7, 'metodo_pagamento' => 'checkout',
        ]);

        $this->assertSame(200, $result['status'], json_encode($result['body']));
        $this->assertSame('assinatura', $result['body']['data']['tipo_pagamento']);

        $assinatura = DB::table('assinaturas')->first();
        $this->assertSame('recorrente', $assinatura->tipo_cobranca);
        $this->assertSame(3, (int) $assinatura->frequencia_id);
        $this->assertSame(
            AcademyDateTime::now()->modify('+3 months')->format('Y-m-d'),
            $assinatura->proxima_cobranca,
        );
    }

    public function test_migrar_reaproveita_assinatura_pendente_e_encerra_so_do_tenant(): void
    {
        $this->inserirMatricula();
        $this->inserirPlano(2, 500.0);
        // Assinatura pendente desta matrícula (reaproveitada) e uma de outro tenant que não pode mudar.
        DB::table('assinaturas')->insert([
            ['id' => 70, 'tenant_id' => self::TENANT, 'matricula_id' => self::MATRICULA, 'status_id' => 1, 'valor' => 300.0, 'payment_url' => 'antiga'],
            ['id' => 71, 'tenant_id' => 999, 'matricula_id' => self::MATRICULA, 'status_id' => 1, 'valor' => 1.0, 'payment_url' => 'outro-tenant'],
        ]);

        $mp = $this->mercadoPago();
        $mp->shouldReceive('criarPreferenciaPagamento')->once()
            ->andReturn(['id' => 'pref-2', 'init_point' => 'https://mp/nova']);

        $result = $this->service()->migrar(self::USER, self::TENANT, ['plano_id' => 2, 'metodo_pagamento' => 'checkout']);

        $this->assertSame(200, $result['status'], json_encode($result['body']));
        $this->assertSame('https://mp/nova', DB::table('assinaturas')->where('id', 70)->value('payment_url'));
        $this->assertSame('outro-tenant', DB::table('assinaturas')->where('id', 71)->value('payment_url'));
        $this->assertSame(2, DB::table('assinaturas')->count());
    }

    public function test_migrar_preserva_assinatura_vigente_quando_status_final_nao_existe(): void
    {
        $this->inserirMatricula();
        $this->inserirPlano(2, 500.0);
        DB::table('assinatura_status')->whereIn('codigo', ['paga', 'ativa'])->delete();
        DB::table('assinatura_status')->insert(['id' => 3, 'codigo' => 'approved']);
        DB::table('assinaturas')->insert([
            'id' => 72,
            'tenant_id' => self::TENANT,
            'matricula_id' => self::MATRICULA,
            'status_id' => 3,
            'status_gateway' => 'approved',
            'valor' => 300.0,
        ]);

        $mp = $this->mercadoPago();
        $mp->shouldReceive('criarPreferenciaPagamento')->once()
            ->andReturn(['id' => 'pref-status-final', 'init_point' => 'https://mp/nova']);

        $result = $this->service()->migrar(self::USER, self::TENANT, ['plano_id' => 2, 'metodo_pagamento' => 'checkout']);

        $this->assertSame(200, $result['status'], json_encode($result['body']));
        $this->assertSame(3, (int) DB::table('assinaturas')->where('id', 72)->value('status_id'));
        $this->assertSame('approved', DB::table('assinaturas')->where('id', 72)->value('status_gateway'));
    }

    public function test_migrar_ciclo_sem_frequencia_vinculada_usa_nome_pela_duracao(): void
    {
        $this->inserirMatricula();
        $this->inserirPlano(2, 500.0);
        // Ciclo trimestral sem assinatura_frequencia_id → LEFT JOIN traz ciclo_nome NULL.
        DB::table('plano_ciclos')->insert([
            'id' => 8, 'plano_id' => 2, 'tenant_id' => self::TENANT, 'ativo' => 1, 'valor' => 900.0,
            'meses' => 3, 'permite_recorrencia' => 0, 'assinatura_frequencia_id' => null,
        ]);

        $mp = $this->mercadoPago();
        $mp->shouldReceive('criarPreferenciaPagamento')->once()
            ->with(Mockery::on(fn ($d) => str_contains($d['descricao'], '(Trimestral)')))
            ->andReturn(['id' => 'pref-3', 'init_point' => 'https://mp/x']);

        $result = $this->service()->migrar(self::USER, self::TENANT, [
            'plano_id' => 2, 'plano_ciclo_id' => 8, 'metodo_pagamento' => 'checkout',
        ]);

        $this->assertSame(200, $result['status'], json_encode($result['body']));
        $this->assertSame('Trimestral', $result['body']['data']['plano_novo']['ciclo_nome']);
        // Sem frequência vinculada e sem código 'trimestral' cadastrado: fallback legado (4 = mensal).
        $this->assertSame(4, (int) DB::table('assinaturas')->value('frequencia_id'));
    }

    /**
     * @return array<string, array{?string, ?int, int}>
     */
    public static function frequenciasDoCiclo(): array
    {
        return [
            // nome NULL/vazio na frequência, mas vínculo válido → usa o id vinculado (9)
            'frequência sem nome' => [null, 9, 9],
            'frequência com nome vazio' => ['', 9, 9],
            // id órfão (frequência inexistente) → fallback legado por nome (4 = mensal)
            'id órfão' => ['Trimestral', 555, 4],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('frequenciasDoCiclo')]
    public function test_frequencia_da_assinatura_vem_do_vinculo_do_ciclo(?string $nomeFrequencia, int $frequenciaCiclo, int $esperado): void
    {
        $this->inserirMatricula();
        $this->inserirPlano(2, 500.0);
        if ($frequenciaCiclo !== 555) {
            DB::table('assinatura_frequencias')->insert(['id' => $frequenciaCiclo, 'nome' => $nomeFrequencia, 'codigo' => 'bimestral_x', 'meses' => 2]);
        }
        DB::table('plano_ciclos')->insert([
            'id' => 9, 'plano_id' => 2, 'tenant_id' => self::TENANT, 'ativo' => 1, 'valor' => 600.0,
            'meses' => 2, 'permite_recorrencia' => 0, 'assinatura_frequencia_id' => $frequenciaCiclo,
        ]);

        $mp = $this->mercadoPago();
        $mp->shouldReceive('criarPreferenciaPagamento')->once()->andReturn(['id' => 'p', 'init_point' => 'u']);

        $result = $this->service()->migrar(self::USER, self::TENANT, [
            'plano_id' => 2, 'plano_ciclo_id' => 9, 'metodo_pagamento' => 'checkout',
        ]);

        $this->assertSame(200, $result['status'], json_encode($result['body']));
        $this->assertSame($esperado, (int) DB::table('assinaturas')->value('frequencia_id'));
    }

    public function test_migrar_pix_salva_registro_pix(): void
    {
        $this->inserirMatricula();
        $this->inserirPlano(2, 500.0);

        $mp = $this->mercadoPago();
        $mp->shouldReceive('criarPagamentoPix')->once()->andReturn([
            'id' => 999, 'status' => 'pending', 'ticket_url' => 'https://mp/pix',
            'qr_code' => 'abc', 'qr_code_base64' => 'b64', 'date_of_expiration' => '2026-10-02T12:00:00.000-03:00',
        ]);

        $result = $this->service()->migrar(self::USER, self::TENANT, ['plano_id' => 2, 'metodo_pagamento' => 'pix']);

        $this->assertSame(200, $result['status'], json_encode($result['body']));
        $this->assertSame('pix', $result['body']['data']['tipo_pagamento']);
        $pix = DB::table('pagamentos_pix')->first();
        $this->assertSame('999', (string) $pix->payment_id);
        $this->assertSame('2026-10-02 12:00:00', $pix->expires_at);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function expiracoesPix(): array
    {
        return [
            'sem offset → horário da academia' => ['2026-10-02 12:00:00', '2026-10-02 12:00:00'],
            'offset do MP (-04:00) → São Paulo' => ['2026-10-02T12:00:00.000-04:00', '2026-10-02 13:00:00'],
            'UTC → São Paulo' => ['2026-10-02T12:00:00Z', '2026-10-02 09:00:00'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('expiracoesPix')]
    public function test_migrar_pix_grava_expiracao_no_fuso_da_academia(string $doGateway, string $gravado): void
    {
        $this->inserirMatricula();
        $this->inserirPlano(2, 500.0);
        $mp = $this->mercadoPago();
        $mp->shouldReceive('criarPagamentoPix')->once()->andReturn([
            'id' => 2000, 'status' => 'pending', 'ticket_url' => 'https://mp/pix', 'date_of_expiration' => $doGateway,
        ]);

        // Processo em outro fuso: o resultado não pode depender dele.
        $original = date_default_timezone_get();
        date_default_timezone_set('Asia/Tokyo');
        try {
            $result = $this->service()->migrar(self::USER, self::TENANT, ['plano_id' => 2, 'metodo_pagamento' => 'pix']);
        } finally {
            date_default_timezone_set($original);
        }

        $this->assertSame(200, $result['status'], json_encode($result['body']));
        $this->assertSame($gravado, DB::table('pagamentos_pix')->value('expires_at'));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function expiracoesPixInvalidas(): array
    {
        return [
            'texto livre' => ['amanhã cedo'],
            'relativo (DateTime aceitaria)' => ['tomorrow'],
            'só ano (viraria 20:26 de hoje)' => ['2026'],
            'timestamp' => ['1727740800'],
            'dia inexistente (rolaria p/ março)' => ['2026-02-30T10:00:00-03:00'],
            'hora inválida' => ['2026-10-02T25:00:00Z'],
            'offset além do máximo' => ['2026-10-02T12:00:00+14:30'],
            'minutos do offset inválidos' => ['2026-10-02T12:00:00-03:60'],
            'offset de 14 horas com minutos' => ['2026-10-02T12:00:00+14:01'],
            'só data' => ['2026-10-02'],
            'não-string' => [1727740800],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('expiracoesPixInvalidas')]
    public function test_migrar_pix_com_expiracao_invalida_grava_null_e_nao_aborta(mixed $expiracao): void
    {
        $this->inserirMatricula();
        $this->inserirPlano(2, 500.0);

        $mp = $this->mercadoPago();
        $mp->shouldReceive('criarPagamentoPix')->once()->andReturn([
            'id' => 1000, 'status' => 'pending', 'ticket_url' => 'https://mp/pix', 'date_of_expiration' => $expiracao,
        ]);

        \Illuminate\Support\Facades\Log::spy();

        $result = $this->service()->migrar(self::USER, self::TENANT, ['plano_id' => 2, 'metodo_pagamento' => 'pix']);

        $this->assertSame(200, $result['status'], json_encode($result['body']));
        $this->assertNull(DB::table('pagamentos_pix')->value('expires_at'));
        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $msg, array $ctx) => str_contains($msg, 'date_of_expiration') && $ctx['valor'] === $expiracao,
        );
    }

    public function test_servico_usa_o_pdo_atual_da_conexao_apos_troca(): void
    {
        $service = $this->service();
        $lookup = $this->metodoPrivado('lookupId');
        $this->assertSame(11, $lookup->invoke($service, 'status_matricula', 'ativa', null));

        // Simula reconnect/troca: a conexão passa a usar outro banco depois do serviço criado.
        $novo = new \PDO('sqlite::memory:');
        $novo->exec('CREATE TABLE status_matricula (id INTEGER PRIMARY KEY, codigo TEXT)');
        $novo->exec("INSERT INTO status_matricula (id, codigo) VALUES (77, 'ativa')");
        DB::connection()->setPdo($novo);

        $this->assertSame(77, $lookup->invoke($service, 'status_matricula', 'ativa', null));
    }

    public function test_lookup_id_recusa_tabela_fora_da_lista(): void
    {
        $lookup = $this->metodoPrivado('lookupId');

        $this->assertSame(11, $lookup->invoke($this->service(), 'status_matricula', 'ativa', null));

        $this->expectException(\InvalidArgumentException::class);
        $lookup->invoke($this->service(), 'usuarios; DROP TABLE matriculas; --', 'x', null);
    }

    public function test_resolver_contexto_usa_flags_recebidas_sem_reler_parametros(): void
    {
        $this->inserirMatricula();
        $this->inserirPlano(2, 500.0);
        DB::table('assinatura_frequencias')->insert(['id' => 3, 'nome' => 'Trimestral', 'codigo' => 'trimestral', 'meses' => 3]);
        DB::table('plano_ciclos')->insert([
            'id' => 7, 'plano_id' => 2, 'tenant_id' => self::TENANT, 'ativo' => 1, 'valor' => 900.0,
            'meses' => 3, 'permite_recorrencia' => 1, 'assinatura_frequencia_id' => 3,
        ]);
        $resolver = $this->metodoPrivado('resolverContexto');
        $service = $this->service();

        // No banco o cartão está habilitado → ciclo recorrente.
        $lidoDoBanco = $resolver->invoke($service, self::USER, self::TENANT, 2, 7);
        $this->assertTrue($lidoDoBanco['is_recorrente']);

        // Flags recebidas (cartão desligado) prevalecem: não relê os parâmetros.
        $comFlags = $resolver->invoke($service, self::USER, self::TENANT, 2, 7, [
            'habilitar_pix' => true,
            'habilitar_cartao_credito' => false,
        ]);
        $this->assertFalse($comFlags['is_recorrente']);
    }

    public function test_flags_de_pagamento_nao_releem_o_banco_no_mesmo_fluxo(): void
    {
        // Parametro usa PDO direto (fora do query log do Laravel): prova pela leitura de um valor
        // alterado no banco entre as chamadas — se relesse, veria o novo valor.
        (new \ReflectionProperty(\App\Models\Parametro::class, 'cache'))->setValue(null, []);
        $primeira = \App\Support\MobilePagamentoMetodos::flags(self::TENANT);

        DB::table('parametros')->update(['valor_padrao' => 'false']);
        $segunda = \App\Support\MobilePagamentoMetodos::flags(self::TENANT);

        $this->assertSame(['habilitar_pix' => true, 'habilitar_cartao_credito' => true], $primeira);
        $this->assertSame($primeira, $segunda);
    }

    public function test_migrar_falha_do_mercado_pago_retorna_erro_pagamento_com_matricula_pendente(): void
    {
        $this->inserirMatricula();
        $this->inserirPlano(2, 500.0);

        $mp = $this->mercadoPago();
        $mensagemExcecao = 'MP fora do ar: token APP_USR-123';
        $mp->shouldReceive('criarPreferenciaPagamento')->once()->andThrow(new \RuntimeException($mensagemExcecao));
        config(['app.debug' => true]);

        $result = $this->service()->migrar(self::USER, self::TENANT, ['plano_id' => 2, 'metodo_pagamento' => 'checkout']);

        $this->assertSame(500, $result['status']);
        $this->assertSame('ERRO_PAGAMENTO', $result['body']['code']);
        $this->assertArrayNotHasKey('mp_error', $result['body']);
        $this->assertStringNotContainsString($mensagemExcecao, json_encode($result['body']));
        $this->assertStringNotContainsString('APP_USR', json_encode($result['body']));
        $this->assertSame(self::MATRICULA, $result['body']['matricula_id']);
        $this->assertSame('pendente', $result['body']['data']['status']);
        // A migração em si já foi gravada; o aluno reabre o pagamento pendente.
        $this->assertSame(2, (int) $this->row('matriculas', self::MATRICULA)['plano_id']);
        $this->assertSame(0, DB::table('assinaturas')->count());
    }

    public function test_migrar_nao_deixa_registro_parcial_quando_persistencia_pos_gateway_falha(): void
    {
        $this->inserirMatricula();
        $this->inserirPlano(2, 500.0);

        $mp = $this->mercadoPago();
        $mp->shouldReceive('criarPagamentoPix')->once()->andReturn([
            'id' => 'pix-123',
            'ticket_url' => 'https://mp.test/ticket',
            'qr_code' => 'qr',
            'qr_code_base64' => 'qr64',
            'status' => 'pending',
            'external_reference' => 'MATRICULA-30-abcd1234',
        ]);

        // Quebra a gravação da assinatura, que ocorre depois do pagamento já existir no gateway.
        DB::statement('DROP TABLE assinaturas');

        $result = $this->service()->migrar(self::USER, self::TENANT, ['plano_id' => 2, 'metodo_pagamento' => 'pix']);

        $this->assertSame(500, $result['status']);
        $this->assertSame('ERRO_PAGAMENTO', $result['body']['code']);
        // A referência do gateway volta na resposta e vai para o log, permitindo reconciliação.
        $this->assertSame('MATRICULA-30-abcd1234', $result['body']['external_reference']);
        // PIX e assinatura compartilham a transação: nada fica gravado pela metade.
        $this->assertSame(0, DB::table('pagamentos_pix')->count());
        // A migração em si (commitada antes) permanece.
        $this->assertSame(2, (int) $this->row('matriculas', self::MATRICULA)['plano_id']);
    }

    public function test_migrar_pendente_regrava_vigencia_atual_normalizada(): void
    {
        $this->inserirMatricula();
        DB::table('matriculas')->where('id', self::MATRICULA)->update([
            'data_inicio' => $this->dia(-10).' 00:00:00',
            'data_vencimento' => $this->dia(20).' 00:00:00',
            'proxima_data_vencimento' => $this->dia(20).' 00:00:00',
        ]);
        $this->inserirPlano(2, 500.0);
        $mp = $this->mercadoPago();
        $mp->shouldReceive('criarPreferenciaPagamento')->once()->andReturn(['id' => 'p', 'init_point' => 'u']);

        $result = $this->service()->migrar(self::USER, self::TENANT, ['plano_id' => 2, 'metodo_pagamento' => 'checkout']);

        $this->assertSame(200, $result['status'], json_encode($result['body']));
        $matricula = $this->row('matriculas', self::MATRICULA);
        $this->assertSame($this->dia(-10), $matricula['data_inicio']);
        $this->assertSame($this->dia(20), $matricula['data_vencimento']);
        $this->assertSame($this->dia(20), $matricula['proxima_data_vencimento']);
    }

    public function test_migrar_aborta_sem_gravar_se_update_nao_encontrar_matricula_no_tenant(): void
    {
        $this->inserirMatricula();
        $this->inserirPlano(2, 500.0);
        config(['app.debug' => true]);
        // Simula a matrícula mudando de tenant entre a leitura e a atualização.
        DB::statement("
            CREATE TRIGGER muda_tenant_antes_update BEFORE UPDATE ON matriculas
            BEGIN
                UPDATE matriculas SET tenant_id = 999 WHERE id = OLD.id;
                SELECT RAISE(IGNORE);
            END
        ");
        $this->mercadoPagoNaoUsado();

        $result = $this->service()->migrar(self::USER, self::TENANT, ['plano_id' => 2, 'metodo_pagamento' => 'checkout']);

        $this->assertSame(500, $result['status']);
        $this->assertSame('ERRO_INTERNO', $result['body']['code']);
        $this->assertArrayNotHasKey('error', $result['body']);
        $this->assertSame(0, DB::table('historico_planos')->count());
        $this->assertSame(0, DB::table('creditos_aluno')->count());
        $this->assertSame(0, DB::table('pagamentos_plano')->count());
    }

    public function test_migrar_nao_aborta_quando_row_count_zero_mas_matricula_existe_no_tenant(): void
    {
        $this->inserirMatricula();
        $this->inserirPlano(2, 500.0);
        DB::statement('CREATE TRIGGER ignora_update BEFORE UPDATE ON matriculas BEGIN SELECT RAISE(IGNORE); END');

        $mp = $this->mercadoPago();
        $mp->shouldReceive('criarPreferenciaPagamento')->once()
            ->andReturn(['id' => 'pref-row-count-zero', 'init_point' => 'https://mp/checkout']);

        $result = $this->service()->migrar(self::USER, self::TENANT, ['plano_id' => 2, 'metodo_pagamento' => 'checkout']);

        $this->assertSame(200, $result['status'], json_encode($result['body']));
        $this->assertSame(1, DB::table('matriculas')->where('id', self::MATRICULA)->where('tenant_id', self::TENANT)->count());
    }

    public function test_migrar_sem_status_pendente_configurado_nao_grava_nada(): void
    {
        $this->inserirMatricula();
        $this->inserirPlano(2, 500.0);
        DB::table('status_matricula')->where('codigo', 'pendente')->delete();
        $this->mercadoPagoNaoUsado();
        $this->pagamentosPlano->shouldNotReceive('cancelarParcelasAbertas');

        $result = $this->service()->migrar(self::USER, self::TENANT, ['plano_id' => 2, 'metodo_pagamento' => 'checkout']);

        $this->assertSame(500, $result['status']);
        $this->assertSame('ERRO_INTERNO', $result['body']['code']);
        $this->assertSame(1, (int) $this->row('matriculas', self::MATRICULA)['plano_id']);
        $this->assertSame(0, DB::table('creditos_aluno')->count());
    }

    // ---------------------------------------------------------------- helpers

    /** Acesso a método privado de MatriculaMigracaoService (padrão repetido nos testes acima). */
    private function metodoPrivado(string $nome): \ReflectionMethod
    {
        $metodo = new \ReflectionMethod(MatriculaMigracaoService::class, $nome);
        $metodo->setAccessible(true);

        return $metodo;
    }

    private function service(): MatriculaMigracaoService
    {
        $usuarios = Mockery::mock(UsuarioRepository::class);
        $usuarios->shouldReceive('findById')->andReturn([
            'id' => self::USER, 'nome' => 'Aluno Teste', 'email' => 'aluno@teste.com',
            'telefone' => '11999999999', 'cpf' => '12345678901',
        ]);

        return new MatriculaMigracaoService($usuarios, $this->aptidao, $this->pagamentosPlano);
    }

    private function aptidaoApta(bool $geraCredito = true): void
    {
        $this->aptidao->shouldReceive('avaliarAptidaoMigracao')->andReturn([
            'apto' => true,
            'gera_credito' => $geraCredito,
            'code' => $geraCredito ? 'OK' : 'SEM_PAGAMENTO_PAGO',
            'message' => $geraCredito ? 'Matrícula apta para migração' : 'Sem pagamento quitado neste ciclo',
            'motivo' => null,
        ])->byDefault();
    }

    private function mercadoPago(): MockInterface
    {
        $mp = Mockery::mock(MercadoPagoService::class);
        $this->app->bind(MercadoPagoService::class, fn () => $mp);

        return $mp;
    }

    private function mercadoPagoNaoUsado(): void
    {
        $this->app->bind(MercadoPagoService::class, function () {
            $this->fail('Mercado Pago não deveria ser chamado');
        });
    }

    private function dia(int $offset): string
    {
        return AcademyDateTime::now()->modify("{$offset} days")->format('Y-m-d');
    }

    /** Matrícula ativa: plano 1 (R$300), ciclo de 30 dias com 20 restantes. */
    private function matricula(array $extra = []): array
    {
        return array_merge([
            'id' => self::MATRICULA,
            'tenant_id' => self::TENANT,
            'aluno_id' => self::ALUNO,
            'plano_id' => 1,
            'plano_ciclo_id' => null,
            'plano_nome' => 'Plano Atual',
            'valor' => 300.0,
            'data_inicio' => $this->dia(-10),
            'data_vencimento' => $this->dia(20),
            'proxima_data_vencimento' => $this->dia(20),
            'dia_vencimento' => 5,
            'status_id' => 11,
            'status_codigo' => 'ativa',
        ], $extra);
    }

    private function inserirMatricula(): void
    {
        $m = $this->matricula();
        unset($m['plano_nome'], $m['status_codigo']);
        $m['updated_at'] = AcademyDateTime::nowFormatted();
        DB::table('matriculas')->insert($m);
    }

    private function inserirPlano(int $id, float $valor): void
    {
        DB::table('planos')->insert([
            'id' => $id, 'tenant_id' => self::TENANT, 'nome' => "Plano {$id}", 'valor' => $valor,
            'duracao_dias' => 30, 'modalidade_id' => 1, 'ativo' => 1,
        ]);
    }

    private function pagamentoPago(int $id): void
    {
        DB::table('pagamentos_plano')->insert([
            'id' => $id, 'tenant_id' => self::TENANT, 'aluno_id' => self::ALUNO, 'matricula_id' => self::MATRICULA,
            'plano_id' => 1, 'valor' => 300.0, 'data_vencimento' => $this->dia(-10), 'status_pagamento_id' => 2,
            'data_pagamento' => $this->dia(-10),
        ]);
    }

    private function row(string $table, int $id): array
    {
        return (array) DB::table($table)->where('id', $id)->first();
    }

    private function criarSchema(): void
    {
        $this->registrarFuncoesSqlite([
            'NOW' => [fn () => AcademyDateTime::nowFormatted(), 0],
            'CURDATE' => [fn () => AcademyDateTime::today(), 0],
            'CONCAT' => [fn (...$p) => implode('', $p), -1],
        ]);

        $ddl = [
            'CREATE TABLE alunos (id INTEGER PRIMARY KEY, usuario_id INTEGER)',
            'CREATE TABLE tenants (id INTEGER PRIMARY KEY, nome TEXT)',
            'CREATE TABLE modalidades (id INTEGER PRIMARY KEY, nome TEXT)',
            'CREATE TABLE planos (id INTEGER PRIMARY KEY, tenant_id INTEGER, nome TEXT, valor REAL, duracao_dias INTEGER, modalidade_id INTEGER, ativo INTEGER)',
            'CREATE TABLE assinatura_frequencias (id INTEGER PRIMARY KEY, nome TEXT, codigo TEXT, meses INTEGER)',
            'CREATE TABLE plano_ciclos (id INTEGER PRIMARY KEY, plano_id INTEGER, tenant_id INTEGER, ativo INTEGER, valor REAL, meses INTEGER, permite_recorrencia INTEGER, assinatura_frequencia_id INTEGER)',
            'CREATE TABLE parametros (id INTEGER PRIMARY KEY, codigo TEXT, valor_padrao TEXT, tipo_valor TEXT, ativo INTEGER)',
            'CREATE TABLE parametros_tenant (id INTEGER PRIMARY KEY, tenant_id INTEGER, parametro_id INTEGER, valor TEXT, ativo INTEGER)',
            'CREATE TABLE status_matricula (id INTEGER PRIMARY KEY, codigo TEXT)',
            'CREATE TABLE motivo_matricula (id INTEGER PRIMARY KEY, codigo TEXT)',
            'CREATE TABLE matriculas (id INTEGER PRIMARY KEY, tenant_id INTEGER, aluno_id INTEGER, plano_id INTEGER, plano_ciclo_id INTEGER, plano_anterior_id INTEGER, valor REAL, data_inicio TEXT, data_vencimento TEXT, proxima_data_vencimento TEXT, dia_vencimento INTEGER, status_id INTEGER, motivo_id INTEGER, observacoes TEXT, cancelado_por INTEGER, data_cancelamento TEXT, motivo_cancelamento TEXT, updated_at TEXT)',
            'CREATE TABLE historico_planos (id INTEGER PRIMARY KEY, usuario_id INTEGER, plano_anterior_id INTEGER, plano_novo_id INTEGER, data_inicio TEXT, data_vencimento TEXT, valor_pago REAL, motivo TEXT, observacoes TEXT, criado_por INTEGER)',
            'CREATE TABLE creditos_aluno (id INTEGER PRIMARY KEY, tenant_id INTEGER, aluno_id INTEGER, matricula_origem_id INTEGER, pagamento_origem_id INTEGER, valor REAL, valor_utilizado REAL DEFAULT 0, status_credito_id INTEGER DEFAULT 1, motivo TEXT, criado_por INTEGER, updated_at TEXT)',
            'CREATE TABLE pagamentos_plano (id INTEGER PRIMARY KEY, tenant_id INTEGER, aluno_id INTEGER, matricula_id INTEGER, plano_id INTEGER, valor REAL, credito_id INTEGER, credito_aplicado REAL, data_vencimento TEXT, status_pagamento_id INTEGER, data_pagamento TEXT, observacoes TEXT, criado_por INTEGER, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE assinatura_status (id INTEGER PRIMARY KEY, codigo TEXT)',
            'CREATE TABLE assinatura_gateways (id INTEGER PRIMARY KEY, codigo TEXT)',
            'CREATE TABLE metodos_pagamento (id INTEGER PRIMARY KEY, codigo TEXT)',
            'CREATE TABLE assinaturas (id INTEGER PRIMARY KEY, tenant_id INTEGER, matricula_id INTEGER, criado_em TEXT, aluno_id INTEGER, plano_id INTEGER, gateway_id INTEGER, gateway_assinatura_id TEXT, gateway_preference_id TEXT, external_reference TEXT, payment_url TEXT, status_id INTEGER, status_gateway TEXT, valor REAL, frequencia_id INTEGER, dia_cobranca INTEGER, data_inicio TEXT, proxima_cobranca TEXT, data_fim TEXT, tipo_cobranca TEXT, atualizado_em TEXT, metodo_pagamento_id INTEGER)',
            'CREATE TABLE pagamentos_pix (id INTEGER PRIMARY KEY, tenant_id INTEGER, matricula_id INTEGER, payment_id TEXT, ticket_url TEXT, qr_code TEXT, qr_code_base64 TEXT, expires_at TEXT, status TEXT)',
        ];
        foreach ($ddl as $sql) {
            DB::statement($sql);
        }

        DB::table('alunos')->insert(['id' => self::ALUNO, 'usuario_id' => self::USER]);
        DB::table('tenants')->insert(['id' => self::TENANT, 'nome' => 'Academia Teste']);
        DB::table('modalidades')->insert(['id' => 1, 'nome' => 'Cross']);
        DB::table('planos')->insert(['id' => 1, 'tenant_id' => self::TENANT, 'nome' => 'Plano Atual', 'valor' => 300.0, 'duracao_dias' => 30, 'modalidade_id' => 1, 'ativo' => 1]);
        DB::table('status_matricula')->insert([['id' => 11, 'codigo' => 'ativa'], ['id' => 12, 'codigo' => 'pendente']]);
        DB::table('motivo_matricula')->insert([['id' => 1, 'codigo' => 'nova'], ['id' => 2, 'codigo' => 'upgrade'], ['id' => 3, 'codigo' => 'downgrade']]);
        DB::table('assinatura_status')->insert([['id' => 1, 'codigo' => 'pendente'], ['id' => 2, 'codigo' => 'paga']]);
        DB::table('assinatura_gateways')->insert(['id' => 1, 'codigo' => 'mercadopago']);
        DB::table('metodos_pagamento')->insert([['id' => 1, 'codigo' => 'credit_card'], ['id' => 2, 'codigo' => 'pix']]);
        DB::table('assinatura_frequencias')->insert(['id' => 4, 'nome' => 'Mensal', 'codigo' => 'mensal', 'meses' => 1]);
        // PIX e cartão habilitados (padrão do parâmetro).
        DB::table('parametros')->insert([
            ['id' => 1, 'codigo' => 'habilitar_pix', 'valor_padrao' => 'true', 'tipo_valor' => 'boolean', 'ativo' => 1],
            ['id' => 2, 'codigo' => 'habilitar_cartao_credito', 'valor_padrao' => 'true', 'tipo_valor' => 'boolean', 'ativo' => 1],
        ]);
    }
}
