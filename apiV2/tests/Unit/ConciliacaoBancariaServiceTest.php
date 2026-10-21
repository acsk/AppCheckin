<?php

namespace Tests\Unit;

use App\Services\Admin\ConciliacaoBancariaService as C;
use PHPUnit\Framework\TestCase;

class ConciliacaoBancariaServiceTest extends TestCase
{
    private function asaas(string $id, float $valor, ?float $liquido, array $extra = []): array
    {
        return C::normalizarAsaas($extra + [
            'id' => $id, 'status' => 'RECEIVED', 'billingType' => 'PIX', 'value' => $valor, 'netValue' => $liquido,
            'paymentDate' => '2026-10-19', 'externalReference' => null,
        ]);
    }

    private function parcela(int $id, int $matricula, float $valor, int $status, array $extra = []): array
    {
        return $extra + [
            'id' => $id, 'matricula_id' => $matricula, 'valor' => $valor, 'valor_liquido' => null, 'status_pagamento_id' => $status,
            'data_pagamento' => $status === 2 ? '2026-10-19' : null, 'data_vencimento' => '2026-10-19', 'tipo_baixa_id' => $status === 2 ? 4 : null,
            'observacoes' => '', 'aluno_nome' => 'Aluno '.$matricula, 'plano_nome' => 'Plano', 'forma_pagamento_nome' => 'Pix',
        ];
    }

    private function linha(array $r, string $id): array
    {
        foreach ($r['linhas'] as $l) {
            if ($l['id'] === $id) {
                return $l;
            }
        }
        $this->fail("linha {$id} não encontrada");
    }

    public function test_pix_pago_e_baixado_concilia(): void
    {
        $r = C::montar(
            [$this->asaas('pay_hjp', 120, 118.01)],
            [$this->parcela(1472, 385, 120, 2, ['valor_liquido' => 118.01, 'tarifa_cartao' => '4.50', 'observacoes' => 'Pago via Asaas (Pix) [Asaas pay_hjp]'])],
            [], '2026-10-01', '2026-10-31'
        );

        $l = $this->linha($r, 'asaas:pay_hjp');
        $this->assertSame('conciliado', $l['status']);
        $this->assertSame(1472, $l['parcela']['id']);
        $this->assertSame(4.5, $l['parcela']['acrescimo']);
        $this->assertEqualsWithDelta(1.99, $l['taxa'], 0.001);
        $this->assertSame(['quantidade' => 1, 'bruto' => 120.0, 'taxas' => 1.99, 'liquido' => 118.01, 'a_receber' => 0.0, 'sem_liquido' => 0], $r['resumo']['provedor']);
        $this->assertSame(0, $r['resumo']['pendencias']);
    }

    public function test_liquido_diferente_do_gravado_e_divergente_e_pode_reprocessar(): void
    {
        $r = C::montar(
            [$this->asaas('pay_a', 120, 118.01, ['externalReference' => 'MAT-385-x'])],
            [$this->parcela(1, 385, 120, 2, ['valor_liquido' => 117.00, 'observacoes' => '[Asaas pay_a]'])],
            [], '2026-10-01', '2026-10-31'
        );

        $l = $this->linha($r, 'asaas:pay_a');
        $this->assertSame('divergente', $l['status']);
        $this->assertStringStartsWith('Líquido', $l['motivo']);
        $this->assertTrue($l['pode_reprocessar']);
    }

    public function test_recebido_no_provedor_com_parcela_aberta_e_sem_baixa(): void
    {
        $r = C::montar(
            [$this->asaas('pay_b', 70, 68.01, ['externalReference' => 'MAT-385-y'])],
            [$this->parcela(10, 385, 70, 1)],
            [], '2026-10-01', '2026-10-31'
        );

        $l = $this->linha($r, 'asaas:pay_b');
        $this->assertSame('sem_baixa', $l['status']);
        $this->assertSame(10, $l['parcela']['id']);
        $this->assertSame('sem_baixa', $l['baixa']);
        $this->assertStringContainsString('parcela #10 continua em aberto', $l['motivo']);
        $this->assertTrue($l['pode_reprocessar']);
    }

