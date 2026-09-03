<?php

namespace App\Services\Admin;

use App\Repositories\TenantPaymentCredentialsRepository;
use App\Services\EncryptionService;
use App\Services\MercadoPagoService;
use Throwable;

/**
 * Credenciais de pagamento do tenant (paridade Slim TenantPaymentCredentialsController).
 */
class AdminPaymentCredentialsService
{
    public function __construct(
        private readonly TenantPaymentCredentialsRepository $credentials,
        private readonly EncryptionService $encryption,
    ) {}

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function obter(int $tenantId): array
    {
        try {
            $row = $this->credentials->obterPorTenant($tenantId);

            if (! $row) {
                return [
                    'status' => 200,
                    'body' => [
                        'success' => true,
                        'data' => null,
                        'message' => 'Nenhuma credencial configurada',
                    ],
                ];
            }

            $row['public_key_test_masked'] = $this->mascarar($row['public_key_test'] ?? null);
            $row['public_key_prod_masked'] = $this->mascarar($row['public_key_prod'] ?? null);
            unset($row['public_key_test'], $row['public_key_prod']);

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'data' => $row,
                ],
            ];
        } catch (Throwable $e) {
            error_log('[PaymentCredentials] Erro ao obter: '.$e->getMessage());

            return [
                'status' => 500,
                'body' => [
                    'success' => false,
                    'message' => 'Erro ao obter credenciais: '.$e->getMessage(),
                ],
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{status: int, body: array<string, mixed>}
     */
    public function salvar(int $tenantId, array $body): array
    {
        try {
            $environment = $body['environment'] ?? 'sandbox';
            $provider = $body['provider'] ?? 'mercadopago';

            $accessTokenTest = isset($body['access_token_test']) && ! empty($body['access_token_test'])
                ? $this->encryption->encrypt($body['access_token_test'])
                : null;
            $accessTokenProd = isset($body['access_token_prod']) && ! empty($body['access_token_prod'])
                ? $this->encryption->encrypt($body['access_token_prod'])
                : null;
            $webhookSecret = isset($body['webhook_secret']) && ! empty($body['webhook_secret'])
                ? $this->encryption->encrypt($body['webhook_secret'])
                : null;

            $dados = [
                'provider' => $provider,
                'environment' => $environment,
                'public_key_test' => $body['public_key_test'] ?? null,
                'public_key_prod' => $body['public_key_prod'] ?? null,
                'is_active' => $body['is_active'] ?? true,
                'access_token_test' => $accessTokenTest,
                'access_token_prod' => $accessTokenProd,
                'webhook_secret' => $webhookSecret,
            ];

            if ($this->credentials->existePorTenant($tenantId)) {
                $update = [
                    'provider' => $dados['provider'],
                    'environment' => $dados['environment'],
                    'public_key_test' => $dados['public_key_test'],
                    'public_key_prod' => $dados['public_key_prod'],
                    'is_active' => $dados['is_active'],
                ];

                if ($accessTokenTest !== null) {
                    $update['access_token_test'] = $accessTokenTest;
                }
                if ($accessTokenProd !== null) {
                    $update['access_token_prod'] = $accessTokenProd;
                }
                if ($webhookSecret !== null) {
                    $update['webhook_secret'] = $webhookSecret;
                }

                $this->credentials->atualizar($tenantId, $update);
                $message = 'Credenciais atualizadas com sucesso';
            } else {
                $this->credentials->inserir($tenantId, $dados);
                $message = 'Credenciais cadastradas com sucesso';
            }

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'message' => $message,
                ],
            ];
        } catch (Throwable $e) {
            error_log('[PaymentCredentials] Erro ao salvar: '.$e->getMessage());

            return [
                'status' => 500,
                'body' => [
                    'success' => false,
                    'message' => 'Erro ao salvar credenciais: '.$e->getMessage(),
                ],
            ];
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function testar(int $tenantId): array
    {
        try {
            $mercadoPago = new MercadoPagoService($tenantId);
            $publicKey = $mercadoPago->getPublicKey();

            if (empty($publicKey)) {
                return [
                    'status' => 400,
                    'body' => [
                        'success' => false,
                        'message' => 'Credenciais não configuradas ou inválidas',
                    ],
                ];
            }

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'message' => 'Conexão com Mercado Pago OK',
                    'data' => [
                        'public_key_prefix' => substr($publicKey, 0, 15).'...',
                    ],
                ],
            ];
        } catch (Throwable $e) {
            return [
                'status' => 500,
                'body' => [
                    'success' => false,
                    'message' => 'Erro ao testar conexão: '.$e->getMessage(),
                ],
            ];
        }
    }

    private function mascarar(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $length = strlen($value);
        if ($length <= 10) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 6).str_repeat('*', $length - 10).substr($value, -4);
    }
}
