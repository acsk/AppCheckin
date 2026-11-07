<?php

namespace Tests\Unit;

use App\Support\VencimentoTurmaFixa;
use DateTime;
use PHPUnit\Framework\TestCase;

class VencimentoTurmaFixaTest extends TestCase
{
    private function proximo(string $venc, ?int $meses, int $duracao, ?int $dia): string
    {
        return VencimentoTurmaFixa::proximo(new DateTime($venc), $meses, $duracao, $dia)->format('Y-m-d');
    }

    public function test_proximo_vencimento_mantem_o_dia_do_mes(): void
    {
        $this->assertSame('2026-12-10', $this->proximo('2026-11-10', 1, 30, 10));
        $this->assertSame('2027-01-10', $this->proximo('2026-12-10', 1, 30, 10));
        $this->assertSame('2027-05-10', $this->proximo('2026-11-10', 6, 30, 10));
    }

    public function test_plano_mensal_sem_ciclo_nao_desliza_30_dias(): void
    {
        // +30 dias sairia em 09/01; o vencimento fica no dia 10
        $this->assertSame('2027-01-10', $this->proximo('2026-12-10', null, 30, 10));
        $this->assertSame('2026-12-10', $this->proximo('2026-11-10', null, 31, 10));
    }

    public function test_dia_31_em_mes_curto_cai_no_ultimo_dia_e_volta_ao_31(): void
    {
        $fev = $this->proximo('2026-01-31', 1, 30, 31);
        $this->assertSame('2026-02-28', $fev);
        $this->assertSame('2026-03-31', $this->proximo($fev, 1, 30, 31));
        $this->assertSame('2026-04-30', $this->proximo('2026-03-31', 1, 30, 31));
    }

    public function test_sem_dia_cadastrado_usa_o_dia_da_propria_data(): void
    {
        $this->assertSame('2026-12-05', $this->proximo('2026-11-05', 1, 30, null));
    }

    public function test_duracao_que_nao_e_mensal_soma_dias(): void
    {
        $this->assertSame('2026-11-17', $this->proximo('2026-11-10', null, 7, 10));
        $this->assertSame('2027-01-08', $this->proximo('2026-11-10', null, 59, 10));
    }

    public function test_pagar_antes_ou_depois_nao_muda_o_vencimento_seguinte(): void
    {
        // O próximo vencimento depende só do vencimento da parcela, nunca da data do pagamento
        $this->assertSame('2026-12-10', $this->proximo('2026-11-10', 1, 30, 10));
    }

    public function test_ultima_ocorrencia_do_dia_de_vencimento(): void
    {
        $this->assertSame('2026-12-10', VencimentoTurmaFixa::ultimaOcorrencia('2026-12-10', 10));
        $this->assertSame('2026-12-10', VencimentoTurmaFixa::ultimaOcorrencia('2026-12-12', 10));
        $this->assertSame('2026-12-10', VencimentoTurmaFixa::ultimaOcorrencia('2027-01-09', 10));
        $this->assertSame('2027-01-10', VencimentoTurmaFixa::ultimaOcorrencia('2027-01-20', 10));
        $this->assertSame('2026-02-28', VencimentoTurmaFixa::ultimaOcorrencia('2026-03-02', 31));
    }
}
