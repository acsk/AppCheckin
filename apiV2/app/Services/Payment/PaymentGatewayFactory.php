<?php

namespace App\Services\Payment;

use App\Services\Asaas\AsaasService;
use App\Services\MercadoPagoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentGatewayFactory
{
    /**
     * @return list<array{id: string, nome: string, environment: string}>
     */
    public function listarDisponiveis(int $tenantId, bool $podeSandbox): array
    {
        $rows = DB::table('tenant_payment_credentials')
            ->where('tenant_id', $tenantId)
            ->where('is_active', 1)
            ->orderBy('provider')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $provider = strtolower((string) ($row->provider ?? ''));
            if (! in_array($provider, ['mercadopago', 'asaas'], true)) {
                continue;
            }

            $environment = ((string) ($row->environment ?? 'sandbox')) === 'production'
                ? 'production'
                : 'sandbox';

            if ($environment === 'sandbox' && ! $podeSandbox) {
                continue;
            }

            if ($provider === 'asaas') {
                $asaas = new AsaasService($tenantId);
                if (! $asaas->credenciaisOficiaisValidas()) {
                    continue;
                }
            }

            $nome = $provider === 'asaas' ? 'Asaas' : 'Mercado Pago';
            if ($environment === 'sandbox') {
                $nome .= ' (sandbox)';
            }

            $out[] = [
                'id' => $provider,
                'nome' => $nome,
                'environment' => $environment,
            ];
        }

        return $out;
    }

    /**
     * @return array{
     *   ok: bool,
     *   provider?: string,
     *   instance?: MercadoPagoService|AsaasService,
     *   status?: int,
     *   code?: string,
     *   error?: string,
     *   gateways?: list<array{id: string, nome: string, environment: string}>
     * }
     */
    public function resolverPix(int $tenantId, bool $podeSandbox, ?string $requested): array
    {
        $gateways = $this->listarDisponiveis($tenantId, $podeSandbox);
        $pedido = strtolower(trim((string) $requested));

        if ($gateways === []) {
            return [
                'ok' => false,
                'status' => 400,
                'code' => 'SEM_GATEWAY_PIX',
                'error' => 'Nenhum provedor de PIX disponível.',
                'gateways' => [],
            ];
        }

        $ids = array_column($gateways, 'id');

        if ($pedido !== '') {
            if (! in_array($pedido, $ids, true)) {
                return [
                    'ok' => false,
                    'status' => 400,
                    'code' => 'GATEWAY_INDISPONIVEL',
                    'error' => 'Este provedor de pagamento não está disponível.',
                    'gateways' => $gateways,
                ];
            }

            return [
                'ok' => true,
                'provider' => $pedido,
                'instance' => $this->instancia($pedido, $tenantId),
                'gateways' => $gateways,
            ];
        }

        if (count($gateways) === 1) {
            $id = $gateways[0]['id'];

            return [
                'ok' => true,
                'provider' => $id,
                'instance' => $this->instancia($id, $tenantId),
                'gateways' => $gateways,
            ];
        }

        return [
            'ok' => false,
            'status' => 409,
            'code' => 'ESCOLHA_GATEWAY',
            'error' => 'Escolha Mercado Pago ou Asaas para gerar o PIX.',
            'gateways' => $gateways,
        ];
    }

    public function providerForTenant(int $tenantId): string
    {
        $row = DB::table('tenant_payment_credentials')
            ->where('tenant_id', $tenantId)
            ->where('is_active', 1)
            ->orderByRaw("CASE WHEN environment = 'production' THEN 0 ELSE 1 END")
            ->orderBy('provider')
            ->first(['provider']);

        $provider = strtolower((string) ($row->provider ?? 'mercadopago'));

        return $provider !== '' ? $provider : 'mercadopago';
    }

    public function pixGateway(int $tenantId): MercadoPagoService|AsaasService
    {
        $escolha = $this->resolverPix($tenantId, true, null);
        if ($escolha['ok'] ?? false) {
            return $escolha['instance'];
        }

        if (($escolha['code'] ?? '') === 'ESCOLHA_GATEWAY' && ! empty($escolha['gateways'][0]['id'])) {
            return $this->instancia($escolha['gateways'][0]['id'], $tenantId);
        }

        if ($this->providerForTenant($tenantId) === 'asaas') {
            $asaas = new AsaasService($tenantId);
            if ($asaas->credenciaisOficiaisValidas()) {
                return $asaas;
            }

            Log::warning('Asaas sem API key oficial ($aact_…); PIX cai no Mercado Pago', [
                'tenant_id' => $tenantId,
            ]);
        }

        return new MercadoPagoService($tenantId);
    }

    private function instancia(string $provider, int $tenantId): MercadoPagoService|AsaasService
    {
        return $provider === 'asaas'
            ? new AsaasService($tenantId)
            : new MercadoPagoService($tenantId);
    }
}
