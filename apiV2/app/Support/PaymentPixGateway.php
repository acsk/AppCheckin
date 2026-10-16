<?php

namespace App\Support;

final class PaymentPixGateway
{
    public static function inferir(?string $paymentId, ?string $ticketUrl = null): string
    {
        $id = strtolower((string) $paymentId);
        $url = strtolower((string) $ticketUrl);

        if (str_starts_with($id, 'pay_') || str_contains($url, 'asaas.com')) {
            return 'asaas';
        }

        return 'mercadopago';
    }
}
