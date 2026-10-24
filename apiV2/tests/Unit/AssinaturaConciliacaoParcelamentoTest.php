<?php

namespace Tests\Unit;

use App\Services\Admin\AdminAssinaturaService;
use Tests\TestCase;

class AssinaturaConciliacaoParcelamentoTest extends TestCase
{
    private function chamar(string $metodo, array $args): mixed
    {
        $service = (new \ReflectionClass(AdminAssinaturaService::class))->newInstanceWithoutConstructor();
        $m = new \ReflectionMethod($service, $metodo);

        return $m->invokeArgs($service, $args);
    }

    private function parcela(string $id, string $due, ?int $numero = null): array
    {
        return ['id' => $id, 'status' => 'CONFIRMED', 'transaction_amount' => 107.89, 'payment_method_id' => 'CREDIT_CARD',
            'date_created' => '2026-10-19', 'date_approved' => '2026-10-19', 'due_date' => $due,
            'installment_id' => 'ins_1', 'installment_number' => $numero, 'installment_count' => null];
    }

    public function test_numera_e_ordena_as_parcelas_do_cartao(): void
    {
        $lista = $this->chamar('numerarParcelas', [[$this->parcela('pay_2', '2026-11-19'), $this->parcela('pay_1', '2026-10-19')]]);

        $this->assertSame(['pay_1', 'pay_2'], array_column($lista, 'id'));
        $this->assertSame([1, 2], array_column($lista, 'installment_number'));
        $this->assertSame([2, 2], array_column($lista, 'installment_count'));
    }

    public function test_cartao_parcelado_bate_com_a_parcela_local_cheia(): void
    {
        $provedor = $this->chamar('numerarParcelas', [[$this->parcela('pay_2', '2026-11-19'), $this->parcela('pay_1', '2026-10-19')]]);
        $local = [['id' => 1473, 'valor' => '215.77', 'status_pagamento_id' => 2, 'status' => 'Pago', 'data_pagamento' => '2026-10-19',
            'data_vencimento' => '2026-10-20', 'forma_pagamento_nome' => 'Cartão', 'observacoes' => '[Asaas pay_1]']];

        $c = $this->chamar('montarConciliacao', [true, 'Asaas', $provedor, $local]);
        $linhas = array_column($c['linhas'], null, 'campo');

        $this->assertTrue($c['ok'], json_encode($c['linhas']));
        $this->assertSame(215.78, $linhas['valor']['provedor']);
        $this->assertSame('2× de R$ 107,89', $linhas['valor']['provedor_detalhe']);
        $this->assertSame('2026-10-19', $linhas['vencimento']['provedor']);
        $this->assertSame(1473, $c['parcela']['id']);
    }

    public function test_pix_avulso_continua_comparando_vencimento(): void
    {
        $provedor = [['id' => 'pay_x', 'status' => 'RECEIVED', 'transaction_amount' => 120, 'payment_method_id' => 'PIX', 'due_date' => '2026-10-19', 'installment_id' => null]];
        $local = [['id' => 9, 'valor' => '120.00', 'status_pagamento_id' => 2, 'status' => 'Pago', 'data_vencimento' => '2026-10-20', 'forma_pagamento_nome' => 'Pix']];

        $c = $this->chamar('montarConciliacao', [true, 'Asaas', $provedor, $local]);

        $this->assertFalse($c['ok']);
        $this->assertFalse(array_column($c['linhas'], null, 'campo')['vencimento']['ok']);
    }

