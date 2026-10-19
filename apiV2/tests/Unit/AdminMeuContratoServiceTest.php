<?php

namespace Tests\Unit;

use App\Repositories\PagamentoContratoRepository;
use App\Repositories\PlanoSistemaRepository;
use App\Repositories\TenantPlanoRepository;
use App\Services\Admin\AdminMeuContratoService;
use App\Services\SuperAdmin\CobrancaContratoService;
use App\Services\SuperAdmin\CobrancaTenantService;
use App\Services\SuperAdmin\SuperAdminContratoService;
use App\Services\SuperAdmin\SuperAdminPagamentoContratoService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class AdminMeuContratoServiceTest extends TestCase
{
    private const FREE = ['id' => 4, 'codigo' => 'free', 'nome' => 'Free', 'tipo_cobranca' => 'gratuito', 'valor' => 0, 'valor_por_aluno' => null, 'duracao_dias' => 30, 'prazo_dias' => null, 'max_alunos' => 100, 'max_admins' => 1];

    private const PRO = ['id' => 5, 'codigo' => 'pro', 'nome' => 'Professional', 'tipo_cobranca' => 'fixo', 'valor' => 199, 'valor_por_aluno' => null, 'duracao_dias' => 30, 'prazo_dias' => null, 'max_alunos' => 150, 'max_admins' => 3];

    private const MINI = ['id' => 7, 'codigo' => 'mini', 'nome' => 'Mini', 'tipo_cobranca' => 'fixo', 'valor' => 49, 'valor_por_aluno' => null, 'duracao_dias' => 30, 'prazo_dias' => null, 'max_alunos' => 20, 'max_admins' => 1];

    /**
     * @return array{0: AdminMeuContratoService, 1: CobrancaContratoService&MockInterface, 2: SuperAdminContratoService&MockInterface}
     */
    private function service(int $alunos = 10, array $formas = ['PIX', 'CREDIT_CARD']): array
    {
        $contratos = Mockery::mock(TenantPlanoRepository::class);
        $contratos->shouldReceive('buscarContratoVigente')->andReturn(['id' => 40, 'plano_sistema_id' => 4, 'status_id' => 1]);

        $planos = Mockery::mock(PlanoSistemaRepository::class);
        $planos->shouldReceive('listarPublicos')->andReturn([self::FREE, self::PRO, self::MINI]);

        $cobranca = Mockery::mock(CobrancaTenantService::class);
        $cobranca->shouldReceive('alunosAtivos')->andReturn($alunos);
        $cobranca->shouldReceive('valorMensal')->andReturn(199.0);

        $gateway = Mockery::mock(CobrancaContratoService::class);
        $gateway->shouldReceive('formasHabilitadas')->andReturn($formas);

        $contratoService = Mockery::mock(SuperAdminContratoService::class);

        return [new AdminMeuContratoService($contratos, $planos, Mockery::mock(PagamentoContratoRepository::class), $gateway, $cobranca, $contratoService), $gateway, $contratoService];
    }

    public function test_recusa_plano_que_a_academia_ja_usa(): void
    {
        [$service] = $this->service();

        $res = $service->trocarPlano(2, ['plano_sistema_id' => 4]);

        $this->assertSame(422, $res['status']);
        $this->assertStringContainsString('já está neste plano', $res['body']['message']);
    }

    public function test_recusa_plano_com_limite_menor_que_os_alunos_ativos(): void
    {
        [$service, , $contratoService] = $this->service(alunos: 35);
        $contratoService->shouldNotReceive('trocarPlano');

        $res = $service->trocarPlano(2, ['plano_sistema_id' => 7, 'forma_pagamento' => 'PIX']);

        $this->assertSame(422, $res['status']);
        $this->assertStringContainsString('até 20 alunos', $res['body']['message']);
    }

    public function test_plano_pago_exige_forma_habilitada(): void
    {
        [$service, , $contratoService] = $this->service();
        $contratoService->shouldNotReceive('trocarPlano');

        $res = $service->trocarPlano(2, ['plano_sistema_id' => 5, 'forma_pagamento' => 'BOLETO']);

        $this->assertSame(422, $res['status']);
    }

    public function test_plano_pago_indisponivel_sem_conta_asaas(): void
    {
        [$service] = $this->service(formas: []);

        $res = $service->trocarPlano(2, ['plano_sistema_id' => 5, 'forma_pagamento' => 'PIX']);

        $this->assertSame(503, $res['status']);
    }

    public function test_troca_paga_gera_a_primeira_cobranca_no_asaas(): void
    {
        [$service, $gateway, $contratoService] = $this->service();
        $contratoService->shouldReceive('trocarPlano')->once()
            ->with(2, Mockery::on(fn (array $d) => $d['plano_sistema_id'] === 5 && str_contains($d['observacoes'], 'painel da academia (Ana)')))
            ->andReturn(['status' => 200, 'body' => ['contrato' => ['id' => 41]]]);
        $gateway->shouldReceive('gerarProxima')->once()->with(41, 'PIX')
            ->andReturn(['id' => 'pay_1', 'billing_type' => 'PIX', 'url' => 'https://asaas/i/1', 'pix_payload' => '000201', 'external_reference' => 'CTR-1']);

        $res = $service->trocarPlano(2, ['plano_sistema_id' => 5, 'forma_pagamento' => 'pix'], 'Ana');

        $this->assertSame(200, $res['status']);
        $this->assertSame('pendente', $res['body']['contrato']['status']);
        $this->assertSame('https://asaas/i/1', $res['body']['cobranca']['url']);
        $this->assertSame(199.0, $res['body']['valor']);
        $this->assertArrayNotHasKey('external_reference', $res['body']['cobranca']);
    }

    public function test_troca_para_gratis_nao_gera_cobranca(): void
    {
        $contratos = Mockery::mock(TenantPlanoRepository::class);
        $contratos->shouldReceive('buscarContratoVigente')->andReturn(['id' => 40, 'plano_sistema_id' => 5, 'status_id' => 1]);
        $planos = Mockery::mock(PlanoSistemaRepository::class);
        $planos->shouldReceive('listarPublicos')->andReturn([self::FREE, self::PRO]);
        $cobranca = Mockery::mock(CobrancaTenantService::class);
        $cobranca->shouldReceive('alunosAtivos')->andReturn(10);
        $gateway = Mockery::mock(CobrancaContratoService::class);
        $gateway->shouldNotReceive('gerarProxima');
        $contratoService = Mockery::mock(SuperAdminContratoService::class);
        $contratoService->shouldReceive('trocarPlano')->once()->andReturn(['status' => 200, 'body' => ['contrato' => ['id' => 42]]]);
        $service = new AdminMeuContratoService($contratos, $planos, Mockery::mock(PagamentoContratoRepository::class), $gateway, $cobranca, $contratoService);

        $res = $service->trocarPlano(2, ['plano_sistema_id' => 4]);

        $this->assertSame(200, $res['status']);
        $this->assertSame('ativo', $res['body']['contrato']['status']);
        $this->assertNull($res['body']['cobranca']);
    }

    public function test_pagamento_de_contrato_cancelado_nao_reativa_o_contrato(): void
    {
        $pagamentos = Mockery::mock(PagamentoContratoRepository::class);
        $pagamentos->shouldReceive('buscarPorId')->with(90)->andReturn(['id' => 90, 'tenant_plano_id' => 14, 'data_vencimento' => '2026-10-19', 'valor' => 299.9]);
        $pagamentos->shouldReceive('confirmarPagamento')->once();
        $pagamentos->shouldNotReceive('criar');
        $contratos = Mockery::mock(TenantPlanoRepository::class);
        $contratos->shouldReceive('buscarPorId')->with(14)->andReturn(['id' => 14, 'status_id' => 3, 'tenant_id' => 2]);
        $contratos->shouldNotReceive('atualizarStatus');

        $service = new SuperAdminPagamentoContratoService($pagamentos, $contratos, Mockery::mock(CobrancaTenantService::class));
        $res = $service->confirmar(90, []);

        $this->assertSame(200, $res['status']);
        $this->assertStringContainsString('continua assim', $res['body']['message']);
    }
}
