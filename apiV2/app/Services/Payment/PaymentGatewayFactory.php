<?php

namespace App\Services\Payment;

use App\Services\Asaas\AsaasService;
use App\Services\MercadoPagoService;
use Illuminate\Support\Facades\DB;

class PaymentGatewayFactory
{
    /**
     * @return list<array{id: string, nome: string, environment: string}>
     */
    public function listarDisponiveis(int $tenantId, bool $podeSandbox, string $metodo = 'pix'): array
    {
        $rows = $podeSandbox
            ? $this->todosDoTenant($tenantId)
            : $this->ativos($tenantId);

        $out = [];
        foreach ($rows as $row) {
            $provider = strtolower((string) ($row->provider ?? ''));
            if (! in_array($provider, ['mercadopago', 'asaas'], true)) {
                continue;
            }

            $environment = ((string) ($row->environment ?? 'sandbox')) === 'production'
                ? 'production'
                : 'sandbox';

            $ativo = $this->estaAtivo($row);
            if (! $ativo && ! $podeSandbox) {
                continue;
            }

            if ($environment === 'sandbox' && ! $podeSandbox) {
                continue;
            }

            if (! $this->temCredencialDoAmbiente($row)) {
                continue;
            }

            if (! $this->aceitaMetodo($row, $metodo)) {
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
     * Métodos que o aluno realmente consegue pagar com os provedores visíveis agora.
     *
     * @return array{pix: bool, boleto: bool, cartao: bool}
     */
    public function metodosHabilitados(int $tenantId, bool $podeSandbox): array
    {
        return [
            'pix' => $this->listarDisponiveis($tenantId, $podeSandbox, 'pix') !== [],
            'boleto' => $this->listarDisponiveis($tenantId, $podeSandbox, 'boleto') !== [],
            'cartao' => $this->listarDisponiveis($tenantId, $podeSandbox, 'cartao') !== [],
        ];
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
        $gateways = $this->listarDisponiveis($tenantId, $podeSandbox, 'pix');
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
        $row = $this->ativos($tenantId)
            ->sortBy(function ($item) {
                $prioridade = ((string) ($item->environment ?? '')) === 'production' ? '0' : '1';

                return $prioridade.'_'.strtolower((string) ($item->provider ?? ''));
            })
            ->first();

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

        return new MercadoPagoService($tenantId);
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function ativos(int $tenantId)
    {
        return DB::table('tenant_payment_credentials')
            ->where('tenant_id', $tenantId)
            ->where(function ($query) {
                $query->where('is_active', 1)
                    ->orWhere('is_active', true)
                    ->orWhere('is_active', '1');
            })
            ->orderBy('provider')
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function todosDoTenant(int $tenantId)
    {
        return DB::table('tenant_payment_credentials')
            ->where('tenant_id', $tenantId)
            ->orderBy('provider')
            ->get();
    }

    private function estaAtivo(object $row): bool
    {
        $valor = $row->is_active ?? 0;

        return $valor === 1 || $valor === true || $valor === '1';
    }

    public function resolverMetodo(int $tenantId, bool $podeSandbox, string $metodo, ?string $requested): array
    {
        $metodo = strtolower($metodo);
        if (in_array($metodo, ['cartao', 'credit_card', 'card'], true)) {
            $metodo = 'cartao';
        }
        $gateways = $this->listarDisponiveis($tenantId, $podeSandbox, $metodo);
        $pedido = strtolower(trim((string) $requested));

        if ($gateways === []) {
            return [
                'ok' => false,
                'status' => 400,
                'code' => 'SEM_GATEWAY',
                'error' => 'Nenhum provedor disponível para este método de pagamento.',
                'gateways' => [],
            ];
        }

        $ids = array_column($gateways, 'id');
        if ($pedido !== '' && in_array($pedido, $ids, true)) {
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
            'error' => 'Escolha Mercado Pago ou Asaas para continuar.',
            'gateways' => $gateways,
        ];
    }

    private function aceitaMetodo(object $row, string $metodo): bool
    {
        $metodo = strtolower($metodo);
        $flags = (array) $row;
        $ligado = static function (mixed $valor): bool {
            if ($valor === null) {
                return true;
            }

            if ($valor === 0 || $valor === '0' || $valor === false || $valor === 'false') {
                return false;
            }

            return $valor === 1 || $valor === true || $valor === '1'
                || filter_var($valor, FILTER_VALIDATE_BOOLEAN) === true;
        };

        return match ($metodo) {
            'boleto' => $ligado($flags['enable_boleto'] ?? true),
            'cartao', 'checkout', 'credit_card' => $ligado($flags['enable_credit_card'] ?? true)
                || ($metodo === 'checkout' && $ligado($flags['enable_boleto'] ?? true)),
            default => $ligado($flags['enable_pix'] ?? true),
        };
    }

    private function temCredencialDoAmbiente(object $row): bool
    {
        $producao = ((string) ($row->environment ?? '')) === 'production';
        $token = $producao
            ? (string) ($row->access_token_prod ?? '')
            : (string) ($row->access_token_test ?? '');

        return trim($token) !== '';
    }

    private function instancia(string $provider, int $tenantId): MercadoPagoService|AsaasService
    {
        return $provider === 'asaas'
            ? new AsaasService($tenantId)
            : new MercadoPagoService($tenantId);
    }
}