    public function test_pix_trocado_e_nao_cancelado_no_asaas_aparece_como_pendencia(): void
    {
        // Matrícula 385: Pix de R$ 70 (mensal) gerado, aluno trocou para o bimestral de R$ 120 e pagou
        $provedor = [
            ['id' => 'pay_hjpofajk95bw1yyt', 'status' => 'RECEIVED', 'transaction_amount' => 120, 'payment_method_id' => 'PIX', 'due_date' => '2026-10-19', 'date_approved' => '2026-10-19', 'installment_id' => null],
            ['id' => 'pay_g8nrqnodppm6ftt9', 'status' => 'OVERDUE', 'transaction_amount' => 70, 'payment_method_id' => 'PIX', 'due_date' => '2026-10-19', 'date_approved' => null, 'installment_id' => null],
        ];
        $local = [
            ['id' => 1472, 'valor' => '120.00', 'status_pagamento_id' => 2, 'status' => 'Pago', 'data_pagamento' => '2026-10-19', 'data_vencimento' => '2026-10-19', 'forma_pagamento_nome' => 'Pix', 'observacoes' => '[Asaas pay_hjpofajk95bw1yyt]'],
            ['id' => 1471, 'valor' => '70.00', 'status_pagamento_id' => 4, 'status' => 'Cancelado', 'data_pagamento' => null, 'data_vencimento' => '2026-10-19', 'forma_pagamento_nome' => null, 'observacoes' => 'Cancelado por upgrade'],
        ];

        $c = $this->chamar('montarConciliacao', [true, 'Asaas', $provedor, $local]);

        $this->assertFalse($c['ok']);
        $this->assertSame('Há uma cobrança pendente no Asaas', $c['titulo']);
        $this->assertCount(1, $c['pendencias']);
        $this->assertSame('cobranca_aberta_sem_parcela', $c['pendencias'][0]['tipo']);
        $this->assertSame('pay_g8nrqnodppm6ftt9', $c['pendencias'][0]['payment_id']);
    }

    public function test_cobranca_removida_no_asaas_some_das_pendencias(): void
    {
        // Asaas devolve a cobrança removida com o status antigo e deleted=true
        $removida = $this->chamar('mapearCobrancaAsaas', [[
            'id' => 'pay_g8nrqnodppm6ftt9', 'status' => 'OVERDUE', 'deleted' => true, 'value' => 70, 'billingType' => 'PIX', 'dueDate' => '2026-10-19',
        ]]);
        $this->assertSame('CANCELLED', $removida['status']);

        $provedor = [
            ['id' => 'pay_hjpofajk95bw1yyt', 'status' => 'RECEIVED', 'transaction_amount' => 120, 'payment_method_id' => 'PIX', 'due_date' => '2026-10-19', 'date_approved' => '2026-10-19', 'installment_id' => null],
            $removida,
        ];
        $local = [
            ['id' => 1472, 'valor' => '120.00', 'status_pagamento_id' => 2, 'status' => 'Pago', 'data_pagamento' => '2026-10-19', 'data_vencimento' => '2026-10-19', 'forma_pagamento_nome' => 'Pix', 'observacoes' => '[Asaas pay_hjpofajk95bw1yyt]'],
        ];

        $c = $this->chamar('montarConciliacao', [true, 'Asaas', $provedor, $local]);

        $this->assertSame([], $c['pendencias']);
    }

    public function test_pagamento_antigo_ja_baixado_nao_vira_pendencia(): void
    {
        $provedor = [
            ['id' => 'pay_novo', 'status' => 'RECEIVED', 'transaction_amount' => 120, 'payment_method_id' => 'PIX', 'due_date' => '2026-10-19', 'date_approved' => '2026-10-19', 'installment_id' => null],
            ['id' => 'pay_antigo', 'status' => 'RECEIVED', 'transaction_amount' => 120, 'payment_method_id' => 'PIX', 'due_date' => '2026-08-19', 'date_approved' => '2026-08-19', 'installment_id' => null],
        ];
        $local = [
            ['id' => 2, 'valor' => '120.00', 'status_pagamento_id' => 2, 'data_pagamento' => '2026-10-19', 'data_vencimento' => '2026-10-19', 'forma_pagamento_nome' => 'Pix', 'observacoes' => '[Asaas pay_novo]'],
            ['id' => 1, 'valor' => '120.00', 'status_pagamento_id' => 2, 'data_pagamento' => '2026-08-19', 'data_vencimento' => '2026-08-19', 'forma_pagamento_nome' => 'Pix', 'observacoes' => 'Pago via Asaas'],
        ];

        $c = $this->chamar('montarConciliacao', [true, 'Asaas', $provedor, $local]);

        $this->assertSame([], $c['pendencias']);
        $this->assertTrue($c['ok']);
    }

