<?php

namespace App\Services\MercadoPago;

use App\Services\EncryptionService;
use PDO;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Valida assinatura HMAC dos webhooks Mercado Pago (cabeçalho x-signature).
 *
 * @see https://www.mercadopago.com.br/developers/pt/docs/your-integrations/notifications/webhooks
 */
class WebhookSignatureValidator
{
    private const SIGNATURE_MAX_AGE_SECONDS = 600;

    private EncryptionService $encryption;

    public function __construct(?EncryptionService $encryption = null)
    {
        $this->encryption = $encryption ?? new EncryptionService();
    }

    /**
     * @return array{allowed: bool, code: string, message: string}
     */
    public function validate(Request $request, PDO $db): array
    {
        $secrets = $this->collectSecrets($db);
        $signatureRequired = $this->isSignatureRequired($secrets);
        $xSignature = trim($request->getHeaderLine('x-signature'));
        $xRequestId = trim($request->getHeaderLine('x-request-id'));

        if ($xSignature === '') {
            if ($signatureRequired) {
                return [
                    'allowed' => false,
                    'code' => 'WEBHOOK_SIGNATURE_MISSING',
                    'message' => 'Assinatura do webhook ausente (x-signature)',
                ];
            }

            return [
                'allowed' => true,
                'code' => 'WEBHOOK_SIGNATURE_SKIPPED',
                'message' => 'Assinatura não exigida neste ambiente',
            ];
        }

        if ($secrets === []) {
            if ($signatureRequired) {
                return [
                    'allowed' => false,
                    'code' => 'WEBHOOK_SECRET_NOT_CONFIGURED',
                    'message' => 'Assinatura recebida, mas nenhum webhook secret está configurado',
                ];
            }

            error_log('[WebhookSignature] x-signature recebido sem secrets configurados; aceitando por política permissiva.');

            return [
                'allowed' => true,
                'code' => 'WEBHOOK_SIGNATURE_NO_SECRETS',
                'message' => 'Sem secrets configurados',
            ];
        }

        $dataId = $this->resolveDataId($request);
        if ($dataId === null || $dataId === '') {
            return [
                'allowed' => false,
                'code' => 'WEBHOOK_DATA_ID_MISSING',
                'message' => 'ID da notificação ausente para validar assinatura',
            ];
        }

        if ($xRequestId === '') {
            return [
                'allowed' => false,
                'code' => 'WEBHOOK_REQUEST_ID_MISSING',
                'message' => 'Cabeçalho x-request-id ausente',
            ];
        }

        $parsed = $this->parseSignatureHeader($xSignature);
        if ($parsed === null) {
            return [
                'allowed' => false,
                'code' => 'WEBHOOK_SIGNATURE_INVALID_FORMAT',
                'message' => 'Formato inválido do cabeçalho x-signature',
            ];
        }

        if (!$this->isTimestampFresh((int) $parsed['ts'])) {
            return [
                'allowed' => false,
                'code' => 'WEBHOOK_SIGNATURE_EXPIRED',
                'message' => 'Timestamp da assinatura fora da janela permitida',
            ];
        }

        $manifest = sprintf('id:%s;request-id:%s;ts:%s;', $dataId, $xRequestId, $parsed['ts']);

        foreach ($secrets as $secret) {
            $expected = hash_hmac('sha256', $manifest, $secret);
            if (hash_equals($expected, $parsed['v1'])) {
                return [
                    'allowed' => true,
                    'code' => 'WEBHOOK_SIGNATURE_VALID',
                    'message' => 'Assinatura válida',
                ];
            }
        }

        return [
            'allowed' => false,
            'code' => 'WEBHOOK_SIGNATURE_INVALID',
            'message' => 'Assinatura do webhook inválida',
        ];
    }

    /**
     * @param list<string> $secrets
     */
    private function isSignatureRequired(array $secrets): bool
    {
        $configured = $_ENV['MP_WEBHOOK_SIGNATURE_REQUIRED'] ?? $_SERVER['MP_WEBHOOK_SIGNATURE_REQUIRED'] ?? null;

        if ($configured !== null && $configured !== '') {
            return filter_var($configured, FILTER_VALIDATE_BOOLEAN);
        }

        // Produção: exige assinatura quando há pelo menos um secret configurado
        if (\App\Support\AppEnvironment::isProduction()) {
            return $secrets !== [];
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function collectSecrets(PDO $db): array
    {
        $secrets = [];

        $global = trim((string) (
            $_ENV['MP_WEBHOOK_SECRET']
            ?? $_SERVER['MP_WEBHOOK_SECRET']
            ?? getenv('MP_WEBHOOK_SECRET')
            ?: ''
        ));

        if ($global !== '') {
            $secrets[] = $global;
        }

        try {
            $stmt = $db->query("
                SELECT webhook_secret
                FROM tenant_payment_credentials
                WHERE provider = 'mercadopago'
                  AND is_active = 1
                  AND webhook_secret IS NOT NULL
                  AND webhook_secret != ''
            ");

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                try {
                    $plain = $this->encryption->decrypt((string) $row['webhook_secret']);
                    if ($plain !== '') {
                        $secrets[] = $plain;
                    }
                } catch (\Throwable $e) {
                    error_log('[WebhookSignature] Falha ao descriptografar webhook_secret do tenant: ' . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            error_log('[WebhookSignature] Falha ao buscar secrets no banco: ' . $e->getMessage());
        }

        return array_values(array_unique($secrets));
    }

    private function resolveDataId(Request $request): ?string
    {
        $query = $request->getQueryParams();

        $fromQuery = $this->normalizeDataId($query['data.id'] ?? null)
            ?? $this->normalizeDataId($query['id'] ?? null);
        if ($fromQuery !== null) {
            return $fromQuery;
        }

        $body = $request->getParsedBody();
        if (!is_array($body)) {
            $raw = (string) $request->getBody();
            $decoded = json_decode($raw, true);
            $body = is_array($decoded) ? $decoded : [];
        }

        return $this->normalizeDataId($body['data']['id'] ?? null)
            ?? $this->normalizeDataId($body['id'] ?? null);
    }

    /**
     * Aceita qualquer valor presente (inclui 0 e '0'); rejeita apenas null e string vazia.
     */
    private function normalizeDataId(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * @return array{ts: string, v1: string}|null
     */
    private function parseSignatureHeader(string $header): ?array
    {
        $ts = null;
        $v1 = null;

        foreach (explode(',', $header) as $part) {
            $part = trim($part);
            if (str_starts_with($part, 'ts=')) {
                $ts = substr($part, 3);
            } elseif (str_starts_with($part, 'v1=')) {
                $v1 = substr($part, 3);
            }
        }

        if ($ts === null || $v1 === null || $ts === '' || $v1 === '') {
            return null;
        }

        return ['ts' => $ts, 'v1' => $v1];
    }

    private function isTimestampFresh(int $timestamp): bool
    {
        if ($timestamp <= 0) {
            return false;
        }

        return abs(time() - $timestamp) <= self::SIGNATURE_MAX_AGE_SECONDS;
    }
}
