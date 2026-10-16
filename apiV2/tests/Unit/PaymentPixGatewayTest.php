<?php

namespace Tests\Unit;

use App\Support\PaymentPixGateway;
use Tests\TestCase;

class PaymentPixGatewayTest extends TestCase
{
    public function test_infers_asaas_from_payment_id_and_url(): void
    {
        $this->assertSame('asaas', PaymentPixGateway::inferir('pay_abc', null));
        $this->assertSame('asaas', PaymentPixGateway::inferir('123', 'https://www.asaas.com/i/xyz'));
        $this->assertSame('mercadopago', PaymentPixGateway::inferir('123456789', 'https://www.mercadopago.com.br/payments/123'));
    }
}
