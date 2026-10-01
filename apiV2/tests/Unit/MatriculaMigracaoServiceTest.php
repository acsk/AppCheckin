<?php

namespace Tests\Unit;

use App\Repositories\UsuarioRepository;
use App\Services\MatriculaMigracaoAptidaoService;
use App\Services\MatriculaMigracaoService;
use App\Services\Mobile\MobileMigracaoPlanoService;
use App\Services\PagamentoPlanoService;
use Mockery;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

class MatriculaMigracaoServiceTest extends TestCase
{
    use RequiresSqlite;


    public function test_resolve_na_v2_sem_api_legada(): void
    {
        $service = app(MatriculaMigracaoService::class);

        $this->assertInstanceOf(MatriculaMigracaoService::class, $service);
        $this->assertFalse(class_exists(\App\Models\Usuario::class, false));
    }

    public function test_simular_exige_plano_sem_carregar_codigo_legado(): void
    {
        $result = app(MobileMigracaoPlanoService::class)->simular(1, 1, 0, null);

        $this->assertSame(400, $result['status']);
        $this->assertSame('PLANO_OBRIGATORIO', $result['body']['code']);
    }

    public function test_aptidao_delegada_ao_servico_v2(): void
    {
        $aptidao = Mockery::mock(MatriculaMigracaoAptidaoService::class);
        $aptidao->shouldReceive('temParcelaAtrasada')->once()->with(7, 1)->andReturn(true);

        $service = new MatriculaMigracaoService(
            Mockery::mock(UsuarioRepository::class),
            $aptidao,
            Mockery::mock(PagamentoPlanoService::class),
        );

        $this->assertTrue($service->temParcelaAtrasada(7, 1));
    }

    public function test_status_matricula_ausente_retorna_null_em_vez_de_zero(): void
    {
        $this->requireSqlite();
        \Illuminate\Support\Facades\DB::statement('CREATE TABLE status_matricula (id INTEGER PRIMARY KEY, codigo TEXT)');
        \Illuminate\Support\Facades\DB::table('status_matricula')->insert(['id' => 5, 'codigo' => 'ativo']);

        $service = app(MatriculaMigracaoService::class);
        $resolver = new \ReflectionMethod($service, 'resolverStatusMatriculaId');
        $resolver->setAccessible(true);

        // 'ativa' não existe, cai no legado 'ativo'
        $this->assertSame(5, $resolver->invoke($service, true));
        // 'pendente' não existe: null (migrar() devolve ERRO_INTERNO sem gravar)
        $this->assertNull($resolver->invoke($service, false));
    }

    public function test_credito_proporcional(): void
    {
        $r = MatriculaMigracaoService::calcularCreditoProporcional(300.0, '2026-09-01', '2026-10-01', '2026-09-16');

        $this->assertSame(30, $r['dias_totais']);
        $this->assertSame(15, $r['dias_restantes']);
        $this->assertSame(150.0, $r['credito']);
        $this->assertSame(150.0, $r['valor_consumido']);
        $this->assertSame('proporcional', $r['tipo_credito']);
        $this->assertTrue($r['tem_credito']);
        $this->assertFalse($r['ciclo_invalido']);
    }

    public function test_nao_altera_timezone_global_do_processo(): void
    {
        $antes = date_default_timezone_get();

        MatriculaMigracaoService::calcularCreditoProporcional(300.0, '2026-09-01', '2026-10-01');

        $this->assertSame($antes, date_default_timezone_get());
    }

    public function test_credito_zero_apos_vencimento(): void
    {
        $r = MatriculaMigracaoService::calcularCreditoProporcional(300.0, '2026-09-01', '2026-10-01', '2026-10-05');

        $this->assertSame(0.0, $r['credito']);
        $this->assertSame('sem_credito', $r['tipo_credito']);
        $this->assertFalse($r['tem_credito']);
        $this->assertFalse($r['ciclo_invalido']);
        $this->assertSame(0, $r['dias_restantes']);
    }
}
