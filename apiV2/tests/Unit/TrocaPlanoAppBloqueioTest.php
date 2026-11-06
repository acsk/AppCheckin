<?php

namespace Tests\Unit;

use App\Services\AppConfigService;
use App\Support\TrocaPlanoApp;
use Mockery;
use Tests\TestCase;

/** O app mostra o bloqueio que a API manda por ciclo: à vista e mensal seguem a mesma regra da compra. */
class TrocaPlanoAppBloqueioTest extends TestCase
{
    private function parametro(bool $ligado): void
    {
        $config = Mockery::mock(AppConfigService::class);
        $config->shouldReceive('parametro')->with('troca_plano_app', 3)->andReturn($ligado);
        $this->app->instance(AppConfigService::class, $config);
    }

    public function test_desligado_bloqueia_outro_ciclo_nos_dois_modos(): void
    {
        $this->parametro(false);

        // 2x por semana mensal (ciclo 50) ativo; aluno olha o quadrimestral (ciclo 52) do mesmo plano
        $b = TrocaPlanoApp::bloqueioDoCiclo(3, ['plano_id' => 8, 'plano_ciclo_id' => 50], 8, 52);

        $this->assertSame(TrocaPlanoApp::CODIGO, $b['codigo']);
        $this->assertSame(['avista', 'assinatura'], $b['modos']);
        $this->assertSame(TrocaPlanoApp::MENSAGEM, $b['mensagem']);
    }

    public function test_renovar_o_mesmo_plano_e_ciclo_nao_bloqueia(): void
    {
        $this->parametro(false);

        $this->assertNull(TrocaPlanoApp::bloqueioDoCiclo(3, ['plano_id' => 8, 'plano_ciclo_id' => 50], 8, 50));
    }

    public function test_sem_plano_ativo_ou_com_parametro_ligado_nao_bloqueia(): void
    {
        $this->parametro(false);
        $this->assertNull(TrocaPlanoApp::bloqueioDoCiclo(3, null, 8, 52));

        $this->parametro(true);
        $this->assertNull(TrocaPlanoApp::bloqueioDoCiclo(3, ['plano_id' => 8, 'plano_ciclo_id' => 50], 9, 60));
    }
}
