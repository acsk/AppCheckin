<?php

namespace Tests\Unit;

use App\Services\MercadoPagoService;
use PHPUnit\Framework\TestCase;

class MercadoPagoLiquidoTest extends TestCase
{
    public function test_pix_aprovado_traz_liquido_e_taxa(): void
    {
        $p = [
            'status' => 'approved', 'transaction_amount' => 70,
            'transaction_details' => ['net_received_amount' => 69.31], 'fee_details' => [['type' => 'mercadopago_fee', 'amount' => 0.69]],
        ];

        $this->assertSame(69.31, MercadoPagoService::liquidoDoPagamento($p));
        $this->assertSame(0.69, MercadoPagoService::taxaDoPagamento($p));
    }

    public function test_sem_net_received_calcula_pelas_taxas(): void
    {
        $p = ['status' => 'approved', 'transaction_amount' => 200, 'transaction_details' => ['net_received_amount' => 0], 'fee_details' => [['amount' => 7.98], ['amount' => 2]]];

        $this->assertSame(190.02, MercadoPagoService::liquidoDoPagamento($p));
    }

    public function test_pendente_nao_tem_liquido(): void
    {
        $p = ['status' => 'pending', 'transaction_amount' => 70, 'transaction_details' => ['net_received_amount' => 0]];

        $this->assertNull(MercadoPagoService::liquidoDoPagamento($p));
        $this->assertNull(MercadoPagoService::taxaDoPagamento($p));
    }
}