    public function test_conciliacao_mostra_o_liquido_e_a_taxa(): void
    {
        $provedor = [['id' => 'pay_hjpofajk95bw1yyt', 'status' => 'RECEIVED', 'transaction_amount' => 120, 'net_amount' => 118.01, 'payment_method_id' => 'PIX', 'due_date' => '2026-10-19', 'date_approved' => '2026-10-19', 'installment_id' => null]];
        $local = [['id' => 1472, 'valor' => '120.00', 'valor_liquido' => '118.01', 'status_pagamento_id' => 2, 'status' => 'Pago', 'data_pagamento' => '2026-10-19', 'data_vencimento' => '2026-10-19', 'forma_pagamento_nome' => 'Pix', 'observacoes' => '[Asaas pay_hjpofajk95bw1yyt]']];

        $c = $this->chamar('montarConciliacao', [true, 'Asaas', $provedor, $local]);
        $liq = array_column($c['linhas'], null, 'campo')['liquido'];

        $this->assertTrue($c['ok']);
        $this->assertSame(118.01, $liq['provedor']);
        $this->assertSame(118.01, $liq['checkin']);
        $this->assertSame('taxa R$ 1,99', $liq['provedor_detalhe']);
    }

    public function test_liquido_diferente_do_gravado_e_divergencia(): void
    {
        $provedor = [['id' => 'pay_x', 'status' => 'RECEIVED', 'transaction_amount' => 120, 'net_amount' => 118.01, 'payment_method_id' => 'PIX', 'due_date' => '2026-10-19', 'date_approved' => '2026-10-19', 'installment_id' => null]];
        $local = [['id' => 1, 'valor' => '120.00', 'valor_liquido' => null, 'status_pagamento_id' => 2, 'status' => 'Pago', 'data_pagamento' => '2026-10-19', 'data_vencimento' => '2026-10-19', 'forma_pagamento_nome' => 'Pix', 'observacoes' => '[Asaas pay_x]']];

        $c = $this->chamar('montarConciliacao', [true, 'Asaas', $provedor, $local]);

        $this->assertFalse($c['ok']);
        $this->assertFalse(array_column($c['linhas'], null, 'campo')['liquido']['ok']);
    }

    public function test_parcela_paga_com_id_da_cobranca_ganha_da_cancelada_com_mesmo_vencimento(): void
    {
        // Matrícula 451: Pix gerado de novo, a parcela antiga foi substituída e a nova (vencimento do plano) foi paga
        $provedor = [['id' => 'pay_tiepo34bi3ll1gi5', 'status' => 'RECEIVED', 'transaction_amount' => 20, 'net_amount' => 18.01,
            'payment_method_id' => 'PIX', 'due_date' => '2026-10-22', 'date_approved' => '2026-10-22', 'installment_id' => null]];
        $local = [
            ['id' => 1493, 'valor' => '20.00', 'status_pagamento_id' => 2, 'status' => 'Pago', 'data_pagamento' => '2026-10-22', 'data_vencimento' => '2026-10-23',
                'forma_pagamento_nome' => 'Pix', 'valor_liquido' => '18.01', 'observacoes' => 'Pago via Asaas (Pix) [Asaas pay_tiepo34bi3ll1gi5]'],
            ['id' => 1492, 'valor' => '20.00', 'status_pagamento_id' => 4, 'status' => 'Cancelado', 'data_pagamento' => null, 'data_vencimento' => '2026-10-22',
                'forma_pagamento_nome' => null, 'valor_liquido' => null, 'observacoes' => 'Aguardando pagamento via Asaas [Substituída por nova cobrança]'],
        ];

        $c = $this->chamar('montarConciliacao', [true, 'Asaas', $provedor, $local]);

        $this->assertSame(1493, $c['parcela']['id']);
        $this->assertTrue($c['ok'], json_encode($c['linhas']));
    }
}