    public function test_matricula_pelo_pix_salvo_quando_nao_ha_referencia(): void
    {
        $r = C::montar([$this->asaas('pay_c', 70, 68.01)], [], ['pay_c' => 77], '2026-10-01', '2026-10-31');

        $l = $this->linha($r, 'asaas:pay_c');
        $this->assertSame('sem_baixa', $l['status']);
        $this->assertSame(77, $l['matricula_id']);
    }

    public function test_cobranca_sem_matricula_e_sem_vinculo(): void
    {
        $r = C::montar([$this->asaas('pay_d', 50, 49)], [], [], '2026-10-01', '2026-10-31');

        $this->assertSame('sem_vinculo', $this->linha($r, 'asaas:pay_d')['status']);
        $this->assertSame(0, $r['resumo']['pendencias']);
    }

    public function test_provedor_so_com_vinculados_tira_o_sem_vinculo_da_tela_e_dos_totais(): void
    {
        $pagamentos = [
            $this->asaas('pay_e', 100, 98, ['externalReference' => 'MAT-5-P10']),
            $this->asaas('pay_f', 300, 290),
        ];
        $locais = [$this->parcela(10, 5, 100, 2, ['observacoes' => 'Asaas pay_e'])];

        $todos = C::montar($pagamentos, $locais, [], '2026-10-01', '2026-10-31');
        $filtrado = C::montar($pagamentos, $locais, [], '2026-10-01', '2026-10-31', null, ['asaas']);
        $outro = C::montar($pagamentos, $locais, [], '2026-10-01', '2026-10-31', null, ['mercadopago']);

        $this->assertSame('sem_vinculo', $this->linha($todos, 'asaas:pay_f')['status']);
        $this->assertSame(['asaas:pay_e'], array_column(array_filter($filtrado['linhas'], fn ($l) => $l['origem'] === 'provedor'), 'id'));
        $this->assertCount(count($todos['linhas']), $outro['linhas']);
        $this->assertEqualsWithDelta(400.0, $todos['resumo']['provedor']['bruto'], 0.001);
        $this->assertEqualsWithDelta(100.0, $filtrado['resumo']['provedor']['bruto'], 0.001);
    }

    public function test_cartao_parcelado_casa_as_duas_parcelas_com_a_parcela_cheia(): void
    {
        $cartao = ['billingType' => 'CREDIT_CARD', 'installment' => 'ins_1', 'externalReference' => 'MAT-63-z'];
        $r = C::montar(
            [
                $this->asaas('pay_p1', 107.89, 102.01, $cartao + ['installmentNumber' => 1]),
                $this->asaas('pay_p2', 107.89, 102.01, $cartao + ['installmentNumber' => 2]),
            ],
            [$this->parcela(1473, 63, 215.78, 2, ['observacoes' => '[Asaas pay_p1]'])],
            [], '2026-10-01', '2026-10-31'
        );

        $this->assertSame('conciliado', $this->linha($r, 'asaas:pay_p1')['status']);
        $p2 = $this->linha($r, 'asaas:pay_p2');
        $this->assertSame('conciliado', $p2['status']);
        $this->assertSame(1473, $p2['parcela']['id']);
        $this->assertSame(0.0, $p2['parcela']['acrescimo'], 'sem acréscimo gravado, a mensalidade é o valor da parcela');
        $this->assertStringContainsString('cartão parcelado', $p2['nota']);
        $this->assertEqualsWithDelta(215.78, $r['resumo']['checkin']['integracao'], 0.001, 'a parcela local conta uma vez');
    }

    public function test_baixa_automatica_sem_pagamento_no_provedor_e_dinheiro_ficam_do_lado_do_checkin(): void
    {
        $r = C::montar(
            [],
            [
                $this->parcela(20, 5, 90, 2, ['observacoes' => 'Pago via Asaas [Asaas pay_sumiu]']),
                $this->parcela(21, 6, 80, 2, ['tipo_baixa_id' => 1, 'forma_pagamento_nome' => 'Dinheiro']),
                $this->parcela(22, 7, 80, 2, ['data_pagamento' => '2026-09-25']),
            ],
            [], '2026-10-01', '2026-10-31'
        );

        $semReceb = $this->linha($r, 'checkin:20');
        $this->assertSame('sem_recebimento', $semReceb['status']);
        $this->assertSame('pay_sumiu', $semReceb['payment_id']);
        $this->assertSame('manual', $this->linha($r, 'checkin:21')['status']);
        $this->assertSame('Baixa manual', $this->linha($r, 'checkin:21')['baixa_nome']);
        $this->assertSame('automatica', $semReceb['baixa']);
        $this->assertSame([['forma' => 'Dinheiro', 'quantidade' => 1, 'valor' => 80.0]], $r['resumo']['checkin']['manual_por_forma']);
        $this->assertCount(2, $r['linhas'], 'parcela paga fora do período não entra');
        $this->assertSame(1, $r['resumo']['pendencias']);
        $this->assertEqualsWithDelta(80.0, $r['resumo']['checkin']['manual'], 0.001);
    }

