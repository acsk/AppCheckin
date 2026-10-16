<?php

namespace App\Services\Asaas;

use App\Services\EncryptionService;
use App\Support\AcademyDateTime;
use App\Support\AsaasApiKey;
use App\Support\ReferenciaExterna;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AsaasService
{
    private string $accessToken = '';
    private string $baseUrl;
    private bool $productionMode = false;
    private ?int $tenantId = null;

    public function __construct(?int $tenantId = null)
    {
        $this->tenantId = $tenantId;
        $this->baseUrl = (string) config('appcheckin.asaas.sandbox_url');
        $this->carregarCredenciais();
    }

    public function criarPagamentoPix(array $data): array
    {
        $this->validarCredenciais();

        $cpf = isset($data['aluno_cpf']) ? preg_replace('/[^0-9]/', '', (string) $data['aluno_cpf']) : '';
        if (strlen($cpf) !== 11) {
            throw new \RuntimeException('CPF válido é obrigatório para pagamento PIX');
        }

        $matriculaId = ReferenciaExterna::normalizarId($data['matricula_id'] ?? null);
        if (! $matriculaId) {
            throw new \RuntimeException('matricula_id é obrigatório para pagamento PIX');
        }
        $externalReference = ReferenciaExterna::matricula($matriculaId);

        $customerId = $this->encontrarOuCriarCliente([
            'name' => trim((string) ($data['aluno_nome'] ?? 'Aluno')),
            'cpfCnpj' => $cpf,
            'email' => $data['aluno_email'] ?? null,
            'mobilePhone' => preg_replace('/[^0-9]/', '', (string) ($data['aluno_telefone'] ?? '')) ?: null,
        ]);

        $dueDate = AcademyDateTime::today();
        $payment = $this->request('POST', '/payments', [
            'customer' => $customerId,
            'billingType' => 'PIX',
            'value' => round((float) $data['valor'], 2),
            'dueDate' => $dueDate,
            'description' => $data['descricao'] ?? $data['plano_nome'] ?? 'Pagamento PIX',
            'externalReference' => $externalReference,
        ]);

        $paymentId = (string) ($payment['id'] ?? '');
        if ($paymentId === '') {
            throw new \RuntimeException('Asaas não retornou o ID da cobrança PIX');
        }

        $qr = $this->request('GET', '/payments/'.$paymentId.'/pixQrCode');
        $encoded = (string) ($qr['encodedImage'] ?? '');
        if ($encoded !== '' && ! str_starts_with($encoded, 'data:')) {
            $encoded = 'data:image/png;base64,'.$encoded;
        }

        return [
            'id' => $paymentId,
            'status' => $this->mapStatus((string) ($payment['status'] ?? 'PENDING')),
            'status_detail' => $payment['status'] ?? null,
            'external_reference' => $payment['externalReference'] ?? $externalReference,
            'date_of_expiration' => $qr['expirationDate'] ?? $payment['dueDate'] ?? null,
            'qr_code' => $qr['payload'] ?? null,
            'qr_code_base64' => $encoded !== '' ? $encoded : null,
            'ticket_url' => $payment['invoiceUrl'] ?? $payment['bankSlipUrl'] ?? null,
            'raw' => ['payment' => $payment, 'qr' => $qr],
        ];
    }

    /**
     * @return array{ok: bool, account?: array<string, mixed>, message: string}
     */
    public function testarConexao(): array
    {
        $this->validarCredenciais();
        $account = $this->request('GET', '/myAccount');

        return [
            'ok' => true,
            'account' => [
                'name' => $account['name'] ?? null,
                'email' => $account['email'] ?? null,
                'walletId' => $account['walletId'] ?? null,
            ],
            'message' => 'Conexão com Asaas OK ('.($this->productionMode ? 'produção' : 'sandbox').')',
        ];
    }

    public function credenciaisOficiaisValidas(): bool
    {
        $environment = $this->productionMode ? 'production' : 'sandbox';

        return $this->accessToken !== '' && AsaasApiKey::valida($this->accessToken, $environment);
    }

    public function webhookSecret(): ?string
    {
        if (! $this->tenantId) {
            return null;
        }

        $row = DB::table('tenant_payment_credentials')
            ->where('tenant_id', $this->tenantId)
            ->where('provider', 'asaas')
            ->first(['webhook_secret']);

        $secret = (string) ($row->webhook_secret ?? '');
        if ($secret === '') {
            return null;
        }

        return $this->decryptIfNeeded($secret);
    }

    private function encontrarOuCriarCliente(array $dados): string
    {
        $cpf = $dados['cpfCnpj'];
        $busca = $this->request('GET', '/customers', null, [
            'cpfCnpj' => $cpf,
            'limit' => 1,
        ]);
        $existente = $busca['data'][0]['id'] ?? null;
        if (is_string($existente) && $existente !== '') {
            return $existente;
        }

        $payload = [
            'name' => $dados['name'] !== '' ? $dados['name'] : 'Aluno',
            'cpfCnpj' => $cpf,
            'notificationDisabled' => true,
        ];
        if (! empty($dados['email'])) {
            $payload['email'] = $dados['email'];
        }
        if (! empty($dados['mobilePhone']) && strlen($dados['mobilePhone']) >= 10) {
            $payload['mobilePhone'] = $dados['mobilePhone'];
        }

        $criado = $this->request('POST', '/customers', $payload);
        $id = (string) ($criado['id'] ?? '');
        if ($id === '') {
            throw new \RuntimeException('Asaas não retornou o cliente');
        }

        return $id;
    }

    private function mapStatus(string $asaasStatus): string
    {
        return match (strtoupper($asaasStatus)) {
            'RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH' => 'approved',
            'OVERDUE' => 'expired',
            'REFUNDED', 'REFUND_REQUESTED' => 'refunded',
            'DELETED', 'RESTORED' => 'cancelled',
            default => 'pending',
        };
    }

    private function carregarCredenciais(): void
    {
        if (! $this->tenantId) {
            return;
        }

        $credentials = DB::table('tenant_payment_credentials')
            ->where('tenant_id', $this->tenantId)
            ->where('provider', 'asaas')
            ->where('is_active', 1)
            ->first();

        if (! $credentials) {
            return;
        }

        $this->productionMode = ($credentials->environment ?? 'sandbox') === 'production';
        $this->baseUrl = $this->productionMode
            ? (string) config('appcheckin.asaas.production_url')
            : (string) config('appcheckin.asaas.sandbox_url');

        $encrypted = $this->productionMode
            ? (string) ($credentials->access_token_prod ?? '')
            : (string) ($credentials->access_token_test ?? '');

        $this->accessToken = AsaasApiKey::normalizar($this->decryptIfNeeded($encrypted));
    }

    private function decryptIfNeeded(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $encryption = new EncryptionService();
        if ($encryption->isEncrypted($value)) {
            try {
                return $encryption->decrypt($value);
            } catch (\Throwable) {
                return $value;
            }
        }

        return $value;
    }

    private function validarCredenciais(): void
    {
        if ($this->accessToken === '') {
            throw new \RuntimeException('Credenciais Asaas não configuradas para este tenant');
        }

        $environment = $this->productionMode ? 'production' : 'sandbox';
        if (! AsaasApiKey::valida($this->accessToken, $environment)) {
            throw new \RuntimeException(AsaasApiKey::mensagemInvalida($environment));
        }
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $body = null, array $query = []): array
    {
        $url = rtrim($this->baseUrl, '/').'/'.ltrim($path, '/');
        $userAgent = 'AppCheckin/2.0 (PHP; '.($this->productionMode ? 'production' : 'sandbox').')';
        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => $userAgent,
            'access_token' => $this->accessToken,
        ];

        // GET não pode ter body (Asaas responde 403). Não usar asJson() no GET.
        $method = strtoupper($method);
        if ($method === 'GET') {
            $response = Http::withHeaders($headers)->timeout(30)->get($url, $query);
        } else {
            $response = Http::withHeaders($headers)
                ->asJson()
                ->timeout(30)
                ->post($url, $body ?? []);
        }

        $json = $response->json();
        if (! is_array($json)) {
            $json = [];
        }

        if ($response->failed()) {
            $errors = $json['errors'][0]['description'] ?? $json['message'] ?? $response->body();
            Log::warning('Asaas API erro', [
                'status' => $response->status(),
                'path' => $path,
                'errors' => $json['errors'] ?? $errors,
            ]);
            throw new \RuntimeException('Asaas: '.$errors);
        }

        return $json;
    }
}
