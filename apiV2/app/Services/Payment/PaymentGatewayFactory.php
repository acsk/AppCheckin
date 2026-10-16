<?php

namespace App\Services\Payment;

use App\Services\Asaas\AsaasService;
use App\Services\MercadoPagoService;
use Illuminate\Support\Facades\DB;

class PaymentGatewayFactory
{
    public function providerForTenant(int $tenantId): string
    {
        $row = DB::table('tenant_payment_credentials')
            ->where('tenant_id', $tenantId)
            ->where('is_active', 1)
            ->first(['provider']);

        $provider = strtolower((string) ($row->provider ?? 'mercadopago'));

        return $provider !== '' ? $provider : 'mercadopago';
    }

    public function pixGateway(int $tenantId): MercadoPagoService|AsaasService
    {
        if ($this->providerForTenant($tenantId) === 'asaas') {
            return new AsaasService($tenantId);
        }

        return new MercadoPagoService($tenantId);
    }
}