    public function test_baixa_de_provedor_nao_consultado_fica_nao_conferida(): void
    {
        // Produção: só o Asaas foi consultado, mas as baixas de outubro vieram do Mercado Pago
        $r = C::montar(
            [],
            [
                $this->parcela(1435, 257, 200, 2, ['observacoes' => 'Pago via Mercado Pago - Payment #183482972067']),
                $this->parcela(1460, 189, 72.66, 2, ['observacoes' => 'Pago via Asaas (Cartão) [Asaas pay_qhfl2c64yzl1pa1y]']),
            ],
            [], '2026-10-01', '2026-10-31', ['asaas']
        );

        $mp = $this->linha($r, 'checkin:1435');
        $this->assertSame('nao_conferido', $mp['status']);
        $this->assertStringContainsString('Mercado Pago', $mp['motivo']);
        $this->assertSame('sem_recebimento', $this->linha($r, 'checkin:1460')['status']);
        $this->assertSame(1, $r['resumo']['pendencias'], 'não conferido não é pendência');
    }

    public function test_pagamento_de_outra_data_explica_a_baixa_e_nao_soma_no_periodo(): void
    {
        $fora = ['fora_do_periodo' => true] + $this->asaas('pay_sumiu', 90, 88, ['paymentDate' => '2026-09-28']);
        $r = C::montar(
            [$fora],
            [$this->parcela(20, 5, 90, 2, ['observacoes' => '[Asaas pay_sumiu]'])],
            [], '2026-10-01', '2026-10-31'
        );

        $l = $this->linha($r, 'asaas:pay_sumiu');
        $this->assertSame('conciliado', $l['status']);
        $this->assertStringContainsString('Recebido em 28/09/2026', $l['nota']);
        $this->assertSame(0, $r['resumo']['provedor']['quantidade']);
    }

    public function test_cartao_confirmado_entra_como_a_receber(): void
    {
        $p = $this->asaas('pay_e', 100, 96.5, ['status' => 'CONFIRMED', 'paymentDate' => null, 'confirmedDate' => '2026-10-10', 'estimatedCreditDate' => '2026-11-10', 'billingType' => 'CREDIT_CARD']);
        $this->assertSame('2026-10-10', $p['data']);
        $this->assertSame('2026-11-10', $p['data_credito']);

        $r = C::montar([$p], [], [], '2026-10-01', '2026-10-31');
        $this->assertEqualsWithDelta(96.5, $r['resumo']['provedor']['a_receber'], 0.001);
    }

    public function test_id_numerico_do_mercado_pago_nao_casa_com_prefixo_de_outro_id(): void
    {
        $mp = C::normalizarMercadoPago([
            'id' => 123456, 'status' => 'approved', 'transaction_amount' => 100, 'payment_type_id' => 'bank_transfer', 'payment_method_id' => 'pix',
            'date_approved' => '2026-10-19T23:30:00.000-04:00', 'money_release_status' => 'released',
            'transaction_details' => ['net_received_amount' => 99.01], 'fee_details' => [['amount' => 0.99]],
        ]);
        $this->assertSame('2026-10-20', $mp['data'], 'data no fuso de Brasília');
        $this->assertSame('Pix', $mp['forma']);
        $this->assertEqualsWithDelta(0.99, $mp['taxa'], 0.001);

        $r = C::montar([$mp], [$this->parcela(30, 9, 100, 2, ['observacoes' => 'Payment #1234567', 'data_pagamento' => '2026-10-20'])], [], '2026-10-01', '2026-10-31');

        $this->assertSame('sem_vinculo', $this->linha($r, 'mercadopago:123456')['status']);
        $this->assertSame('sem_recebimento', $this->linha($r, 'checkin:30')['status']);
    }
}
