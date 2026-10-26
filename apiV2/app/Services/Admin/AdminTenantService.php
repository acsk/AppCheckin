<?php

namespace App\Services\Admin;

use App\Repositories\TenantRepository;
use App\Support\TenantInstagram;
use App\Support\TenantWhatsappLinks;
use Throwable;

class AdminTenantService
{
    public function __construct(
        private readonly TenantRepository $tenants,
    ) {}

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function obterWhatsappLinks(int $tenantId): array
    {
        try {
            $tenant = $this->tenants->findById($tenantId);

            if (! $tenant) {
                return [
                    'status' => 404,
                    'body' => [
                        'success' => false,
                        'message' => 'Academia não encontrada',
                    ],
                ];
            }

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'data' => [
                        'whatsapp_links' => TenantWhatsappLinks::parse($tenant['whatsapp_links'] ?? null),
                        'instagram_url' => $tenant['instagram_url'] ?? null,
                    ],
                ],
            ];
        } catch (Throwable $e) {
            error_log('[AdminTenant] Erro ao obter links WhatsApp: '.$e->getMessage());

            return [
                'status' => 500,
                'body' => [
                    'success' => false,
                    'message' => 'Erro ao obter links da academia',
                ],
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{status: int, body: array<string, mixed>}
     */
    public function salvarWhatsappLinks(int $tenantId, array $body): array
    {
        try {
            $tenant = $this->tenants->findById($tenantId);

            if (! $tenant) {
                return [
                    'status' => 404,
                    'body' => [
                        'success' => false,
                        'message' => 'Academia não encontrada',
                    ],
                ];
            }

            $links = $body['whatsapp_links'] ?? TenantWhatsappLinks::parse($tenant['whatsapp_links'] ?? null);

            if (($error = TenantWhatsappLinks::validateForSave($links)) !== null) {
                return [
                    'status' => 422,
                    'body' => [
                        'success' => false,
                        'message' => $error,
                    ],
                ];
            }

            $instagram = array_key_exists('instagram_url', $body) ? $body['instagram_url'] : ($tenant['instagram_url'] ?? null);
            if (($error = TenantInstagram::validar($instagram)) !== null) {
                return ['status' => 422, 'body' => ['success' => false, 'message' => $error]];
            }
            $instagram = TenantInstagram::normalizar($instagram);
            $parsed = TenantWhatsappLinks::parse($links ?? []);

            if (! $this->tenants->updateContactLinks($tenantId, $parsed, $instagram)) {
                return [
                    'status' => 500,
                    'body' => [
                        'success' => false,
                        'message' => 'Erro ao salvar links da academia',
                    ],
                ];
            }

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'message' => 'Links da academia salvos com sucesso',
                    'data' => [
                        'whatsapp_links' => $parsed,
                        'instagram_url' => $instagram,
                    ],
                ],
            ];
        } catch (Throwable $e) {
            error_log('[AdminTenant] Erro ao salvar links WhatsApp: '.$e->getMessage());

            return [
                'status' => 500,
                'body' => [
                    'success' => false,
                    'message' => 'Erro ao salvar links da academia',
                ],
            ];
        }
    }
}
