<?php

namespace Tests\Unit;

use App\Repositories\DashboardRepository;
use App\Repositories\PagamentoContratoRepository;
use App\Repositories\PlanoSistemaRepository;
use App\Repositories\TenantPlanoRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UsuarioRepository;
use App\Services\SuperAdmin\CobrancaTenantService;
use App\Services\SuperAdmin\SuperAdminAcademiaService;
use App\Services\SuperAdmin\SuperAdminContratoService;
use App\Services\SuperAdmin\SuperAdminPagamentoContratoService;
use App\Services\SuperAdmin\SuperAdminPapelService;
use App\Services\SuperAdmin\SuperAdminPlanoSistemaService;
use App\Support\TipoCobrancaPlanoSistema;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class PlanosSaasContratoTest extends TestCase
{
    private const PLANO_FREE = [
        'id' => 4, 'codigo' => 'free', 'nome' => 'Free', 'tipo_cobranca' => 'gratuito',
        'valor' => '0.00', 'valor_por_aluno' => null, 'max_alunos' => null, 'ativo' => 1,
    ];

    private const PLANO_COMPLETO = [
        'id' => 5, 'codigo' => 'completo', 'nome' => 'AppCheckin Completo', 'tipo_cobranca' => 'por_aluno',
        'valor' => '0.00', 'valor_por_aluno' => '3.50', 'max_alunos' => 100, 'ativo' => 1,
    ];

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_calcular_valor_por_tipo(): void
    {
        $this->assertSame(0.0, TipoCobrancaPlanoSistema::calcularValor(self::PLANO_FREE, 80));
        $this->assertSame(259.0, TipoCobrancaPlanoSistema::calcularValor(self::PLANO_COMPLETO, 74));
        $this->assertSame(3.5, TipoCobrancaPlanoSistema::calcularValor(self::PLANO_COMPLETO, 0));
        $this->assertSame(199.9, TipoCobrancaPlanoSistema::calcularValor(['tipo_cobranca' => 'fixo', 'valor' => '199.90'], 10));
    }

    public function test_plano_sem_tipo_usa_valor_para_inferir(): void
    {
        $this->assertSame('gratuito', TipoCobrancaPlanoSistema::doPlano(['valor' => '0.00']));
        $this->assertSame('fixo', TipoCobrancaPlanoSistema::doPlano(['valor' => '99.00']));
    }

    public function test_criar_plano_por_aluno_exige_valor_por_aluno(): void
    {
        $service = new SuperAdminPlanoSistemaService(Mockery::mock(PlanoSistemaRepository::class));

        $result = $service->create(['nome' => 'Completo', 'tipo_cobranca' => 'por_aluno', 'valor_por_aluno' => '']);

        $this->assertSame(422, $result['status']);
        $this->assertContains('Valor por aluno deve ser maior que zero', $result['body']['errors']);
    }

    public function test_criar_plano_tipo_invalido(): void
    {
        $service = new SuperAdminPlanoSistemaService(Mockery::mock(PlanoSistemaRepository::class));

        $result = $service->create(['nome' => 'X', 'tipo_cobranca' => 'anual']);

        $this->assertSame(422, $result['status']);
        $this->assertSame([TipoCobrancaPlanoSistema::mensagemInvalido()], $result['body']['errors']);
    }

    public function test_criar_plano_gratuito_zera_valor(): void
    {
        $repo = Mockery::mock(PlanoSistemaRepository::class);
        $repo->shouldReceive('criar')
            ->once()
            ->with(Mockery::on(fn (array $d) => $d['valor'] === 0 && $d['valor_por_aluno'] === null && $d['tipo_cobranca'] === 'gratuito'))
            ->andReturn(10);
        $repo->shouldReceive('buscarPorId')->with(10)->andReturn(['id' => 10]);

        $service = new SuperAdminPlanoSistemaService($repo);
        $result = $service->create(['nome' => 'Free 2', 'tipo_cobranca' => 'gratuito', 'valor' => 50, 'valor_por_aluno' => 2]);

        $this->assertSame(201, $result['status']);
    }

    public function test_associar_free_sem_forma_pagamento_cria_contrato_ativo_sem_pagamento(): void
    {
        [$service, $contratos, $pagamentos] = $this->contratoService(self::PLANO_FREE);

        $contratos->shouldReceive('criar')
            ->once()
            ->with(Mockery::on(fn (array $d) => $d['status_id'] === 1 && $d['data_fim'] === null && $d['plano_sistema_id'] === 4))
            ->andReturn(30);
        $contratos->shouldReceive('buscarPorId')->with(30)->andReturn(['id' => 30]);
        $pagamentos->shouldNotReceive('criar');

        $result = $service->associarPlano(7, ['plano_sistema_id' => 4]);

        $this->assertSame(201, $result['status']);
    }

    public function test_associar_data_fim_antes_do_inicio_retorna_422(): void
    {
        [$service, $contratos] = $this->contratoService(self::PLANO_FREE);
        $contratos->shouldNotReceive('criar');

        $result = $service->associarPlano(7, [
            'plano_sistema_id' => 4,
            'data_inicio' => '2026-10-19',
            'data_fim' => '2026-10-01',
        ]);

        $this->assertSame(422, $result['status']);
        $this->assertStringContainsString('Data de fim', $result['body']['message']);
    }

    public function test_associar_plano_pago_exige_forma_pagamento(): void
    {
        [$service, $contratos] = $this->contratoService(self::PLANO_COMPLETO);
        $contratos->shouldNotReceive('criar');

        $result = $service->associarPlano(7, ['plano_sistema_id' => 5]);

        $this->assertSame(422, $result['status']);
        $this->assertStringContainsString('Forma de pagamento', $result['body']['message']);
    }

    public function test_associar_por_aluno_cobra_alunos_ativos_vezes_valor(): void
    {
        [$service, $contratos, $pagamentos] = $this->contratoService(self::PLANO_COMPLETO, alunosAtivos: 74);

        $contratos->shouldReceive('criar')
            ->once()
            ->with(Mockery::on(fn (array $d) => $d['status_id'] === 2 && $d['data_fim'] === '2027-10-19'))
            ->andReturn(31);
        $contratos->shouldReceive('buscarPorId')->with(31)->andReturn(['id' => 31]);
        $pagamentos->shouldReceive('criar')
            ->once()
            ->with(Mockery::on(fn (array $d) => $d['valor'] === 259.0 && $d['data_vencimento'] === '2026-10-19' && $d['forma_pagamento_id'] === 2))
            ->andReturn(1);

        $result = $service->associarPlano(7, [
            'plano_sistema_id' => 5,
            'forma_pagamento_id' => 2,
            'data_inicio' => '2026-10-19',
            'data_fim' => '2027-10-19',
        ]);

        $this->assertSame(201, $result['status']);
    }

    public function test_estimativa_cobranca_por_aluno(): void
    {
        [$service, $contratos] = $this->contratoService(self::PLANO_COMPLETO, alunosAtivos: 120);
        $contratos->shouldReceive('buscarContratoVigente')->with(7)->andReturn([
            'id' => 31, 'plano_sistema_id' => 5, 'plano_nome' => 'AppCheckin Completo',
            'status_id' => 1, 'status_nome' => 'Ativo', 'data_inicio' => '2026-10-19', 'data_fim' => null,
        ]);

        $result = $service->estimativaCobranca(7);

        $this->assertSame(200, $result['status']);
        $this->assertSame(420.0, $result['body']['estimativa']['total_estimado']);
        $this->assertSame(120, $result['body']['estimativa']['alunos_ativos']);
        $this->assertTrue($result['body']['estimativa']['acima_do_limite']);
    }

    public function test_confirmar_por_aluno_recalcula_proximo_pagamento(): void
    {
        $pagamentos = Mockery::mock(PagamentoContratoRepository::class);
        $contratos = Mockery::mock(TenantPlanoRepository::class);

        $pagamentos->shouldReceive('buscarPorId')->with(50)->andReturn([
            'id' => 50, 'tenant_plano_id' => 31, 'valor' => '3.50',
            'data_vencimento' => '2026-10-19', 'forma_pagamento_id' => 2,
        ]);
        $pagamentos->shouldReceive('confirmarPagamento')->once();
        $pagamentos->shouldReceive('temPagamentosPendentes')->with(31)->andReturn(false);
        $pagamentos->shouldReceive('listarPorContrato')->with(31)->andReturn([]);
        $pagamentos->shouldReceive('criar')
            ->once()
            ->with(Mockery::on(fn (array $d) => $d['valor'] === 35.0 && $d['data_vencimento'] === '2026-11-18'))
            ->andReturn(51);
        $contratos->shouldReceive('atualizarStatus')->with(31, 1)->once();
        $contratos->shouldReceive('buscarPorId')->with(31)->andReturn([
            'id' => 31, 'tenant_id' => 7, 'duracao_dias' => 30, 'data_fim' => null,
            'tipo_cobranca' => 'por_aluno', 'plano_valor' => '0.00', 'valor_por_aluno' => '3.50',
        ]);

        $service = new SuperAdminPagamentoContratoService($pagamentos, $contratos, $this->cobranca(10));

        $this->assertSame(200, $service->confirmar(50, [])['status']);
    }

    public function test_confirmar_nao_gera_pagamento_apos_fim_do_contrato(): void
    {
        $pagamentos = Mockery::mock(PagamentoContratoRepository::class);
        $contratos = Mockery::mock(TenantPlanoRepository::class);

        $pagamentos->shouldReceive('buscarPorId')->with(50)->andReturn([
            'id' => 50, 'tenant_plano_id' => 31, 'valor' => '199.00',
            'data_vencimento' => '2026-10-19', 'forma_pagamento_id' => 2,
        ]);
        $pagamentos->shouldReceive('confirmarPagamento')->once();
        $pagamentos->shouldReceive('temPagamentosPendentes')->andReturn(false);
        $pagamentos->shouldReceive('listarPorContrato')->andReturn([]);
        $pagamentos->shouldNotReceive('criar');
        $contratos->shouldReceive('atualizarStatus');
        $contratos->shouldReceive('buscarPorId')->with(31)->andReturn([
            'id' => 31, 'tenant_id' => 7, 'duracao_dias' => 30, 'data_fim' => '2026-11-01',
            'tipo_cobranca' => 'fixo', 'plano_valor' => '199.00', 'valor_por_aluno' => null,
        ]);

        $service = new SuperAdminPagamentoContratoService($pagamentos, $contratos, $this->cobranca(0));

        $this->assertSame(200, $service->confirmar(50, [])['status']);
    }

    public function test_criar_academia_sem_plano_gera_contrato_free(): void
    {
        $tenants = Mockery::mock(TenantRepository::class);
        $tenants->shouldReceive('findBySlug')->andReturn(null);
        $tenants->shouldReceive('create')->once()->andReturn(9);
        $tenants->shouldReceive('findById')->with(9)->andReturn(['id' => 9, 'nome' => 'Box Teste']);

        $usuarios = Mockery::mock(UsuarioRepository::class);
        $usuarios->shouldReceive('emailExists')->andReturn(false);
        $usuarios->shouldReceive('criarUsuarioCompleto')->once()->andReturn(15);

        $planos = Mockery::mock(PlanoSistemaRepository::class);
        $planos->shouldReceive('buscarPorCodigo')->with('free')->once()->andReturn(self::PLANO_FREE);

        $contratos = Mockery::mock(TenantPlanoRepository::class);
        $contratos->shouldReceive('criar')
            ->once()
            ->with(Mockery::on(fn (array $d) => $d['tenant_id'] === 9
                && $d['plano_sistema_id'] === 4
                && $d['status_id'] === 1
                && $d['data_fim'] === null))
            ->andReturn(40);
        $contratos->shouldReceive('buscarPorId')->with(40)->andReturn(['id' => 40, 'plano_nome' => 'Free']);

        $service = new SuperAdminAcademiaService(
            $tenants,
            $contratos,
            $usuarios,
            Mockery::mock(SuperAdminPapelService::class),
            $planos,
        );

        $result = $service->criarAcademia([
            'nome' => 'Box Teste',
            'email' => 'box@example.com',
            'senha_admin' => 'segredo123',
        ]);

        $this->assertSame(201, $result['status']);
        $this->assertSame('Free', $result['body']['contrato']['plano_nome']);
    }

    public function test_criar_academia_com_plano_inativo_ou_data_fim_passada_retorna_422(): void
    {
        $tenants = Mockery::mock(TenantRepository::class);
        $tenants->shouldReceive('findBySlug')->andReturn(null);
        $tenants->shouldNotReceive('create');

        $usuarios = Mockery::mock(UsuarioRepository::class);
        $usuarios->shouldReceive('emailExists')->andReturn(false);

        $planos = Mockery::mock(PlanoSistemaRepository::class);
        $planos->shouldReceive('buscarPorId')->with(7)->andReturn(['id' => 7, 'ativo' => 0]);

        $service = new SuperAdminAcademiaService(
            $tenants,
            Mockery::mock(TenantPlanoRepository::class),
            $usuarios,
            Mockery::mock(SuperAdminPapelService::class),
            $planos,
        );

        $result = $service->criarAcademia([
            'nome' => 'Box Teste',
            'email' => 'box@example.com',
            'senha_admin' => 'segredo123',
            'plano_sistema_id' => 7,
            'data_fim' => '2000-01-01',
        ]);

        $this->assertSame(422, $result['status']);
        $this->assertContains('Plano do sistema não encontrado ou inativo', $result['body']['errors']);
        $this->assertContains('Data de fim do contrato não pode ser anterior a hoje', $result['body']['errors']);
    }

    public function test_listar_academias_inclui_contrato_vigente(): void
    {
        $tenants = Mockery::mock(TenantRepository::class);
        $tenants->shouldReceive('getAll')->andReturn([
            ['id' => 9, 'nome' => 'Box Teste'],
            ['id' => 10, 'nome' => 'Sem Contrato'],
        ]);
        $tenants->shouldReceive('resumoUsoPorTenant')->andReturn([9 => ['alunos_ativos' => 12, 'checkins_mes' => 30]]);

        $contratos = Mockery::mock(TenantPlanoRepository::class);
        $contratos->shouldReceive('buscarContratoVigente')->with(9)->andReturn([
            'id' => 40, 'plano_sistema_id' => 4, 'plano_nome' => 'Free', 'plano_codigo' => 'free',
            'tipo_cobranca' => 'gratuito', 'status_id' => 1, 'status_nome' => 'Ativo',
            'data_inicio' => '2026-10-19', 'data_fim' => null,
        ]);
        $contratos->shouldReceive('buscarContratoVigente')->with(10)->andReturn(null);

        $service = new SuperAdminAcademiaService(
            $tenants,
            $contratos,
            Mockery::mock(UsuarioRepository::class),
            Mockery::mock(SuperAdminPapelService::class),
            Mockery::mock(PlanoSistemaRepository::class),
        );

        $academias = $service->listarAcademias([])['body']['academias'];

        $this->assertSame('Free', $academias[0]['contrato']['plano_nome']);
        $this->assertNull($academias[0]['contrato']['data_fim']);
        $this->assertNull($academias[1]['contrato']);
        $this->assertSame(12, $academias[0]['alunos_ativos']);
        $this->assertSame(30, $academias[0]['checkins_mes']);
        $this->assertSame(0, $academias[1]['alunos_ativos']);
    }

    /**
     * @param  array<string, mixed>  $plano
     * @return array{0: SuperAdminContratoService, 1: TenantPlanoRepository&MockInterface, 2: PagamentoContratoRepository&MockInterface}
     */
    private function contratoService(array $plano, int $alunosAtivos = 0): array
    {
        $contratos = Mockery::mock(TenantPlanoRepository::class);
        $contratos->shouldReceive('buscarContratoAtivo')->andReturn(null)->byDefault();

        $tenants = Mockery::mock(TenantRepository::class);
        $tenants->shouldReceive('findById')->andReturn(['id' => 7, 'nome' => 'Academia']);
        $tenants->shouldReceive('inicializarFormasPagamento');

        $planos = Mockery::mock(PlanoSistemaRepository::class);
        $planos->shouldReceive('buscarPorId')->with($plano['id'])->andReturn($plano);

        $pagamentos = Mockery::mock(PagamentoContratoRepository::class);

        $service = new SuperAdminContratoService($contratos, $tenants, $planos, $pagamentos, $this->cobranca($alunosAtivos));

        return [$service, $contratos, $pagamentos];
    }

    private function cobranca(int $alunosAtivos): CobrancaTenantService
    {
        $dashboard = Mockery::mock(DashboardRepository::class);
        $dashboard->shouldReceive('matriculasCards')->andReturn([
            'alunos_com_matricula_ativa' => $alunosAtivos,
            'ativas' => $alunosAtivos,
            'vencidas' => 0,
            'pendentes' => 0,
            'bloqueadas' => 0,
        ]);

        return new CobrancaTenantService($dashboard);
    }
}
