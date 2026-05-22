<?php

namespace Tests\Unit;

use App\Services\MercadoPagoService;
use ReflectionMethod;
use Tests\TestCase;

class MercadoPagoPayerExtractionTest extends TestCase
{
    public function test_extrair_payer_without_parent_key_does_not_error(): void
    {
        $service = new MercadoPagoService;
        $extrair = new ReflectionMethod(MercadoPagoService::class, 'extrairPayer');
        $extrair->setAccessible(true);

        $this->assertSame(
            ['email' => null, 'identification' => null],
            $extrair->invoke($service, [])
        );
    }

    public function test_resolver_payment_method_id_without_nested_object(): void
    {
        $service = new MercadoPagoService;
        $resolver = new ReflectionMethod(MercadoPagoService::class, 'resolverPaymentMethodId');
        $resolver->setAccessible(true);

        $this->assertSame('account_money', $resolver->invoke($service, [], 'account_money'));
        $this->assertSame('pix', $resolver->invoke($service, ['payment_method_id' => 'pix']));
        $this->assertSame('visa', $resolver->invoke($service, ['payment_method' => ['id' => 'visa']], 'account_money'));
    }
}
