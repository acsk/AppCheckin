<?php

namespace Tests\Unit;

use App\Repositories\PagamentoContratoRepository;
use App\Repositories\PlanoSistemaRepository;
use App\Repositories\TenantPlanoRepository;
use App\Repositories\UsuarioRepository;
use App\Services\RateLimiter;
use App\Services\Site\CheckoutService;
use App\Services\SuperAdmin\CobrancaContratoService;
use App\Services\SuperAdmin\SuperAdminAcademiaService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CheckoutServiceTest extends TestCase
{
    private const FREE = ['id' => 4, 'nome' => 'Free', 'tipo_cobranca' => 'gratuito', 'valor' => 0, 'valor_por_aluno' => null, 'prazo_dias' => 30, 'max_alunos' => 100];

    private const COMPLETO = ['id' => 5, 'nome' => 'Completo', 'tipo_cobranca' => 'por_aluno', 'valor' => 0, 'valor_por_aluno' => 3.5, 'prazo_dias' => null, 'max_alunos' => 100];

    private MockInterface $planos;

    private MockInterface $usuarios;

    private MockInterface $academias;

    private MockInterface $pagamentos;

    private MockInterface $cobrancas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->planos = Mockery::mock(PlanoSistemaRepository::class);
        $this->planos->shouldReceive('listarPublicos')->andReturn([self::FREE, self::COMPLETO]);
        $this->usuarios = Mockery::mock(UsuarioRepository::class);
        $this->usuarios->shouldReceive('emailExists')->andReturn(false)->byDefault();
        $this->academias = Mockery::mock(SuperAdminAcademiaService::class);
        $this->pagamentos = Mockery::mock(PagamentoContratoRepository::class);
        $this->cobrancas = Mockery::mock(CobrancaContratoService::class);
        $this->cobrancas->shouldReceive('formasHabilitadas')->andReturn(['PIX', 'BOLETO', 'CREDIT_CARD'])->byDefault();
    }

    private function service(): CheckoutService
    {
        $limiter = Mockery::mock(RateLimiter::class);
        $limiter->shouldReceive('attempt')->andReturn(['allowed' => true, 'remaining' => 4, 'retryAfter' => null]);

        return new CheckoutService(
            $this->planos,
            $this->usuarios,
            $this->academias,
            Mockery::mock(TenantPlanoRepository::class),
            $this->pagamentos,
            $this->cobrancas,
            $limiter,
        );
    }

    /** @return array<string, mixed> */
    private function dados(array $extra = []): array
    {
        return array_merge([
            'academia_nome' => 'Cia da Natação',
            'documento' => '11.444.777/0001-61',
            'cidade' => 'Maceió – AL',
            'responsavel_nome' => 'Ana Souza',
            'email' => 'Ana@Academia.com.br',
            'telefone' => '(82) 90000-0000',
            'senha' => 'segredo1',
            'plano_id' => 5,
            'forma_pagamento' => 'pix',
            'alunos' => 60,
            'aceite_termos' => true,
        ], $extra);
    }

    private function academiaCriada(): void
    {
        DB::shouldReceive('transaction')->andReturnUsing(fn (\Closure $cb) => $cb());
        $this->academias->shouldReceive('criarAcademia')->once()->andReturn(['status' => 201, 'body' => [
            'academia' => ['id' => 9, 'nome' => 'Cia da Natação'],
            'contrato' => ['id' => 30, 'data_fim' => null],
        ]]);
    }

    public function test_devolve_erros_por_campo(): void
    {
        $res = $this->service()->assinar(['plano_id' => 5, 'documento' => '11111111111'], '1.1.1.1');

        $this->assertSame(422, $res['status']);
        $this->assertSame('Confira o CNPJ ou CPF.', $res['body']['errors']['documento']);
        $this->assertArrayHasKey('forma_pagamento', $res['body']['errors']);
        $this->assertArrayHasKey('aceite_termos', $res['body']['errors']);
    }

    public function test_plano_pago_sem_gateway_do_sistema_fica_indisponivel(): void
    {
        $this->cobrancas->shouldReceive('formasHabilitadas')->andReturn([]);

        $this->assertSame(503, $this->service()->assinar($this->dados(), '1.1.1.1')['status']);
    }

    public function test_email_ja_usado(): void
    {
        $this->usuarios->shouldReceive('emailExists')->with('ana@academia.com.br', null, null)->andReturn(true);

        $res = $this->service()->assinar($this->dados(), '1.1.1.1');

        $this->assertSame(409, $res['status']);
        $this->assertArrayHasKey('email', $res['body']['errors']);
    }

    public function test_plano_gratis_cria_conta_sem_cobranca_e_com_prazo(): void
    {
        DB::shouldReceive('transaction')->andReturnUsing(fn (\Closure $cb) => $cb());
        $this->academias->shouldReceive('criarAcademia')->once()
            ->with(Mockery::on(fn (array $d) => $d['plano_sistema_id'] === 4
                && $d['cidade'] === 'Maceió' && $d['estado'] === 'AL'
                && $d['cnpj'] === '11444777000161' && $d['responsavel_cpf'] === null
                && $d['data_fim'] === date('Y-m-d', strtotime('+30 days'))))
            ->andReturn(['status' => 201, 'body' => ['academia' => ['id' => 9, 'nome' => 'Cia'], 'contrato' => ['id' => 30, 'data_fim' => '2026-11-18']]]);
        $this->pagamentos->shouldNotReceive('criar');
        $this->cobrancas->shouldNotReceive('gerar');

        $res = $this->service()->assinar($this->dados(['plano_id' => 4, 'forma_pagamento' => null]), '1.1.1.1');

        $this->assertSame(201, $res['status']);
        $this->assertSame('ativo', $res['body']['contrato']['status']);
        $this->assertNull($res['body']['cobranca']);
    }

    public function test_plano_por_aluno_gera_primeira_cobranca_no_asaas(): void
    {
        $this->academiaCriada();
        DB::shouldReceive('table->where->value')->andReturn(2);
        $this->pagamentos->shouldReceive('criar')->once()
            ->with(Mockery::on(fn (array $d) => $d['tenant_plano_id'] === 30 && $d['valor'] === 210.0 && $d['forma_pagamento_id'] === 2))
            ->andReturn(77);
        $this->cobrancas->shouldReceive('gerar')->once()->with(77, 'PIX')->andReturn([
            'id' => 'pay_1', 'billing_type' => 'PIX', 'vencimento' => '2026-10-19', 'url' => 'https://asaas/i/1',
            'boleto_url' => null, 'pix_payload' => '000201', 'pix_qr_code' => 'data:image/png;base64,AA', 'status' => 'pending',
        ]);

        $res = $this->service()->assinar($this->dados(), '1.1.1.1');

        $this->assertSame(201, $res['status']);
        $this->assertSame('pendente', $res['body']['contrato']['status']);
        $this->assertSame(210.0, $res['body']['valor']);
        $this->assertSame('000201', $res['body']['cobranca']['pix_payload']);
        $this->assertArrayNotHasKey('id', $res['body']['cobranca']);
    }

    public function test_conta_fica_criada_se_o_asaas_falhar(): void
    {
        $this->academiaCriada();
        DB::shouldReceive('table->where->value')->andReturn(9);
        $this->pagamentos->shouldReceive('criar')->andReturn(77);
        $this->cobrancas->shouldReceive('gerar')->andThrow(new \RuntimeException('Asaas: fora do ar'));

        $res = $this->service()->assinar($this->dados(['forma_pagamento' => 'CREDIT_CARD']), '1.1.1.1');

        $this->assertSame(201, $res['status']);
        $this->assertNull($res['body']['cobranca']);
        $this->assertNotNull($res['body']['aviso']);
    }

    /** Formulário completo do site (empresa ou pessoa física, endereço, responsável, senha com confirmação). */
    private function completo(array $extra = []): array
    {
        return array_merge([
            'tipo_pessoa' => 'pf',
            'academia_nome' => 'Studio Ana',
            'cep' => '57035-000', 'logradouro' => 'Rua A', 'numero' => '10', 'complemento' => '', 'bairro' => 'Ponta Verde', 'cidade' => 'Maceió', 'estado' => 'al',
            'responsavel_nome' => 'Ana Souza',
            'responsavel_cpf' => '529.982.247-25',
            'responsavel_nascimento' => '1990-05-20',
            'endereco_responsavel_igual' => true,
            'email' => 'ana@studio.com',
            'telefone' => '(82) 98888-7777',
            'senha' => 'segredo12',
            'senha_confirmacao' => 'segredo12',
            'plano_id' => 4,
            'aceite_termos' => true,
        ], $extra);
    }

    public function test_formulario_completo_valida_todos_os_campos(): void
    {
        $res = $this->service()->assinar($this->completo([
            'tipo_pessoa' => 'pj', 'cnpj' => '11.444.777/0001-00', 'razao_social' => 'AB',
            'cep' => '570', 'logradouro' => '', 'numero' => '', 'bairro' => '', 'cidade' => '', 'estado' => 'ZZ',
            'responsavel_nome' => 'Ana', 'responsavel_cpf' => '111.111.111-11', 'responsavel_nascimento' => '2015-01-01',
            'email' => 'ana@', 'telefone' => '(82) 3333-4444', 'senha' => 'abcdefgh',
        ]), '1.1.1.1');

        $this->assertSame(422, $res['status']);
        $this->assertSame([
            'cnpj', 'razao_social', 'cep', 'logradouro', 'numero', 'bairro', 'cidade', 'estado',
            'responsavel_cpf', 'responsavel_nascimento', 'responsavel_nome', 'email', 'telefone', 'senha',
        ], array_keys($res['body']['errors']));
        $this->assertSame('O responsável precisa ter 18 anos ou mais.', $res['body']['errors']['responsavel_nascimento']);
    }

    public function test_senha_precisa_ser_confirmada(): void
    {
        $res = $this->service()->assinar($this->completo(['senha_confirmacao' => 'segredo13']), '1.1.1.1');

        $this->assertSame(422, $res['status']);
        $this->assertSame(['senha_confirmacao' => 'As senhas não são iguais.'], $res['body']['errors']);
    }

    public function test_endereco_do_responsavel_e_validado_quando_e_outro(): void
    {
        $res = $this->service()->assinar($this->completo(['endereco_responsavel_igual' => false, 'responsavel_cep' => '1']), '1.1.1.1');

        $this->assertSame(['responsavel_cep', 'responsavel_logradouro', 'responsavel_numero', 'responsavel_bairro', 'responsavel_cidade', 'responsavel_estado'],
            array_keys($res['body']['errors']));
    }

    public function test_cpf_ja_cadastrado_e_recusado(): void
    {
        $this->usuarios->shouldReceive('cpfExists')->with('52998224725')->andReturn(true);
        $this->academias->shouldNotReceive('criarAcademia');

        $res = $this->service()->assinar($this->completo(), '1.1.1.1');

        $this->assertSame(409, $res['status']);
        $this->assertArrayHasKey('responsavel_cpf', $res['body']['errors']);
    }

    public function test_pessoa_fisica_cria_academia_com_endereco_e_completa_o_responsavel(): void
    {
        DB::shouldReceive('transaction')->andReturnUsing(fn (\Closure $cb) => $cb());
        $this->usuarios->shouldReceive('cpfExists')->andReturn(false);
        $this->academias->shouldReceive('criarAcademia')->once()
            ->with(Mockery::on(fn (array $d) => $d['cnpj'] === null && $d['responsavel_cpf'] === '52998224725'
                && $d['cep'] === '57035000' && $d['estado'] === 'AL' && $d['bairro'] === 'Ponta Verde' && $d['complemento'] === null
                && $d['senha_admin'] === 'segredo12'))
            ->andReturn(['status' => 201, 'body' => ['academia' => ['id' => 9, 'nome' => 'Studio Ana'], 'admin' => ['id' => 40], 'contrato' => ['id' => 30, 'data_fim' => null]]]);
        $this->usuarios->shouldReceive('gravarDadosPessoais')->once()
            ->with(40, ['whatsapp' => '82988887777', 'data_nascimento' => '1990-05-20', 'cep' => '57035000', 'logradouro' => 'Rua A',
                'numero' => '10', 'complemento' => '', 'bairro' => 'Ponta Verde', 'cidade' => 'Maceió', 'estado' => 'AL']);

        $res = $this->service()->assinar($this->completo(), '1.1.1.1');

        $this->assertSame(201, $res['status']);
    }
}
